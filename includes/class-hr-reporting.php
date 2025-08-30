<?php

/**
 * HR Reporting & Manager Dashboards
 * 
 * Advanced reporting system for HR managers with team-specific dashboards,
 * automated reports, and ministry-specific KPIs
 */
class UNIVGA_HR_Reporting {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX handlers for manager dashboards
        add_action('wp_ajax_univga_manager_dashboard_data', array($this, 'get_manager_dashboard_data'));
        add_action('wp_ajax_univga_team_performance_data', array($this, 'get_team_performance_data'));
        add_action('wp_ajax_univga_employee_detailed_view', array($this, 'get_employee_detailed_view'));
        
        // Export handlers  
        add_action('wp_ajax_univga_export_hr_report', array($this, 'export_hr_report'));
        add_action('wp_ajax_univga_export_certified_list', array($this, 'export_certified_list'));
        add_action('wp_ajax_univga_export_team_comparison', array($this, 'export_team_comparison'));
        add_action('wp_ajax_univga_export_attendance_report', array($this, 'export_attendance_report'));
        
        // Automated reports
        add_action('wp_ajax_univga_setup_automated_reports', array($this, 'setup_automated_reports'));
        add_action('univga_send_weekly_hr_report', array($this, 'send_weekly_hr_report'));
        add_action('univga_send_monthly_hr_report', array($this, 'send_monthly_hr_report'));
        
        // Schedule automated reports
        if (!wp_next_scheduled('univga_send_weekly_hr_report')) {
            wp_schedule_event(strtotime('next Monday 9:00am'), 'weekly', 'univga_send_weekly_hr_report');
        }
        
        if (!wp_next_scheduled('univga_send_monthly_hr_report')) {
            wp_schedule_event(strtotime('first Monday of next month 9:00am'), 'monthly', 'univga_send_monthly_hr_report');
        }
    }
    
    /**
     * Get manager dashboard data - Vue globale
     */
    public function get_manager_dashboard_data() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_team_view')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $manager_id = get_current_user_id();
        
        // Get manager's teams
        $teams = $this->get_manager_teams($manager_id, $org_id);
        
        $global_data = array(
            'seats_usage' => $this->get_seats_usage_summary($org_id, $teams),
            'average_progression' => $this->get_average_progression($org_id, $teams),
            'completion_rates' => $this->get_completion_rates_summary($org_id, $teams),
            'certificates_earned' => $this->get_certificates_summary($org_id, $teams),
            'recent_activity' => $this->get_recent_activity($org_id, $teams),
            'performance_trends' => $this->get_performance_trends($org_id, $teams)
        );
        
        wp_send_json_success($global_data);
    }
    
    /**
     * Get team performance data - Vue par équipe
     */
    public function get_team_performance_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_team_view')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $team_id = intval($_POST['team_id']);
        $org_id = intval($_POST['org_id']);
        
        $team_data = array(
            'team_info' => $this->get_team_basic_info($team_id),
            'progression_stats' => $this->get_team_progression_stats($team_id),
            'laggards' => $this->identify_team_laggards($team_id),
            'top_performers' => $this->identify_top_performers($team_id),
            'course_breakdown' => $this->get_team_course_breakdown($team_id),
            'monthly_trends' => $this->get_team_monthly_trends($team_id)
        );
        
        wp_send_json_success($team_data);
    }
    
    /**
     * Get employee detailed view - Vue par employé
     */
    public function get_employee_detailed_view() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_member_view_team')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $user_id = intval($_POST['user_id']);
        $org_id = intval($_POST['org_id']);
        
        $employee_data = array(
            'profile' => $this->get_employee_profile($user_id),
            'progression_percentage' => $this->get_employee_progression_percentage($user_id, $org_id),
            'last_access' => $this->get_employee_last_access($user_id, $org_id),
            'course_grades' => $this->get_employee_course_grades($user_id, $org_id),
            'certificates' => $this->get_employee_certificates($user_id, $org_id),
            'learning_path_progress' => $this->get_learning_path_progress($user_id, $org_id),
            'engagement_score' => $this->calculate_engagement_score($user_id, $org_id),
            'time_spent' => $this->get_time_spent_learning($user_id, $org_id)
        );
        
        wp_send_json_success($employee_data);
    }
    
    /**
     * Export HR report with multiple formats
     */
    public function export_hr_report() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_reports_export')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $format = sanitize_text_field($_POST['format']); // csv, excel, pdf
        $org_id = intval($_POST['org_id']);
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        $data = $this->generate_hr_report_data($report_type, $org_id, $filters);
        
        switch ($format) {
            case 'excel':
                $file_url = $this->export_to_excel($data, $report_type);
                break;
            case 'pdf':
                $file_url = $this->export_to_pdf($data, $report_type);
                break;
            default:
                $file_url = $this->export_to_csv($data, $report_type);
        }
        
        if ($file_url) {
            wp_send_json_success(array('download_url' => $file_url));
        } else {
            wp_send_json_error(__('Export failed', UNIVGA_TEXT_DOMAIN));
        }
    }
    
    /**
     * Export certified employees list
     */
    public function export_certified_list() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_cert_view_all')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $certification_id = isset($_POST['certification_id']) ? intval($_POST['certification_id']) : null;
        $format = sanitize_text_field($_POST['format']);
        
        $certified_list = $this->get_certified_employees_list($org_id, $certification_id);
        
        switch ($format) {
            case 'excel':
                $file_url = $this->export_certified_to_excel($certified_list);
                break;
            case 'pdf':
                $file_url = $this->export_certified_to_pdf($certified_list);
                break;
            default:
                $file_url = $this->export_certified_to_csv($certified_list);
        }
        
        wp_send_json_success(array('download_url' => $file_url));
    }
    
    /**
     * Export team comparison report
     */
    public function export_team_comparison() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_reports_view_org')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $format = sanitize_text_field($_POST['format']);
        
        $comparison_data = $this->generate_team_comparison_data($org_id);
        
        switch ($format) {
            case 'excel':
                $file_url = $this->export_comparison_to_excel($comparison_data);
                break;
            case 'pdf':
                $file_url = $this->export_comparison_to_pdf($comparison_data);
                break;
            default:
                $file_url = $this->export_comparison_to_csv($comparison_data);
        }
        
        wp_send_json_success(array('download_url' => $file_url));
    }
    
    /**
     * Export attendance and activity report
     */
    public function export_attendance_report() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_reports_view_org')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $date_range = sanitize_text_field($_POST['date_range']);
        $format = sanitize_text_field($_POST['format']);
        
        $attendance_data = $this->generate_attendance_report($org_id, $date_range);
        
        switch ($format) {
            case 'excel':
                $file_url = $this->export_attendance_to_excel($attendance_data);
                break;
            case 'pdf':
                $file_url = $this->export_attendance_to_pdf($attendance_data);
                break;
            default:
                $file_url = $this->export_attendance_to_csv($attendance_data);
        }
        
        wp_send_json_success(array('download_url' => $file_url));
    }
    
    /**
     * Send weekly automated HR report
     */
    public function send_weekly_hr_report() {
        global $wpdb;
        
        // Get all organizations with active HR managers
        $hr_managers = $wpdb->get_results("
            SELECT DISTINCT u.ID, u.user_email, u.display_name, m.org_id
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->prefix}univga_members m ON u.ID = m.user_id
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'univga_profile'
            AND um.meta_value IN ('hr', 'admin', 'manager')
            AND m.status = 'active'
        ");
        
        foreach ($hr_managers as $manager) {
            $report_data = $this->generate_weekly_report_data($manager->org_id);
            $this->send_hr_email_report($manager, $report_data, 'weekly');
        }
    }
    
    /**
     * Send monthly automated HR report
     */
    public function send_monthly_hr_report() {
        global $wpdb;
        
        $hr_managers = $wpdb->get_results("
            SELECT DISTINCT u.ID, u.user_email, u.display_name, m.org_id
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->prefix}univga_members m ON u.ID = m.user_id
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'univga_profile'
            AND um.meta_value IN ('hr', 'admin')
            AND m.status = 'active'
        ");
        
        foreach ($hr_managers as $manager) {
            $report_data = $this->generate_monthly_report_data($manager->org_id);
            $this->send_hr_email_report($manager, $report_data, 'monthly');
        }
    }
    
    /**
     * Get ministry-specific KPIs
     */
    public function get_ministry_kpis($org_id) {
        global $wpdb;
        
        return array(
            'agents_trained' => $this->count_trained_agents($org_id),
            'average_progression' => $this->calculate_ministry_avg_progression($org_id),
            'exam_success_rate' => $this->calculate_exam_success_rate($org_id),
            'compliance_rate' => $this->calculate_compliance_rate($org_id),
            'training_hours' => $this->calculate_total_training_hours($org_id),
            'cost_per_trainee' => $this->calculate_cost_per_trainee($org_id),
            'departmental_breakdown' => $this->get_departmental_training_breakdown($org_id)
        );
    }
    
    // === HELPER METHODS ===
    
    /**
     * Get manager's teams
     */
    private function get_manager_teams($manager_id, $org_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT t.* FROM {$wpdb->prefix}univga_teams t
            WHERE t.org_id = %d
            AND (t.manager_id = %d OR EXISTS (
                SELECT 1 FROM {$wpdb->prefix}univga_members m
                WHERE m.team_id = t.id AND m.user_id = %d AND m.role IN ('manager', 'team_lead')
            ))
        ", $org_id, $manager_id, $manager_id));
    }
    
    /**
     * Get seats usage summary
     */
    private function get_seats_usage_summary($org_id, $teams) {
        global $wpdb;
        
        $team_ids = wp_list_pluck($teams, 'id');
        if (empty($team_ids)) return array('total' => 0, 'used' => 0, 'available' => 0);
        
        $team_ids_str = implode(',', array_map('intval', $team_ids));
        
        $stats = $wpdb->get_row("
            SELECT 
                SUM(sp.seats_total) as total_seats,
                SUM(sp.seats_used) as used_seats
            FROM {$wpdb->prefix}univga_seat_pools sp
            WHERE sp.org_id = {$org_id}
        ");
        
        return array(
            'total' => intval($stats->total_seats),
            'used' => intval($stats->used_seats),
            'available' => intval($stats->total_seats) - intval($stats->used_seats),
            'utilization_rate' => intval($stats->total_seats) > 0 ? round((intval($stats->used_seats) / intval($stats->total_seats)) * 100, 1) : 0
        );
    }
    
    /**
     * Get average progression
     */
    private function get_average_progression($org_id, $teams) {
        global $wpdb;
        
        $team_ids = wp_list_pluck($teams, 'id');
        if (empty($team_ids)) return 0;
        
        $team_ids_str = implode(',', array_map('intval', $team_ids));
        
        $avg = $wpdb->get_var("
            SELECT AVG(
                CASE 
                    WHEN ae.event_type = 'course_completed' THEN 100
                    ELSE CAST(JSON_UNQUOTE(JSON_EXTRACT(ae.event_data, '$.progress')) AS DECIMAL(5,2))
                END
            )
            FROM {$wpdb->prefix}univga_analytics_events ae
            INNER JOIN {$wpdb->prefix}univga_members m ON ae.user_id = m.user_id
            WHERE m.team_id IN ({$team_ids_str})
            AND ae.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return round(floatval($avg), 1);
    }
    
    /**
     * Generate weekly report data
     */
    private function generate_weekly_report_data($org_id) {
        return array(
            'period' => 'Semaine du ' . date('d/m/Y', strtotime('last Monday')) . ' au ' . date('d/m/Y'),
            'new_enrollments' => $this->count_new_enrollments($org_id, 'week'),
            'completions' => $this->count_completions($org_id, 'week'),
            'certificates_earned' => $this->count_certificates_earned($org_id, 'week'),
            'active_learners' => $this->count_active_learners($org_id, 'week'),
            'top_courses' => $this->get_top_courses($org_id, 'week'),
            'team_performance' => $this->get_teams_performance_summary($org_id, 'week')
        );
    }
    
    /**
     * Generate monthly report data
     */
    private function generate_monthly_report_data($org_id) {
        return array(
            'period' => 'Mois de ' . date('F Y', strtotime('first day of last month')),
            'monthly_stats' => $this->get_monthly_stats($org_id),
            'trends' => $this->get_monthly_trends($org_id),
            'compliance_status' => $this->get_monthly_compliance_status($org_id),
            'budget_utilization' => $this->get_budget_utilization($org_id),
            'recommendations' => $this->generate_monthly_recommendations($org_id)
        );
    }
    
    /**
     * Send HR email report
     */
    private function send_hr_email_report($manager, $report_data, $frequency) {
        $subject = sprintf(
            __('Rapport RH %s - %s', UNIVGA_TEXT_DOMAIN),
            $frequency === 'weekly' ? 'Hebdomadaire' : 'Mensuel',
            get_bloginfo('name')
        );
        
        $template = $frequency === 'weekly' ? 'hr_weekly_report' : 'hr_monthly_report';
        
        // Generate HTML report
        ob_start();
        include UNIVGA_PLUGIN_DIR . 'templates/emails/' . $template . '.php';
        $html_content = ob_get_clean();
        
        // Send email
        wp_mail(
            $manager->user_email,
            $subject,
            $html_content,
            array('Content-Type: text/html; charset=UTF-8')
        );
    }
    
    /**
     * Export to Excel format
     */
    private function export_to_excel($data, $report_type) {
        // Require PHPSpreadsheet library
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return false;
        }
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Add headers and data based on report type
        $this->populate_excel_sheet($sheet, $data, $report_type);
        
        // Save file
        $upload_dir = wp_upload_dir();
        $filename = 'hr-report-' . $report_type . '-' . date('Y-m-d-H-i-s') . '.xlsx';
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($file_path);
        
        return $upload_dir['url'] . '/' . $filename;
    }
    
    /**
     * Export to PDF format
     */
    private function export_to_pdf($data, $report_type) {
        // Use TCPDF or similar library
        if (!class_exists('TCPDF')) {
            return false;
        }
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('UNIVGA LMS');
        $pdf->SetTitle('Rapport RH - ' . ucfirst($report_type));
        
        $pdf->AddPage();
        
        // Generate PDF content
        $html_content = $this->generate_pdf_content($data, $report_type);
        $pdf->writeHTML($html_content, true, false, true, false, '');
        
        // Save file
        $upload_dir = wp_upload_dir();
        $filename = 'hr-report-' . $report_type . '-' . date('Y-m-d-H-i-s') . '.pdf';
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        $pdf->Output($file_path, 'F');
        
        return $upload_dir['url'] . '/' . $filename;
    }
    
    // Additional helper methods would continue here...
    // For brevity, showing key structure and methods
}

// Initialize HR Reporting system
UNIVGA_HR_Reporting::getInstance();