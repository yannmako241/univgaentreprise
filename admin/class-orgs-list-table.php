<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Organizations list table
 */
class UNIVGA_Orgs_List_Table extends WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'organization',
            'plural' => 'organizations',
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
            'legal_id' => __('Legal ID', UNIVGA_TEXT_DOMAIN),
            'contact' => __('Contact', UNIVGA_TEXT_DOMAIN),
            'email_domain' => __('Email Domain', UNIVGA_TEXT_DOMAIN),
            'members' => __('Members', UNIVGA_TEXT_DOMAIN),
            'teams' => __('Teams', UNIVGA_TEXT_DOMAIN),
            'status' => __('Status', UNIVGA_TEXT_DOMAIN),
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
            'status' => array('status', false),
        );
    }
    
    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return array(
            'activate' => __('Activate', UNIVGA_TEXT_DOMAIN),
            'deactivate' => __('Deactivate', UNIVGA_TEXT_DOMAIN),
        );
    }
    
    /**
     * Prepare items
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'name';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'ASC';
        
        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => $orderby,
            'order' => $order,
        );
        
        $items = UNIVGA_Orgs::get_all($args);
        $total_items = UNIVGA_Orgs::get_count();
        
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
            case 'legal_id':
                return $item->legal_id ?: '—';
            case 'email_domain':
                return $item->email_domain ?: '—';
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
        return sprintf('<input type="checkbox" name="organization[]" value="%d" />', $item->id);
    }
    
    /**
     * Column name
     */
    public function column_name($item) {
        $edit_url = add_query_arg(array(
            'action' => 'edit',
            'org_id' => $item->id,
        ));
        
        $delete_url = wp_nonce_url(
            add_query_arg(array(
                'action' => 'delete_org',
                'org_id' => $item->id,
            )),
            'delete_org_' . $item->id
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
     * Column contact
     */
    public function column_contact($item) {
        if (!$item->contact_user_id) {
            return '—';
        }
        
        $user = get_userdata($item->contact_user_id);
        return $user ? $user->display_name . '<br><small>' . $user->user_email . '</small>' : '—';
    }
    
    /**
     * Column members
     */
    public function column_members($item) {
        $count = UNIVGA_Members::get_org_members_count($item->id, array('status' => 'active'));
        return $count;
    }
    
    /**
     * Column teams
     */
    public function column_teams($item) {
        $count = UNIVGA_Teams::get_count_by_org($item->id);
        return $count;
    }
    
    /**
     * Column status
     */
    public function column_status($item) {
        return univga_get_status_badge($item->status, 'org');
    }
}
