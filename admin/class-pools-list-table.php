<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Seat pools list table
 */
class UNIVGA_Pools_List_Table extends WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'pool',
            'plural' => 'pools',
            'ajax' => false,
        ));
    }
    
    /**
     * Get columns
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'org_name' => __('Organization', UNIVGA_TEXT_DOMAIN),
            'team_name' => __('Team', UNIVGA_TEXT_DOMAIN),
            'scope' => __('Scope', UNIVGA_TEXT_DOMAIN),
            'seats' => __('Seats', UNIVGA_TEXT_DOMAIN),
            'utilization' => __('Utilization', UNIVGA_TEXT_DOMAIN),
            'expires_at' => __('Expires', UNIVGA_TEXT_DOMAIN),
            'auto_enroll' => __('Auto Enroll', UNIVGA_TEXT_DOMAIN),
            'created_at' => __('Created', UNIVGA_TEXT_DOMAIN),
        );
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'created_at' => array('created_at', true),
            'expires_at' => array('expires_at', false),
            'seats_total' => array('seats_total', false),
        );
    }
    
    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Delete', UNIVGA_TEXT_DOMAIN),
        );
    }
    
    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $org_filter = isset($_REQUEST['org_filter']) ? intval($_REQUEST['org_filter']) : 0;
        $status_filter = isset($_REQUEST['status_filter']) ? sanitize_text_field($_REQUEST['status_filter']) : '';
        
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'created_at';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        $where = array('1=1');
        $values = array();
        
        if ($search) {
            $where[] = 'o.name LIKE %s';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $search_term;
        }
        
        if ($org_filter) {
            $where[] = 'p.org_id = %d';
            $values[] = $org_filter;
        }
        
        if ($status_filter === 'expired') {
            $where[] = 'p.expires_at IS NOT NULL AND p.expires_at < NOW()';
        } elseif ($status_filter === 'expiring') {
            $where[] = 'p.expires_at IS NOT NULL AND p.expires_at > NOW() AND p.expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY)';
        } elseif ($status_filter === 'active') {
            $where[] = '(p.expires_at IS NULL OR p.expires_at > NOW()) AND p.seats_used < p.seats_total';
        }
        
        $sql = "SELECT p.*, o.name as org_name, t.name as team_name
                FROM {$wpdb->prefix}univga_seat_pools p
                JOIN {$wpdb->prefix}univga_orgs o ON p.org_id = o.id
                LEFT JOIN {$wpdb->prefix}univga_teams t ON p.team_id = t.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.{$orderby} {$order}
                LIMIT {$per_page} OFFSET {$offset}";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        $items = $wpdb->get_results($sql);
        
        // Get total count
        $count_sql = "SELECT COUNT(*)
                     FROM {$wpdb->prefix}univga_seat_pools p
                     JOIN {$wpdb->prefix}univga_orgs o ON p.org_id = o.id
                     WHERE " . implode(' AND ', $where);
        
        if (!empty($values)) {
            $count_sql = $wpdb->prepare($count_sql, $values);
        }
        
        $total_items = $wpdb->get_var($count_sql);
        
        $this->items = $items;
        
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
        ));
        
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }
    
    /**
     * Column default
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'created_at':
                return univga_format_date($item->created_at, true);
            default:
                return isset($item->$column_name) ? $item->$column_name : '';
        }
    }
    
    /**
     * Column checkbox
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="pool[]" value="%d" />', $item->id);
    }
    
    /**
     * Column team name
     */
    public function column_team_name($item) {
        return $item->team_name ?: __('All Teams', UNIVGA_TEXT_DOMAIN);
    }
    
    /**
     * Column scope
     */
    public function column_scope($item) {
        $scope_label = univga_get_scope_type_label($item->scope_type);
        $scope_items = univga_get_scope_items_display($item->scope_type, $item->scope_ids);
        
        return sprintf('%s<br><small>%s</small>', $scope_label, $scope_items);
    }
    
    /**
     * Column seats
     */
    public function column_seats($item) {
        return sprintf('%d / %d', $item->seats_used, $item->seats_total);
    }
    
    /**
     * Column utilization
     */
    public function column_utilization($item) {
        $percentage = $item->seats_total > 0 ? ($item->seats_used / $item->seats_total) * 100 : 0;
        $color = univga_get_utilization_color($percentage);
        
        return sprintf('<span class="badge badge-%s">%s</span>', 
            $color, 
            univga_format_progress($percentage)
        );
    }
    
    /**
     * Column expires at
     */
    public function column_expires_at($item) {
        if (!$item->expires_at) {
            return __('Never', UNIVGA_TEXT_DOMAIN);
        }
        
        $expires_timestamp = strtotime($item->expires_at);
        $now = time();
        
        if ($expires_timestamp < $now) {
            return '<span class="badge badge-danger">' . __('Expired', UNIVGA_TEXT_DOMAIN) . '</span>';
        } elseif ($expires_timestamp < ($now + (7 * 24 * 60 * 60))) {
            return '<span class="badge badge-warning">' . univga_format_date($item->expires_at) . '</span>';
        }
        
        return univga_format_date($item->expires_at);
    }
    
    /**
     * Column auto enroll
     */
    public function column_auto_enroll($item) {
        return $item->auto_enroll ? 
            '<span class="badge badge-success">' . __('Yes', UNIVGA_TEXT_DOMAIN) . '</span>' :
            '<span class="badge badge-secondary">' . __('No', UNIVGA_TEXT_DOMAIN) . '</span>';
    }
    
    /**
     * Extra tablenav
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';
            
            // Organization filter
            echo '<select name="org_filter">';
            echo '<option value="">' . __('All Organizations', UNIVGA_TEXT_DOMAIN) . '</option>';
            echo univga_get_organization_options(isset($_REQUEST['org_filter']) ? intval($_REQUEST['org_filter']) : 0);
            echo '</select>';
            
            // Status filter
            $status_filter = isset($_REQUEST['status_filter']) ? sanitize_text_field($_REQUEST['status_filter']) : '';
            echo '<select name="status_filter">';
            echo '<option value="">' . __('All Pools', UNIVGA_TEXT_DOMAIN) . '</option>';
            echo '<option value="active"' . selected($status_filter, 'active', false) . '>' . __('Active', UNIVGA_TEXT_DOMAIN) . '</option>';
            echo '<option value="expired"' . selected($status_filter, 'expired', false) . '>' . __('Expired', UNIVGA_TEXT_DOMAIN) . '</option>';
            echo '<option value="expiring"' . selected($status_filter, 'expiring', false) . '>' . __('Expiring Soon', UNIVGA_TEXT_DOMAIN) . '</option>';
            echo '</select>';
            
            submit_button(__('Filter', UNIVGA_TEXT_DOMAIN), 'secondary', 'filter', false);
            
            echo '</div>';
        }
    }
}
