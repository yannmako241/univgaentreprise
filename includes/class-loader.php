<?php

/**
 * Main loader class - Singleton pattern
 */
class UNIVGA_Loader {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Initialize components
        add_action('init', array($this, 'init_components'));
        
        // Admin hooks
        if (is_admin()) {
            UNIVGA_Admin::getInstance();
        }
        
        // Public hooks
        UNIVGA_Shortcodes::getInstance();
        
        // REST API
        add_action('rest_api_init', array('UNIVGA_REST', 'init'));
        
        // Cron
        UNIVGA_Cron::getInstance();
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Initialize capabilities
        UNIVGA_Capabilities::getInstance();
        
        // Initialize WooCommerce hooks
        UNIVGA_Checkout_Hooks::getInstance();
        
        // Initialize products metabox
        UNIVGA_Products::getInstance();
        
        // Initialize invitations
        UNIVGA_Invitations::getInstance();
        
        // Initialize advanced features
        UNIVGA_Analytics::getInstance();
        UNIVGA_Learning_Paths::getInstance();
        UNIVGA_Certifications::getInstance();
        UNIVGA_Bulk_Operations::getInstance();
        UNIVGA_Notifications::getInstance();
        UNIVGA_Gamification::getInstance();
        UNIVGA_Integrations::getInstance();
        UNIVGA_WhiteLabel::getInstance();
        UNIVGA_Enhanced_Permissions::getInstance();
    }
}
