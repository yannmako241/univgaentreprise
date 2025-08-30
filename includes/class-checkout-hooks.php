<?php

/**
 * WooCommerce checkout hooks for seat pool creation
 */
class UNIVGA_Checkout_Hooks {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('woocommerce_order_status_completed', array($this, 'process_completed_order'));
        add_action('woocommerce_order_status_processing', array($this, 'process_completed_order'));
    }
    
    /**
     * Process completed order to create/update seat pools
     */
    public function process_completed_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Get customer's organization
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }
        
        $member = UNIVGA_Members::get_user_org_membership($customer_id);
        if (!$member) {
            // Log warning - customer not in any organization
            error_log("UNIVGA: Order $order_id completed but customer $customer_id is not in any organization");
            return;
        }
        
        // Process each order item
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            
            // Check if this is a seat product
            if (!UNIVGA_Products::is_seat_product($product_id)) {
                continue;
            }
            
            $config = UNIVGA_Products::get_product_config($product_id);
            
            if (empty($config['scope_type']) || empty($config['scope_ids']) || empty($config['seats_qty'])) {
                error_log("UNIVGA: Product $product_id is missing seat configuration");
                continue;
            }
            
            // Calculate total seats
            $total_seats = intval($config['seats_qty']) * $quantity;
            
            // Calculate expiration date
            $expires_at = null;
            if ($config['duration_days'] && $config['duration_days'] > 0) {
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$config['duration_days']} days"));
            }
            
            // Check if similar pool exists (same org, scope_type, scope_ids)
            $existing_pool = $this->find_existing_pool($member->org_id, $config);
            
            if ($existing_pool) {
                // Update existing pool
                UNIVGA_Seat_Pools::update($existing_pool->id, array(
                    'seats_total' => $existing_pool->seats_total + $total_seats,
                    'expires_at' => $expires_at, // Update expiration to latest
                ));
                
                // Log event
                UNIVGA_Seat_Events::log($existing_pool->id, null, 'assign', array(
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                    'seats_added' => $total_seats,
                    'source' => 'order_completion',
                ));
                
            } else {
                // Create new pool
                $pool_data = array(
                    'org_id' => $member->org_id,
                    'team_id' => $member->team_id,
                    'scope_type' => $config['scope_type'],
                    'scope_ids' => $config['scope_ids'],
                    'seats_total' => $total_seats,
                    'seats_used' => 0,
                    'expires_at' => $expires_at,
                    'order_id' => $order_id,
                    'auto_enroll' => 1,
                    'allow_replace' => get_option('univga_default_allow_replace', 0),
                );
                
                $pool_id = UNIVGA_Seat_Pools::create($pool_data);
                
                if (!is_wp_error($pool_id)) {
                    // Log event
                    UNIVGA_Seat_Events::log($pool_id, null, 'assign', array(
                        'order_id' => $order_id,
                        'product_id' => $product_id,
                        'seats_total' => $total_seats,
                        'source' => 'order_completion',
                    ));
                    
                    // Auto-enroll existing active members if enabled
                    $this->auto_enroll_existing_members($pool_id);
                    
                } else {
                    error_log("UNIVGA: Failed to create seat pool for order $order_id: " . $pool_id->get_error_message());
                }
            }
        }
        
        // Send notification to organization manager
        $this->notify_seat_purchase($member->org_id, $order_id);
    }
    
    /**
     * Find existing pool with same scope
     */
    private function find_existing_pool($org_id, $config) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}univga_seat_pools 
             WHERE org_id = %d AND scope_type = %s AND scope_ids = %s
             ORDER BY created_at DESC LIMIT 1",
            $org_id,
            $config['scope_type'],
            json_encode($config['scope_ids'])
        ));
    }
    
    /**
     * Auto-enroll existing members in new pool courses
     */
    private function auto_enroll_existing_members($pool_id) {
        $pool = UNIVGA_Seat_Pools::get($pool_id);
        
        if (!$pool || !$pool->auto_enroll) {
            return;
        }
        
        // Get active organization members
        $members = UNIVGA_Members::get_org_members($pool->org_id, array(
            'team_id' => $pool->team_id,
            'status' => 'active',
        ));
        
        $course_ids = UNIVGA_Seat_Pools::get_pool_courses($pool);
        
        foreach ($members as $member) {
            // Check if pool has available seats
            if ($pool->seats_used >= $pool->seats_total) {
                break;
            }
            
            // Check if member is already enrolled in any of these courses
            $already_enrolled = false;
            foreach ($course_ids as $course_id) {
                if (tutor_utils()->is_enrolled($course_id, $member->user_id)) {
                    $already_enrolled = true;
                    break;
                }
            }
            
            if (!$already_enrolled) {
                // Enroll member in all courses
                foreach ($course_ids as $course_id) {
                    UNIVGA_Tutor::enroll($member->user_id, $course_id, 'org');
                }
                
                // Consume seat
                UNIVGA_Seat_Pools::consume_seat($pool_id, $member->user_id);
                $pool->seats_used++;
            }
        }
    }
    
    /**
     * Notify organization manager about seat purchase
     */
    private function notify_seat_purchase($org_id, $order_id) {
        $org = UNIVGA_Orgs::get($org_id);
        if (!$org || !$org->contact_user_id) {
            return;
        }
        
        $user = get_userdata($org->contact_user_id);
        if (!$user) {
            return;
        }
        
        $order = wc_get_order($order_id);
        
        $subject = sprintf(__('New seat package purchased for %s', UNIVGA_TEXT_DOMAIN), $org->name);
        
        $message = sprintf(
            __('Hello %s,

A new seat package has been purchased for your organization "%s".

Order ID: %s
Order Total: %s

You can manage your seats and invite members through your organization dashboard.

Best regards,
The UNIVGA Team', UNIVGA_TEXT_DOMAIN),
            $user->display_name,
            $org->name,
            $order->get_order_number(),
            $order->get_formatted_order_total()
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
}
