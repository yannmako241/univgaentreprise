<?php

/**
 * Organization members management
 */
class UNIVGA_Members {
    
    /**
     * Add member to organization/team
     */
    public static function add_member($org_id, $team_id, $user_id, $status = 'active') {
        global $wpdb;
        
        // Check if member already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}univga_org_members WHERE org_id = %d AND user_id = %d",
            $org_id, $user_id
        ));
        
        if ($existing) {
            // Update existing membership
            return $wpdb->update(
                $wpdb->prefix . 'univga_org_members',
                array(
                    'team_id' => $team_id,
                    'status' => $status,
                    'removed_at' => null,
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%s'),
                array('%d')
            ) !== false;
        }
        
        // Create new membership
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_org_members',
            array(
                'org_id' => intval($org_id),
                'team_id' => $team_id ? intval($team_id) : null,
                'user_id' => intval($user_id),
                'status' => $status,
            ),
            array('%d', '%d', '%d', '%s')
        );
        
        if ($result !== false && $status === 'active') {
            // Auto-enroll in organization courses
            self::auto_enroll_member($org_id, $team_id, $user_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Remove member from organization
     */
    public static function remove_member($org_id, $user_id, $allow_replace = false) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'univga_org_members',
            array(
                'status' => 'removed',
                'removed_at' => current_time('mysql'),
            ),
            array(
                'org_id' => $org_id,
                'user_id' => $user_id,
            ),
            array('%s', '%s'),
            array('%d', '%d')
        );
        
        if ($result !== false && $allow_replace) {
            // Release seats for potential replacement
            self::release_member_seats($org_id, $user_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Get organization members
     */
    public static function get_org_members($org_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => null,
            'team_id' => null,
            'limit' => null,
            'offset' => 0,
            'orderby' => 'joined_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('m.org_id = %d');
        $values = array($org_id);
        
        if ($args['status']) {
            $where[] = 'm.status = %s';
            $values[] = $args['status'];
        }
        
        if ($args['team_id']) {
            $where[] = 'm.team_id = %d';
            $values[] = $args['team_id'];
        }
        
        $sql = "SELECT m.*, u.user_login, u.user_email, u.display_name as user_display_name
                FROM {$wpdb->prefix}univga_org_members m
                LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
                WHERE " . implode(' AND ', $where);
                
        // Handle ORDER BY clause properly
        $valid_orderby = array('joined_at', 'user_login', 'user_email', 'status');
        if (in_array($args['orderby'], $valid_orderby)) {
            $orderby = $args['orderby'] === 'joined_at' ? 'm.joined_at' : $args['orderby'];
            $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? $args['order'] : 'DESC';
            $sanitized_orderby = sanitize_sql_orderby($orderby . ' ' . $order);
            if (!empty($sanitized_orderby)) {
                $sql .= " ORDER BY " . $sanitized_orderby;
            } else {
                $sql .= " ORDER BY m.joined_at DESC";
            }
        } else {
            $sql .= " ORDER BY m.joined_at DESC";
        }
        
        if ($args['limit']) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    /**
     * Get organization members count
     */
    public static function get_org_members_count($org_id, $args = array()) {
        global $wpdb;
        
        $where = array('org_id = %d');
        $values = array($org_id);
        
        if (isset($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}univga_org_members WHERE " . implode(' AND ', $where);
        
        return (int) $wpdb->get_var($wpdb->prepare($sql, $values));
    }
    
    /**
     * Get member by ID
     */
    public static function get($member_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}univga_org_members WHERE id = %d",
            $member_id
        ));
    }
    
    /**
     * Get all members with details
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'org_id' => null,
            'team_id' => null,
            'status' => null,
            'limit' => null,
            'offset' => 0,
            'orderby' => 'joined_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['org_id'] !== null) {
            $where[] = 'm.org_id = %d';
            $values[] = $args['org_id'];
        }
        
        if ($args['team_id'] !== null) {
            $where[] = 'm.team_id = %d';
            $values[] = $args['team_id'];
        }
        
        if ($args['status'] !== null) {
            $where[] = 'm.status = %s';
            $values[] = $args['status'];
        }
        
        $sql = "SELECT m.*, 
                       u.user_login, u.user_email, u.display_name as user_display_name,
                       o.name as org_name,
                       t.name as team_name
                FROM {$wpdb->prefix}univga_org_members m
                LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
                LEFT JOIN {$wpdb->prefix}univga_orgs o ON m.org_id = o.id
                LEFT JOIN {$wpdb->prefix}univga_teams t ON m.team_id = t.id
                WHERE " . implode(' AND ', $where);
                
        // Handle ORDER BY clause properly
        $valid_orderby = array('joined_at', 'user_login', 'user_email', 'status');
        if (in_array($args['orderby'], $valid_orderby)) {
            $orderby = $args['orderby'] === 'joined_at' ? 'm.joined_at' : $args['orderby'];
            $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? $args['order'] : 'DESC';
            $sanitized_orderby = sanitize_sql_orderby($orderby . ' ' . $order);
            if (!empty($sanitized_orderby)) {
                $sql .= " ORDER BY " . $sanitized_orderby;
            } else {
                $sql .= " ORDER BY m.joined_at DESC";
            }
        } else {
            $sql .= " ORDER BY m.joined_at DESC";
        }
        
        if ($args['limit']) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get member with details
     */
    public static function get_with_details($member_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, 
                    u.user_login, u.user_email, u.display_name as user_display_name,
                    o.name as org_name,
                    t.name as team_name
             FROM {$wpdb->prefix}univga_org_members m
             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}univga_orgs o ON m.org_id = o.id
             LEFT JOIN {$wpdb->prefix}univga_teams t ON m.team_id = t.id
             WHERE m.id = %d",
            $member_id
        ));
    }
    
    /**
     * Update member
     */
    public static function update($member_id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['team_id'])) {
            $update_data['team_id'] = $data['team_id'] ? intval($data['team_id']) : null;
            $formats[] = '%d';
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return true;
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'univga_org_members',
            $update_data,
            array('id' => $member_id),
            $formats,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Auto-enroll member in organization courses
     */
    private static function auto_enroll_member($org_id, $team_id, $user_id) {
        // Get active seat pools for this org/team
        if (class_exists('UNIVGA_Seat_Pools') && method_exists('UNIVGA_Seat_Pools', 'get_by_org')) {
            $pools = UNIVGA_Seat_Pools::get_by_org($org_id);
            
            foreach ($pools as $pool) {
                // Check if pool has available seats
                if ($pool->seats_used >= $pool->seats_total) {
                    continue;
                }
                
                // Get courses from pool scope
                if (method_exists('UNIVGA_Seat_Pools', 'get_pool_courses')) {
                    $course_ids = UNIVGA_Seat_Pools::get_pool_courses($pool);
                    
                    foreach ($course_ids as $course_id) {
                        // Enroll user in course
                        if (class_exists('UNIVGA_Tutor') && method_exists('UNIVGA_Tutor', 'enroll')) {
                            UNIVGA_Tutor::enroll($user_id, $course_id, 'org');
                        }
                    }
                    
                    // Consume seat
                    if (method_exists('UNIVGA_Seat_Pools', 'assign_user')) {
                        UNIVGA_Seat_Pools::assign_user($pool->id, $user_id);
                    }
                }
            }
        }
    }
    
    /**
     * Get user organization membership
     */
    public static function get_user_org_membership($user_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, o.name as org_name, t.name as team_name
             FROM {$wpdb->prefix}univga_org_members m
             LEFT JOIN {$wpdb->prefix}univga_orgs o ON m.org_id = o.id
             LEFT JOIN {$wpdb->prefix}univga_teams t ON m.team_id = t.id
             WHERE m.user_id = %d AND m.status = 'active'
             LIMIT 1",
            $user_id
        ));
    }
    
    /**
     * Release member seats when removed
     */
    private static function release_member_seats($org_id, $user_id) {
        global $wpdb;
        
        // Get pools where user consumed seats
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT e.pool_id, p.allow_replace
             FROM {$wpdb->prefix}univga_seat_events e
             JOIN {$wpdb->prefix}univga_seat_pools p ON e.pool_id = p.id
             WHERE e.user_id = %d AND e.type = 'consume' AND p.org_id = %d",
            $user_id, $org_id
        ));
        
        foreach ($events as $event) {
            if ($event->allow_replace) {
                // Release seat for replacement
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}univga_seat_pools SET seats_used = seats_used - 1 WHERE id = %d",
                    $event->pool_id
                ));
            }
        }
    }
}