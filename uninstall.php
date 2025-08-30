<?php

/**
 * Uninstall script for UNIVGA Business Pro plugin
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permission to uninstall
if (!current_user_can('activate_plugins')) {
    exit;
}

// Confirm we're uninstalling the right plugin
if (__FILE__ !== WP_UNINSTALL_PLUGIN) {
    exit;
}

global $wpdb;

// Only proceed if user explicitly wants to remove all data
$remove_data = get_option('univga_remove_data_on_uninstall', false);

if ($remove_data) {
    
    // Drop custom tables
    $tables = array(
        $wpdb->prefix . 'univga_orgs',
        $wpdb->prefix . 'univga_teams', 
        $wpdb->prefix . 'univga_org_members',
        $wpdb->prefix . 'univga_seat_pools',
        $wpdb->prefix . 'univga_seat_events',
        $wpdb->prefix . 'univga_analytics_events',
        $wpdb->prefix . 'univga_analytics_summary',
        $wpdb->prefix . 'univga_learning_paths',
        $wpdb->prefix . 'univga_learning_path_courses',
        $wpdb->prefix . 'univga_learning_path_assignments',
        $wpdb->prefix . 'univga_certifications',
        $wpdb->prefix . 'univga_user_certifications',
        $wpdb->prefix . 'univga_user_points',
        $wpdb->prefix . 'univga_badges',
        $wpdb->prefix . 'univga_user_badges',
        $wpdb->prefix . 'univga_notifications',
        $wpdb->prefix . 'univga_notification_templates',
        $wpdb->prefix . 'univga_bulk_operations',
        $wpdb->prefix . 'univga_integrations',
        $wpdb->prefix . 'univga_conversations',
        $wpdb->prefix . 'univga_conversation_participants',
        $wpdb->prefix . 'univga_messages',
        $wpdb->prefix . 'univga_branding',
        $wpdb->prefix . 'univga_ai_analytics',
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Remove plugin options
    $options = array(
        'univga_activated',
        'univga_plugin_version',
        'univga_db_version',
        'univga_default_allow_replace',
        'univga_debug_mode',
        'univga_dashboard_page_id',
        'univga_invitation_expire_days',
        'univga_last_cron_summary',
        'univga_remove_data_on_uninstall',
        'univga_gamification_settings',
        'univga_integration_settings',
        'univga_notification_settings',
        'univga_learning_paths_settings',
        'univga_certification_settings',
        'univga_branding_settings',
        'univga_ai_analytics_settings',
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Remove user meta related to plugin
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'univga_%'");
    
    // Remove post meta for WooCommerce products
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_univga_%'");
    
    // Remove custom roles
    remove_role('org_manager');
    remove_role('team_lead');
    
    // Remove capabilities from administrator role
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('univga_org_manage');
        $admin_role->remove_cap('univga_team_manage');
        $admin_role->remove_cap('univga_seats_manage');
        $admin_role->remove_cap('univga_reports_view');
        $admin_role->remove_cap('univga_admin_access');
        
        // Advanced features capabilities
        $admin_role->remove_cap('univga_analytics_advanced');
        $admin_role->remove_cap('univga_learning_paths_create');
        $admin_role->remove_cap('univga_cert_create');
        $admin_role->remove_cap('univga_cert_award');
        $admin_role->remove_cap('univga_bulk_operations');
        $admin_role->remove_cap('univga_notifications_broadcast');
        $admin_role->remove_cap('univga_gamification_manage');
        $admin_role->remove_cap('univga_integrations_manage');
        $admin_role->remove_cap('univga_branding_manage');
        $admin_role->remove_cap('univga_permissions_manage');
        $admin_role->remove_cap('univga_audit_logs');
        $admin_role->remove_cap('univga_ai_analytics');
        
        // Detailed permissions
        $admin_role->remove_cap('univga_member_view_team');
        $admin_role->remove_cap('univga_member_edit_team');
        $admin_role->remove_cap('univga_member_invite');
        $admin_role->remove_cap('univga_member_remove');
        $admin_role->remove_cap('univga_team_view');
        $admin_role->remove_cap('univga_team_edit');
        $admin_role->remove_cap('univga_team_create');
        $admin_role->remove_cap('univga_team_delete');
    }
    
    // Clear scheduled hooks
    wp_clear_scheduled_hook('univga_org_resync');
    wp_clear_scheduled_hook('univga_daily_ai_analysis');
    wp_clear_scheduled_hook('univga_send_weekly_hr_report');
    wp_clear_scheduled_hook('univga_send_monthly_hr_report');
    wp_clear_scheduled_hook('univga_gamification_weekly_reset');
    wp_clear_scheduled_hook('univga_cert_expiry_check');
    wp_clear_scheduled_hook('univga_notification_cleanup');
    
    // Remove any transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_univga_%' OR option_name LIKE '_transient_timeout_univga_%'");
    
    // Force clear object cache
    wp_cache_flush();
    
} else {
    
    // If not removing data, just clean up temporary stuff
    wp_clear_scheduled_hook('univga_org_resync');
    wp_clear_scheduled_hook('univga_daily_ai_analysis');
    wp_clear_scheduled_hook('univga_send_weekly_hr_report');
    wp_clear_scheduled_hook('univga_send_monthly_hr_report');
    wp_clear_scheduled_hook('univga_gamification_weekly_reset');
    wp_clear_scheduled_hook('univga_cert_expiry_check');
    wp_clear_scheduled_hook('univga_notification_cleanup');
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_univga_%' OR option_name LIKE '_transient_timeout_univga_%'");
    wp_cache_flush();
    
}

// Log uninstallation
error_log('UNIVGA Business Pro: Plugin uninstalled. Data removal: ' . ($remove_data ? 'YES' : 'NO'));

