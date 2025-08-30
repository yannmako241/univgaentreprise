<?php

/**
 * UNIVGA Integrations Class
 * Integration Hub for Enterprise Tools (SSO, HR systems)
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_Integrations {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_univga_setup_integration', array($this, 'setup_integration'));
        add_action('wp_ajax_univga_test_integration', array($this, 'test_integration'));
        add_action('wp_ajax_univga_sync_integration', array($this, 'sync_integration'));
        add_action('wp_ajax_univga_get_integrations', array($this, 'get_integrations'));
        
        // SSO Handlers
        add_action('init', array($this, 'handle_saml_auth'));
        add_action('wp_login', array($this, 'handle_sso_login'), 10, 2);
        
        // HR System Sync
        add_action('univga_hr_sync', array($this, 'sync_hr_data'));
        
        // Calendar Integration
        add_action('wp_ajax_univga_schedule_training', array($this, 'schedule_training_session'));
        
        // Slack/Teams Integration
        add_action('univga_course_completed', array($this, 'notify_slack_completion'), 10, 3);
        add_action('wp_ajax_univga_slack_command', array($this, 'handle_slack_command'));
    }
    
    /**
     * Setup integration
     */
    public function setup_integration() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $org_id = intval($_POST['org_id']);
        $integration_type = sanitize_text_field($_POST['integration_type']);
        $settings = $_POST['settings'];
        
        // Encrypt sensitive settings
        $encrypted_settings = $this->encrypt_sensitive_data($settings);
        
        $result = $wpdb->replace(
            $wpdb->prefix . 'univga_integrations',
            array(
                'org_id' => $org_id,
                'integration_type' => $integration_type,
                'settings' => json_encode($encrypted_settings),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array('message' => 'Integration configured successfully'));
        } else {
            wp_send_json_error('Failed to configure integration');
        }
    }
    
    /**
     * Get integrations for organization
     */
    public function get_integrations() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        global $wpdb;
        
        $integrations = $wpdb->get_results($wpdb->prepare("
            SELECT *, 
                   CASE WHEN last_sync IS NULL THEN 'Never' 
                        ELSE DATE_FORMAT(last_sync, '%%M %%d, %%Y at %%h:%%i %%p') 
                   END as last_sync_formatted
            FROM {$wpdb->prefix}univga_integrations
            WHERE org_id = %d
            ORDER BY integration_type
        ", $org_id));
        
        // Decrypt settings for display (excluding sensitive fields)
        foreach ($integrations as $integration) {
            $settings = json_decode($integration->settings, true);
            $integration->settings = $this->sanitize_settings_for_display($settings);
        }
        
        wp_send_json_success($integrations);
    }
    
    /**
     * Test integration connection
     */
    public function test_integration() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $integration_type = sanitize_text_field($_POST['integration_type']);
        $settings = $_POST['settings'];
        
        $result = $this->test_integration_connection($integration_type, $settings);
        
        wp_send_json_success($result);
    }
    
    /**
     * Test integration connection
     */
    private function test_integration_connection($type, $settings) {
        switch ($type) {
            case 'saml_sso':
                return $this->test_saml_connection($settings);
                
            case 'active_directory':
                return $this->test_ldap_connection($settings);
                
            case 'bamboo_hr':
                return $this->test_bamboo_hr_connection($settings);
                
            case 'workday':
                return $this->test_workday_connection($settings);
                
            case 'slack':
                return $this->test_slack_connection($settings);
                
            case 'microsoft_teams':
                return $this->test_teams_connection($settings);
                
            case 'google_calendar':
                return $this->test_google_calendar_connection($settings);
                
            default:
                return array('success' => false, 'message' => 'Unknown integration type');
        }
    }
    
    /**
     * Test SAML SSO connection
     */
    private function test_saml_connection($settings) {
        if (empty($settings['idp_url']) || empty($settings['certificate'])) {
            return array('success' => false, 'message' => 'Missing required SAML configuration');
        }
        
        // Basic validation of SAML settings
        if (!filter_var($settings['idp_url'], FILTER_VALIDATE_URL)) {
            return array('success' => false, 'message' => 'Invalid IdP URL');
        }
        
        return array('success' => true, 'message' => 'SAML configuration appears valid');
    }
    
    /**
     * Test LDAP/AD connection
     */
    private function test_ldap_connection($settings) {
        if (!function_exists('ldap_connect')) {
            return array('success' => false, 'message' => 'LDAP extension not available');
        }
        
        $server = $settings['server'] ?? '';
        $port = $settings['port'] ?? 389;
        $username = $settings['username'] ?? '';
        $password = $settings['password'] ?? '';
        
        $connection = @ldap_connect($server, $port);
        if (!$connection) {
            return array('success' => false, 'message' => 'Could not connect to LDAP server');
        }
        
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        
        if (!@ldap_bind($connection, $username, $password)) {
            ldap_close($connection);
            return array('success' => false, 'message' => 'LDAP authentication failed');
        }
        
        ldap_close($connection);
        return array('success' => true, 'message' => 'LDAP connection successful');
    }
    
    /**
     * Test BambooHR connection
     */
    private function test_bamboo_hr_connection($settings) {
        $api_key = $settings['api_key'] ?? '';
        $company_domain = $settings['company_domain'] ?? '';
        
        if (empty($api_key) || empty($company_domain)) {
            return array('success' => false, 'message' => 'Missing API key or company domain');
        }
        
        $url = "https://api.bamboohr.com/api/gateway.php/{$company_domain}/v1/employees/directory";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($api_key . ':x'),
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Connection failed: ' . $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return array('success' => false, 'message' => 'API returned status code: ' . $code);
        }
        
        return array('success' => true, 'message' => 'BambooHR connection successful');
    }
    
    /**
     * Test Slack connection
     */
    private function test_slack_connection($settings) {
        $bot_token = $settings['bot_token'] ?? '';
        
        if (empty($bot_token)) {
            return array('success' => false, 'message' => 'Missing bot token');
        }
        
        $response = wp_remote_post('https://slack.com/api/auth.test', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $bot_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Connection failed: ' . $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$body['ok']) {
            return array('success' => false, 'message' => 'Slack API error: ' . ($body['error'] ?? 'Unknown error'));
        }
        
        return array('success' => true, 'message' => 'Slack connection successful');
    }
    
    /**
     * Handle SAML authentication
     */
    public function handle_saml_auth() {
        if (!isset($_GET['univga_saml']) || !isset($_POST['SAMLResponse'])) {
            return;
        }
        
        $org_id = intval($_GET['org_id'] ?? 0);
        if (!$org_id) {
            wp_die('Invalid organization');
        }
        
        // Get SAML settings
        $saml_settings = $this->get_integration_settings($org_id, 'saml_sso');
        if (!$saml_settings) {
            wp_die('SAML not configured for this organization');
        }
        
        // Decode and validate SAML response
        $saml_response = base64_decode($_POST['SAMLResponse']);
        $user_data = $this->parse_saml_response($saml_response, $saml_settings);
        
        if (!$user_data) {
            wp_die('Invalid SAML response');
        }
        
        // Create or update user
        $user = $this->create_or_update_sso_user($user_data, $org_id);
        
        if (is_wp_error($user)) {
            wp_die($user->get_error_message());
        }
        
        // Log in user
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        // Redirect to dashboard
        wp_redirect(admin_url('admin.php?page=univga-dashboard'));
        exit;
    }
    
    /**
     * Parse SAML response
     */
    private function parse_saml_response($saml_response, $settings) {
        // This is a simplified SAML parser
        // In production, use a proper SAML library like SimpleSAMLphp
        
        $dom = new DOMDocument();
        if (!@$dom->loadXML($saml_response)) {
            return false;
        }
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        
        // Extract user attributes
        $email = $xpath->evaluate('string(//saml:Attribute[@Name="email"]/saml:AttributeValue)');
        $first_name = $xpath->evaluate('string(//saml:Attribute[@Name="first_name"]/saml:AttributeValue)');
        $last_name = $xpath->evaluate('string(//saml:Attribute[@Name="last_name"]/saml:AttributeValue)');
        $username = $xpath->evaluate('string(//saml:Attribute[@Name="username"]/saml:AttributeValue)');
        
        if (empty($email)) {
            return false;
        }
        
        return array(
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'username' => $username ?: $email
        );
    }
    
    /**
     * Create or update SSO user
     */
    private function create_or_update_sso_user($user_data, $org_id) {
        $email = $user_data['email'];
        $user = get_user_by('email', $email);
        
        if ($user) {
            // Update existing user
            wp_update_user(array(
                'ID' => $user->ID,
                'first_name' => $user_data['first_name'],
                'last_name' => $user_data['last_name'],
                'display_name' => trim($user_data['first_name'] . ' ' . $user_data['last_name'])
            ));
        } else {
            // Create new user
            $user_id = wp_create_user(
                $user_data['username'],
                wp_generate_password(),
                $email
            );
            
            if (is_wp_error($user_id)) {
                return $user_id;
            }
            
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $user_data['first_name'],
                'last_name' => $user_data['last_name'],
                'display_name' => trim($user_data['first_name'] . ' ' . $user_data['last_name'])
            ));
            
            $user = get_user_by('ID', $user_id);
            
            // Add user to organization
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'univga_org_members',
                array(
                    'org_id' => $org_id,
                    'user_id' => $user_id,
                    'status' => 'active',
                    'joined_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s')
            );
        }
        
        return $user;
    }
    
    /**
     * Sync HR data
     */
    public function sync_hr_data() {
        global $wpdb;
        
        $integrations = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}univga_integrations 
            WHERE integration_type IN ('bamboo_hr', 'workday', 'adp') 
            AND is_active = 1
        ");
        
        foreach ($integrations as $integration) {
            $settings = json_decode($integration->settings, true);
            $this->sync_hr_system_data($integration->org_id, $integration->integration_type, $settings);
        }
    }
    
    /**
     * Sync specific HR system data
     */
    private function sync_hr_system_data($org_id, $type, $settings) {
        switch ($type) {
            case 'bamboo_hr':
                $this->sync_bamboo_hr_data($org_id, $settings);
                break;
                
            case 'workday':
                $this->sync_workday_data($org_id, $settings);
                break;
        }
        
        // Update last sync time
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'univga_integrations',
            array('last_sync' => current_time('mysql')),
            array('org_id' => $org_id, 'integration_type' => $type),
            array('%s'),
            array('%d', '%s')
        );
    }
    
    /**
     * Sync BambooHR data
     */
    private function sync_bamboo_hr_data($org_id, $settings) {
        $api_key = $this->decrypt_sensitive_data($settings['api_key']);
        $company_domain = $settings['company_domain'];
        
        $url = "https://api.bamboohr.com/api/gateway.php/{$company_domain}/v1/employees/directory";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($api_key . ':x'),
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('BambooHR sync failed: ' . $response->get_error_message());
            return;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$data || !isset($data['employees'])) {
            error_log('BambooHR sync: Invalid response data');
            return;
        }
        
        foreach ($data['employees'] as $employee) {
            $this->update_user_from_hr_data($org_id, array(
                'email' => $employee['workEmail'] ?? '',
                'first_name' => $employee['firstName'] ?? '',
                'last_name' => $employee['lastName'] ?? '',
                'department' => $employee['department'] ?? '',
                'job_title' => $employee['jobTitle'] ?? '',
                'hire_date' => $employee['hireDate'] ?? '',
                'employee_id' => $employee['id'] ?? ''
            ));
        }
    }
    
    /**
     * Update user from HR data
     */
    private function update_user_from_hr_data($org_id, $hr_data) {
        if (empty($hr_data['email'])) {
            return;
        }
        
        $user = get_user_by('email', $hr_data['email']);
        if (!$user) {
            return; // Don't create users from HR sync, only update existing
        }
        
        // Update user meta
        update_user_meta($user->ID, 'department', $hr_data['department']);
        update_user_meta($user->ID, 'job_title', $hr_data['job_title']);
        update_user_meta($user->ID, 'hire_date', $hr_data['hire_date']);
        update_user_meta($user->ID, 'employee_id', $hr_data['employee_id']);
        
        // Update WordPress user data
        wp_update_user(array(
            'ID' => $user->ID,
            'first_name' => $hr_data['first_name'],
            'last_name' => $hr_data['last_name'],
            'display_name' => trim($hr_data['first_name'] . ' ' . $hr_data['last_name'])
        ));
    }
    
    /**
     * Handle Slack command
     */
    public function handle_slack_command() {
        if (!isset($_POST['token']) || !isset($_POST['command'])) {
            wp_die('Invalid request');
        }
        
        // Verify Slack token
        $org_id = intval($_POST['team_domain'] ?? 0); // This would need proper mapping
        $slack_settings = $this->get_integration_settings($org_id, 'slack');
        
        if (!$slack_settings || $_POST['token'] !== $slack_settings['verification_token']) {
            wp_die('Invalid token');
        }
        
        $command = $_POST['command'];
        $text = $_POST['text'] ?? '';
        $user_name = $_POST['user_name'] ?? '';
        
        $response = $this->process_slack_command($command, $text, $user_name, $org_id);
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    /**
     * Process Slack command
     */
    private function process_slack_command($command, $text, $user_name, $org_id) {
        switch ($command) {
            case '/univga-progress':
                return $this->get_slack_user_progress($user_name, $org_id);
                
            case '/univga-leaderboard':
                return $this->get_slack_leaderboard($org_id);
                
            case '/univga-courses':
                return $this->get_slack_available_courses($org_id);
                
            default:
                return array(
                    'response_type' => 'ephemeral',
                    'text' => 'Unknown command. Available commands: /univga-progress, /univga-leaderboard, /univga-courses'
                );
        }
    }
    
    /**
     * Notify Slack of course completion
     */
    public function notify_slack_completion($user_id, $course_id, $org_id) {
        $slack_settings = $this->get_integration_settings($org_id, 'slack');
        
        if (!$slack_settings || !$slack_settings['notify_completions']) {
            return;
        }
        
        $user = get_user_by('ID', $user_id);
        $course = get_post($course_id);
        
        $message = sprintf(
            '🎉 *%s* just completed the course "*%s*"! Congratulations! 👏',
            $user->display_name,
            $course->post_title
        );
        
        $this->send_slack_message($slack_settings, $message, $slack_settings['channel']);
    }
    
    /**
     * Send Slack message
     */
    private function send_slack_message($settings, $message, $channel = null) {
        $bot_token = $this->decrypt_sensitive_data($settings['bot_token']);
        $channel = $channel ?: $settings['default_channel'];
        
        wp_remote_post('https://slack.com/api/chat.postMessage', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $bot_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'channel' => $channel,
                'text' => $message,
                'as_user' => false,
                'username' => 'UNIVGA Bot'
            )),
            'timeout' => 15
        ));
    }
    
    /**
     * Get integration settings
     */
    private function get_integration_settings($org_id, $integration_type) {
        global $wpdb;
        
        $integration = $wpdb->get_row($wpdb->prepare("
            SELECT settings FROM {$wpdb->prefix}univga_integrations 
            WHERE org_id = %d AND integration_type = %s AND is_active = 1
        ", $org_id, $integration_type));
        
        return $integration ? json_decode($integration->settings, true) : null;
    }
    
    /**
     * Encrypt sensitive data
     */
    private function encrypt_sensitive_data($data) {
        $sensitive_fields = array('password', 'api_key', 'client_secret', 'bot_token', 'private_key');
        
        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive_fields) && !empty($value)) {
                $data[$key] = $this->encrypt_value($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Decrypt sensitive data
     */
    private function decrypt_sensitive_data($encrypted_value) {
        return $this->decrypt_value($encrypted_value);
    }
    
    /**
     * Encrypt a value
     */
    private function encrypt_value($value) {
        if (function_exists('openssl_encrypt')) {
            $key = wp_hash('univga_encryption_key');
            $iv = substr(hash('sha256', $key), 0, 16);
            return base64_encode(openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv));
        }
        
        // Fallback to base64 (not secure, but better than plain text)
        return base64_encode($value);
    }
    
    /**
     * Decrypt a value
     */
    private function decrypt_value($encrypted_value) {
        if (function_exists('openssl_decrypt')) {
            $key = wp_hash('univga_encryption_key');
            $iv = substr(hash('sha256', $key), 0, 16);
            $decrypted = openssl_decrypt(base64_decode($encrypted_value), 'AES-256-CBC', $key, 0, $iv);
            return $decrypted !== false ? $decrypted : $encrypted_value;
        }
        
        // Fallback from base64
        return base64_decode($encrypted_value);
    }
    
    /**
     * Sanitize settings for display
     */
    private function sanitize_settings_for_display($settings) {
        $sensitive_fields = array('password', 'api_key', 'client_secret', 'bot_token', 'private_key');
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $sensitive_fields)) {
                $settings[$key] = str_repeat('*', min(8, strlen($value)));
            }
        }
        
        return $settings;
    }
    
    // Additional test methods for other integrations...
    private function test_workday_connection($settings) {
        // Workday API test implementation
        return array('success' => true, 'message' => 'Workday test not implemented');
    }
    
    private function test_teams_connection($settings) {
        // Microsoft Teams API test implementation
        return array('success' => true, 'message' => 'Teams test not implemented');
    }
    
    private function test_google_calendar_connection($settings) {
        // Google Calendar API test implementation
        return array('success' => true, 'message' => 'Google Calendar test not implemented');
    }
}