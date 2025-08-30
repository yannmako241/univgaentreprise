<?php

class UNIVGA_Seat_Pools {

    /**
     * Count total seat pools
     */
    public static function count() {
        global $wpdb;
        $table = $wpdb->prefix . 'univga_seat_pools';
        $sql = "SELECT COUNT(*) FROM {$table}";
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Get seat pools by organization
     */
    public static function get_by_org($org_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'univga_seat_pools';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d ORDER BY created_at DESC",
            $org_id
        ));
    }
    
    /**
     * Get courses covered by a pool
     */
    public static function get_pool_courses($pool) {
        if (!$pool) return array();
        
        $scope_type = $pool->scope_type;
        $scope_ids = $pool->scope_ids;
        
        // Decode JSON if needed
        if (is_string($scope_ids) && strlen($scope_ids) && $scope_ids[0] === '[') {
            $scope_ids = json_decode($scope_ids, true);
        }
        $scope_ids = is_array($scope_ids) ? $scope_ids : (array)$scope_ids;
        
        $course_ids = array();
        
        if ($scope_type === 'course') {
            $course_ids = array_map('intval', $scope_ids);
        } elseif ($scope_type === 'category') {
            // Get courses from categories
            foreach ($scope_ids as $cat_id) {
                $cat_courses = get_posts(array(
                    'post_type' => 'courses',
                    'posts_per_page' => -1,
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'course-category',
                            'field' => 'term_id',
                            'terms' => $cat_id
                        )
                    ),
                    'fields' => 'ids'
                ));
                $course_ids = array_merge($course_ids, $cat_courses);
            }
        }
        
        return array_unique($course_ids);
    }

    /**
     * Query des pools avec filtres (org_id, team_id, scope, search) + pagination.
     * Retourne un objet : (items=array d'objets stdClass, total=int, per_page, paged).
     */
    public static function query($args = []) {
        global $wpdb;
        $defaults = [
            'org_id'   => 0,
            'team_id'  => 0,
            'scope'    => '', // course|category|bundle
            'search'   => '',
            'paged'    => 1,
            'per_page' => 20,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];
        $a = wp_parse_args($args, $defaults);

        $table = $wpdb->prefix . 'univga_seat_pools';
        $where = ['1=1'];
        $params = [];

        if ( $a['org_id'] )  { $where[] = 'org_id = %d';      $params[] = (int)$a['org_id']; }
        if ( $a['team_id'] ) { $where[] = 'team_id = %d';     $params[] = (int)$a['team_id']; }
        if ( $a['scope'] )   { $where[] = 'scope_type = %s';  $params[] = $a['scope']; }
        if ( $a['search'] )  { $where[] = '(scope_ids LIKE %s OR scope_type LIKE %s)';
                               $like = '%' . $wpdb->esc_like($a['search']) . '%';
                               $params[] = $like; $params[] = $like; }

        $where_sql = implode(' AND ', $where);

        // Total
        $sql_total = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var( $wpdb->prepare($sql_total, $params) );

        // Items
        $orderby = preg_replace('/[^a-zA-Z0-9_]/', '', $a['orderby']);
        $order   = strtoupper($a['order']) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = max(1, (int)$a['per_page']);
        $offset = max(0, ((int)$a['paged'] - 1) * $limit);

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params_items = array_merge($params, [$limit, $offset]);

        $items = $wpdb->get_results( $wpdb->prepare($sql, $params_items) );

        return (object)[
            'items'    => $items ?: [],
            'total'    => $total,
            'per_page' => $limit,
            'paged'    => (int)$a['paged'],
        ];
    }

    /**
     * Delete a seat pool
     */
    public static function delete($pool_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'univga_seat_pools';
        $pool_id = (int)$pool_id;

        // Optionnel : vérifier que le pool existe
        $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$table} WHERE id=%d", $pool_id) );
        if ( ! $exists ) {
            return new WP_Error('pool_not_found', __('Pool introuvable.', 'univga'));
        }

        $deleted = $wpdb->delete($table, ['id' => $pool_id], ['%d']);
        if ( false === $deleted ) {
            return new WP_Error('pool_delete_fail', __('Échec de suppression du pool.', 'univga'));
        }

        // Optionnel : log d'audit
        if ( class_exists('UNIVGA_SeatEvents') && method_exists('UNIVGA_SeatEvents', 'log') ) {
            UNIVGA_SeatEvents::log($pool_id, 0, 'delete', ['by'=>get_current_user_id()]);
        }

        return true;
    }

    /**
     * Assign user to pool and consume seat
     */
    public static function assign_user($pool_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'univga_seat_pools';
        $pool  = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", (int)$pool_id) );

        if ( ! $pool ) return new WP_Error('pool_not_found', __('Pool introuvable.', 'univga'));

        $total = (int)$pool->seats_total;
        $used  = (int)$pool->seats_used;
        if ( $used >= $total ) {
            return new WP_Error('no_seat_left', __('Aucun siège disponible dans ce pool.', 'univga'));
        }

        // Consommation
        $updated = $wpdb->update(
            $table,
            ['seats_used' => $used + 1],
            ['id' => (int)$pool_id],
            ['%d'],
            ['%d']
        );
        if ( false === $updated ) {
            return new WP_Error('consume_fail', __('Impossible de consommer un siège.', 'univga'));
        }

        // Résolution des cours depuis scope
        $scope_type = $pool->scope_type;
        $scope_ids  = $pool->scope_ids;
        if ( is_string($scope_ids) && strlen($scope_ids) && $scope_ids[0] === '[' ) {
            $scope_ids = json_decode($scope_ids, true);
        }
        $scope_ids = is_array($scope_ids) ? $scope_ids : (array)$scope_ids;

        $course_ids = [];
        if ( class_exists('UNIVGA_Courses') && method_exists('UNIVGA_Courses','resolve_scope') ) {
            $course_ids = UNIVGA_Courses::resolve_scope($scope_type, $scope_ids);
        } else {
            // fallback : si scope_type == course → ids déjà des course_ids
            if ( $scope_type === 'course' ) $course_ids = array_map('intval', $scope_ids);
        }

        // Enrôlement Tutor LMS
        if ( ! empty($course_ids) && class_exists('UNIVGA_Tutor') && method_exists('UNIVGA_Tutor','enroll') ) {
            foreach ( $course_ids as $cid ) {
                UNIVGA_Tutor::enroll( (int)$user_id, (int)$cid, 'org' );
            }
        }

        // Log
        if ( class_exists('UNIVGA_SeatEvents') && method_exists('UNIVGA_SeatEvents','log') ) {
            UNIVGA_SeatEvents::log( (int)$pool_id, (int)$user_id, 'consume', ['by'=>get_current_user_id()] );
        }

        return true;
    }
    
    /**
     * Get seat pool by ID
     */
    public static function get($pool_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'univga_seat_pools';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $pool_id
        ));
    }
    
    /**
     * Create new seat pool
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'univga_seat_pools';
        
        $defaults = array(
            'org_id' => 0,
            'team_id' => null,
            'scope_type' => 'course',
            'scope_ids' => '',
            'seats_total' => 0,
            'seats_used' => 0,
            'expires_at' => null,
            'order_id' => null,
            'auto_enroll' => 1,
            'allow_replace' => 0,
        );
        
        $pool_data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $table,
            array(
                'org_id' => intval($pool_data['org_id']),
                'team_id' => $pool_data['team_id'] ? intval($pool_data['team_id']) : null,
                'scope_type' => sanitize_text_field($pool_data['scope_type']),
                'scope_ids' => is_array($pool_data['scope_ids']) ? json_encode($pool_data['scope_ids']) : $pool_data['scope_ids'],
                'seats_total' => intval($pool_data['seats_total']),
                'seats_used' => intval($pool_data['seats_used']),
                'expires_at' => $pool_data['expires_at'] ? $pool_data['expires_at'] : null,
                'order_id' => $pool_data['order_id'] ? intval($pool_data['order_id']) : null,
                'auto_enroll' => intval($pool_data['auto_enroll']),
                'allow_replace' => intval($pool_data['allow_replace']),
            ),
            array('%d', '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update seat pool
     */
    public static function update($pool_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'univga_seat_pools';
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['seats_total'])) {
            $update_data['seats_total'] = intval($data['seats_total']);
            $formats[] = '%d';
        }
        
        if (isset($data['seats_used'])) {
            $update_data['seats_used'] = intval($data['seats_used']);
            $formats[] = '%d';
        }
        
        if (isset($data['auto_enroll'])) {
            $update_data['auto_enroll'] = intval($data['auto_enroll']);
            $formats[] = '%d';
        }
        
        if (isset($data['allow_replace'])) {
            $update_data['allow_replace'] = intval($data['allow_replace']);
            $formats[] = '%d';
        }
        
        if (isset($data['scope_type'])) {
            $update_data['scope_type'] = sanitize_text_field($data['scope_type']);
            $formats[] = '%s';
        }
        
        if (isset($data['scope_ids'])) {
            $update_data['scope_ids'] = is_array($data['scope_ids']) ? json_encode($data['scope_ids']) : $data['scope_ids'];
            $formats[] = '%s';
        }
        
        if (isset($data['expires_at'])) {
            $update_data['expires_at'] = $data['expires_at'];
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return true;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => intval($pool_id)),
            $formats,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get all seat pools with details
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'univga_seat_pools';
        
        $defaults = array(
            'org_id' => null,
            'team_id' => null,
            'is_active' => null,
            'limit' => null,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['org_id'] !== null) {
            $where[] = 'org_id = %d';
            $values[] = $args['org_id'];
        }
        
        if ($args['team_id'] !== null) {
            $where[] = 'team_id = %d';
            $values[] = $args['team_id'];
        }
        
        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = $args['is_active'];
        }
        
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where);
        
        $valid_orderby = array('created_at', 'seats_total', 'seats_used');
        if (in_array($args['orderby'], $valid_orderby)) {
            $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? $args['order'] : 'DESC';
            $sql .= " ORDER BY " . sanitize_sql_orderby($args['orderby'] . ' ' . $order);
        } else {
            $sql .= " ORDER BY created_at DESC";
        }
        
        // Add LIMIT and OFFSET to the parameters if needed
        if ($args['limit']) {
            $sql .= " LIMIT %d OFFSET %d";
            $values[] = intval($args['limit']);
            $values[] = intval($args['offset']);
        }
        
        // Only use prepare if we have parameters
        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($sql, $values));
        } else {
            return $wpdb->get_results($sql);
        }
    }

}