<?php

/**
 * Database Migration Handler
 */
class UNIVGA_DB_Migration {

    /**
     * Run all migrations
     */
    public static function run_migrations() {
        self::add_missing_columns_to_orgs();
        self::ensure_teams_table();
    }

    /**
     * Add missing columns to univga_orgs table
     */
    private static function add_missing_columns_to_orgs() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'univga_orgs';
        
        // Check if description column exists
        $description_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'description'",
            DB_NAME,
            $table
        ));
        
        if (!$description_exists) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN description TEXT DEFAULT NULL AFTER name");
        }
        
        // Check if max_seats column exists
        $max_seats_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'max_seats'",
            DB_NAME,
            $table
        ));
        
        if (!$max_seats_exists) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN max_seats INT DEFAULT 100 AFTER email_domain");
        }
    }

    /**
     * Ensure teams table exists
     */
    private static function ensure_teams_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'univga_teams';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            org_id bigint(20) unsigned NOT NULL,
            name varchar(191) NOT NULL,
            description text DEFAULT NULL,
            team_lead_id bigint(20) unsigned DEFAULT NULL,
            status tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY team_lead_id (team_lead_id),
            KEY status (status),
            FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}univga_orgs(id) ON DELETE CASCADE,
            FOREIGN KEY (team_lead_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}