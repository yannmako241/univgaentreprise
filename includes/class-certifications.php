<?php

/**
 * UNIVGA Certifications Class
 * Advanced Certification & Compliance Tracking System
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_Certifications {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_univga_create_certification', array($this, 'create_certification'));
        add_action('wp_ajax_univga_get_certifications', array($this, 'get_certifications'));
        add_action('wp_ajax_univga_award_certification', array($this, 'award_certification'));
        add_action('wp_ajax_univga_check_compliance', array($this, 'check_compliance_status'));
        add_action('univga_course_completed', array($this, 'check_certification_eligibility'), 10, 3);
        add_action('wp_ajax_univga_generate_certificate', array($this, 'generate_certificate'));
        
        // Cron jobs for compliance monitoring
        add_action('univga_check_expiring_certifications', array($this, 'check_expiring_certifications'));
        add_action('wp', array($this, 'schedule_compliance_checks'));
    }
    
    /**
     * Schedule compliance check cron jobs
     */
    public function schedule_compliance_checks() {
        if (!wp_next_scheduled('univga_check_expiring_certifications')) {
            wp_schedule_event(time(), 'daily', 'univga_check_expiring_certifications');
        }
    }
    
    /**
     * Create new certification
     */
    public function create_certification() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $cert_data = array(
            'org_id' => intval($_POST['org_id']),
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'requirements' => json_encode($_POST['requirements']),
            'validity_period' => intval($_POST['validity_period']), // days
            'template_id' => sanitize_text_field($_POST['template_id']),
            'is_compliance' => isset($_POST['is_compliance']) ? 1 : 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_certifications',
            $cert_data,
            array('%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'certification_id' => $wpdb->insert_id,
                'message' => 'Certification created successfully'
            ));
        } else {
            wp_send_json_error('Failed to create certification');
        }
    }
    
    /**
     * Get certifications for organization
     */
    public function get_certifications() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        global $wpdb;
        
        $certifications = $wpdb->get_results($wpdb->prepare("
            SELECT c.*,
                   COUNT(uc.id) as total_earned,
                   COUNT(CASE WHEN uc.status = 'earned' AND (uc.expires_date IS NULL OR uc.expires_date > NOW()) THEN 1 END) as active_count,
                   COUNT(CASE WHEN uc.expires_date <= NOW() THEN 1 END) as expired_count
            FROM {$wpdb->prefix}univga_certifications c
            LEFT JOIN {$wpdb->prefix}univga_user_certifications uc ON c.id = uc.certification_id
            WHERE c.org_id = %d
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ", $org_id));
        
        wp_send_json_success($certifications);
    }
    
    /**
     * Award certification to user
     */
    public function award_certification() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_org_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $user_id = intval($_POST['user_id']);
        $certification_id = intval($_POST['certification_id']);
        $org_id = intval($_POST['org_id']);
        
        // Get certification details
        $certification = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_certifications 
            WHERE id = %d AND org_id = %d
        ", $certification_id, $org_id));
        
        if (!$certification) {
            wp_send_json_error('Certification not found');
            return;
        }
        
        // Calculate expiry date
        $expires_date = null;
        if ($certification->validity_period > 0) {
            $expires_date = date('Y-m-d H:i:s', strtotime('+' . $certification->validity_period . ' days'));
        }
        
        // Generate certificate
        $certificate_url = $this->generate_certificate_file($user_id, $certification);
        
        // Award certification
        $award_data = array(
            'user_id' => $user_id,
            'certification_id' => $certification_id,
            'org_id' => $org_id,
            'status' => 'earned',
            'earned_date' => current_time('mysql'),
            'expires_date' => $expires_date,
            'certificate_url' => $certificate_url
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_user_certifications',
            $award_data,
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Send notification
            $this->send_certification_notification($user_id, $certification, 'awarded');
            
            // Award points if gamification is active
            do_action('univga_award_points', $user_id, 'certification_earned', $certification_id);
            
            wp_send_json_success(array(
                'message' => 'Certification awarded successfully',
                'certificate_url' => $certificate_url
            ));
        } else {
            wp_send_json_error('Failed to award certification');
        }
    }
    
    /**
     * Check certification eligibility after course completion
     */
    public function check_certification_eligibility($user_id, $course_id, $org_id) {
        global $wpdb;
        
        // Find certifications that might be eligible
        $certifications = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_certifications 
            WHERE org_id = %d AND requirements IS NOT NULL
        ", $org_id));
        
        foreach ($certifications as $cert) {
            $requirements = json_decode($cert->requirements, true);
            
            if ($this->user_meets_certification_requirements($user_id, $requirements)) {
                // Auto-award certification
                $this->auto_award_certification($user_id, $cert);
            }
        }
    }
    
    /**
     * Check if user meets certification requirements
     */
    private function user_meets_certification_requirements($user_id, $requirements) {
        global $wpdb;
        
        foreach ($requirements as $requirement) {
            switch ($requirement['type']) {
                case 'courses_completed':
                    $required_courses = $requirement['course_ids'];
                    $completed_courses = $wpdb->get_col($wpdb->prepare("
                        SELECT DISTINCT course_id 
                        FROM {$wpdb->prefix}univga_analytics_events 
                        WHERE user_id = %d AND event_type = 'course_completed'
                    ", $user_id));
                    
                    foreach ($required_courses as $required_course) {
                        if (!in_array($required_course, $completed_courses)) {
                            return false;
                        }
                    }
                    break;
                    
                case 'learning_path_completed':
                    $path_id = $requirement['path_id'];
                    $path_completed = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) 
                        FROM {$wpdb->prefix}univga_learning_path_assignments 
                        WHERE user_id = %d AND path_id = %d AND status = 'completed'
                    ", $user_id, $path_id));
                    
                    if (!$path_completed) {
                        return false;
                    }
                    break;
                    
                case 'minimum_score':
                    $min_score = $requirement['score'];
                    // This would integrate with quiz/assessment scores
                    // Implementation depends on quiz plugin integration
                    break;
                    
                case 'time_requirement':
                    $min_hours = $requirement['hours'];
                    // Check total learning time
                    break;
            }
        }
        
        return true;
    }
    
    /**
     * Auto-award certification
     */
    private function auto_award_certification($user_id, $certification) {
        global $wpdb;
        
        // Check if already awarded
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}univga_user_certifications 
            WHERE user_id = %d AND certification_id = %d AND status = 'earned'
        ", $user_id, $certification->id));
        
        if ($existing) {
            return false;
        }
        
        // Calculate expiry date
        $expires_date = null;
        if ($certification->validity_period > 0) {
            $expires_date = date('Y-m-d H:i:s', strtotime('+' . $certification->validity_period . ' days'));
        }
        
        // Generate certificate
        $certificate_url = $this->generate_certificate_file($user_id, $certification);
        
        // Award certification
        $award_data = array(
            'user_id' => $user_id,
            'certification_id' => $certification->id,
            'org_id' => $certification->org_id,
            'status' => 'earned',
            'earned_date' => current_time('mysql'),
            'expires_date' => $expires_date,
            'certificate_url' => $certificate_url
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_user_certifications',
            $award_data,
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Send notification
            $this->send_certification_notification($user_id, $certification, 'auto_awarded');
            
            // Award points
            do_action('univga_award_points', $user_id, 'certification_earned', $certification->id);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate certificate file
     */
    private function generate_certificate_file($user_id, $certification) {
        $user = get_user_by('ID', $user_id);
        
        // Create certificate HTML
        $html = $this->get_certificate_template($user, $certification);
        
        // Generate PDF (requires a PDF library like TCPDF or mPDF)
        // For now, we'll create a simple HTML certificate
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/univga-certificates/';
        
        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
        }
        
        $filename = 'certificate-' . $user_id . '-' . $certification->id . '-' . time() . '.html';
        $filepath = $cert_dir . $filename;
        
        file_put_contents($filepath, $html);
        
        return $upload_dir['baseurl'] . '/univga-certificates/' . $filename;
    }
    
    /**
     * Get certificate template
     */
    private function get_certificate_template($user, $certification) {
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Certificate of Completion</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .certificate { border: 10px solid #0073aa; padding: 50px; margin: 20px; }
                .title { font-size: 48px; color: #0073aa; margin-bottom: 30px; }
                .subtitle { font-size: 24px; margin-bottom: 40px; }
                .name { font-size: 36px; font-weight: bold; color: #333; margin: 30px 0; }
                .description { font-size: 18px; margin: 20px 0; }
                .date { font-size: 16px; color: #666; margin-top: 40px; }
            </style>
        </head>
        <body>
            <div class="certificate">
                <div class="title">Certificate of Completion</div>
                <div class="subtitle">This is to certify that</div>
                <div class="name">' . esc_html($user->display_name) . '</div>
                <div class="subtitle">has successfully completed</div>
                <div class="name">' . esc_html($certification->name) . '</div>
                <div class="description">' . esc_html($certification->description) . '</div>
                <div class="date">Awarded on ' . date('F j, Y') . '</div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Send certification notification
     */
    private function send_certification_notification($user_id, $certification, $type) {
        $user = get_user_by('ID', $user_id);
        
        $subject = sprintf(__('Congratulations! You\'ve earned the %s certification', UNIVGA_TEXT_DOMAIN), $certification->name);
        
        $message = sprintf(
            __('Dear %s,<br><br>Congratulations! You have successfully earned the <strong>%s</strong> certification.<br><br>%s<br><br>You can view and download your certificate from your dashboard.', UNIVGA_TEXT_DOMAIN),
            $user->display_name,
            $certification->name,
            $certification->description
        );
        
        wp_mail(
            $user->user_email,
            $subject,
            $message,
            array('Content-Type: text/html; charset=UTF-8')
        );
        
        // Create in-app notification
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'univga_notifications',
            array(
                'user_id' => $user_id,
                'org_id' => $certification->org_id,
                'type' => 'certification_earned',
                'title' => $subject,
                'message' => strip_tags($message),
                'action_url' => admin_url('admin.php?page=univga-dashboard'),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Check compliance status
     */
    public function check_compliance_status() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        global $wpdb;
        
        $compliance_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.name as certification_name,
                COUNT(uc.id) as total_holders,
                COUNT(CASE WHEN uc.expires_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
                COUNT(CASE WHEN uc.expires_date <= NOW() THEN 1 END) as expired,
                COUNT(CASE WHEN uc.status = 'earned' AND (uc.expires_date IS NULL OR uc.expires_date > NOW()) THEN 1 END) as active
            FROM {$wpdb->prefix}univga_certifications c
            LEFT JOIN {$wpdb->prefix}univga_user_certifications uc ON c.id = uc.certification_id
            WHERE c.org_id = %d AND c.is_compliance = 1
            GROUP BY c.id, c.name
            ORDER BY expiring_soon DESC, expired DESC
        ", $org_id));
        
        wp_send_json_success($compliance_data);
    }
    
    /**
     * Check for expiring certifications (cron job)
     */
    public function check_expiring_certifications() {
        global $wpdb;
        
        // Find certifications expiring in 30 days
        $expiring = $wpdb->get_results("
            SELECT uc.*, u.user_email, u.display_name, c.name as cert_name
            FROM {$wpdb->prefix}univga_user_certifications uc
            LEFT JOIN {$wpdb->users} u ON uc.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}univga_certifications c ON uc.certification_id = c.id
            WHERE uc.expires_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
            AND uc.status = 'earned'
        ");
        
        foreach ($expiring as $cert) {
            $this->send_expiration_warning($cert);
        }
        
        // Mark expired certifications
        $wpdb->query("
            UPDATE {$wpdb->prefix}univga_user_certifications 
            SET status = 'expired'
            WHERE expires_date <= NOW() AND status = 'earned'
        ");
    }
    
    /**
     * Send expiration warning
     */
    private function send_expiration_warning($cert_data) {
        $subject = sprintf(__('Your %s certification expires soon', UNIVGA_TEXT_DOMAIN), $cert_data->cert_name);
        
        $days_until_expiry = ceil((strtotime($cert_data->expires_date) - time()) / (60 * 60 * 24));
        
        $message = sprintf(
            __('Dear %s,<br><br>This is a reminder that your <strong>%s</strong> certification will expire in %d days on %s.<br><br>Please renew your certification to maintain compliance.', UNIVGA_TEXT_DOMAIN),
            $cert_data->display_name,
            $cert_data->cert_name,
            $days_until_expiry,
            date('F j, Y', strtotime($cert_data->expires_date))
        );
        
        wp_mail(
            $cert_data->user_email,
            $subject,
            $message,
            array('Content-Type: text/html; charset=UTF-8')
        );
    }
    
    /**
     * Generate certificate via AJAX
     */
    public function generate_certificate() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $user_cert_id = intval($_POST['user_cert_id']);
        global $wpdb;
        
        $cert_data = $wpdb->get_row($wpdb->prepare("
            SELECT uc.*, c.name, c.description, u.display_name
            FROM {$wpdb->prefix}univga_user_certifications uc
            LEFT JOIN {$wpdb->prefix}univga_certifications c ON uc.certification_id = c.id
            LEFT JOIN {$wpdb->users} u ON uc.user_id = u.ID
            WHERE uc.id = %d
        ", $user_cert_id));
        
        if (!$cert_data) {
            wp_send_json_error('Certificate not found');
            return;
        }
        
        if ($cert_data->certificate_url) {
            wp_send_json_success(array('certificate_url' => $cert_data->certificate_url));
        } else {
            // Generate new certificate
            $certificate_url = $this->generate_certificate_file($cert_data->user_id, $cert_data);
            
            // Update record
            $wpdb->update(
                $wpdb->prefix . 'univga_user_certifications',
                array('certificate_url' => $certificate_url),
                array('id' => $user_cert_id),
                array('%s'),
                array('%d')
            );
            
            wp_send_json_success(array('certificate_url' => $certificate_url));
        }
    }
}