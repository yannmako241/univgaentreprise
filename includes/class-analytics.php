<?php

/**
 * UNIVGA Analytics Class
 * Advanced Learning Analytics Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_Analytics {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_univga_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_univga_export_analytics', array($this, 'export_analytics'));
        add_action('univga_course_completed', array($this, 'track_completion'), 10, 3);
        add_action('univga_course_started', array($this, 'track_start'), 10, 3);
    }
    
    /**
     * Track course completion
     */
    public function track_completion($user_id, $course_id, $org_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'univga_analytics_events';
        
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'org_id' => $org_id,
                'course_id' => $course_id,
                'event_type' => 'course_completed',
                'event_data' => json_encode(array(
                    'completion_time' => time(),
                    'progress' => 100
                )),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );
        
        // Update completion stats
        $this->update_completion_stats($org_id, $course_id);
    }
    
    /**
     * Track course start
     */
    public function track_start($user_id, $course_id, $org_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'univga_analytics_events';
        
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'org_id' => $org_id,
                'course_id' => $course_id,
                'event_type' => 'course_started',
                'event_data' => json_encode(array(
                    'start_time' => time(),
                    'progress' => 0
                )),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get analytics data for dashboard
     */
    public function get_analytics_data() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_reports_view')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $timeframe = sanitize_text_field($_POST['timeframe']);
        
        $data = array(
            'completion_rates' => $this->get_completion_rates($org_id, $timeframe),
            'engagement_metrics' => $this->get_engagement_metrics($org_id, $timeframe),
            'progress_tracking' => $this->get_progress_tracking($org_id, $timeframe),
            'skill_gaps' => $this->identify_skill_gaps($org_id),
            'at_risk_learners' => $this->identify_at_risk_learners($org_id)
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * Get completion rates
     */
    private function get_completion_rates($org_id, $timeframe) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($timeframe);
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.post_title as course_name,
                COUNT(CASE WHEN ae.event_type = 'course_started' THEN 1 END) as started,
                COUNT(CASE WHEN ae.event_type = 'course_completed' THEN 1 END) as completed,
                ROUND((COUNT(CASE WHEN ae.event_type = 'course_completed' THEN 1 END) / 
                       COUNT(CASE WHEN ae.event_type = 'course_started' THEN 1 END)) * 100, 2) as completion_rate
            FROM {$wpdb->prefix}univga_analytics_events ae
            LEFT JOIN {$wpdb->posts} c ON ae.course_id = c.ID
            WHERE ae.org_id = %d {$date_condition}
            AND ae.event_type IN ('course_started', 'course_completed')
            GROUP BY ae.course_id, c.post_title
            ORDER BY completion_rate DESC
        ", $org_id));
        
        return $results;
    }
    
    /**
     * Get engagement metrics
     */
    private function get_engagement_metrics($org_id, $timeframe) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($timeframe);
        
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as active_learners,
                COUNT(CASE WHEN event_type = 'course_started' THEN 1 END) as courses_started,
                COUNT(CASE WHEN event_type = 'course_completed' THEN 1 END) as courses_completed,
                AVG(CASE WHEN event_type = 'course_completed' 
                    THEN JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.completion_time')) END) as avg_completion_time
            FROM {$wpdb->prefix}univga_analytics_events
            WHERE org_id = %d {$date_condition}
        ", $org_id));
        
        return $metrics;
    }
    
    /**
     * Get progress tracking data
     */
    private function get_progress_tracking($org_id, $timeframe) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($timeframe);
        
        $progress = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.display_name as learner_name,
                c.post_title as course_name,
                ae.event_type,
                ae.created_at,
                JSON_UNQUOTE(JSON_EXTRACT(ae.event_data, '$.progress')) as progress
            FROM {$wpdb->prefix}univga_analytics_events ae
            LEFT JOIN {$wpdb->users} u ON ae.user_id = u.ID
            LEFT JOIN {$wpdb->posts} c ON ae.course_id = c.ID
            WHERE ae.org_id = %d {$date_condition}
            ORDER BY ae.created_at DESC
            LIMIT 100
        ", $org_id));
        
        return $progress;
    }
    
    /**
     * Identify skill gaps
     */
    private function identify_skill_gaps($org_id) {
        global $wpdb;
        
        // Identify courses with low completion rates or high dropout
        $skill_gaps = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.post_title as course_name,
                COUNT(CASE WHEN ae.event_type = 'course_started' THEN 1 END) as started,
                COUNT(CASE WHEN ae.event_type = 'course_completed' THEN 1 END) as completed,
                ROUND((COUNT(CASE WHEN ae.event_type = 'course_completed' THEN 1 END) / 
                       COUNT(CASE WHEN ae.event_type = 'course_started' THEN 1 END)) * 100, 2) as completion_rate
            FROM {$wpdb->prefix}univga_analytics_events ae
            LEFT JOIN {$wpdb->posts} c ON ae.course_id = c.ID
            WHERE ae.org_id = %d
            AND ae.event_type IN ('course_started', 'course_completed')
            GROUP BY ae.course_id, c.post_title
            HAVING completion_rate < 60
            ORDER BY completion_rate ASC
        ", $org_id));
        
        return $skill_gaps;
    }
    
    /**
     * Identify at-risk learners
     */
    private function identify_at_risk_learners($org_id) {
        global $wpdb;
        
        // Find learners who started courses but haven't completed them in 30+ days
        $at_risk = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.display_name as learner_name,
                u.user_email,
                COUNT(DISTINCT ae.course_id) as started_courses,
                COUNT(CASE WHEN ae.event_type = 'course_completed' THEN 1 END) as completed_courses,
                MAX(ae.created_at) as last_activity
            FROM {$wpdb->prefix}univga_analytics_events ae
            LEFT JOIN {$wpdb->users} u ON ae.user_id = u.ID
            WHERE ae.org_id = %d
            AND ae.event_type = 'course_started'
            AND ae.user_id NOT IN (
                SELECT DISTINCT user_id 
                FROM {$wpdb->prefix}univga_analytics_events 
                WHERE org_id = %d AND event_type = 'course_completed' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            )
            GROUP BY ae.user_id, u.display_name, u.user_email
            HAVING started_courses > completed_courses
            AND last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY last_activity ASC
        ", $org_id, $org_id));
        
        return $at_risk;
    }
    
    /**
     * Get date condition for SQL queries
     */
    private function get_date_condition($timeframe) {
        switch ($timeframe) {
            case '7days':
                return 'AND ae.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            case '30days':
                return 'AND ae.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            case '90days':
                return 'AND ae.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
            case '1year':
                return 'AND ae.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
            default:
                return '';
        }
    }
    
    /**
     * Update completion statistics
     */
    private function update_completion_stats($org_id, $course_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(CASE WHEN event_type = 'course_started' THEN 1 END) as started,
                COUNT(CASE WHEN event_type = 'course_completed' THEN 1 END) as completed
            FROM {$wpdb->prefix}univga_analytics_events
            WHERE org_id = %d AND course_id = %d
        ", $org_id, $course_id));
        
        $completion_rate = $stats->started > 0 ? ($stats->completed / $stats->started) * 100 : 0;
        
        // Store aggregated stats for faster reporting
        $wpdb->replace(
            $wpdb->prefix . 'univga_analytics_summary',
            array(
                'org_id' => $org_id,
                'course_id' => $course_id,
                'total_started' => $stats->started,
                'total_completed' => $stats->completed,
                'completion_rate' => $completion_rate,
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%d', '%f', '%s')
        );
    }
    
    /**
     * Export analytics data
     */
    public function export_analytics() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_reports_view')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $report_type = sanitize_text_field($_POST['report_type']);
        
        $filename = 'univga-analytics-' . $report_type . '-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        switch ($report_type) {
            case 'completion_rates':
                $data = $this->get_completion_rates($org_id, 'all');
                fputcsv($output, array('Course Name', 'Started', 'Completed', 'Completion Rate %'));
                foreach ($data as $row) {
                    fputcsv($output, array($row->course_name, $row->started, $row->completed, $row->completion_rate));
                }
                break;
                
            case 'learner_progress':
                $data = $this->get_progress_tracking($org_id, 'all');
                fputcsv($output, array('Learner', 'Course', 'Event Type', 'Date', 'Progress %'));
                foreach ($data as $row) {
                    fputcsv($output, array($row->learner_name, $row->course_name, $row->event_type, $row->created_at, $row->progress));
                }
                break;
        }
        
        fclose($output);
        exit;
    }
}