<?php

/**
 * User Profiles and Permissions Management
 * 
 * Handles granular permission profiles like Admin, HR, Accountant, Manager, Member
 */
class UNIVGA_User_Profiles {
    
    /**
     * Available user profiles
     */
    const PROFILES = array(
        'admin' => 'Administrator',
        'hr' => 'Human Resources',
        'accountant' => 'Accountant', 
        'manager' => 'Manager',
        'member' => 'Member'
    );
    
    /**
     * Profile capabilities mapping
     */
    private static $profile_capabilities = array(
        'admin' => array(
            // All capabilities
            'univga_admin_access',
            'univga_org_create', 'univga_org_edit', 'univga_org_delete', 'univga_org_view', 'univga_org_manage',
            'univga_team_create', 'univga_team_edit', 'univga_team_delete', 'univga_team_view', 'univga_team_manage', 'univga_team_assign_members',
            'univga_member_invite', 'univga_member_remove', 'univga_member_edit', 'univga_member_view_all', 'univga_member_view_team',
            'univga_seats_create', 'univga_seats_edit', 'univga_seats_assign', 'univga_seats_revoke', 'univga_seats_view_usage', 'univga_seats_manage',
            'univga_analytics_basic', 'univga_analytics_advanced', 'univga_reports_generate', 'univga_reports_export', 'univga_reports_view_team', 'univga_reports_view_org', 'univga_reports_view',
            'univga_learning_paths_create', 'univga_learning_paths_assign', 'univga_learning_paths_manage',
            'univga_cert_create', 'univga_cert_award', 'univga_cert_revoke', 'univga_cert_view_all',
            'univga_bulk_import', 'univga_bulk_enroll', 'univga_bulk_operations',
            'univga_gamification_manage', 'univga_badges_create', 'univga_badges_award', 'univga_points_adjust',
            'univga_notifications_send', 'univga_notifications_broadcast', 'univga_templates_manage',
            'univga_integrations_manage', 'univga_sso_configure', 'univga_hr_sync',
            'univga_branding_manage', 'univga_custom_css', 'univga_custom_domain',
            'univga_settings_global', 'univga_permissions_manage', 'univga_audit_logs', 'univga_system_health',
            'univga_messaging_send', 'univga_messaging_moderate', 'univga_messaging_view_all', 'univga_messaging_admin',
            'univga_financial_view', 'univga_financial_reports', 'univga_billing_manage', 'univga_payments_view'
        ),
        
        'hr' => array(
            // HR-focused capabilities
            'univga_org_view',
            'univga_team_create', 'univga_team_edit', 'univga_team_view', 'univga_team_manage', 'univga_team_assign_members',
            'univga_member_invite', 'univga_member_remove', 'univga_member_edit', 'univga_member_view_all', 'univga_member_view_team',
            'univga_seats_assign', 'univga_seats_view_usage',
            'univga_analytics_basic', 'univga_reports_generate', 'univga_reports_view_team', 'univga_reports_view_org',
            'univga_learning_paths_create', 'univga_learning_paths_assign', 'univga_learning_paths_manage',
            'univga_cert_create', 'univga_cert_award', 'univga_cert_view_all',
            'univga_bulk_import', 'univga_bulk_enroll', 'univga_bulk_operations',
            'univga_gamification_manage', 'univga_badges_award', 'univga_points_adjust',
            'univga_notifications_send', 'univga_notifications_broadcast', 'univga_templates_manage',
            'univga_messaging_send', 'univga_messaging_moderate'
        ),
        
        'accountant' => array(
            // Accountant/Financial capabilities
            'univga_org_view',
            'univga_team_view',
            'univga_member_view_all', 'univga_member_view_team',
            'univga_seats_view_usage',
            'univga_analytics_basic', 'univga_analytics_advanced', 'univga_reports_generate', 'univga_reports_export', 'univga_reports_view_org',
            'univga_financial_view', 'univga_financial_reports', 'univga_billing_manage', 'univga_payments_view',
            'univga_messaging_send'
        ),
        
        'manager' => array(
            // Team management capabilities
            'univga_org_view',
            'univga_team_edit', 'univga_team_view', 'univga_team_assign_members',
            'univga_member_invite', 'univga_member_edit', 'univga_member_view_team',
            'univga_seats_assign',
            'univga_analytics_basic', 'univga_reports_generate', 'univga_reports_view_team',
            'univga_learning_paths_assign',
            'univga_cert_award', 'univga_cert_view_all',
            'univga_bulk_enroll',
            'univga_badges_award', 'univga_points_adjust',
            'univga_notifications_send',
            'univga_messaging_send'
        ),
        
        'member' => array(
            // Basic member capabilities
            'univga_org_view',
            'univga_team_view',
            'univga_member_view_team',
            'univga_analytics_basic',
            'univga_messaging_send'
        )
    );
    
    /**
     * Initialize user profiles system
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_capabilities'));
        add_action('wp_ajax_univga_update_user_profile', array(__CLASS__, 'ajax_update_user_profile'));
        add_action('wp_ajax_univga_get_user_capabilities', array(__CLASS__, 'ajax_get_user_capabilities'));
        add_filter('user_has_cap', array(__CLASS__, 'check_user_capabilities'), 10, 4);
    }
    
    /**
     * Register custom capabilities
     */
    public static function register_capabilities() {
        // Ensure capabilities are registered for each role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (self::$profile_capabilities['admin'] as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Get user profile
     */
    public static function get_user_profile($user_id) {
        return get_user_meta($user_id, 'univga_profile', true) ?: 'member';
    }
    
    /**
     * Set user profile
     */
    public static function set_user_profile($user_id, $profile) {
        if (!array_key_exists($profile, self::PROFILES)) {
            return false;
        }
        
        // Update user meta
        update_user_meta($user_id, 'univga_profile', $profile);
        
        // Update user capabilities based on profile
        self::update_user_capabilities($user_id, $profile);
        
        return true;
    }
    
    /**
     * Update user capabilities based on profile
     */
    private static function update_user_capabilities($user_id, $profile) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Remove all UNIVGA capabilities first
        foreach (self::$profile_capabilities['admin'] as $cap) {
            $user->remove_cap($cap);
        }
        
        // Add capabilities for the new profile
        if (isset(self::$profile_capabilities[$profile])) {
            foreach (self::$profile_capabilities[$profile] as $cap) {
                $user->add_cap($cap);
            }
        }
        
        return true;
    }
    
    /**
     * Get profile capabilities
     */
    public static function get_profile_capabilities($profile) {
        return self::$profile_capabilities[$profile] ?? array();
    }
    
    /**
     * Check if user has financial visibility (for accountant profile)
     */
    public static function user_has_financial_access($user_id) {
        $profile = self::get_user_profile($user_id);
        return in_array($profile, array('admin', 'accountant'));
    }
    
    /**
     * Check if user can manage teams (for HR/Manager profiles)
     */
    public static function user_can_manage_teams($user_id) {
        $profile = self::get_user_profile($user_id);
        return in_array($profile, array('admin', 'hr', 'manager'));
    }
    
    /**
     * Get profile display name
     */
    public static function get_profile_display_name($profile) {
        return self::PROFILES[$profile] ?? 'Unknown';
    }
    
    /**
     * Get all profiles
     */
    public static function get_all_profiles() {
        return self::PROFILES;
    }
    
    /**
     * Filter user capabilities based on profile
     */
    public static function check_user_capabilities($allcaps, $caps, $args, $user) {
        $user_id = $user->ID;
        $profile = self::get_user_profile($user_id);
        
        // If user has admin role, grant all capabilities
        if (in_array('administrator', $user->roles)) {
            return $allcaps;
        }
        
        // Apply profile-based capabilities
        $profile_caps = self::get_profile_capabilities($profile);
        
        foreach ($caps as $cap) {
            if (strpos($cap, 'univga_') === 0) {
                $allcaps[$cap] = in_array($cap, $profile_caps);
            }
        }
        
        return $allcaps;
    }
    
    /**
     * AJAX handler for updating user profile
     */
    public static function ajax_update_user_profile() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'univga_admin_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('univga_permissions_manage')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $user_id = intval($_POST['user_id']);
        $profile = sanitize_text_field($_POST['profile']);
        
        $result = self::set_user_profile($user_id, $profile);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('User profile updated successfully', UNIVGA_TEXT_DOMAIN),
                'profile' => $profile,
                'profile_name' => self::get_profile_display_name($profile)
            ));
        } else {
            wp_send_json_error(__('Failed to update user profile', UNIVGA_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX handler for getting user capabilities
     */
    public static function ajax_get_user_capabilities() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'univga_admin_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('univga_admin_access')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $user_id = intval($_POST['user_id']);
        $profile = self::get_user_profile($user_id);
        $capabilities = self::get_profile_capabilities($profile);
        
        // Group capabilities by category for better display
        $grouped_caps = array(
            'organization' => array(),
            'teams' => array(),
            'members' => array(),
            'analytics' => array(),
            'financial' => array()
        );
        
        foreach ($capabilities as $cap) {
            $label = self::get_capability_label($cap);
            
            if (strpos($cap, 'org_') !== false) {
                $grouped_caps['organization'][] = array('cap' => $cap, 'label' => $label);
            } elseif (strpos($cap, 'team_') !== false) {
                $grouped_caps['teams'][] = array('cap' => $cap, 'label' => $label);
            } elseif (strpos($cap, 'member_') !== false) {
                $grouped_caps['members'][] = array('cap' => $cap, 'label' => $label);
            } elseif (strpos($cap, 'analytics_') !== false || strpos($cap, 'reports_') !== false) {
                $grouped_caps['analytics'][] = array('cap' => $cap, 'label' => $label);
            } elseif (strpos($cap, 'financial_') !== false || strpos($cap, 'billing_') !== false || strpos($cap, 'payments_') !== false) {
                $grouped_caps['financial'][] = array('cap' => $cap, 'label' => $label);
            }
        }
        
        wp_send_json_success($grouped_caps);
    }
    
    /**
     * Get human-readable capability label
     */
    private static function get_capability_label($capability) {
        $labels = array(
            'univga_org_create' => 'Create Organizations',
            'univga_org_edit' => 'Edit Organizations',
            'univga_org_delete' => 'Delete Organizations',
            'univga_org_view' => 'View Organizations',
            'univga_team_create' => 'Create Teams',
            'univga_team_edit' => 'Edit Teams',
            'univga_team_delete' => 'Delete Teams',
            'univga_team_view' => 'View Teams',
            'univga_member_invite' => 'Invite Members',
            'univga_member_remove' => 'Remove Members',
            'univga_member_edit' => 'Edit Members',
            'univga_member_view_all' => 'View All Members',
            'univga_analytics_advanced' => 'Advanced Analytics',
            'univga_reports_generate' => 'Generate Reports',
            'univga_financial_view' => 'View Financial Data',
            'univga_financial_reports' => 'Financial Reports',
            'univga_billing_manage' => 'Manage Billing',
            'univga_payments_view' => 'View Payments'
        );
        
        return $labels[$capability] ?? ucwords(str_replace(array('univga_', '_'), array('', ' '), $capability));
    }
}

// Initialize user profiles system
UNIVGA_User_Profiles::init();