<?php

/**
 * Helper functions for UNIVGA plugin
 */

/**
 * Get organization select options
 */
function univga_get_organization_options($selected = 0) {
    $organizations = UNIVGA_Orgs::get_all(array('status' => 1));
    $options = '<option value="">' . __('Select Organization...', UNIVGA_TEXT_DOMAIN) . '</option>';
    
    foreach ($organizations as $org) {
        $selected_attr = selected($selected, $org->id, false);
        $options .= '<option value="' . $org->id . '" ' . $selected_attr . '>' . esc_html($org->name) . '</option>';
    }
    
    return $options;
}

/**
 * Get team select options for organization
 */
function univga_get_team_options($org_id, $selected = 0) {
    if (!$org_id) {
        return '<option value="">' . __('Select Team...', UNIVGA_TEXT_DOMAIN) . '</option>';
    }
    
    $teams = UNIVGA_Teams::get_by_org($org_id);
    $options = '<option value="">' . __('No Team', UNIVGA_TEXT_DOMAIN) . '</option>';
    
    foreach ($teams as $team) {
        $selected_attr = selected($selected, $team->id, false);
        $options .= '<option value="' . $team->id . '" ' . $selected_attr . '>' . esc_html($team->name) . '</option>';
    }
    
    return $options;
}

/**
 * Get course select options
 */
function univga_get_course_options($selected = array()) {
    $courses = get_posts(array(
        'post_type' => tutor()->course_post_type,
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
    ));
    
    $options = '';
    
    foreach ($courses as $course) {
        $selected_attr = in_array($course->ID, (array)$selected) ? 'selected' : '';
        $options .= '<option value="' . $course->ID . '" ' . $selected_attr . '>' . esc_html($course->post_title) . '</option>';
    }
    
    return $options;
}

/**
 * Get course category select options
 */
function univga_get_course_category_options($selected = array()) {
    $categories = get_terms(array(
        'taxonomy' => 'course-category',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ));
    
    $options = '';
    
    foreach ($categories as $category) {
        $selected_attr = in_array($category->term_id, (array)$selected) ? 'selected' : '';
        $options .= '<option value="' . $category->term_id . '" ' . $selected_attr . '>' . esc_html($category->name) . '</option>';
    }
    
    return $options;
}

/**
 * Format date for display
 */
function univga_format_date($date, $include_time = false) {
    if (!$date) {
        return __('Never', UNIVGA_TEXT_DOMAIN);
    }
    
    $format = $include_time ? get_option('date_format') . ' ' . get_option('time_format') : get_option('date_format');
    return date_i18n($format, strtotime($date));
}

/**
 * Format progress percentage
 */
function univga_format_progress($progress) {
    return number_format($progress, 1) . '%';
}

/**
 * Get status badge HTML
 */
function univga_get_status_badge($status, $type = 'member') {
    $badges = array(
        'member' => array(
            'active' => '<span class="badge badge-success">' . __('Active', UNIVGA_TEXT_DOMAIN) . '</span>',
            'invited' => '<span class="badge badge-warning">' . __('Invited', UNIVGA_TEXT_DOMAIN) . '</span>',
            'removed' => '<span class="badge badge-danger">' . __('Removed', UNIVGA_TEXT_DOMAIN) . '</span>',
        ),
        'org' => array(
            '1' => '<span class="badge badge-success">' . __('Active', UNIVGA_TEXT_DOMAIN) . '</span>',
            '0' => '<span class="badge badge-secondary">' . __('Inactive', UNIVGA_TEXT_DOMAIN) . '</span>',
        ),
    );
    
    return isset($badges[$type][$status]) ? $badges[$type][$status] : '<span class="badge badge-secondary">' . esc_html($status) . '</span>';
}

/**
 * Get scope type label
 */
function univga_get_scope_type_label($scope_type) {
    $labels = array(
        'course' => __('Specific Courses', UNIVGA_TEXT_DOMAIN),
        'category' => __('Course Categories', UNIVGA_TEXT_DOMAIN),
        'bundle' => __('Course Bundle', UNIVGA_TEXT_DOMAIN),
    );
    
    return isset($labels[$scope_type]) ? $labels[$scope_type] : esc_html($scope_type);
}

/**
 * Get scope items display
 */
function univga_get_scope_items_display($scope_type, $scope_ids) {
    if (!$scope_ids) {
        return __('None selected', UNIVGA_TEXT_DOMAIN);
    }
    
    $ids = is_string($scope_ids) ? json_decode($scope_ids, true) : $scope_ids;
    if (!is_array($ids)) {
        return __('Invalid scope', UNIVGA_TEXT_DOMAIN);
    }
    
    $items = array();
    
    switch ($scope_type) {
        case 'course':
        case 'bundle':
            foreach ($ids as $id) {
                $course = get_post($id);
                if ($course) {
                    $items[] = $course->post_title;
                }
            }
            break;
            
        case 'category':
            foreach ($ids as $id) {
                $category = get_term($id, 'course-category');
                if ($category && !is_wp_error($category)) {
                    $items[] = $category->name;
                }
            }
            break;
    }
    
    if (empty($items)) {
        return __('No valid items', UNIVGA_TEXT_DOMAIN);
    }
    
    if (count($items) > 3) {
        return implode(', ', array_slice($items, 0, 3)) . sprintf(__(' and %d more', UNIVGA_TEXT_DOMAIN), count($items) - 3);
    }
    
    return implode(', ', $items);
}

/**
 * Check if user is organization manager
 */
function univga_is_org_manager($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Check WordPress capabilities first
    if (user_can($user, 'univga_org_manage') || user_can($user, 'univga_admin_access') || user_can($user, 'manage_options')) {
        return true;
    }
    
    // Fallback: Check if user is contact/manager of any organization
    global $wpdb;
    $org_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}univga_orgs WHERE contact_user_id = %d AND status = 1",
        $user_id
    ));
    
    return $org_count > 0;
}

/**
 * Get current user's organization
 */
function univga_get_current_user_org() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return null;
    }
    
    return UNIVGA_Members::get_user_org_membership($user_id);
}

/**
 * Sanitize array of integers
 */
function univga_sanitize_int_array($array) {
    if (!is_array($array)) {
        return array();
    }
    
    return array_map('intval', array_filter($array, function($value) {
        return is_numeric($value) && $value > 0;
    }));
}

/**
 * Generate nonce field
 */
function univga_nonce_field($action, $name = '_wpnonce') {
    return wp_nonce_field($action, $name, true, false);
}

/**
 * Verify nonce
 */
function univga_verify_nonce($nonce, $action) {
    return wp_verify_nonce($nonce, $action);
}

/**
 * Get admin page URL
 */
function univga_get_admin_url($page, $args = array()) {
    $url = admin_url('admin.php?page=' . $page);
    
    if (!empty($args)) {
        $url = add_query_arg($args, $url);
    }
    
    return $url;
}

/**
 * Display admin notice
 */
function univga_display_admin_notice($message, $type = 'success') {
    $class = 'notice notice-' . $type . ' is-dismissible';
    echo '<div class="' . $class . '"><p>' . esc_html($message) . '</p></div>';
}

/**
 * Get utilization color class
 */
function univga_get_utilization_color($percentage) {
    if ($percentage >= 90) {
        return 'danger';
    } elseif ($percentage >= 75) {
        return 'warning';
    } elseif ($percentage >= 50) {
        return 'info';
    }
    
    return 'success';
}

/**
 * Time ago helper
 */
function univga_time_ago($date) {
    if (!$date) {
        return __('Never', UNIVGA_TEXT_DOMAIN);
    }
    
    return sprintf(__('%s ago', UNIVGA_TEXT_DOMAIN), human_time_diff(strtotime($date)));
}

/**
 * Get dashboard page URL
 */
function univga_get_dashboard_url() {
    $page_id = get_option('univga_dashboard_page_id');
    
    if ($page_id) {
        return get_permalink($page_id);
    }
    
    return home_url();
}

/**
 * Log debug message
 */
function univga_log($message, $level = 'info') {
    if (get_option('univga_debug_mode', 0)) {
        error_log("UNIVGA [{$level}]: " . $message);
    }
}
