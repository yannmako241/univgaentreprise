<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Teams list table
 */
class UNIVGA_Teams_List_Table extends WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'team',
            'plural' => 'teams',
            'ajax' => false,
        ));
    }
    
    /**
     * Get columns
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'name' => __('Name', UNIVGA_TEXT_DOMAIN),
            'organization' => __('Organization', UNIVGA_TEXT_DOMAIN),
            'manager' => __('Manager', UNIVGA_TEXT_DOMAIN),
            'members' => __('Members', UNIVGA_TEXT_DOMAIN),
            'created_at' => __('Created', UNIVGA_TEXT_DOMAIN),
        );
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'name' => array('name', false),
            'created_at' => array('created_at', true),
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
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'name';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'ASC';
        
        $where = array('1=1');
        $values = array();
        
        if ($search) {
            $where[] = '(t.name LIKE %s OR o.name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        $sql = "SELECT t.*, o.name as org_name, u.display_name as manager_name
                FROM {$wpdb->prefix}univga_teams t
                JOIN {$wpdb->prefix}univga_orgs o ON t.org_id = o.id
                LEFT JOIN {$wpdb->users} u ON t.manager_user_id = u.ID
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.{$orderby} {$order}
                LIMIT {$per_page} OFFSET {$offset}";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        $items = $wpdb->get_results($sql);
        
        // Get total count
        $count_sql = "SELECT COUNT(*)
                     FROM {$wpdb->prefix}univga_teams t
                     JOIN {$wpdb->prefix}univga_orgs o ON t.org_id = o.id
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
        return sprintf('<input type="checkbox" name="team[]" value="%d" />', $item->id);
    }
    
    /**
     * Column name
     */
    public function column_name($item) {
        $edit_url = add_query_arg(array(
            'action' => 'edit',
            'team_id' => $item->id,
        ));
        
        $delete_url = wp_nonce_url(
            add_query_arg(array(
                'action' => 'delete_team',
                'team_id' => $item->id,
            )),
            'delete_team_' . $item->id
        );
        
        $actions = array(
            'edit' => '<a href="' . $edit_url . '">' . __('Edit', UNIVGA_TEXT_DOMAIN) . '</a>',
            'delete' => '<a href="' . $delete_url . '" onclick="return confirm(\'' . __('Are you sure?', UNIVGA_TEXT_DOMAIN) . '\')">' . __('Delete', UNIVGA_TEXT_DOMAIN) . '</a>',
        );
        
        return sprintf('%1$s %2$s', 
            '<strong>' . esc_html($item->name) . '</strong>',
            $this->row_actions($actions)
        );
    }
    
    /**
     * Column organization
     */
    public function column_organization($item) {
        return esc_html($item->org_name);
    }
    
    /**
     * Column manager
     */
    public function column_manager($item) {
        return $item->manager_name ?: '—';
    }
    
    /**
     * Column members
     */
    public function column_members($item) {
        $count = UNIVGA_Members::get_org_members_count($item->org_id, array(
            'team_id' => $item->id,
            'status' => 'active',
        ));
        return $count;
    }
}
