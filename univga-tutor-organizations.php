<?php
/**
 * Plugin Name: UNIVGA Business Pro (Organizations & Seats)
 * Plugin URI: https://univga.com
 * Description: Premium add-on for Tutor LMS and WooCommerce for enterprise seat management and organization control
 * Version: 5.0.0
 * Author: UNIVGA
 * Author URI: https://univga.com
 * Text Domain: univga-tutor-organizations
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 6.6
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * License: Commercial
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UNIVGA_PLUGIN_FILE', __FILE__);
define('UNIVGA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UNIVGA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UNIVGA_PLUGIN_VERSION', '5.0.0');
define('UNIVGA_TEXT_DOMAIN', 'univga-tutor-organizations');

/**
 * Main plugin class
 */
class UNIVGA_Tutor_Organizations {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check dependencies
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Load text domain
        load_plugin_textdomain(UNIVGA_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include core files
        $this->includes();
        
        // Initialize loader
        UNIVGA_Loader::getInstance();
    }
    
    /**
     * Check plugin dependencies
     */
    private function check_dependencies() {
        $missing = array();
        
        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            $missing[] = 'WooCommerce';
        }
        
        // Check Tutor LMS
        if (!function_exists('tutor')) {
            $missing[] = 'Tutor LMS';
        }
        
        if (!empty($missing)) {
            add_action('admin_notices', function() use ($missing) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('UNIVGA Business Pro requires the following plugins to be active: %s', UNIVGA_TEXT_DOMAIN),
                    implode(', ', $missing)
                );
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Include core files
     */
    private function includes() {
        // Helper functions first
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/helpers.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/helpers.php';
        }
        
        // Core classes in dependency order
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-installer.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-installer.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-capabilities.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-capabilities.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-user-profiles.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-user-profiles.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-enhanced-permissions.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-enhanced-permissions.php';
        }
        
        // Data classes
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-orgs.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-orgs.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-teams.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-teams.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-members.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-members.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-seat-pools.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-seat-pools.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-seat-events.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-seat-events.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-invitations.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-invitations.php';
        }
        
        // Integration classes
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-products.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-products.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-checkout-hooks.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-checkout-hooks.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-tutor.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-tutor.php';
        }
        
        // Analytics and reporting
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-reports.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-reports.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-analytics.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-analytics.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-ai-analytics.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-ai-analytics.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-hr-reporting.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-hr-reporting.php';
        }
        
        // Advanced features
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-learning-paths.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-learning-paths.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-certifications.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-certifications.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-gamification.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-gamification.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-whitelabel.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-whitelabel.php';
        }
        
        // Operations and utilities
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-bulk-operations.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-bulk-operations.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-notifications.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-notifications.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-internal-messaging.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-internal-messaging.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-integrations.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-integrations.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-rest.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-rest.php';
        }
        
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-cron.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-cron.php';
        }
        
        // Admin classes (only in admin)
        if (is_admin()) {
            if (file_exists(UNIVGA_PLUGIN_DIR . 'admin/class-admin.php')) {
                require_once UNIVGA_PLUGIN_DIR . 'admin/class-admin.php';
            }
            
            if (file_exists(UNIVGA_PLUGIN_DIR . 'admin/class-orgs-list-table.php')) {
                require_once UNIVGA_PLUGIN_DIR . 'admin/class-orgs-list-table.php';
            }
            
            if (file_exists(UNIVGA_PLUGIN_DIR . 'admin/class-teams-list-table.php')) {
                require_once UNIVGA_PLUGIN_DIR . 'admin/class-teams-list-table.php';
            }
            
            if (file_exists(UNIVGA_PLUGIN_DIR . 'admin/class-members-list-table.php')) {
                require_once UNIVGA_PLUGIN_DIR . 'admin/class-members-list-table.php';
            }
            
            if (file_exists(UNIVGA_PLUGIN_DIR . 'admin/class-pools-list-table.php')) {
                require_once UNIVGA_PLUGIN_DIR . 'admin/class-pools-list-table.php';
            }
        }
        
        // Public classes
        if (file_exists(UNIVGA_PLUGIN_DIR . 'public/class-shortcodes.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'public/class-shortcodes.php';
        }
        
        // Loader must be loaded last
        if (file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-loader.php')) {
            require_once UNIVGA_PLUGIN_DIR . 'includes/class-loader.php';
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        require_once UNIVGA_PLUGIN_DIR . 'includes/class-installer.php';
        UNIVGA_Installer::activate();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook('univga_org_resync');
    }
}

// Initialize plugin
UNIVGA_Tutor_Organizations::getInstance();
