<?php

/**
 * WooCommerce product integration
 */
class UNIVGA_Products {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_product_metabox'));
        add_action('save_post', array($this, 'save_product_meta'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
    }
    
    /**
     * Add metabox for product seat configuration
     */
    public function add_product_metabox() {
        add_meta_box(
            'univga-seat-config',
            __('UNIVGA Seat Configuration', UNIVGA_TEXT_DOMAIN),
            array($this, 'render_product_metabox'),
            'product',
            'normal',
            'high'
        );
    }
    
    /**
     * Render product metabox
     */
    public function render_product_metabox($post) {
        wp_nonce_field('univga_product_meta', 'univga_product_meta_nonce');
        
        $scope_type = get_post_meta($post->ID, '_univga_scope_type', true);
        $scope_ids = get_post_meta($post->ID, '_univga_scope_ids', true);
        $seats_qty = get_post_meta($post->ID, '_univga_seats_qty', true);
        $duration_days = get_post_meta($post->ID, '_univga_duration_days', true);
        
        ?>
        <div class="univga-product-config">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="univga_scope_type"><?php _e('Scope Type', UNIVGA_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <select name="univga_scope_type" id="univga_scope_type">
                            <option value=""><?php _e('Select scope type...', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="course" <?php selected($scope_type, 'course'); ?>><?php _e('Specific Courses', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="category" <?php selected($scope_type, 'category'); ?>><?php _e('Course Categories', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="bundle" <?php selected($scope_type, 'bundle'); ?>><?php _e('Course Bundle', UNIVGA_TEXT_DOMAIN); ?></option>
                        </select>
                        <p class="description"><?php _e('Define what courses this seat package covers', UNIVGA_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="univga_scope_ids"><?php _e('Scope Selection', UNIVGA_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <div id="univga_scope_selection">
                            <?php $this->render_scope_selection($scope_type, $scope_ids); ?>
                        </div>
                        <p class="description"><?php _e('Select the courses or categories included in this package', UNIVGA_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="univga_seats_qty"><?php _e('Seats Quantity', UNIVGA_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <input type="number" name="univga_seats_qty" id="univga_seats_qty" 
                               value="<?php echo esc_attr($seats_qty); ?>" min="1" step="1" />
                        <p class="description"><?php _e('Number of seats included in this package', UNIVGA_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="univga_duration_days"><?php _e('Duration (Days)', UNIVGA_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <input type="number" name="univga_duration_days" id="univga_duration_days" 
                               value="<?php echo esc_attr($duration_days); ?>" min="0" step="1" />
                        <p class="description"><?php _e('Access duration in days (0 = unlimited)', UNIVGA_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#univga_scope_type').change(function() {
                var scope_type = $(this).val();
                $.post(ajaxurl, {
                    action: 'univga_get_scope_selection',
                    scope_type: scope_type,
                    nonce: '<?php echo wp_create_nonce('univga_scope_selection'); ?>'
                }, function(response) {
                    $('#univga_scope_selection').html(response);
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render scope selection based on type
     */
    private function render_scope_selection($scope_type, $scope_ids) {
        if (!$scope_ids) {
            $scope_ids = array();
        }
        
        switch ($scope_type) {
            case 'course':
                $courses = get_posts(array(
                    'post_type' => tutor()->course_post_type,
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                ));
                
                echo '<select name="univga_scope_ids[]" multiple style="width: 100%; height: 200px;">';
                foreach ($courses as $course) {
                    $selected = in_array($course->ID, $scope_ids) ? 'selected' : '';
                    echo '<option value="' . $course->ID . '" ' . $selected . '>' . esc_html($course->post_title) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'category':
                $categories = get_terms(array(
                    'taxonomy' => 'course-category',
                    'hide_empty' => false,
                ));
                
                echo '<select name="univga_scope_ids[]" multiple style="width: 100%; height: 200px;">';
                foreach ($categories as $category) {
                    $selected = in_array($category->term_id, $scope_ids) ? 'selected' : '';
                    echo '<option value="' . $category->term_id . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'bundle':
                $courses = get_posts(array(
                    'post_type' => tutor()->course_post_type,
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                ));
                
                echo '<select name="univga_scope_ids[]" multiple style="width: 100%; height: 200px;">';
                foreach ($courses as $course) {
                    $selected = in_array($course->ID, $scope_ids) ? 'selected' : '';
                    echo '<option value="' . $course->ID . '" ' . $selected . '>' . esc_html($course->post_title) . '</option>';
                }
                echo '</select>';
                break;
                
            default:
                echo '<p>' . __('Please select a scope type first.', UNIVGA_TEXT_DOMAIN) . '</p>';
        }
    }
    
    /**
     * Save product meta
     */
    public function save_product_meta($post_id) {
        if (!isset($_POST['univga_product_meta_nonce']) || 
            !wp_verify_nonce($_POST['univga_product_meta_nonce'], 'univga_product_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save scope type
        if (isset($_POST['univga_scope_type'])) {
            update_post_meta($post_id, '_univga_scope_type', sanitize_text_field($_POST['univga_scope_type']));
        }
        
        // Save scope IDs
        if (isset($_POST['univga_scope_ids'])) {
            $scope_ids = array_map('intval', $_POST['univga_scope_ids']);
            update_post_meta($post_id, '_univga_scope_ids', $scope_ids);
        } else {
            delete_post_meta($post_id, '_univga_scope_ids');
        }
        
        // Save seats quantity
        if (isset($_POST['univga_seats_qty'])) {
            update_post_meta($post_id, '_univga_seats_qty', intval($_POST['univga_seats_qty']));
        }
        
        // Save duration
        if (isset($_POST['univga_duration_days'])) {
            update_post_meta($post_id, '_univga_duration_days', intval($_POST['univga_duration_days']));
        }
    }
    
    /**
     * Add fields to WooCommerce product data panel
     */
    public function add_product_fields() {
        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox(array(
            'id' => '_univga_is_seat_product',
            'label' => __('UNIVGA Seat Product', UNIVGA_TEXT_DOMAIN),
            'description' => __('Check this if this product provides organization seats', UNIVGA_TEXT_DOMAIN),
        ));
        
        echo '</div>';
    }
    
    /**
     * Save WooCommerce product fields
     */
    public function save_product_fields($post_id) {
        $is_seat_product = isset($_POST['_univga_is_seat_product']) ? 'yes' : 'no';
        update_post_meta($post_id, '_univga_is_seat_product', $is_seat_product);
    }
    
    /**
     * Check if product is a seat product
     */
    public static function is_seat_product($product_id) {
        return get_post_meta($product_id, '_univga_is_seat_product', true) === 'yes' ||
               get_post_meta($product_id, '_univga_scope_type', true);
    }
    
    /**
     * Get product seat configuration
     */
    public static function get_product_config($product_id) {
        return array(
            'scope_type' => get_post_meta($product_id, '_univga_scope_type', true),
            'scope_ids' => get_post_meta($product_id, '_univga_scope_ids', true),
            'seats_qty' => get_post_meta($product_id, '_univga_seats_qty', true),
            'duration_days' => get_post_meta($product_id, '_univga_duration_days', true),
        );
    }
}

// AJAX handler for scope selection
add_action('wp_ajax_univga_get_scope_selection', function() {
    if (!wp_verify_nonce($_POST['nonce'], 'univga_scope_selection')) {
        wp_die('Security check failed');
    }
    
    $scope_type = sanitize_text_field($_POST['scope_type']);
    $products = new UNIVGA_Products();
    $products->render_scope_selection($scope_type, array());
    wp_die();
});
