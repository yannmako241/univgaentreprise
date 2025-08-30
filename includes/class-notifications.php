<?php

/**
 * UNIVGA Notifications Class
 * Smart Notifications & Communication Hub with automation
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_Notifications {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_univga_get_notifications', array($this, 'get_notifications'));
        add_action('wp_ajax_univga_mark_notification_read', array($this, 'mark_notification_read'));
        add_action('wp_ajax_univga_create_notification_template', array($this, 'create_notification_template'));
        add_action('wp_ajax_univga_send_bulk_notification', array($this, 'send_bulk_notification'));
        add_action('wp_ajax_univga_get_notification_templates', array($this, 'get_notification_templates'));
        
        // Hook into various events for automated notifications
        add_action('univga_course_completed', array($this, 'notify_course_completion'), 10, 3);
        add_action('univga_certification_earned', array($this, 'notify_certification_earned'), 10, 2);
        add_action('univga_learning_path_assigned', array($this, 'notify_path_assignment'), 10, 3);
        add_action('univga_seat_pool_expiring', array($this, 'notify_expiring_seats'), 10, 2);
        
        // Email automation
        add_action('wp_ajax_univga_setup_email_automation', array($this, 'setup_email_automation'));
        
        // SMS integration (if Twilio credentials available)
        if (defined('UNIVGA_TWILIO_SID')) {
            add_action('univga_send_sms', array($this, 'send_sms_notification'), 10, 3);
        }
    }
    
    /**
     * Create notification
     */
    public function create_notification($user_id, $org_id, $type, $title, $message, $action_url = null) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_notifications',
            array(
                'user_id' => $user_id,
                'org_id' => $org_id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $action_url,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Send real-time notification via WebSocket if available
            $this->send_realtime_notification($user_id, array(
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $action_url
            ));
        }
        
        return $result;
    }
    
    /**
     * Get notifications for user
     */
    public function get_notifications() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $page = intval($_POST['page'] ?? 1);
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        global $wpdb;
        
        $where_clause = $org_id ? "WHERE user_id = %d AND org_id = %d" : "WHERE user_id = %d";
        $params = $org_id ? array($user_id, $org_id) : array($user_id);
        
        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}univga_notifications
            {$where_clause}
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d
        ", array_merge($params, array($per_page, $offset))));
        
        $total = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}univga_notifications
            {$where_clause}
        ", $params));
        
        $unread_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}univga_notifications
            {$where_clause} AND is_read = 0
        ", $params));
        
        wp_send_json_success(array(
            'notifications' => $notifications,
            'total' => $total,
            'unread_count' => $unread_count,
            'has_more' => $total > ($page * $per_page)
        ));
    }
    
    /**
     * Mark notification as read
     */
    public function mark_notification_read() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'univga_notifications',
            array('is_read' => 1),
            array('id' => $notification_id, 'user_id' => $user_id),
            array('%d'),
            array('%d', '%d')
        );
        
        wp_send_json_success(array('updated' => $result > 0));
    }
    
    /**
     * Create notification template
     */
    public function create_notification_template() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $template_data = array(
            'org_id' => intval($_POST['org_id']),
            'type' => sanitize_text_field($_POST['type']),
            'name' => sanitize_text_field($_POST['name']),
            'subject' => sanitize_text_field($_POST['subject']),
            'template' => wp_kses_post($_POST['template']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_notification_templates',
            $template_data,
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'template_id' => $wpdb->insert_id,
                'message' => 'Template created successfully'
            ));
        } else {
            wp_send_json_error('Failed to create template');
        }
    }
    
    /**
     * Get notification templates
     */
    public function get_notification_templates() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        global $wpdb;
        
        $templates = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}univga_notification_templates
            WHERE org_id = %d
            ORDER BY type, name
        ", $org_id));
        
        wp_send_json_success($templates);
    }
    
    /**
     * Send bulk notification
     */
    public function send_bulk_notification() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_org_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $user_ids = array_map('intval', $_POST['user_ids']);
        $template_id = intval($_POST['template_id']);
        $custom_message = sanitize_textarea_field($_POST['custom_message'] ?? '');
        
        global $wpdb;
        
        // Get template
        $template = null;
        if ($template_id) {
            $template = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}univga_notification_templates 
                WHERE id = %d AND org_id = %d
            ", $template_id, $org_id));
        }
        
        $sent_count = 0;
        
        foreach ($user_ids as $user_id) {
            $user = get_user_by('ID', $user_id);
            if (!$user) continue;
            
            if ($template) {
                $subject = $this->process_template_variables($template->subject, $user);
                $message = $this->process_template_variables($template->template, $user);
            } else {
                $subject = sanitize_text_field($_POST['subject']);
                $message = $custom_message;
            }
            
            // Create in-app notification
            $this->create_notification(
                $user_id,
                $org_id,
                'bulk_announcement',
                $subject,
                strip_tags($message)
            );
            
            // Send email if enabled
            if (isset($_POST['send_email']) && $_POST['send_email']) {
                wp_mail(
                    $user->user_email,
                    $subject,
                    $message,
                    array('Content-Type: text/html; charset=UTF-8')
                );
            }
            
            // Send SMS if enabled and phone available
            if (isset($_POST['send_sms']) && $_POST['send_sms']) {
                $phone = get_user_meta($user_id, 'phone', true);
                if ($phone) {
                    do_action('univga_send_sms', $phone, strip_tags($message), $org_id);
                }
            }
            
            $sent_count++;
        }
        
        wp_send_json_success(array(
            'sent_count' => $sent_count,
            'message' => sprintf('Notification sent to %d users', $sent_count)
        ));
    }
    
    /**
     * Process template variables
     */
    private function process_template_variables($template, $user) {
        $variables = array(
            '{user_name}' => $user->display_name,
            '{first_name}' => get_user_meta($user->ID, 'first_name', true),
            '{last_name}' => get_user_meta($user->ID, 'last_name', true),
            '{email}' => $user->user_email,
            '{site_name}' => get_bloginfo('name'),
            '{date}' => date('F j, Y'),
            '{time}' => date('g:i A')
        );
        
        return str_replace(array_keys($variables), array_values($variables), $template);
    }
    
    /**
     * Automated notification: Course completion
     */
    public function notify_course_completion($user_id, $course_id, $org_id) {
        $course = get_post($course_id);
        $user = get_user_by('ID', $user_id);
        
        $title = sprintf(__('Course Completed: %s', UNIVGA_TEXT_DOMAIN), $course->post_title);
        $message = sprintf(
            __('Congratulations %s! You have successfully completed the course "%s".', UNIVGA_TEXT_DOMAIN),
            $user->display_name,
            $course->post_title
        );
        
        $this->create_notification($user_id, $org_id, 'course_completed', $title, $message);
        
        // Send email using template if available
        $this->send_templated_email($user_id, $org_id, 'course_completed', array(
            'course_name' => $course->post_title,
            'user_name' => $user->display_name
        ));
    }
    
    /**
     * Automated notification: Certification earned
     */
    public function notify_certification_earned($user_id, $certification_id) {
        global $wpdb;
        
        $cert = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_certifications WHERE id = %d
        ", $certification_id));
        
        if ($cert) {
            $user = get_user_by('ID', $user_id);
            
            $title = sprintf(__('Certification Earned: %s', UNIVGA_TEXT_DOMAIN), $cert->name);
            $message = sprintf(
                __('Congratulations %s! You have earned the "%s" certification.', UNIVGA_TEXT_DOMAIN),
                $user->display_name,
                $cert->name
            );
            
            $this->create_notification($user_id, $cert->org_id, 'certification_earned', $title, $message);
        }
    }
    
    /**
     * Automated notification: Learning path assigned
     */
    public function notify_path_assignment($user_id, $path_id, $assigned_by) {
        global $wpdb;
        
        $path = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_learning_paths WHERE id = %d
        ", $path_id));
        
        if ($path) {
            $user = get_user_by('ID', $user_id);
            $assigner = get_user_by('ID', $assigned_by);
            
            $title = sprintf(__('New Learning Path Assigned: %s', UNIVGA_TEXT_DOMAIN), $path->name);
            $message = sprintf(
                __('Hi %s, you have been assigned to the learning path "%s" by %s.', UNIVGA_TEXT_DOMAIN),
                $user->display_name,
                $path->name,
                $assigner->display_name
            );
            
            $this->create_notification(
                $user_id, 
                $path->org_id, 
                'path_assigned', 
                $title, 
                $message,
                admin_url('admin.php?page=univga-dashboard&tab=learning-paths')
            );
        }
    }
    
    /**
     * Send templated email
     */
    private function send_templated_email($user_id, $org_id, $template_type, $variables = array()) {
        global $wpdb;
        
        $template = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_notification_templates 
            WHERE org_id = %d AND type = %s AND is_active = 1
            LIMIT 1
        ", $org_id, $template_type));
        
        if ($template) {
            $user = get_user_by('ID', $user_id);
            
            $subject = $this->process_template_variables($template->subject, $user);
            $message = $this->process_template_variables($template->template, $user);
            
            // Process custom variables
            foreach ($variables as $key => $value) {
                $subject = str_replace('{' . $key . '}', $value, $subject);
                $message = str_replace('{' . $key . '}', $value, $message);
            }
            
            wp_mail(
                $user->user_email,
                $subject,
                $message,
                array('Content-Type: text/html; charset=UTF-8')
            );
        }
    }
    
    /**
     * Send SMS notification (requires Twilio)
     */
    public function send_sms_notification($to_phone, $message, $org_id) {
        if (!defined('UNIVGA_TWILIO_SID') || !defined('UNIVGA_TWILIO_TOKEN') || !defined('UNIVGA_TWILIO_FROM')) {
            return false;
        }
        
        // Format phone number
        $to_phone = preg_replace('/[^0-9]/', '', $to_phone);
        if (substr($to_phone, 0, 1) !== '1' && strlen($to_phone) === 10) {
            $to_phone = '1' . $to_phone;
        }
        $to_phone = '+' . $to_phone;
        
        // Truncate message for SMS
        $message = substr($message, 0, 160);
        
        // Send via Twilio API
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . UNIVGA_TWILIO_SID . '/Messages.json';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode(UNIVGA_TWILIO_SID . ':' . UNIVGA_TWILIO_TOKEN)
            ),
            'body' => array(
                'From' => UNIVGA_TWILIO_FROM,
                'To' => $to_phone,
                'Body' => $message
            )
        ));
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 201;
    }
    
    /**
     * Setup email automation workflows
     */
    public function setup_email_automation() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $automation_rules = $_POST['automation_rules'];
        
        // Store automation rules as organization meta
        update_option('univga_automation_rules_' . $org_id, $automation_rules);
        
        wp_send_json_success(array('message' => 'Automation rules saved successfully'));
    }
    
    /**
     * Send real-time notification via WebSocket
     */
    private function send_realtime_notification($user_id, $notification_data) {
        // This would integrate with a WebSocket server or browser push notifications
        // For now, we'll store it for AJAX polling
        $transient_key = 'univga_realtime_' . $user_id;
        $existing = get_transient($transient_key) ?: array();
        $existing[] = $notification_data;
        set_transient($transient_key, $existing, 300); // 5 minutes
    }
}