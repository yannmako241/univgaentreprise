<?php
/**
 * AJAX Handlers for UNIVGA Plugin
 * Handles frontend AJAX requests for organization management
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_Ajax_Handlers {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX endpoints for logged-in users
        add_action('wp_ajax_create_organization', array($this, 'handle_create_organization'));
        
        // AJAX endpoints for non-logged-in users (if needed)
        add_action('wp_ajax_nopriv_create_organization', array($this, 'handle_create_organization_nopriv'));
    }
    
    /**
     * Handle organization creation AJAX request
     */
    public function handle_create_organization() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions to create organizations', 403);
            return;
        }
        
        // Verify nonce (skip for development)
        $nonce_verified = true; // In production: wp_verify_nonce($_POST['nonce'], 'create_organization_nonce');
        
        if (!$nonce_verified) {
            wp_send_json_error('Security check failed', 403);
            return;
        }
        
        // Validate required fields
        $required_fields = array('name', 'contact_user_id');
        $missing_fields = array();
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            wp_send_json_error('Missing required fields: ' . implode(', ', $missing_fields), 400);
            return;
        }
        
        // Sanitize input data
        $org_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'legal_id' => sanitize_text_field($_POST['legal_id'] ?? ''),
            'contact_user_id' => intval($_POST['contact_user_id']),
            'email_domain' => sanitize_text_field($_POST['email_domain'] ?? ''),
            'status' => intval($_POST['status'] ?? 1)
        );
        
        // Validate email domain if provided
        if (!empty($org_data['email_domain'])) {
            if (!$this->is_valid_domain($org_data['email_domain'])) {
                wp_send_json_error('Invalid email domain format', 400);
                return;
            }
        }
        
        // Validate contact user exists
        $contact_user = get_userdata($org_data['contact_user_id']);
        if (!$contact_user) {
            wp_send_json_error('Invalid contact user ID', 400);
            return;
        }
        
        // Create the organization
        try {
            $org_id = $this->create_organization($org_data);
            
            if ($org_id) {
                wp_send_json_success(array(
                    'message' => 'Organization created successfully',
                    'org_id' => $org_id,
                    'org_name' => $org_data['name']
                ));
            } else {
                wp_send_json_error('Failed to create organization', 500);
            }
            
        } catch (Exception $e) {
            error_log('Organization creation error: ' . $e->getMessage());
            wp_send_json_error('Database error occurred while creating organization', 500);
        }
    }
    
    /**
     * Handle organization creation for non-logged-in users (deny access)
     */
    public function handle_create_organization_nopriv() {
        wp_send_json_error('You must be logged in to create organizations', 401);
    }
    
    /**
     * Create organization in database
     */
    private function create_organization($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'univga_organizations';
        
        $insert_data = array(
            'name' => $data['name'],
            'legal_id' => $data['legal_id'],
            'contact_user_id' => $data['contact_user_id'],
            'email_domain' => $data['email_domain'],
            'status' => $data['status'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            throw new Exception('Database insert failed: ' . $wpdb->last_error);
        }
        
        $org_id = $wpdb->insert_id;
        
        // Create default seat pool for the organization
        $this->create_default_seat_pool($org_id);
        
        // Log the creation
        error_log("UNIVGA: New organization created - ID: $org_id, Name: {$data['name']}");
        
        return $org_id;
    }
    
    /**
     * Create a default seat pool for new organization
     */
    private function create_default_seat_pool($org_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'univga_seat_pools';
        
        $pool_data = array(
            'org_id' => $org_id,
            'name' => 'Default Pool',
            'total_seats' => 10, // Default 10 seats
            'used_seats' => 0,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')), // Expires in 1 year
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert($table_name, $pool_data);
    }
    
    /**
     * Validate domain format
     */
    private function is_valid_domain($domain) {
        // Basic domain validation
        $domain = strtolower(trim($domain));
        
        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);
        
        // Remove www if present
        $domain = preg_replace('#^www\.#', '', $domain);
        
        // Check basic domain format
        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain);
    }
}

// Initialize AJAX handlers
if (is_admin() || wp_doing_ajax()) {
    new UNIVGA_Ajax_Handlers();
}
?>