<?php

/**
 * Shortcodes handler
 */
class UNIVGA_Shortcodes {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('univga_org_dashboard', array($this, 'org_dashboard_shortcode'));
        add_shortcode('univga_team_dashboard', array($this, 'team_dashboard_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if (is_singular() && has_shortcode(get_post()->post_content, 'univga_org_dashboard')) {
            wp_enqueue_style('univga-dashboard', UNIVGA_PLUGIN_URL . 'public/css/dashboard.css', array(), UNIVGA_PLUGIN_VERSION);
            wp_enqueue_script('univga-dashboard', UNIVGA_PLUGIN_URL . 'public/js/dashboard.js', array('jquery'), UNIVGA_PLUGIN_VERSION, true);
            
            wp_enqueue_script('univga-courses-fix', UNIVGA_PLUGIN_URL . 'public/js/courses-fix.js', array('jquery', 'univga-dashboard'), UNIVGA_PLUGIN_VERSION, true);
            wp_enqueue_script('univga-analytics', UNIVGA_PLUGIN_URL . 'public/js/analytics.js', array('jquery', 'univga-dashboard'), UNIVGA_PLUGIN_VERSION, true);
            wp_enqueue_script('univga-learning-paths', UNIVGA_PLUGIN_URL . 'public/js/learning-paths.js', array('jquery', 'univga-dashboard'), UNIVGA_PLUGIN_VERSION, true);
            wp_enqueue_script('univga-gamification', UNIVGA_PLUGIN_URL . 'public/js/gamification.js', array('jquery', 'univga-dashboard'), UNIVGA_PLUGIN_VERSION, true);
            wp_enqueue_script('univga-certifications', UNIVGA_PLUGIN_URL . 'public/js/certifications.js', array('jquery', 'univga-dashboard'), UNIVGA_PLUGIN_VERSION, true);
            wp_enqueue_script('univga-messages', UNIVGA_PLUGIN_URL . 'public/js/messages.js', array('jquery', 'univga-dashboard'), UNIVGA_PLUGIN_VERSION, true);
            wp_enqueue_script('univga-administration', UNIVGA_PLUGIN_URL . 'public/js/administration.js', array('jquery', 'univga-dashboard'), UNIVGA_PLUGIN_VERSION, true);
            wp_enqueue_script('univga-whitelabel', UNIVGA_PLUGIN_URL . 'public/js/whitelabel.js', array('jquery', 'univga-dashboard'), UNIVGA_PLUGIN_VERSION, true);
            wp_localize_script('univga-dashboard', 'univga_dashboard', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('univga/v1/'),
                'nonce' => wp_create_nonce('univga_dashboard_nonce'),
                'strings' => array(
                    'confirm_remove' => __('Are you sure you want to remove this member?', UNIVGA_TEXT_DOMAIN),
                    'loading' => __('Loading...', UNIVGA_TEXT_DOMAIN),
                    'error' => __('An error occurred. Please try again.', UNIVGA_TEXT_DOMAIN),
                ),
            ));
        }
    }
    
    /**
     * Organization dashboard shortcode
     */
    public function org_dashboard_shortcode($atts) {
        $atts = shortcode_atts(array(), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="univga-notice univga-notice-warning">' . 
                   __('Please log in to access your organization dashboard.', UNIVGA_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        $user_id = get_current_user_id();
        
        // Check if user is an organization manager
        if (!univga_is_org_manager($user_id)) {
            return '<div class="univga-notice univga-notice-error">' . 
                   __('You do not have permission to access this dashboard.', UNIVGA_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        // Get user's organization
        $member = univga_get_current_user_org();
        if (!$member) {
            return '<div class="univga-notice univga-notice-error">' . 
                   __('You are not associated with any organization.', UNIVGA_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        $org = UNIVGA_Orgs::get($member->org_id);
        if (!$org) {
            return '<div class="univga-notice univga-notice-error">' . 
                   __('Organization not found.', UNIVGA_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        // Start output buffering
        ob_start();
        
        // Include template
        $template_data = array(
            'org' => $org,
            'member' => $member,
            'user_id' => $user_id,
        );
        
        $this->load_template('dashboard-org.php', $template_data);
        
        return ob_get_clean();
    }
    
    /**
     * Team dashboard shortcode
     */
    public function team_dashboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'team_id' => 0,
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<div class="univga-notice univga-notice-warning">' . 
                   __('Please log in to access this dashboard.', UNIVGA_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        $team_id = intval($atts['team_id']);
        if (!$team_id) {
            return '<div class="univga-notice univga-notice-error">' . 
                   __('Team ID is required.', UNIVGA_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        $user_id = get_current_user_id();
        
        // Check if user can manage this team
        if (!UNIVGA_Capabilities::can_manage_team($user_id, $team_id)) {
            return '<div class="univga-notice univga-notice-error">' . 
                   __('You do not have permission to access this team dashboard.', UNIVGA_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        $team = UNIVGA_Teams::get($team_id);
        if (!$team) {
            return '<div class="univga-notice univga-notice-error">' . 
                   __('Team not found.', UNIVGA_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        // Get team organization
        $org = UNIVGA_Orgs::get($team->org_id);
        
        // Start output buffering
        ob_start();
        
        // Include template (simplified version of org dashboard)
        $template_data = array(
            'org' => $org,
            'team' => $team,
            'user_id' => $user_id,
        );
        
        $this->load_template('dashboard-team.php', $template_data);
        
        return ob_get_clean();
    }
    
    /**
     * Load template file
     */
    private function load_template($template, $data = array()) {
        extract($data);
        
        $template_path = UNIVGA_PLUGIN_DIR . 'public/templates/' . $template;
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="univga-notice univga-notice-error">' . 
                 __('Template not found.', UNIVGA_TEXT_DOMAIN) . 
                 '</div>';
        }
    }
}
