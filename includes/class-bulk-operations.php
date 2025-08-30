<?php

/**
 * UNIVGA Bulk Operations Class
 * Bulk Operations & Import Tools for mass user management
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_Bulk_Operations {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_univga_bulk_import_users', array($this, 'handle_bulk_import'));
        add_action('wp_ajax_univga_bulk_enroll_users', array($this, 'handle_bulk_enrollment'));
        add_action('wp_ajax_univga_bulk_assign_teams', array($this, 'handle_bulk_team_assignment'));
        add_action('wp_ajax_univga_get_bulk_operations', array($this, 'get_bulk_operations'));
        add_action('wp_ajax_univga_process_bulk_operation', array($this, 'process_bulk_operation'));
        
        // Handle file uploads
        add_action('wp_ajax_univga_upload_bulk_file', array($this, 'handle_file_upload'));
    }
    
    /**
     * Handle file upload for bulk operations
     */
    public function handle_file_upload() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_org_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!isset($_FILES['bulk_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['bulk_file'];
        $allowed_types = array('text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Invalid file type. Please upload CSV or Excel files only.');
        }
        
        $upload_dir = wp_upload_dir();
        $bulk_dir = $upload_dir['basedir'] . '/univga-bulk/';
        
        if (!file_exists($bulk_dir)) {
            wp_mkdir_p($bulk_dir);
        }
        
        $filename = 'bulk-' . time() . '-' . $file['name'];
        $filepath = $bulk_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Parse the file and return preview
            $preview = $this->parse_file_preview($filepath, $file['type']);
            
            wp_send_json_success(array(
                'filename' => $filename,
                'filepath' => $filepath,
                'preview' => $preview
            ));
        } else {
            wp_send_json_error('Failed to upload file');
        }
    }
    
    /**
     * Parse file preview
     */
    private function parse_file_preview($filepath, $file_type) {
        $preview = array();
        
        if (strpos($file_type, 'csv') !== false) {
            $handle = fopen($filepath, 'r');
            $headers = fgetcsv($handle);
            $preview['headers'] = $headers;
            $preview['rows'] = array();
            
            // Get first 5 rows for preview
            for ($i = 0; $i < 5; $i++) {
                $row = fgetcsv($handle);
                if ($row) {
                    $preview['rows'][] = $row;
                } else {
                    break;
                }
            }
            fclose($handle);
        }
        // Add Excel parsing here if needed (requires PHPSpreadsheet or similar)
        
        return $preview;
    }
    
    /**
     * Handle bulk user import
     */
    public function handle_bulk_import() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_org_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $org_id = intval($_POST['org_id']);
        $team_id = intval($_POST['team_id']);
        $filepath = sanitize_text_field($_POST['filepath']);
        $column_mapping = $_POST['column_mapping'];
        
        // Create bulk operation record
        $operation_id = $this->create_bulk_operation(
            $org_id, 
            'user_import', 
            0, // Will be updated with actual count
            get_current_user_id()
        );
        
        // Process file in background
        wp_schedule_single_event(time(), 'univga_process_bulk_import', array(
            'operation_id' => $operation_id,
            'org_id' => $org_id,
            'team_id' => $team_id,
            'filepath' => $filepath,
            'column_mapping' => $column_mapping
        ));
        
        wp_send_json_success(array(
            'operation_id' => $operation_id,
            'message' => 'Import queued for processing'
        ));
    }
    
    /**
     * Create bulk operation record
     */
    private function create_bulk_operation($org_id, $operation_type, $total_records, $created_by) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'univga_bulk_operations',
            array(
                'org_id' => $org_id,
                'operation_type' => $operation_type,
                'status' => 'pending',
                'total_records' => $total_records,
                'created_by' => $created_by,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%d', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Process bulk import (background task)
     */
    public function process_bulk_import($operation_id, $org_id, $team_id, $filepath, $column_mapping) {
        global $wpdb;
        
        // Update operation status
        $wpdb->update(
            $wpdb->prefix . 'univga_bulk_operations',
            array('status' => 'processing'),
            array('id' => $operation_id),
            array('%s'),
            array('%d')
        );
        
        $handle = fopen($filepath, 'r');
        $headers = fgetcsv($handle); // Skip headers
        
        $processed = 0;
        $failed = 0;
        $errors = array();
        
        while (($row = fgetcsv($handle)) !== false) {
            try {
                $user_data = $this->map_csv_to_user_data($row, $column_mapping);
                
                // Create user
                $user_id = $this->create_user_from_data($user_data);
                
                if (is_wp_error($user_id)) {
                    $failed++;
                    $errors[] = 'Row ' . ($processed + $failed + 1) . ': ' . $user_id->get_error_message();
                } else {
                    // Add to organization
                    $this->add_user_to_organization($user_id, $org_id, $team_id);
                    $processed++;
                }
                
                // Update progress every 10 records
                if (($processed + $failed) % 10 == 0) {
                    $wpdb->update(
                        $wpdb->prefix . 'univga_bulk_operations',
                        array(
                            'processed_records' => $processed,
                            'failed_records' => $failed,
                            'error_log' => json_encode($errors)
                        ),
                        array('id' => $operation_id),
                        array('%d', '%d', '%s'),
                        array('%d')
                    );
                }
                
            } catch (Exception $e) {
                $failed++;
                $errors[] = 'Row ' . ($processed + $failed + 1) . ': ' . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        // Update final status
        $wpdb->update(
            $wpdb->prefix . 'univga_bulk_operations',
            array(
                'status' => 'completed',
                'total_records' => $processed + $failed,
                'processed_records' => $processed,
                'failed_records' => $failed,
                'error_log' => json_encode($errors),
                'completed_at' => current_time('mysql')
            ),
            array('id' => $operation_id),
            array('%s', '%d', '%d', '%d', '%s', '%s'),
            array('%d')
        );
        
        // Clean up uploaded file
        unlink($filepath);
    }
    
    /**
     * Map CSV row to user data
     */
    private function map_csv_to_user_data($row, $column_mapping) {
        $user_data = array();
        
        foreach ($column_mapping as $field => $column_index) {
            if ($column_index !== '' && isset($row[$column_index])) {
                $user_data[$field] = trim($row[$column_index]);
            }
        }
        
        return $user_data;
    }
    
    /**
     * Create user from data
     */
    private function create_user_from_data($user_data) {
        $username = $user_data['username'] ?? $user_data['email'];
        $email = $user_data['email'];
        $first_name = $user_data['first_name'] ?? '';
        $last_name = $user_data['last_name'] ?? '';
        $password = $user_data['password'] ?? wp_generate_password();
        
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address');
        }
        
        if (email_exists($email)) {
            return new WP_Error('email_exists', 'Email already exists');
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (!is_wp_error($user_id)) {
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => trim($first_name . ' ' . $last_name)
            ));
            
            // Store additional meta data
            if (isset($user_data['job_title'])) {
                update_user_meta($user_id, 'job_title', $user_data['job_title']);
            }
            if (isset($user_data['department'])) {
                update_user_meta($user_id, 'department', $user_data['department']);
            }
        }
        
        return $user_id;
    }
    
    /**
     * Add user to organization
     */
    private function add_user_to_organization($user_id, $org_id, $team_id = null) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'univga_org_members',
            array(
                'org_id' => $org_id,
                'team_id' => $team_id,
                'user_id' => $user_id,
                'status' => 'active',
                'joined_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );
    }
    
    /**
     * Handle bulk enrollment
     */
    public function handle_bulk_enrollment() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_org_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $user_ids = array_map('intval', $_POST['user_ids']);
        $course_ids = array_map('intval', $_POST['course_ids']);
        $org_id = intval($_POST['org_id']);
        
        $operation_id = $this->create_bulk_operation(
            $org_id,
            'course_enrollment',
            count($user_ids) * count($course_ids),
            get_current_user_id()
        );
        
        // Process enrollment
        wp_schedule_single_event(time(), 'univga_process_bulk_enrollment', array(
            'operation_id' => $operation_id,
            'user_ids' => $user_ids,
            'course_ids' => $course_ids,
            'org_id' => $org_id
        ));
        
        wp_send_json_success(array(
            'operation_id' => $operation_id,
            'message' => 'Enrollment queued for processing'
        ));
    }
    
    /**
     * Handle bulk team assignment
     */
    public function handle_bulk_team_assignment() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_team_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $user_ids = array_map('intval', $_POST['user_ids']);
        $team_id = intval($_POST['team_id']);
        $org_id = intval($_POST['org_id']);
        
        $updated = 0;
        $failed = 0;
        
        foreach ($user_ids as $user_id) {
            $result = $wpdb->update(
                $wpdb->prefix . 'univga_org_members',
                array('team_id' => $team_id),
                array('user_id' => $user_id, 'org_id' => $org_id),
                array('%d'),
                array('%d', '%d')
            );
            
            if ($result) {
                $updated++;
            } else {
                $failed++;
            }
        }
        
        wp_send_json_success(array(
            'updated' => $updated,
            'failed' => $failed,
            'message' => sprintf('%d users assigned to team, %d failed', $updated, $failed)
        ));
    }
    
    /**
     * Get bulk operations
     */
    public function get_bulk_operations() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        global $wpdb;
        
        $operations = $wpdb->get_results($wpdb->prepare("
            SELECT bo.*, u.display_name as created_by_name
            FROM {$wpdb->prefix}univga_bulk_operations bo
            LEFT JOIN {$wpdb->users} u ON bo.created_by = u.ID
            WHERE bo.org_id = %d
            ORDER BY bo.created_at DESC
            LIMIT 50
        ", $org_id));
        
        wp_send_json_success($operations);
    }
    
    /**
     * Export users to CSV
     */
    public function export_users_csv($org_id, $filters = array()) {
        global $wpdb;
        
        $where_conditions = array("om.org_id = %d");
        $where_values = array($org_id);
        
        if (!empty($filters['team_id'])) {
            $where_conditions[] = "om.team_id = %d";
            $where_values[] = $filters['team_id'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "om.status = %s";
            $where_values[] = $filters['status'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.user_login,
                u.user_email,
                u.display_name,
                um1.meta_value as first_name,
                um2.meta_value as last_name,
                um3.meta_value as job_title,
                um4.meta_value as department,
                t.name as team_name,
                om.status,
                om.joined_at
            FROM {$wpdb->prefix}univga_org_members om
            LEFT JOIN {$wpdb->users} u ON om.user_id = u.ID
            LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
            LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
            LEFT JOIN {$wpdb->usermeta} um3 ON u.ID = um3.user_id AND um3.meta_key = 'job_title'
            LEFT JOIN {$wpdb->usermeta} um4 ON u.ID = um4.user_id AND um4.meta_key = 'department'
            LEFT JOIN {$wpdb->prefix}univga_teams t ON om.team_id = t.id
            WHERE {$where_clause}
            ORDER BY u.display_name ASC
        ", ...$where_values));
        
        $filename = 'univga-users-' . $org_id . '-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, array(
            'Username', 'Email', 'Display Name', 'First Name', 'Last Name', 
            'Job Title', 'Department', 'Team', 'Status', 'Joined Date'
        ));
        
        // Data rows
        foreach ($users as $user) {
            fputcsv($output, array(
                $user->user_login,
                $user->user_email,
                $user->display_name,
                $user->first_name,
                $user->last_name,
                $user->job_title,
                $user->department,
                $user->team_name,
                $user->status,
                $user->joined_at
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get CSV template for bulk import
     */
    public function get_import_template() {
        $filename = 'univga-bulk-import-template.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Template headers
        fputcsv($output, array(
            'username', 'email', 'first_name', 'last_name', 
            'password', 'job_title', 'department'
        ));
        
        // Sample data
        fputcsv($output, array(
            'jdoe', 'john.doe@company.com', 'John', 'Doe',
            'temp123', 'Developer', 'IT'
        ));
        
        fputcsv($output, array(
            'jsmith', 'jane.smith@company.com', 'Jane', 'Smith',
            'temp456', 'Manager', 'Sales'
        ));
        
        fclose($output);
        exit;
    }
}