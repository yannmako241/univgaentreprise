<?php

/**
 * Permission repair utilities
 * Fixes permission issues when plugin is installed on production sites
 */
class UNIVGA_Permission_Repair {
    
    /**
     * Repair permissions for existing organizations and teams
     * This ensures users who should be managers have the correct roles
     */
    public static function repair_all_permissions() {
        global $wpdb;
        
        $repaired_users = array();
        
        // Repair organization managers
        $org_managers = $wpdb->get_results(
            "SELECT DISTINCT contact_user_id, id as org_id, name as org_name
             FROM {$wpdb->prefix}univga_orgs 
             WHERE contact_user_id IS NOT NULL AND status = 1"
        );
        
        foreach ($org_managers as $manager) {
            $user = get_userdata($manager->contact_user_id);
            if ($user && !user_can($user, 'univga_org_manage')) {
                // Add org_manager role if not already present
                $user->add_role('org_manager');
                
                // Ensure user is in the organization members table
                $existing_member = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}univga_org_members 
                     WHERE org_id = %d AND user_id = %d",
                    $manager->org_id, $manager->contact_user_id
                ));
                
                if (!$existing_member) {
                    $wpdb->insert(
                        $wpdb->prefix . 'univga_org_members',
                        array(
                            'org_id' => $manager->org_id,
                            'user_id' => $manager->contact_user_id,
                            'status' => 'active',
                            'joined_at' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%s')
                    );
                }
                
                $repaired_users[] = array(
                    'user' => $user->display_name,
                    'email' => $user->user_email,
                    'role' => 'Organization Manager',
                    'organization' => $manager->org_name
                );
            }
        }
        
        // Repair team managers
        $team_managers = $wpdb->get_results(
            "SELECT DISTINCT t.manager_user_id, t.id as team_id, t.org_id, t.name as team_name, o.name as org_name
             FROM {$wpdb->prefix}univga_teams t
             LEFT JOIN {$wpdb->prefix}univga_orgs o ON t.org_id = o.id
             WHERE t.manager_user_id IS NOT NULL"
        );
        
        foreach ($team_managers as $manager) {
            $user = get_userdata($manager->manager_user_id);
            if ($user && !user_can($user, 'univga_team_manage')) {
                // Add team_lead role if not already present
                $user->add_role('team_lead');
                
                // Ensure user is in the organization/team members table
                $existing_member = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}univga_org_members 
                     WHERE org_id = %d AND user_id = %d",
                    $manager->org_id, $manager->manager_user_id
                ));
                
                if (!$existing_member) {
                    $wpdb->insert(
                        $wpdb->prefix . 'univga_org_members',
                        array(
                            'org_id' => $manager->org_id,
                            'team_id' => $manager->team_id,
                            'user_id' => $manager->manager_user_id,
                            'status' => 'active',
                            'joined_at' => current_time('mysql')
                        ),
                        array('%d', '%d', '%d', '%s', '%s')
                    );
                } elseif (!$existing_member->team_id) {
                    // Update existing member to assign to team
                    $wpdb->update(
                        $wpdb->prefix . 'univga_org_members',
                        array('team_id' => $manager->team_id),
                        array('id' => $existing_member->id),
                        array('%d'), array('%d')
                    );
                }
                
                $repaired_users[] = array(
                    'user' => $user->display_name,
                    'email' => $user->user_email,
                    'role' => 'Team Leader',
                    'organization' => $manager->org_name,
                    'team' => $manager->team_name
                );
            }
        }
        
        // Log the repair action
        if (!empty($repaired_users)) {
            $log_message = 'UNIVGA: Permissions repaired for ' . count($repaired_users) . ' users: ' . 
                          wp_json_encode($repaired_users);
            error_log($log_message);
        }
        
        return $repaired_users;
    }
    
    /**
     * Check what permissions need to be repaired (diagnostic)
     */
    public static function diagnose_permission_issues() {
        global $wpdb;
        
        $issues = array();
        
        // Check organization managers
        $org_issues = $wpdb->get_results(
            "SELECT o.id, o.name, o.contact_user_id, u.display_name, u.user_email
             FROM {$wpdb->prefix}univga_orgs o
             LEFT JOIN {$wpdb->users} u ON o.contact_user_id = u.ID
             WHERE o.contact_user_id IS NOT NULL AND o.status = 1"
        );
        
        foreach ($org_issues as $org) {
            $user = get_userdata($org->contact_user_id);
            if ($user && !user_can($user, 'univga_org_manage')) {
                $issues[] = array(
                    'type' => 'organization_manager',
                    'user_id' => $org->contact_user_id,
                    'user_name' => $org->display_name,
                    'user_email' => $org->user_email,
                    'organization' => $org->name,
                    'problem' => 'User is set as organization contact but lacks org_manager role'
                );
            }
        }
        
        // Check team managers
        $team_issues = $wpdb->get_results(
            "SELECT t.id, t.name, t.manager_user_id, u.display_name, u.user_email, o.name as org_name
             FROM {$wpdb->prefix}univga_teams t
             LEFT JOIN {$wpdb->users} u ON t.manager_user_id = u.ID
             LEFT JOIN {$wpdb->prefix}univga_orgs o ON t.org_id = o.id
             WHERE t.manager_user_id IS NOT NULL"
        );
        
        foreach ($team_issues as $team) {
            $user = get_userdata($team->manager_user_id);
            if ($user && !user_can($user, 'univga_team_manage')) {
                $issues[] = array(
                    'type' => 'team_manager',
                    'user_id' => $team->manager_user_id,
                    'user_name' => $team->display_name,
                    'user_email' => $team->user_email,
                    'organization' => $team->org_name,
                    'team' => $team->name,
                    'problem' => 'User is set as team manager but lacks team_lead role'
                );
            }
        }
        
        return $issues;
    }
    
    /**
     * Create admin notice for permission issues
     */
    public static function show_permission_issues_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $issues = self::diagnose_permission_issues();
        
        if (!empty($issues)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<h3>UNIVGA Business Pro - Problèmes de permissions détectés</h3>';
            echo '<p>' . sprintf(__('%d utilisateurs ont des problèmes de permissions:', UNIVGA_TEXT_DOMAIN), count($issues)) . '</p>';
            echo '<ul>';
            foreach ($issues as $issue) {
                echo '<li><strong>' . esc_html($issue['user_name']) . '</strong> (' . esc_html($issue['user_email']) . ') - ' . esc_html($issue['problem']) . '</li>';
            }
            echo '</ul>';
            echo '<p><a href="' . wp_nonce_url(admin_url('admin.php?page=univga-settings&action=repair_permissions'), 'univga_repair_permissions') . '" class="button button-primary">Réparer automatiquement les permissions</a></p>';
            echo '</div>';
        }
    }
}