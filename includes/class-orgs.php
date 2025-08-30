<?php

/**
 * Organizations CRUD operations
 */
class UNIVGA_Orgs {
    
    /**
     * Create organization
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = array(
            'name' => '',
            'legal_id' => null,
            'contact_user_id' => null,
            'email_domain' => null,
            'status' => 1,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['name'])) {
            return new WP_Error('missing_name', __('Organization name is required', UNIVGA_TEXT_DOMAIN));
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_orgs',
            array(
                'name' => sanitize_text_field($data['name']),
                'legal_id' => $data['legal_id'] ? sanitize_text_field($data['legal_id']) : null,
                'contact_user_id' => $data['contact_user_id'] ? intval($data['contact_user_id']) : null,
                'email_domain' => $data['email_domain'] ? sanitize_text_field($data['email_domain']) : null,
                'status' => intval($data['status']),
            ),
            array('%s', '%s', '%d', '%s', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create organization', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = $wpdb->insert_id;
        
        // Add contact user as organization manager if specified
        if ($data['contact_user_id']) {
            UNIVGA_Members::add_member($org_id, null, $data['contact_user_id'], 'active');
            
            // Assign org_manager role
            $user = get_userdata($data['contact_user_id']);
            if ($user) {
                $user->add_role('org_manager');
            }
        }
        
        // AUTO-ADD ALL WORDPRESS ADMINISTRATORS TO THE ORGANIZATION
        self::add_all_admins_to_organization($org_id);
        
        return $org_id;
    }
    
    /**
     * Get organization by ID
     */
    public static function get($org_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}univga_orgs WHERE id = %d",
            $org_id
        ));
    }
    
    /**
     * Update organization
     */
    public static function update($org_id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $formats[] = '%s';
        }
        
        if (isset($data['legal_id'])) {
            $update_data['legal_id'] = $data['legal_id'] ? sanitize_text_field($data['legal_id']) : null;
            $formats[] = '%s';
        }
        
        if (isset($data['contact_user_id'])) {
            $update_data['contact_user_id'] = $data['contact_user_id'] ? intval($data['contact_user_id']) : null;
            $formats[] = '%d';
        }
        
        if (isset($data['email_domain'])) {
            $update_data['email_domain'] = $data['email_domain'] ? sanitize_text_field($data['email_domain']) : null;
            $formats[] = '%s';
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = intval($data['status']);
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return true;
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'univga_orgs',
            $update_data,
            array('id' => $org_id),
            $formats,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete organization
     */
    public static function delete($org_id) {
        global $wpdb;
        
        // Delete related data
        $wpdb->delete($wpdb->prefix . 'univga_org_members', array('org_id' => $org_id), array('%d'));
        $wpdb->delete($wpdb->prefix . 'univga_teams', array('org_id' => $org_id), array('%d'));
        
        // Delete seat pools and events
        $pools = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}univga_seat_pools WHERE org_id = %d",
            $org_id
        ));
        
        foreach ($pools as $pool_id) {
            $wpdb->delete($wpdb->prefix . 'univga_seat_events', array('pool_id' => $pool_id), array('%d'));
        }
        
        $wpdb->delete($wpdb->prefix . 'univga_seat_pools', array('org_id' => $org_id), array('%d'));
        
        // Delete organization
        return $wpdb->delete($wpdb->prefix . 'univga_orgs', array('id' => $org_id), array('%d')) !== false;
    }
    
    /**
     * Get all organizations
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => null,
            'limit' => null,
            'offset' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['status'] !== null) {
            $where[] = 'status = %d';
            $values[] = $args['status'];
        }
        
        $sql = "SELECT * FROM {$wpdb->prefix}univga_orgs WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY " . sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        if ($args['limit']) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get organization count
     */
    public static function get_count($args = array()) {
        global $wpdb;
        
        $where = array('1=1');
        $values = array();
        
        if (isset($args['status'])) {
            $where[] = 'status = %d';
            $values[] = $args['status'];
        }
        
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}univga_orgs WHERE " . implode(' AND ', $where);
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Add all WordPress administrators to organization
     */
    public static function add_all_admins_to_organization($org_id) {
        // Get all WordPress administrators
        $admin_users = get_users(array(
            'role' => 'administrator',
            'fields' => 'ID'
        ));
        
        foreach ($admin_users as $user_id) {
            // Add each admin to the organization as active member
            UNIVGA_Members::add_member($org_id, null, $user_id, 'active');
        }
        
        return true;
    }
    
    /**
     * Remove administrator from organization (allows admins to remove each other)
     */
    public static function remove_admin_from_organization($org_id, $user_id) {
        // Verify user is admin
        $user = get_userdata($user_id);
        if (!$user || !in_array('administrator', $user->roles)) {
            return new WP_Error('not_admin', __('User is not an administrator', UNIVGA_TEXT_DOMAIN));
        }
        
        // Remove the admin from organization
        return UNIVGA_Members::remove_member($org_id, $user_id, false);
    }
    
    /**
     * Get organization administrators
     */
    public static function get_organization_admins($org_id) {
        global $wpdb;
        
        $admin_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}univga_org_members 
             WHERE org_id = %d AND status = 'active'",
            $org_id
        ));
        
        $admins = array();
        foreach ($admin_ids as $user_id) {
            $user = get_userdata($user_id);
            if ($user && in_array('administrator', $user->roles)) {
                $admins[] = $user;
            }
        }
        
        return $admins;
    }


    /**
     * Get manager emails for organization and/or team
     */
    public static function manager_emails($org_id, $team_id = 0) {
        $emails = array();
        
        // Get organization administrators
        if ($org_id) {
            $org_admins = self::get_organization_admins($org_id);
            foreach ($org_admins as $admin) {
                if (!empty($admin->user_email)) {
                    $emails[] = $admin->user_email;
                }
            }
        }
        
        // Get team manager if specified
        if ($team_id) {
            $team = UNIVGA_Teams::get($team_id);
            if ($team && $team->manager_user_id) {
                $manager = get_userdata($team->manager_user_id);
                if ($manager && !empty($manager->user_email)) {
                    $emails[] = $manager->user_email;
                }
            }
        }
        
        // Get organization contact user
        if ($org_id) {
            $org = self::get($org_id);
            if ($org && $org->contact_user_id) {
                $contact = get_userdata($org->contact_user_id);
                if ($contact && !empty($contact->user_email)) {
                    $emails[] = $contact->user_email;
                }
            }
        }
        
        // Remove duplicates and filter valid emails
        $emails = array_unique($emails);
        $emails = array_filter($emails, function($email) {
            return is_email($email);
        });
        
        return array_values($emails);
    }
}
