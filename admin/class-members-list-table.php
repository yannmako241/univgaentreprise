<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Members list table
 */
class UNIVGA_Members_List_Table extends WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'member',
            'plural' => 'members',
            'ajax' => false,
        ));
    }
    
    /**
     * Get columns
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'display_name' => __('Name', UNIVGA_TEXT_DOMAIN),
            'user_email' => __('Email', UNIVGA_TEXT_DOMAIN),
            'org_name' => __('Organization', UNIVGA_TEXT_DOMAIN),
            'team_name' => __('Team', UNIVGA_TEXT_DOMAIN),
            'status' => __('Status', UNIVGA_TEXT_DOMAIN),
            'enrolled_courses' => __('Courses', UNIVGA_TEXT_DOMAIN),
            'avg_progress' => __('Avg Progress', UNIVGA_TEXT_DOMAIN),
            'joined_at' => __('Joined', UNIVGA_TEXT_DOMAIN),
        );
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'display_name' => array('display_name', false),
            'user_email' => array('user_email', false),
            'joined_at' => array('joined_at', true),
            'status' => array('status', false),
        );
    }
    
    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return array(
            'remove' => __('Remove from Organization', UNIVGA_TEXT_DOMAIN),
            'activate' => __('Activate', UNIVGA_TEXT_DOMAIN),
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
        
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'display_name';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'ASC';
        
        $where = array('1=1');
        $values = array();
        
        if ($search) {
            $where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        if ($org_filter) {
            $where[] = 'm.org_id = %d';
            $values[] = $org_filter;
        }
        
        if ($status_filter) {
            $where[] = 'm.status = %s';
            $values[] = $status_filter;
        }
        
        $sql = "SELECT m.*, u.display_name, u.user_email, u.user_registered,
                       o.name as org_name, t.name as team_name
                FROM {$wpdb->prefix}univga_org_members m
                JOIN {$wpdb->users} u ON m.user_id = u.ID
                JOIN {$wpdb->prefix}univga_orgs o ON m.org_id = o.id
                LEFT JOIN {$wpdb->prefix}univga_teams t ON m.team_id = t.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY u.{$orderby} {$order}
                LIMIT {$per_page} OFFSET {$offset}";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        $items = $wpdb->get_results($sql);
        
        // Enrich with course data
        foreach ($items as &$item) {
            $pools = UNIVGA_Seat_Pools::get_by_org($item->org_id, array('team_id' => $item->team_id));
            $all_courses = array();
            
            foreach ($pools as $pool) {
                $pool_courses = UNIVGA_Seat_Pools::get_pool_courses($pool);
                $all_courses = array_merge($all_courses, $pool_courses);
            }
            
            $all_courses = array_unique($all_courses);
            $course_details = UNIVGA_Tutor::get_member_course_details($item->user_id, $all_courses);
            
            $item->enrolled_courses = count(array_filter($course_details, function($c) { return $c['enrolled']; }));
            
            $enrolled_courses = array_filter($course_details, function($c) { return $c['enrolled']; });
            $total_progress = array_sum(array_column($enrolled_courses, 'progress'));
            $item->avg_progress = count($enrolled_courses) > 0 ? $total_progress / count($enrolled_courses) : 0;
        }
        
        // Get total count
        $count_sql = "SELECT COUNT(*)
                     FROM {$wpdb->prefix}univga_org_members m
                     JOIN {$wpdb->users} u ON m.user_id = u.ID
                     JOIN {$wpdb->prefix}univga_orgs o ON m.org_id = o.id
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
            case 'joined_at':
                return univga_format_date($item->joined_at, true);
            default:
                return isset($item->$column_name) ? $item->$column_name : '';
        }
    }
    
    /**
     * Column checkbox
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="member[]" value="%d" />', $item->id);
    }
    
    /**
     * Column display name
     */
    public function column_display_name($item) {
        $user_url = get_edit_user_link($item->user_id);
        
        $actions = array(
            'view' => '<a href="' . $user_url . '">' . __('View User', UNIVGA_TEXT_DOMAIN) . '</a>',
        );
        
        if ($item->status === 'active') {
            $remove_url = wp_nonce_url(
                add_query_arg(array(
                    'action' => 'remove_member',
                    'member_id' => $item->id,
                )),
                'remove_member_' . $item->id
            );
            
            $actions['remove'] = '<a href="' . $remove_url . '" onclick="return confirm(\'' . __('Remove this member?', UNIVGA_TEXT_DOMAIN) . '\')">' . __('Remove', UNIVGA_TEXT_DOMAIN) . '</a>';
        }
        
        return sprintf('%1$s %2$s', 
            '<strong>' . esc_html($item->display_name) . '</strong>',
            $this->row_actions($actions)
        );
    }
    
    /**
     * Column team name
     */
    public function column_team_name($item) {
        return $item->team_name ?: __('No Team', UNIVGA_TEXT_DOMAIN);
    }
    
    /**
     * Column status
     */
    public function column_status($item) {
        return univga_get_status_badge($item->status, 'member');
    }
    
    /**
     * Column average progress
     */
    public function column_avg_progress($item) {
        return univga_format_progress($item->avg_progress);
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
            echo '<option value="">' . __('All Statuses', UNIVGA_TEXT_DOMAIN) . '</option>';
            echo '<option value="active"' . selected($status_filter, 'active', false) . '>' . __('Active', UNIVGA_TEXT_DOMAIN) . '</option>';
            echo '<option value="invited"' . selected($status_filter, 'invited', false) . '>' . __('Invited', UNIVGA_TEXT_DOMAIN) . '</option>';
            echo '<option value="removed"' . selected($status_filter, 'removed', false) . '>' . __('Removed', UNIVGA_TEXT_DOMAIN) . '</option>';
            echo '</select>';
            
            submit_button(__('Filter', UNIVGA_TEXT_DOMAIN), 'secondary', 'filter', false);
            
            echo '</div>';
        }
    }
}
