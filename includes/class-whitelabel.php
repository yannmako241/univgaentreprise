<?php

/**
 * UNIVGA White-Label Class
 * White-Label Customization Suite for branding
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_WhiteLabel {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_univga_save_branding', array($this, 'save_branding'));
        add_action('wp_ajax_univga_get_branding', array($this, 'get_branding'));
        add_action('wp_ajax_univga_upload_logo', array($this, 'upload_logo'));
        add_action('wp_ajax_univga_preview_branding', array($this, 'preview_branding'));
        
        // Apply branding to frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_custom_styles'));
        add_action('wp_head', array($this, 'output_custom_css'));
        
        // Email branding
        add_filter('univga_email_template', array($this, 'apply_email_branding'), 10, 2);
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
        
        // Dashboard branding
        add_action('univga_dashboard_header', array($this, 'output_branded_header'));
        
        // Custom domain handling
        add_action('init', array($this, 'handle_custom_domain'));
    }
    
    /**
     * Save branding settings
     */
    public function save_branding() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $org_id = intval($_POST['org_id']);
        $branding_data = array(
            'logo_url' => esc_url($_POST['logo_url'] ?? ''),
            'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '#0073aa'),
            'secondary_color' => sanitize_hex_color($_POST['secondary_color'] ?? '#005177'),
            'slogan' => sanitize_text_field($_POST['slogan'] ?? ''),
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'custom_css' => wp_strip_all_tags($_POST['custom_css'] ?? ''),
            'email_header' => esc_url($_POST['email_header'] ?? ''),
            'custom_domain' => sanitize_text_field($_POST['custom_domain'] ?? ''),
            'settings' => json_encode($_POST['settings'] ?? array()),
            'updated_at' => current_time('mysql')
        );
        
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}univga_branding WHERE org_id = %d
        ", $org_id));
        
        if ($existing > 0) {
            $result = $wpdb->update(
                $wpdb->prefix . 'univga_branding',
                $branding_data,
                array('org_id' => $org_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $branding_data['org_id'] = $org_id;
            $branding_data['created_at'] = current_time('mysql');
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'univga_branding',
                $branding_data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Branding settings saved successfully'));
        } else {
            wp_send_json_error('Failed to save branding settings');
        }
    }
    
    /**
     * Get branding settings
     */
    public function get_branding() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        global $wpdb;
        
        $branding = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_branding WHERE org_id = %d
        ", $org_id));
        
        if ($branding) {
            $branding->settings = json_decode($branding->settings, true);
        } else {
            // Return default branding
            $branding = (object) array(
                'org_id' => $org_id,
                'logo_url' => '',
                'primary_color' => '#0073aa',
                'secondary_color' => '#005177',
                'slogan' => '',
                'company_name' => '',
                'custom_css' => '',
                'email_header' => '',
                'custom_domain' => '',
                'settings' => array()
            );
        }
        
        wp_send_json_success($branding);
    }
    
    /**
     * Upload logo
     */
    public function upload_logo() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!isset($_FILES['logo'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['logo'];
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/svg+xml');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Invalid file type. Please upload JPG, PNG, GIF, or SVG files only.');
        }
        
        // Validate file size (max 2MB)
        if ($file['size'] > 2097152) {
            wp_send_json_error('File too large. Maximum size is 2MB.');
        }
        
        $upload_dir = wp_upload_dir();
        $brand_dir = $upload_dir['basedir'] . '/univga-branding/';
        
        if (!file_exists($brand_dir)) {
            wp_mkdir_p($brand_dir);
        }
        
        $org_id = intval($_POST['org_id']);
        $filename = 'logo-' . $org_id . '-' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filepath = $brand_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $logo_url = $upload_dir['baseurl'] . '/univga-branding/' . $filename;
            
            wp_send_json_success(array(
                'logo_url' => $logo_url,
                'message' => 'Logo uploaded successfully'
            ));
        } else {
            wp_send_json_error('Failed to upload logo');
        }
    }
    
    /**
     * Get branding for organization
     */
    public function get_org_branding($org_id) {
        global $wpdb;
        
        $branding = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_branding WHERE org_id = %d
        ", $org_id));
        
        if ($branding) {
            $branding->settings = json_decode($branding->settings, true);
        }
        
        return $branding;
    }
    
    /**
     * Enqueue custom styles
     */
    public function enqueue_custom_styles() {
        // Only enqueue on UNIVGA pages
        if (!$this->is_univga_page()) {
            return;
        }
        
        $org_id = $this->get_current_org_id();
        if (!$org_id) {
            return;
        }
        
        $branding = $this->get_org_branding($org_id);
        if (!$branding) {
            return;
        }
        
        // Generate dynamic CSS
        $css = $this->generate_branding_css($branding);
        
        if ($css) {
            wp_add_inline_style('univga-dashboard', $css);
        }
    }
    
    /**
     * Output custom CSS in head
     */
    public function output_custom_css() {
        if (!$this->is_univga_page()) {
            return;
        }
        
        $org_id = $this->get_current_org_id();
        if (!$org_id) {
            return;
        }
        
        $branding = $this->get_org_branding($org_id);
        if (!$branding || empty($branding->custom_css)) {
            return;
        }
        
        echo '<style type="text/css">' . "\n";
        echo wp_strip_all_tags($branding->custom_css);
        echo "\n" . '</style>' . "\n";
    }
    
    /**
     * Generate branding CSS
     */
    private function generate_branding_css($branding) {
        $css = '';
        
        // Primary color
        if ($branding->primary_color) {
            $css .= "
                .univga-primary { color: {$branding->primary_color} !important; }
                .univga-bg-primary { background-color: {$branding->primary_color} !important; }
                .univga-btn-primary { 
                    background-color: {$branding->primary_color} !important; 
                    border-color: {$branding->primary_color} !important; 
                }
                .univga-btn-primary:hover { 
                    background-color: " . $this->darken_color($branding->primary_color, 20) . " !important; 
                    border-color: " . $this->darken_color($branding->primary_color, 20) . " !important; 
                }
            ";
        }
        
        // Secondary color
        if ($branding->secondary_color) {
            $css .= "
                .univga-secondary { color: {$branding->secondary_color} !important; }
                .univga-bg-secondary { background-color: {$branding->secondary_color} !important; }
                .univga-btn-secondary { 
                    background-color: {$branding->secondary_color} !important; 
                    border-color: {$branding->secondary_color} !important; 
                }
            ";
        }
        
        // Logo
        if ($branding->logo_url) {
            $css .= "
                .univga-logo { 
                    background-image: url('{$branding->logo_url}') !important; 
                    background-size: contain;
                    background-repeat: no-repeat;
                }
            ";
        }
        
        // Company branding styles
        $css .= "
            .univga-company-name { 
                color: {$branding->primary_color} !important; 
                font-weight: bold;
                font-size: 1.5em;
                margin-bottom: 5px;
            }
            .univga-slogan { 
                color: {$branding->secondary_color} !important; 
                font-style: italic;
                font-size: 0.95em;
                margin-bottom: 15px;
            }
            .univga-branded-header {
                text-align: center;
                padding: 20px;
                border-bottom: 3px solid {$branding->primary_color};
                margin-bottom: 20px;
            }
            .univga-logo-img {
                max-height: 80px;
                max-width: 200px;
                margin-bottom: 10px;
            }
        ";
        
        return $css;
    }
    
    /**
     * Darken color utility
     */
    private function darken_color($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Apply email branding
     */
    public function apply_email_branding($content, $org_id) {
        $branding = $this->get_org_branding($org_id);
        
        if (!$branding) {
            return $content;
        }
        
        $header_html = '';
        if ($branding->email_header) {
            $header_html = '<div style="text-align: center; padding: 20px;">';
            $header_html .= '<img src="' . esc_url($branding->email_header) . '" alt="Logo" style="max-width: 300px; height: auto;">';
            $header_html .= '</div>';
        }
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f4f4f4; 
                }
                .email-container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background-color: #ffffff; 
                    padding: 0; 
                }
                .email-header { 
                    background-color: ' . $branding->primary_color . '; 
                    color: #ffffff; 
                    padding: 20px; 
                    text-align: center; 
                }
                .email-body { 
                    padding: 30px; 
                }
                .email-footer { 
                    background-color: #f8f8f8; 
                    padding: 20px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #666; 
                }
                .btn { 
                    display: inline-block; 
                    padding: 10px 20px; 
                    background-color: ' . $branding->primary_color . '; 
                    color: #ffffff; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 10px 0; 
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                ' . $header_html . '
                <div class="email-body">
                    ' . $content . '
                </div>
                <div class="email-footer">
                    <p>This email was sent by ' . get_bloginfo('name') . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Set HTML content type for emails
     */
    public function set_html_content_type($content_type) {
        return 'text/html';
    }
    
    /**
     * Output branded header
     */
    public function output_branded_header() {
        $org_id = $this->get_current_org_id();
        if (!$org_id) {
            return;
        }
        
        $branding = $this->get_org_branding($org_id);
        if (!$branding) {
            return;
        }
        
        echo '<div class="univga-branded-header">';
        
        if ($branding->logo_url) {
            echo '<div class="univga-logo-container">';
            echo '<img src="' . esc_url($branding->logo_url) . '" alt="Logo" class="univga-logo-img">';
            echo '</div>';
        }
        
        if (!empty($branding->company_name)) {
            echo '<div class="univga-company-name">' . esc_html($branding->company_name) . '</div>';
        }
        
        if (!empty($branding->slogan)) {
            echo '<div class="univga-slogan">' . esc_html($branding->slogan) . '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Handle custom domain
     */
    public function handle_custom_domain() {
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($current_domain)) {
            return;
        }
        
        global $wpdb;
        
        // Check if this is a custom domain
        $org_id = $wpdb->get_var($wpdb->prepare("
            SELECT org_id FROM {$wpdb->prefix}univga_branding 
            WHERE custom_domain = %s
        ", $current_domain));
        
        if ($org_id) {
            // Set organization context for this domain
            define('UNIVGA_CUSTOM_DOMAIN_ORG', $org_id);
            
            // Redirect to branded dashboard if needed
            if (is_home() || is_front_page()) {
                wp_redirect(admin_url('admin.php?page=univga-dashboard&org=' . $org_id));
                exit;
            }
        }
    }
    
    /**
     * Check if current page is UNIVGA page
     */
    private function is_univga_page() {
        // Check if we're on UNIVGA admin pages or shortcode pages
        if (is_admin()) {
            return isset($_GET['page']) && strpos($_GET['page'], 'univga') === 0;
        }
        
        // Check for shortcode content
        global $post;
        return $post && (
            has_shortcode($post->post_content, 'univga_dashboard') ||
            has_shortcode($post->post_content, 'univga_join') ||
            strpos($post->post_content, 'univga') !== false
        );
    }
    
    /**
     * Get current organization ID
     */
    private function get_current_org_id() {
        // Check for custom domain org
        if (defined('UNIVGA_CUSTOM_DOMAIN_ORG')) {
            return UNIVGA_CUSTOM_DOMAIN_ORG;
        }
        
        // Check URL parameter
        if (isset($_GET['org'])) {
            return intval($_GET['org']);
        }
        
        // Check user's primary organization
        $user_id = get_current_user_id();
        if ($user_id) {
            global $wpdb;
            $org_id = $wpdb->get_var($wpdb->prepare("
                SELECT org_id FROM {$wpdb->prefix}univga_org_members 
                WHERE user_id = %d AND status = 'active' 
                ORDER BY joined_at ASC 
                LIMIT 1
            ", $user_id));
            
            return $org_id;
        }
        
        return null;
    }
    
    /**
     * Generate branding preview
     */
    public function preview_branding() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $branding_data = $_POST['branding'];
        
        $preview_html = $this->generate_preview_html($branding_data);
        
        wp_send_json_success(array('preview_html' => $preview_html));
    }
    
    /**
     * Generate preview HTML
     */
    private function generate_preview_html($branding) {
        $html = '<div class="branding-preview" style="
            padding: 20px; 
            border: 1px solid #ddd; 
            border-radius: 8px;
            background: white;
            font-family: Arial, sans-serif;
        ">';
        
        // Logo preview
        if (!empty($branding['logo_url'])) {
            $html .= '<div style="text-align: center; margin-bottom: 20px;">';
            $html .= '<img src="' . esc_url($branding['logo_url']) . '" alt="Logo" style="max-height: 60px; max-width: 200px;">';
            $html .= '</div>';
        }
        
        // Color preview
        $primary = $branding['primary_color'] ?? '#0073aa';
        $secondary = $branding['secondary_color'] ?? '#005177';
        
        $html .= '<div style="display: flex; gap: 15px; margin-bottom: 20px;">';
        $html .= '<div style="flex: 1;">';
        $html .= '<button style="
            width: 100%; 
            padding: 10px 15px; 
            background: ' . $primary . '; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 14px;
        ">Primary Button</button>';
        $html .= '</div>';
        $html .= '<div style="flex: 1;">';
        $html .= '<button style="
            width: 100%; 
            padding: 10px 15px; 
            background: ' . $secondary . '; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 14px;
        ">Secondary Button</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Company name and slogan preview
        if (!empty($branding['company_name'])) {
            $html .= '<div style="text-align: center; margin-bottom: 10px;">';
            $html .= '<h2 style="color: ' . $primary . '; margin: 0; font-size: 24px;">' . esc_html($branding['company_name']) . '</h2>';
            $html .= '</div>';
        }
        
        if (!empty($branding['slogan'])) {
            $html .= '<div style="text-align: center; margin-bottom: 20px;">';
            $html .= '<em style="color: ' . $secondary . '; font-size: 16px;">' . esc_html($branding['slogan']) . '</em>';
            $html .= '</div>';
        }

        // Sample content with branding
        $html .= '<div style="border-top: 3px solid ' . $primary . '; padding-top: 15px;">';
        $html .= '<h3 style="color: ' . $primary . '; margin-bottom: 10px;">Dashboard Preview</h3>';
        $html .= '<p style="margin-bottom: 15px;">This shows how your branding will look in the learning dashboard.</p>';
        $html .= '<div style="padding: 10px; background: ' . $secondary . '20; border-left: 4px solid ' . $secondary . ';">';
        $html .= '<strong style="color: ' . $secondary . ';">Course Progress:</strong> 75% Complete';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
}