<?php

/**
 * UNIVGA Enhanced Permissions Class
 * Advanced Role-Based Permissions with granular controls
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_Enhanced_Permissions {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_univga_create_custom_role', array($this, 'create_custom_role'));
        add_action('wp_ajax_univga_update_role_permissions', array($this, 'update_role_permissions'));
        add_action('wp_ajax_univga_get_roles_and_permissions', array($this, 'get_roles_and_permissions'));
        add_action('wp_ajax_univga_assign_user_role', array($this, 'assign_user_role'));
        add_action('wp_ajax_univga_get_permission_matrix', array($this, 'get_permission_matrix'));
        
        // Permission checking hooks
        add_filter('user_has_cap', array($this, 'check_granular_permissions'), 10, 4);
        add_action('admin_init', array($this, 'enforce_permissions'));
        
        // Department-based permissions
        add_action('wp_ajax_univga_set_department_permissions', array($this, 'set_department_permissions'));
        
        // Audit logging
        add_action('wp_ajax_univga_get_permission_audit_log', array($this, 'get_permission_audit_log'));
        add_action('univga_permission_changed', array($this, 'log_permission_change'), 10, 4);
    }
    
    /**
     * Get all available UNIVGA permissions
     */
    public function get_available_permissions() {
        return array(
            // Organization Management
            'univga_org_create' => __('Create Organizations', UNIVGA_TEXT_DOMAIN),
            'univga_org_edit' => __('Edit Organizations', UNIVGA_TEXT_DOMAIN),
            'univga_org_delete' => __('Delete Organizations', UNIVGA_TEXT_DOMAIN),
            'univga_org_view' => __('View Organizations', UNIVGA_TEXT_DOMAIN),
            
            // Team Management
            'univga_team_create' => __('Create Teams', UNIVGA_TEXT_DOMAIN),
            'univga_team_edit' => __('Edit Teams', UNIVGA_TEXT_DOMAIN),
            'univga_team_delete' => __('Delete Teams', UNIVGA_TEXT_DOMAIN),
            'univga_team_view' => __('View Teams', UNIVGA_TEXT_DOMAIN),
            'univga_team_assign_members' => __('Assign Team Members', UNIVGA_TEXT_DOMAIN),
            
            // Member Management
            'univga_member_invite' => __('Invite Members', UNIVGA_TEXT_DOMAIN),
            'univga_member_remove' => __('Remove Members', UNIVGA_TEXT_DOMAIN),
            'univga_member_edit' => __('Edit Member Details', UNIVGA_TEXT_DOMAIN),
            'univga_member_view_all' => __('View All Members', UNIVGA_TEXT_DOMAIN),
            'univga_member_view_team' => __('View Team Members Only', UNIVGA_TEXT_DOMAIN),
            
            // Seat Pool Management
            'univga_seats_create' => __('Create Seat Pools', UNIVGA_TEXT_DOMAIN),
            'univga_seats_edit' => __('Edit Seat Pools', UNIVGA_TEXT_DOMAIN),
            'univga_seats_assign' => __('Assign Seats to Users', UNIVGA_TEXT_DOMAIN),
            'univga_seats_revoke' => __('Revoke Seat Access', UNIVGA_TEXT_DOMAIN),
            'univga_seats_view_usage' => __('View Seat Usage', UNIVGA_TEXT_DOMAIN),
            
            // Course and Learning Management
            'univga_courses_assign' => __('Assign Courses to Users', UNIVGA_TEXT_DOMAIN),
            'univga_courses_bulk_assign' => __('Bulk Assign Courses', UNIVGA_TEXT_DOMAIN),
            'univga_learning_paths_create' => __('Create Learning Paths', UNIVGA_TEXT_DOMAIN),
            'univga_learning_paths_assign' => __('Assign Learning Paths', UNIVGA_TEXT_DOMAIN),
            'univga_learning_paths_manage' => __('Manage Learning Paths', UNIVGA_TEXT_DOMAIN),
            
            // Certification Management
            'univga_cert_create' => __('Create Certifications', UNIVGA_TEXT_DOMAIN),
            'univga_cert_award' => __('Award Certifications', UNIVGA_TEXT_DOMAIN),
            'univga_cert_revoke' => __('Revoke Certifications', UNIVGA_TEXT_DOMAIN),
            'univga_cert_view_all' => __('View All Certifications', UNIVGA_TEXT_DOMAIN),
            
            // Analytics and Reporting
            'univga_analytics_basic' => __('View Basic Analytics', UNIVGA_TEXT_DOMAIN),
            'univga_analytics_advanced' => __('View Advanced Analytics', UNIVGA_TEXT_DOMAIN),
            'univga_reports_generate' => __('Generate Reports', UNIVGA_TEXT_DOMAIN),
            'univga_reports_export' => __('Export Reports', UNIVGA_TEXT_DOMAIN),
            'univga_reports_view_team' => __('View Team Reports Only', UNIVGA_TEXT_DOMAIN),
            'univga_reports_view_org' => __('View Organization Reports', UNIVGA_TEXT_DOMAIN),
            
            // Bulk Operations
            'univga_bulk_import' => __('Bulk Import Users', UNIVGA_TEXT_DOMAIN),
            'univga_bulk_enroll' => __('Bulk Enroll in Courses', UNIVGA_TEXT_DOMAIN),
            'univga_bulk_operations' => __('Perform Bulk Operations', UNIVGA_TEXT_DOMAIN),
            
            // Gamification
            'univga_gamification_manage' => __('Manage Gamification Settings', UNIVGA_TEXT_DOMAIN),
            'univga_badges_create' => __('Create Badges', UNIVGA_TEXT_DOMAIN),
            'univga_badges_award' => __('Award Badges', UNIVGA_TEXT_DOMAIN),
            'univga_points_adjust' => __('Adjust User Points', UNIVGA_TEXT_DOMAIN),
            
            // Notifications and Communication
            'univga_notifications_send' => __('Send Notifications', UNIVGA_TEXT_DOMAIN),
            'univga_notifications_broadcast' => __('Send Broadcast Notifications', UNIVGA_TEXT_DOMAIN),
            'univga_templates_manage' => __('Manage Notification Templates', UNIVGA_TEXT_DOMAIN),
            
            // Integrations
            'univga_integrations_manage' => __('Manage Integrations', UNIVGA_TEXT_DOMAIN),
            'univga_sso_configure' => __('Configure SSO', UNIVGA_TEXT_DOMAIN),
            'univga_hr_sync' => __('Sync HR Data', UNIVGA_TEXT_DOMAIN),
            
            // Branding and Customization
            'univga_branding_manage' => __('Manage White-Label Branding', UNIVGA_TEXT_DOMAIN),
            'univga_custom_css' => __('Edit Custom CSS', UNIVGA_TEXT_DOMAIN),
            'univga_custom_domain' => __('Configure Custom Domain', UNIVGA_TEXT_DOMAIN),
            
            // System Administration
            'univga_settings_global' => __('Global Settings Management', UNIVGA_TEXT_DOMAIN),
            'univga_permissions_manage' => __('Manage User Permissions', UNIVGA_TEXT_DOMAIN),
            'univga_audit_logs' => __('View Audit Logs', UNIVGA_TEXT_DOMAIN),
            'univga_system_health' => __('View System Health', UNIVGA_TEXT_DOMAIN)
        );
    }
    
    /**
     * Create custom role
     */
    public function create_custom_role() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_permissions_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $role_name = sanitize_text_field($_POST['role_name']);
        $role_slug = sanitize_title($_POST['role_slug']);
        $capabilities = $_POST['capabilities'] ?? array();
        $org_id = intval($_POST['org_id'] ?? 0);
        
        // Validate role slug is unique
        if (get_role($role_slug)) {
            wp_send_json_error('Role slug already exists');
            return;
        }
        
        // Prepare capabilities array
        $caps = array('read' => true);
        foreach ($capabilities as $cap) {
            if (array_key_exists($cap, $this->get_available_permissions())) {
                $caps[$cap] = true;
            }
        }
        
        // Create role
        $result = add_role($role_slug, $role_name, $caps);
        
        if ($result) {
            // Store additional role metadata
            $role_meta = array(
                'org_id' => $org_id,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'is_custom' => true,
                'description' => sanitize_textarea_field($_POST['description'] ?? '')
            );
            
            update_option('univga_role_meta_' . $role_slug, $role_meta);
            
            // Log the action
            do_action('univga_permission_changed', 'role_created', $role_slug, get_current_user_id(), $role_meta);
            
            wp_send_json_success(array(
                'message' => 'Custom role created successfully',
                'role_slug' => $role_slug
            ));
        } else {
            wp_send_json_error('Failed to create custom role');
        }
    }
    
    /**
     * Update role permissions
     */
    public function update_role_permissions() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_permissions_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $role_slug = sanitize_text_field($_POST['role_slug']);
        $capabilities = $_POST['capabilities'] ?? array();
        
        $role = get_role($role_slug);
        if (!$role) {
            wp_send_json_error('Role not found');
            return;
        }
        
        // Remove all existing UNIVGA capabilities
        $available_permissions = $this->get_available_permissions();
        foreach ($available_permissions as $cap => $label) {
            $role->remove_cap($cap);
        }
        
        // Add selected capabilities
        foreach ($capabilities as $cap) {
            if (array_key_exists($cap, $available_permissions)) {
                $role->add_cap($cap);
            }
        }
        
        // Log the action
        do_action('univga_permission_changed', 'role_updated', $role_slug, get_current_user_id(), $capabilities);
        
        wp_send_json_success(array('message' => 'Role permissions updated successfully'));
    }
    
    /**
     * Get roles and permissions
     */
    public function get_roles_and_permissions() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_permissions_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wp_roles;
        
        $roles_data = array();
        $available_permissions = $this->get_available_permissions();
        
        foreach ($wp_roles->roles as $role_slug => $role_data) {
            // Only include UNIVGA-related roles and administrator
            if ($role_slug === 'administrator' || 
                strpos($role_slug, 'org_') === 0 || 
                strpos($role_slug, 'team_') === 0 ||
                get_option('univga_role_meta_' . $role_slug)) {
                
                $role_meta = get_option('univga_role_meta_' . $role_slug, array());
                $univga_caps = array_intersect_key($role_data['capabilities'], $available_permissions);
                
                $roles_data[] = array(
                    'slug' => $role_slug,
                    'name' => $role_data['name'],
                    'capabilities' => $univga_caps,
                    'user_count' => count_users()['avail_roles'][$role_slug] ?? 0,
                    'is_custom' => $role_meta['is_custom'] ?? false,
                    'description' => $role_meta['description'] ?? '',
                    'org_id' => $role_meta['org_id'] ?? 0
                );
            }
        }
        
        wp_send_json_success(array(
            'roles' => $roles_data,
            'available_permissions' => $available_permissions
        ));
    }
    
    /**
     * Get permission matrix
     */
    public function get_permission_matrix() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id'] ?? 0);
        
        global $wpdb;
        
        // Get users in organization
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.display_name, u.user_email,
                   om.team_id, t.name as team_name
            FROM {$wpdb->prefix}univga_org_members om
            LEFT JOIN {$wpdb->users} u ON om.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}univga_teams t ON om.team_id = t.id
            WHERE om.org_id = %d AND om.status = 'active'
            ORDER BY u.display_name
        ", $org_id));
        
        $permission_matrix = array();
        $available_permissions = $this->get_available_permissions();
        
        foreach ($users as $user) {
            $user_data = get_userdata($user->ID);
            $user_caps = array();
            
            foreach ($available_permissions as $cap => $label) {
                $user_caps[$cap] = user_can($user->ID, $cap);
            }
            
            $permission_matrix[] = array(
                'user_id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'team_name' => $user->team_name,
                'roles' => $user_data->roles,
                'capabilities' => $user_caps
            );
        }
        
        wp_send_json_success(array(
            'matrix' => $permission_matrix,
            'available_permissions' => $available_permissions
        ));
    }
    
    /**
     * Assign user role
     */
    public function assign_user_role() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_permissions_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $user_id = intval($_POST['user_id']);
        $role_slug = sanitize_text_field($_POST['role_slug']);
        $org_id = intval($_POST['org_id']);
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error('User not found');
            return;
        }
        
        // Remove existing UNIVGA roles
        $existing_roles = $user->roles;
        foreach ($existing_roles as $role) {
            if (strpos($role, 'org_') === 0 || strpos($role, 'team_') === 0) {
                $user->remove_role($role);
            }
        }
        
        // Add new role
        $user->add_role($role_slug);
        
        // Store org-specific role assignment
        update_user_meta($user_id, 'univga_org_role_' . $org_id, $role_slug);
        
        // Log the action
        do_action('univga_permission_changed', 'user_role_assigned', $user_id, get_current_user_id(), array(
            'role' => $role_slug,
            'org_id' => $org_id
        ));
        
        wp_send_json_success(array('message' => 'User role updated successfully'));
    }
    
    /**
     * Set department-based permissions
     */
    public function set_department_permissions() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_permissions_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $department = sanitize_text_field($_POST['department']);
        $permissions = $_POST['permissions'] ?? array();
        
        // Store department permissions
        $dept_perms = get_option('univga_dept_permissions_' . $org_id, array());
        $dept_perms[$department] = $permissions;
        update_option('univga_dept_permissions_' . $org_id, $dept_perms);
        
        // Apply to existing users in that department
        $this->apply_department_permissions($org_id, $department);
        
        wp_send_json_success(array('message' => 'Department permissions updated successfully'));
    }
    
    /**
     * Apply department permissions to users
     */
    private function apply_department_permissions($org_id, $department) {
        global $wpdb;
        
        // Get users in the department
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT om.user_id
            FROM {$wpdb->prefix}univga_org_members om
            LEFT JOIN {$wpdb->usermeta} um ON om.user_id = um.user_id
            WHERE om.org_id = %d 
            AND um.meta_key = 'department' 
            AND um.meta_value = %s
        ", $org_id, $department));
        
        $dept_perms = get_option('univga_dept_permissions_' . $org_id, array());
        $permissions = $dept_perms[$department] ?? array();
        
        foreach ($users as $user) {
            // Store user's department-based permissions
            update_user_meta($user->user_id, 'univga_dept_permissions_' . $org_id, $permissions);
        }
    }
    
    /**
     * Check granular permissions
     */
    public function check_granular_permissions($allcaps, $caps, $args, $user) {
        if (!$user || empty($args)) {
            return $allcaps;
        }
        
        $capability = $args[0] ?? '';
        
        // Only handle UNIVGA capabilities
        if (strpos($capability, 'univga_') !== 0) {
            return $allcaps;
        }
        
        $user_id = $user->ID;
        
        // Get user's organization context
        $org_id = $this->get_user_current_org_context($user_id);
        if (!$org_id) {
            return $allcaps;
        }
        
        // Check department-based permissions
        $dept_permissions = get_user_meta($user_id, 'univga_dept_permissions_' . $org_id, true);
        if (is_array($dept_permissions) && in_array($capability, $dept_permissions)) {
            $allcaps[$capability] = true;
            return $allcaps;
        }
        
        // Check team-based permissions
        $team_id = $this->get_user_team_id($user_id, $org_id);
        if ($team_id) {
            $team_permissions = get_option('univga_team_permissions_' . $team_id, array());
            if (in_array($capability, $team_permissions)) {
                $allcaps[$capability] = true;
                return $allcaps;
            }
        }
        
        // Check context-specific permissions (e.g., can only manage own team)
        $context_check = $this->check_context_permissions($capability, $user_id, $org_id, $args);
        if ($context_check !== null) {
            $allcaps[$capability] = $context_check;
        }
        
        return $allcaps;
    }
    
    /**
     * Check context-specific permissions
     */
    private function check_context_permissions($capability, $user_id, $org_id, $args) {
        switch ($capability) {
            case 'univga_member_view_team':
                // User can only view members of their own team
                $user_team = $this->get_user_team_id($user_id, $org_id);
                $target_team = $args[2] ?? null;
                return $user_team && $user_team == $target_team;
                
            case 'univga_reports_view_team':
                // User can only view reports for their own team
                $user_team = $this->get_user_team_id($user_id, $org_id);
                $report_team = $args[2] ?? null;
                return $user_team && $user_team == $report_team;
                
            case 'univga_team_edit':
                // Team leaders can only edit their own team
                $user_team = $this->get_user_team_id($user_id, $org_id);
                $target_team = $args[2] ?? null;
                return user_can($user_id, 'univga_team_manage') && $user_team == $target_team;
        }
        
        return null;
    }
    
    /**
     * Get user's current organization context
     */
    private function get_user_current_org_context($user_id) {
        // Check URL parameter
        if (isset($_GET['org'])) {
            return intval($_GET['org']);
        }
        
        // Check POST data
        if (isset($_POST['org_id'])) {
            return intval($_POST['org_id']);
        }
        
        // Get user's primary organization
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT org_id FROM {$wpdb->prefix}univga_org_members 
            WHERE user_id = %d AND status = 'active' 
            ORDER BY joined_at ASC LIMIT 1
        ", $user_id));
    }
    
    /**
     * Get user's team ID in organization
     */
    private function get_user_team_id($user_id, $org_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT team_id FROM {$wpdb->prefix}univga_org_members 
            WHERE user_id = %d AND org_id = %d AND status = 'active'
        ", $user_id, $org_id));
    }
    
    /**
     * Enforce permissions on admin pages
     */
    public function enforce_permissions() {
        if (!is_admin()) {
            return;
        }
        
        // ✅ WordPress administrators always pass
        if (current_user_can('manage_options')) {
            return;
        }
        
        $page = $_GET['page'] ?? '';
        
        if (strpos($page, 'univga') !== 0) {
            return;
        }
        
        // Map pages to required capabilities - Updated with all admin pages
        $page_permissions = array(
            'univga-admin-hub' => 'univga_org_view',  // Dashboard
            'univga-organizations' => 'univga_org_view',
            'univga-teams' => 'univga_team_view',
            'univga-members' => 'univga_member_view_all',
            'univga-pools' => 'univga_seats_view_usage',
            'univga-profiles' => 'univga_profiles_view',  // User Profiles
            'univga-hr-dashboards' => 'univga_reports_view',  // HR Reporting
            'univga-ai-analytics' => 'univga_ai_analytics',  // AI Analytics
            'univga-analytics' => 'univga_analytics_basic',
            'univga-reports' => 'univga_reports_view_org',
            'univga-settings' => 'univga_settings_global',
            'univga-integrations' => 'univga_integrations_manage',
            'univga-branding' => 'univga_branding_manage'
        );
        
        if (isset($page_permissions[$page]) && !current_user_can($page_permissions[$page])) {
            wp_die(__('You do not have sufficient permissions to access this page.', UNIVGA_TEXT_DOMAIN));
        }
    }
    
    /**
     * Log permission changes
     */
    public function log_permission_change($action, $target, $user_id, $details) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'univga_seat_events', // Reusing events table for now
            array(
                'pool_id' => 0, // Not applicable for permission logs
                'user_id' => $user_id,
                'type' => 'permission_' . $action,
                'meta' => json_encode(array(
                    'target' => $target,
                    'details' => $details,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                )),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get permission audit log
     */
    public function get_permission_audit_log() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_audit_logs')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $logs = $wpdb->get_results("
            SELECT se.*, u.display_name as user_name
            FROM {$wpdb->prefix}univga_seat_events se
            LEFT JOIN {$wpdb->users} u ON se.user_id = u.ID
            WHERE se.type LIKE 'permission_%'
            ORDER BY se.created_at DESC
            LIMIT 100
        ");
        
        foreach ($logs as &$log) {
            $log->meta = json_decode($log->meta, true);
        }
        
        wp_send_json_success($logs);
    }
}