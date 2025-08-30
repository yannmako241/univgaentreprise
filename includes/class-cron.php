<?php

/**
 * Cron job management for synchronization and maintenance
 */
class UNIVGA_Cron {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('univga_org_resync', array($this, 'run_org_resync'));
        add_action('init', array($this, 'schedule_events'));
    }
    
    /**
     * Schedule cron events if not already scheduled
     */
    public function schedule_events() {
        if (!wp_next_scheduled('univga_org_resync')) {
            wp_schedule_event(time(), 'twicedaily', 'univga_org_resync');
        }
    }
    
    /**
     * Run organization resync job
     */
    public function run_org_resync() {
        $start_time = time();
        $processed_orgs = 0;
        $errors = array();
        
        // Get all active organizations
        $organizations = UNIVGA_Orgs::get_all(array('status' => 1));
        
        foreach ($organizations as $org) {
            try {
                $this->resync_organization($org->id);
                $processed_orgs++;
            } catch (Exception $e) {
                $errors[] = "Org {$org->id}: " . $e->getMessage();
                error_log("UNIVGA Cron Error - Org {$org->id}: " . $e->getMessage());
            }
        }
        
        // Process expired pools
        $expired_count = $this->process_expired_pools();
        
        // Send expiration warnings
        $warnings_sent = $this->send_expiration_warnings();
        
        $duration = time() - $start_time;
        
        // Log summary
        $summary = array(
            'processed_orgs' => $processed_orgs,
            'expired_pools' => $expired_count,
            'warnings_sent' => $warnings_sent,
            'errors' => count($errors),
            'duration' => $duration,
        );
        
        update_option('univga_last_cron_summary', $summary);
        
        if (get_option('univga_debug_mode', 0)) {
            error_log("UNIVGA Cron Summary: " . json_encode($summary));
            if (!empty($errors)) {
                error_log("UNIVGA Cron Errors: " . implode('; ', $errors));
            }
        }
    }
    
    /**
     * Resync organization data
     */
    private function resync_organization($org_id) {
        // Get organization members
        $members = UNIVGA_Members::get_org_members($org_id, array('status' => 'active'));
        
        // Get active seat pools
        $pools = UNIVGA_Seat_Pools::get_by_org($org_id, array('active_only' => true));
        
        foreach ($pools as $pool) {
            $this->resync_pool($pool, $members);
        }
    }
    
    /**
     * Resync individual pool
     */
    private function resync_pool($pool, $members) {
        $course_ids = UNIVGA_Seat_Pools::get_pool_courses($pool);
        
        if (empty($course_ids)) {
            return;
        }
        
        $actual_seats_used = 0;
        $members_to_enroll = array();
        
        foreach ($members as $member) {
            // Check if member should be in this pool (team matching)
            if ($pool->team_id && $member->team_id != $pool->team_id) {
                continue;
            }
            
            $member_enrolled = false;
            
            // Check enrollment in pool courses
            foreach ($course_ids as $course_id) {
                if (tutor_utils()->is_enrolled($course_id, $member->user_id)) {
                    $member_enrolled = true;
                    break;
                }
            }
            
            if ($member_enrolled) {
                $actual_seats_used++;
            } elseif ($pool->auto_enroll && $actual_seats_used < $pool->seats_total) {
                // Member should be enrolled but isn't
                $members_to_enroll[] = $member->user_id;
            }
        }
        
        // Update seat count if there's a discrepancy
        if ($actual_seats_used != $pool->seats_used) {
            UNIVGA_Seat_Pools::update($pool->id, array(
                'seats_used' => $actual_seats_used,
            ));
            
            // Log the adjustment
            UNIVGA_Seat_Events::log($pool->id, null, 'adjust', array(
                'old_seats_used' => $pool->seats_used,
                'new_seats_used' => $actual_seats_used,
                'source' => 'cron_resync',
            ));
        }
        
        // Enroll missing members if there are available seats
        foreach ($members_to_enroll as $user_id) {
            if ($actual_seats_used >= $pool->seats_total) {
                break;
            }
            
            // Enroll in all pool courses
            $enrolled_any = false;
            foreach ($course_ids as $course_id) {
                if (UNIVGA_Tutor::enroll($user_id, $course_id, 'org')) {
                    $enrolled_any = true;
                }
            }
            
            if ($enrolled_any) {
                UNIVGA_Seat_Pools::consume_seat($pool->id, $user_id);
                $actual_seats_used++;
            }
        }
    }
    
    /**
     * Process expired pools
     */
    private function process_expired_pools() {
        global $wpdb;
        
        // Get expired pools
        $expired_pools = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}univga_seat_pools 
             WHERE expires_at IS NOT NULL AND expires_at < NOW()"
        );
        
        $processed_count = 0;
        
        foreach ($expired_pools as $pool) {
            // Log expiration event
            UNIVGA_Seat_Events::log($pool->id, null, 'expire', array(
                'expired_at' => current_time('mysql'),
                'seats_total' => $pool->seats_total,
                'seats_used' => $pool->seats_used,
            ));
            
            // Notify organization manager
            $this->notify_pool_expiration($pool);
            
            $processed_count++;
        }
        
        return $processed_count;
    }
    
    /**
     * Send expiration warnings for pools expiring soon
     */
    private function send_expiration_warnings() {
        global $wpdb;
        
        $warnings_sent = 0;
        $warning_periods = array(15, 7, 1); // Days before expiration
        
        foreach ($warning_periods as $days) {
            $warning_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            
            $expiring_pools = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, o.name as org_name, o.contact_user_id
                 FROM {$wpdb->prefix}univga_seat_pools p
                 JOIN {$wpdb->prefix}univga_orgs o ON p.org_id = o.id
                 WHERE p.expires_at BETWEEN NOW() AND %s
                 AND p.expires_at > NOW()",
                $warning_date
            ));
            
            foreach ($expiring_pools as $pool) {
                // Check if warning already sent for this period
                $warning_key = "expiry_warning_{$pool->id}_{$days}d";
                if (get_transient($warning_key)) {
                    continue;
                }
                
                if ($this->send_expiration_warning($pool, $days)) {
                    set_transient($warning_key, true, 24 * 60 * 60); // 24 hours
                    $warnings_sent++;
                }
            }
        }
        
        return $warnings_sent;
    }
    
    /**
     * Send expiration warning email
     */
    private function send_expiration_warning($pool, $days) {
        if (!$pool->contact_user_id) {
            return false;
        }
        
        $user = get_userdata($pool->contact_user_id);
        if (!$user) {
            return false;
        }
        
        $subject = sprintf(
            __('Seat pool expiring in %d day(s) - %s', UNIVGA_TEXT_DOMAIN),
            $days,
            $pool->org_name
        );
        
        $course_ids = UNIVGA_Seat_Pools::get_pool_courses((object)array(
            'scope_type' => $pool->scope_type,
            'scope_ids' => $pool->scope_ids,
        ));
        
        $course_titles = array();
        foreach ($course_ids as $course_id) {
            $course = get_post($course_id);
            if ($course) {
                $course_titles[] = $course->post_title;
            }
        }
        
        $message = sprintf(
            __('Hello %s,

Your seat pool for "%s" will expire in %d day(s) on %s.

Pool Details:
- Courses: %s
- Total Seats: %d
- Used Seats: %d
- Available Seats: %d

To continue providing access to your team members, please purchase additional seats before the expiration date.

Best regards,
The UNIVGA Team', UNIVGA_TEXT_DOMAIN),
            $user->display_name,
            $pool->org_name,
            $days,
            date('F j, Y', strtotime($pool->expires_at)),
            implode(', ', $course_titles),
            $pool->seats_total,
            $pool->seats_used,
            $pool->seats_total - $pool->seats_used
        );
        
        return wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Notify about pool expiration
     */
    private function notify_pool_expiration($pool) {
        $org = UNIVGA_Orgs::get($pool->org_id);
        if (!$org || !$org->contact_user_id) {
            return;
        }
        
        $user = get_userdata($org->contact_user_id);
        if (!$user) {
            return;
        }
        
        $subject = sprintf(__('Seat pool expired - %s', UNIVGA_TEXT_DOMAIN), $org->name);
        
        $message = sprintf(
            __('Hello %s,

Your seat pool for "%s" has expired as of %s.

New members will no longer be automatically enrolled in the associated courses. Existing members retain their access.

To restore automatic enrollment, please purchase new seats.

Best regards,
The UNIVGA Team', UNIVGA_TEXT_DOMAIN),
            $user->display_name,
            $org->name,
            date('F j, Y H:i', strtotime($pool->expires_at))
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Manual trigger for resync (admin use)
     */
    public static function trigger_manual_resync() {
        wp_schedule_single_event(time() + 10, 'univga_org_resync');
        return true;
    }
}
