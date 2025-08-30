<?php

/**
 * Plugin installer - handles activation, database creation, roles
 */
class UNIVGA_Installer {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::schedule_cron();
        
        // Repair permissions for existing data
        require_once UNIVGA_PLUGIN_DIR . 'includes/class-permission-repair.php';
        UNIVGA_Permission_Repair::repair_all_permissions();
        
        // Set activation flag
        update_option('univga_activated', true);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Organizations table
        $sql_orgs = "CREATE TABLE {$wpdb->prefix}univga_orgs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            legal_id varchar(80) DEFAULT NULL,
            contact_user_id bigint(20) unsigned DEFAULT NULL,
            email_domain varchar(191) DEFAULT NULL,
            status tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_user_id (contact_user_id),
            KEY status (status),
            KEY email_domain (email_domain)
        ) $charset_collate;";
        
        // Teams table
        $sql_teams = "CREATE TABLE {$wpdb->prefix}univga_teams (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            name varchar(191) NOT NULL,
            manager_user_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY manager_user_id (manager_user_id)
        ) $charset_collate;";
        
        // Organization members table
        $sql_members = "CREATE TABLE {$wpdb->prefix}univga_org_members (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            team_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL,
            status enum('invited','active','removed') DEFAULT 'active',
            joined_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            removed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY team_id (team_id),
            KEY user_id (user_id),
            KEY status (status),
            UNIQUE KEY unique_org_user (org_id, user_id)
        ) $charset_collate;";
        
        // Seat pools table
        $sql_pools = "CREATE TABLE {$wpdb->prefix}univga_seat_pools (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            team_id bigint(20) unsigned DEFAULT NULL,
            scope_type enum('course','category','bundle') NOT NULL,
            scope_ids longtext NOT NULL,
            seats_total int(11) NOT NULL DEFAULT 0,
            seats_used int(11) NOT NULL DEFAULT 0,
            expires_at datetime DEFAULT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            auto_enroll tinyint(1) DEFAULT 1,
            allow_replace tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY team_id (team_id),
            KEY scope_type (scope_type),
            KEY expires_at (expires_at),
            KEY order_id (order_id)
        ) $charset_collate;";
        
        // Seat events table (audit log)
        $sql_events = "CREATE TABLE {$wpdb->prefix}univga_seat_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pool_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            type enum('consume','release','expire','assign','invite') NOT NULL,
            meta longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY pool_id (pool_id),
            KEY user_id (user_id),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Analytics tables
        $sql_analytics_events = "CREATE TABLE {$wpdb->prefix}univga_analytics_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            org_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY org_id (org_id),
            KEY course_id (course_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql_analytics_summary = "CREATE TABLE {$wpdb->prefix}univga_analytics_summary (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            total_started int(11) DEFAULT 0,
            total_completed int(11) DEFAULT 0,
            completion_rate decimal(5,2) DEFAULT 0.00,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_org_course (org_id, course_id),
            KEY org_id (org_id),
            KEY course_id (course_id)
        ) $charset_collate;";

        // Learning Paths tables
        $sql_learning_paths = "CREATE TABLE {$wpdb->prefix}univga_learning_paths (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            name varchar(191) NOT NULL,
            description text DEFAULT NULL,
            job_role varchar(100) DEFAULT NULL,
            difficulty_level enum('beginner','intermediate','advanced') DEFAULT 'beginner',
            estimated_duration int(11) DEFAULT 0,
            is_mandatory tinyint(1) DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY job_role (job_role),
            KEY created_by (created_by)
        ) $charset_collate;";

        $sql_learning_path_courses = "CREATE TABLE {$wpdb->prefix}univga_learning_path_courses (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            path_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            order_sequence int(11) NOT NULL,
            is_required tinyint(1) DEFAULT 1,
            prerequisites longtext DEFAULT NULL,
            unlock_condition enum('previous_complete','all_prerequisites','immediate') DEFAULT 'previous_complete',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY path_id (path_id),
            KEY course_id (course_id),
            KEY order_sequence (order_sequence)
        ) $charset_collate;";

        $sql_learning_path_assignments = "CREATE TABLE {$wpdb->prefix}univga_learning_path_assignments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            path_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            assigned_by bigint(20) unsigned NOT NULL,
            status enum('assigned','in_progress','completed','overdue') DEFAULT 'assigned',
            progress_percentage decimal(5,2) DEFAULT 0.00,
            due_date datetime DEFAULT NULL,
            assigned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_path_user (path_id, user_id),
            KEY path_id (path_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        // Certifications tables
        $sql_certifications = "CREATE TABLE {$wpdb->prefix}univga_certifications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            name varchar(191) NOT NULL,
            description text DEFAULT NULL,
            requirements longtext DEFAULT NULL,
            validity_period int(11) DEFAULT 0,
            template_id varchar(100) DEFAULT NULL,
            is_compliance tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY is_compliance (is_compliance)
        ) $charset_collate;";

        $sql_user_certifications = "CREATE TABLE {$wpdb->prefix}univga_user_certifications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            certification_id bigint(20) unsigned NOT NULL,
            org_id bigint(20) unsigned NOT NULL,
            status enum('earned','expired','revoked') DEFAULT 'earned',
            earned_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_date datetime DEFAULT NULL,
            certificate_url varchar(500) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY certification_id (certification_id),
            KEY status (status),
            KEY expires_date (expires_date)
        ) $charset_collate;";

        // Gamification tables
        $sql_user_points = "CREATE TABLE {$wpdb->prefix}univga_user_points (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            org_id bigint(20) unsigned NOT NULL,
            points_earned int(11) DEFAULT 0,
            level_id int(11) DEFAULT 1,
            badges_count int(11) DEFAULT 0,
            last_activity datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_org (user_id, org_id),
            KEY org_id (org_id),
            KEY points_earned (points_earned)
        ) $charset_collate;";

        $sql_badges = "CREATE TABLE {$wpdb->prefix}univga_badges (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            name varchar(191) NOT NULL,
            description text DEFAULT NULL,
            icon_url varchar(500) DEFAULT NULL,
            criteria longtext DEFAULT NULL,
            points_value int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql_user_badges = "CREATE TABLE {$wpdb->prefix}univga_user_badges (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            badge_id bigint(20) unsigned NOT NULL,
            earned_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_badge (user_id, badge_id),
            KEY user_id (user_id),
            KEY badge_id (badge_id)
        ) $charset_collate;";

        // Notifications tables
        $sql_notifications = "CREATE TABLE {$wpdb->prefix}univga_notifications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            org_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(191) NOT NULL,
            message text NOT NULL,
            action_url varchar(500) DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY org_id (org_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql_notification_templates = "CREATE TABLE {$wpdb->prefix}univga_notification_templates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            name varchar(191) NOT NULL,
            subject varchar(191) DEFAULT NULL,
            template text NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY type (type),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Bulk operations tables
        $sql_bulk_operations = "CREATE TABLE {$wpdb->prefix}univga_bulk_operations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            operation_type varchar(50) NOT NULL,
            status enum('pending','processing','completed','failed') DEFAULT 'pending',
            total_records int(11) DEFAULT 0,
            processed_records int(11) DEFAULT 0,
            failed_records int(11) DEFAULT 0,
            error_log longtext DEFAULT NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY operation_type (operation_type),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Integration settings table
        $sql_integrations = "CREATE TABLE {$wpdb->prefix}univga_integrations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            integration_type varchar(50) NOT NULL,
            settings longtext DEFAULT NULL,
            is_active tinyint(1) DEFAULT 0,
            last_sync datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_org_integration (org_id, integration_type),
            KEY integration_type (integration_type),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Internal messaging tables
        $sql_conversations = "CREATE TABLE {$wpdb->prefix}univga_conversations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            subject varchar(191) NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            last_activity datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_archived tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY created_by (created_by),
            KEY last_activity (last_activity),
            KEY is_archived (is_archived)
        ) $charset_collate;";

        $sql_conversation_participants = "CREATE TABLE {$wpdb->prefix}univga_conversation_participants (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            role enum('admin','member') DEFAULT 'member',
            joined_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_read_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_participant (conversation_id, user_id),
            KEY conversation_id (conversation_id),
            KEY user_id (user_id),
            KEY role (role)
        ) $charset_collate;";

        $sql_messages = "CREATE TABLE {$wpdb->prefix}univga_messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            sender_id bigint(20) unsigned NOT NULL,
            message text NOT NULL,
            message_type enum('text','announcement','system') DEFAULT 'text',
            is_priority tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY sender_id (sender_id),
            KEY created_at (created_at),
            KEY message_type (message_type),
            KEY is_priority (is_priority)
        ) $charset_collate;";

        // White-label settings table
        $sql_branding = "CREATE TABLE {$wpdb->prefix}univga_branding (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            logo_url varchar(500) DEFAULT NULL,
            primary_color varchar(7) DEFAULT NULL,
            secondary_color varchar(7) DEFAULT NULL,
            slogan varchar(255) DEFAULT NULL,
            company_name varchar(255) DEFAULT NULL,
            custom_css longtext DEFAULT NULL,
            email_header varchar(500) DEFAULT NULL,
            custom_domain varchar(191) DEFAULT NULL,
            settings longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_org (org_id)
        ) $charset_collate;";

        // AI Analytics storage table
        $sql_ai_analytics = "CREATE TABLE {$wpdb->prefix}univga_ai_analytics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            analysis_type varchar(50) NOT NULL,
            ai_result longtext DEFAULT NULL,
            raw_data longtext DEFAULT NULL,
            confidence_score decimal(3,2) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY analysis_type (analysis_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create all tables
        dbDelta($sql_orgs);
        dbDelta($sql_teams);
        dbDelta($sql_members);
        dbDelta($sql_pools);
        dbDelta($sql_events);
        dbDelta($sql_analytics_events);
        dbDelta($sql_analytics_summary);
        dbDelta($sql_learning_paths);
        dbDelta($sql_learning_path_courses);
        dbDelta($sql_learning_path_assignments);
        dbDelta($sql_certifications);
        dbDelta($sql_user_certifications);
        dbDelta($sql_user_points);
        dbDelta($sql_badges);
        dbDelta($sql_user_badges);
        dbDelta($sql_notifications);
        dbDelta($sql_notification_templates);
        dbDelta($sql_bulk_operations);
        dbDelta($sql_integrations);
        dbDelta($sql_conversations);
        dbDelta($sql_conversation_participants);
        dbDelta($sql_messages);
        dbDelta($sql_branding);
        dbDelta($sql_ai_analytics);
    }
    
    /**
     * Create custom roles and capabilities
     */
    private static function create_roles() {
        // Add organization manager role
        add_role('org_manager', __('Organization Manager', UNIVGA_TEXT_DOMAIN), array(
            'read' => true,
            'univga_org_manage' => true,
            'univga_seats_manage' => true,
            'univga_reports_view' => true,
        ));
        
        // Add team leader role
        add_role('team_lead', __('Team Leader', UNIVGA_TEXT_DOMAIN), array(
            'read' => true,
            'univga_team_manage' => true,
            'univga_reports_view' => true,
        ));
        
        // Add ALL capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            // Complete list of all UNIVGA capabilities
            $all_caps = array(
                // Organization Management
                'univga_org_create', 'univga_org_edit', 'univga_org_delete', 'univga_org_view',
                'univga_org_manage',
                
                // Team Management
                'univga_team_create', 'univga_team_edit', 'univga_team_delete', 'univga_team_view',
                'univga_team_assign_members', 'univga_team_manage',
                
                // Member Management
                'univga_member_invite', 'univga_member_remove', 'univga_member_edit',
                'univga_member_view_all', 'univga_member_view_team',
                
                // Seat Pool Management
                'univga_seats_create', 'univga_seats_edit', 'univga_seats_assign',
                'univga_seats_revoke', 'univga_seats_view_usage', 'univga_seats_manage',
                
                // Course and Learning Management
                'univga_courses_assign', 'univga_courses_bulk_assign',
                'univga_learning_paths_create', 'univga_learning_paths_assign', 'univga_learning_paths_manage',
                
                // Certification Management
                'univga_cert_create', 'univga_cert_award', 'univga_cert_revoke', 'univga_cert_view_all',
                
                // Analytics and Reporting
                'univga_analytics_basic', 'univga_analytics_advanced',
                'univga_reports_generate', 'univga_reports_export', 'univga_reports_view_team',
                'univga_reports_view_org', 'univga_reports_view',
                
                // Bulk Operations
                'univga_bulk_import', 'univga_bulk_enroll', 'univga_bulk_operations',
                
                // Gamification
                'univga_gamification_manage', 'univga_badges_create',
                'univga_badges_award', 'univga_points_adjust',
                
                // Notifications and Communication
                'univga_notifications_send', 'univga_notifications_broadcast', 'univga_templates_manage',
                
                // Integrations
                'univga_integrations_manage', 'univga_sso_configure', 'univga_hr_sync',
                
                // Branding and Customization
                'univga_branding_manage', 'univga_custom_css', 'univga_custom_domain',
                
                // System Administration
                'univga_settings_global', 'univga_permissions_manage',
                'univga_audit_logs', 'univga_system_health',
                'univga_admin_access', 'univga_ai_analytics',
                
                // User Profiles
                'univga_profiles_view', 'univga_profiles_manage',
                
                // HR Features
                'univga_hr_manager'
            );
            
            // Add all capabilities to administrator role
            foreach ($all_caps as $cap) {
                if (!$admin_role->has_cap($cap)) {
                    $admin_role->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Schedule cron jobs
     */
    private static function schedule_cron() {
        if (!wp_next_scheduled('univga_org_resync')) {
            wp_schedule_event(time(), 'twicedaily', 'univga_org_resync');
        }
    }
}
