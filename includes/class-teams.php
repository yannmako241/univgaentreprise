<?php

/**
 * Teams CRUD operations
 */
class UNIVGA_Teams {
    
    /**
     * Create team
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = array(
            'org_id' => 0,
            'name' => '',
            'manager_user_id' => null,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['name']) || empty($data['org_id'])) {
            return new WP_Error('missing_fields', __('Team name and organization are required', UNIVGA_TEXT_DOMAIN));
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_teams',
            array(
                'org_id' => intval($data['org_id']),
                'name' => sanitize_text_field($data['name']),
                'manager_user_id' => $data['manager_user_id'] ? intval($data['manager_user_id']) : null,
            ),
            array('%d', '%s', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create team', UNIVGA_TEXT_DOMAIN));
        }
        
        $team_id = $wpdb->insert_id;
        
        // Add manager as team member if specified
        if ($data['manager_user_id']) {
            UNIVGA_Members::add_member($data['org_id'], $team_id, $data['manager_user_id'], 'active');
            
            // Assign team_lead role
            $user = get_userdata($data['manager_user_id']);
            if ($user) {
                $user->add_role('team_lead');
            }
        }
        
        return $team_id;
    }
    
    /**
     * Get team by ID
     */
    public static function get($team_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}univga_teams WHERE id = %d",
            $team_id
        ));
    }
    
    /**
     * Update team
     */
    public static function update($team_id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $formats[] = '%s';
        }
        
        if (isset($data['manager_user_id'])) {
            $update_data['manager_user_id'] = $data['manager_user_id'] ? intval($data['manager_user_id']) : null;
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return true;
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'univga_teams',
            $update_data,
            array('id' => $team_id),
            $formats,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete team
     */
    public static function delete($team_id) {
        global $wpdb;
        
        // Remove team members (but keep them in org)
        $wpdb->update(
            $wpdb->prefix . 'univga_org_members',
            array('team_id' => null),
            array('team_id' => $team_id),
            array('%d'),
            array('%d')
        );
        
        // Delete team-specific seat pools
        $pools = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}univga_seat_pools WHERE team_id = %d",
            $team_id
        ));
        
        foreach ($pools as $pool_id) {
            $wpdb->delete($wpdb->prefix . 'univga_seat_events', array('pool_id' => $pool_id), array('%d'));
        }
        
        $wpdb->delete($wpdb->prefix . 'univga_seat_pools', array('team_id' => $team_id), array('%d'));
        
        // Delete team
        return $wpdb->delete($wpdb->prefix . 'univga_teams', array('id' => $team_id), array('%d')) !== false;
    }
    
    /**
     * Get teams by organization
     */
    public static function get_by_org($org_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => null,
            'offset' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}univga_teams WHERE org_id = %d",
            $org_id
        );
        
        $sql .= " ORDER BY " . sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        if ($args['limit']) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get team count by organization
     */
    public static function get_count_by_org($org_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}univga_teams WHERE org_id = %d",
            $org_id
        ));
    }
    
    /**
     * Get all teams
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'org_id' => null,
            'status' => null,
            'limit' => null,
            'offset' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['org_id'] !== null) {
            $where[] = 't.org_id = %d';
            $values[] = $args['org_id'];
        }
        
        $sql = "SELECT t.*, o.name as org_name, u.display_name as manager_name,
                       (SELECT COUNT(*) FROM {$wpdb->prefix}univga_org_members WHERE team_id = t.id) as member_count
                FROM {$wpdb->prefix}univga_teams t
                LEFT JOIN {$wpdb->prefix}univga_orgs o ON t.org_id = o.id
                LEFT JOIN {$wpdb->users} u ON t.manager_user_id = u.ID
                WHERE " . implode(' AND ', $where);
                
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
     * Get team with details
     */
    public static function get_with_details($team_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, o.name as org_name, u.display_name as manager_name, u.user_email as manager_email,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}univga_org_members WHERE team_id = t.id) as member_count
             FROM {$wpdb->prefix}univga_teams t
             LEFT JOIN {$wpdb->prefix}univga_orgs o ON t.org_id = o.id
             LEFT JOIN {$wpdb->users} u ON t.manager_user_id = u.ID
             WHERE t.id = %d",
            $team_id
        ));
    }
}