<?php

/**
 * Seat events audit logging
 */
class UNIVGA_Seat_Events {
    
    /**
     * Log seat event
     */
    public static function log($pool_id, $user_id, $type, $meta = array()) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_seat_events',
            array(
                'pool_id' => intval($pool_id),
                'user_id' => $user_id ? intval($user_id) : null,
                'type' => sanitize_text_field($type),
                'meta' => json_encode($meta),
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Get events by pool
     */
    public static function get_by_pool($pool_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'type' => null,
            'user_id' => null,
            'limit' => 50,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('pool_id = %d');
        $values = array($pool_id);
        
        if ($args['type']) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }
        
        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }
        
        $sql = "SELECT e.*, u.display_name, u.user_email
                FROM {$wpdb->prefix}univga_seat_events e
                LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.created_at DESC";
        
        if ($args['limit']) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    /**
     * Get events by organization
     */
    public static function get_by_org($org_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'type' => null,
            'user_id' => null,
            'limit' => 50,
            'offset' => 0,
            'date_from' => null,
            'date_to' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('p.org_id = %d');
        $values = array($org_id);
        
        if ($args['type']) {
            $where[] = 'e.type = %s';
            $values[] = $args['type'];
        }
        
        if ($args['user_id']) {
            $where[] = 'e.user_id = %d';
            $values[] = $args['user_id'];
        }
        
        if ($args['date_from']) {
            $where[] = 'e.created_at >= %s';
            $values[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where[] = 'e.created_at <= %s';
            $values[] = $args['date_to'];
        }
        
        $sql = "SELECT e.*, u.display_name, u.user_email, p.scope_type
                FROM {$wpdb->prefix}univga_seat_events e
                JOIN {$wpdb->prefix}univga_seat_pools p ON e.pool_id = p.id
                LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.created_at DESC";
        
        if ($args['limit']) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    /**
     * Get event statistics
     */
    public static function get_stats($org_id, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $where = array('p.org_id = %d');
        $values = array($org_id);
        
        if ($date_from) {
            $where[] = 'e.created_at >= %s';
            $values[] = $date_from;
        }
        
        if ($date_to) {
            $where[] = 'e.created_at <= %s';
            $values[] = $date_to;
        }
        
        $sql = "SELECT 
                    e.type,
                    COUNT(*) as count,
                    COUNT(DISTINCT e.user_id) as unique_users,
                    COUNT(DISTINCT e.pool_id) as unique_pools
                FROM {$wpdb->prefix}univga_seat_events e
                JOIN {$wpdb->prefix}univga_seat_pools p ON e.pool_id = p.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY e.type";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $values));
        
        $stats = array();
        foreach ($results as $result) {
            $stats[$result->type] = array(
                'count' => (int) $result->count,
                'unique_users' => (int) $result->unique_users,
                'unique_pools' => (int) $result->unique_pools,
            );
        }
        
        return $stats;
    }
    
    /**
     * Clean up old events (optional for performance)
     */
    public static function cleanup_old_events($days = 365) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}univga_seat_events WHERE created_at < %s",
            $cutoff_date
        ));
    }
}
