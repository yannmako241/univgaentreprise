<?php

/**
 * Invitation system for organization members
 */
class UNIVGA_Invitations {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'handle_join_request'));
        add_action('wp_ajax_univga_send_invitation', array($this, 'ajax_send_invitation'));
        add_action('wp_ajax_nopriv_univga_process_join', array($this, 'ajax_process_join'));
    }
    
    /**
     * Send invitation email
     */
    public static function send_invitation($org_id, $team_id, $email, $sender_id) {
        // Validate email domain if organization has domain restriction
        $org = UNIVGA_Orgs::get($org_id);
        if ($org && $org->email_domain) {
            $email_domain = substr(strrchr($email, '@'), 1);
            if (strtolower($email_domain) !== strtolower($org->email_domain)) {
                return new WP_Error('invalid_domain', 
                    sprintf(__('L\'email doit être du domaine : %s', UNIVGA_TEXT_DOMAIN), $org->email_domain)
                );
            }
        }
        
        // Check if user already exists and is member
        $user = get_user_by('email', $email);
        if ($user) {
            $existing_member = UNIVGA_Members::get_user_org_membership($user->ID);
            if ($existing_member && $existing_member->org_id == $org_id) {
                return new WP_Error('already_member', __('L\'utilisateur est déjà membre de cette organisation', UNIVGA_TEXT_DOMAIN));
            }
        }
        
        // Generate secure token
        $token_data = array(
            'org_id' => $org_id,
            'team_id' => $team_id,
            'email' => $email,
            'sender_id' => $sender_id,
            'expires' => time() + (7 * 24 * 60 * 60), // 7 days
        );
        
        $token = self::generate_token($token_data);
        
        // Create join URL
        $join_url = add_query_arg(array(
            'univga_action' => 'join',
            'token' => $token,
        ), home_url());
        
        // Get sender info
        $sender = get_userdata($sender_id);
        $team = $team_id ? UNIVGA_Teams::get($team_id) : null;
        
        // Send email
        $subject = sprintf(__('Invitation à rejoindre %s', UNIVGA_TEXT_DOMAIN), $org->name);
        
        $message = sprintf(
            __('Hello,

You have been invited by %s to join the organization "%s"%s.

To accept this invitation, please click the link below:
%s

This invitation will expire in 7 days.

If you don\'t have an account yet, you can create one during the joining process.

Best regards,
The UNIVGA Team', UNIVGA_TEXT_DOMAIN),
            $sender->display_name,
            $org->name,
            $team ? sprintf(__(' in the team "%s"', UNIVGA_TEXT_DOMAIN), $team->name) : '',
            $join_url
        );
        
        $sent = wp_mail($email, $subject, $message);
        
        if ($sent) {
            // Log invitation event
            UNIVGA_Seat_Events::log(0, $user ? $user->ID : null, 'invite', array(
                'org_id' => $org_id,
                'team_id' => $team_id,
                'email' => $email,
                'sender_id' => $sender_id,
                'token' => $token,
            ));
            
            return true;
        }
        
        return new WP_Error('email_failed', __('Failed to send invitation email', UNIVGA_TEXT_DOMAIN));
    }
    
    /**
     * Handle join request from invitation link
     */
    public function handle_join_request() {
        if (!isset($_GET['univga_action']) || $_GET['univga_action'] !== 'join') {
            return;
        }
        
        if (!isset($_GET['token'])) {
            wp_die(__('Invalid invitation link', UNIVGA_TEXT_DOMAIN));
        }
        
        $token = sanitize_text_field($_GET['token']);
        $token_data = self::verify_token($token);
        
        if (!$token_data) {
            wp_die(__('Invalid or expired invitation', UNIVGA_TEXT_DOMAIN));
        }
        
        // Display join form
        $this->display_join_form($token_data, $token);
    }
    
    /**
     * Display join form
     */
    private function display_join_form($token_data, $token) {
        $org = UNIVGA_Orgs::get($token_data['org_id']);
        $team = $token_data['team_id'] ? UNIVGA_Teams::get($token_data['team_id']) : null;
        
        // Check if user is already logged in
        $current_user = wp_get_current_user();
        $is_logged_in = $current_user->ID > 0;
        
        // Check if logged in user email matches invitation
        $email_match = $is_logged_in && $current_user->user_email === $token_data['email'];
        
        include UNIVGA_PLUGIN_DIR . 'public/templates/join.php';
        exit;
    }
    
    /**
     * AJAX handler for processing join
     */
    public function ajax_process_join() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_join_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $token = sanitize_text_field($_POST['token']);
        $token_data = self::verify_token($token);
        
        if (!$token_data) {
            wp_send_json_error('Invalid or expired invitation');
        }
        
        $user_id = null;
        $current_user = wp_get_current_user();
        
        if ($current_user->ID > 0) {
            // User is already logged in
            if ($current_user->user_email !== $token_data['email']) {
                wp_send_json_error('Email address does not match invitation');
            }
            $user_id = $current_user->ID;
            
        } else {
            // Check if user needs to register or login
            $action = sanitize_text_field($_POST['action_type']);
            
            if ($action === 'register') {
                // Register new user
                $username = sanitize_user($_POST['username']);
                $password = $_POST['password'];
                $first_name = sanitize_text_field($_POST['first_name']);
                $last_name = sanitize_text_field($_POST['last_name']);
                
                if (username_exists($username) || email_exists($token_data['email'])) {
                    wp_send_json_error('Username or email already exists');
                }
                
                $user_id = wp_create_user($username, $password, $token_data['email']);
                
                if (is_wp_error($user_id)) {
                    wp_send_json_error($user_id->get_error_message());
                }
                
                // Update user profile
                wp_update_user(array(
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $first_name . ' ' . $last_name,
                ));
                
                // Log the user in
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                
            } elseif ($action === 'login') {
                // Login existing user
                $username = sanitize_user($_POST['login_username']);
                $password = $_POST['login_password'];
                
                $user = wp_authenticate($username, $password);
                
                if (is_wp_error($user)) {
                    wp_send_json_error('Invalid login credentials');
                }
                
                if ($user->user_email !== $token_data['email']) {
                    wp_send_json_error('Email address does not match invitation');
                }
                
                $user_id = $user->ID;
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
            }
        }
        
        if (!$user_id) {
            wp_send_json_error('Failed to process join request');
        }
        
        // Add user to organization
        $result = UNIVGA_Members::add_member(
            $token_data['org_id'],
            $token_data['team_id'],
            $user_id,
            'active'
        );
        
        if (!$result) {
            wp_send_json_error('Failed to add user to organization');
        }
        
        // Get organization dashboard URL
        $dashboard_url = get_permalink(get_option('univga_dashboard_page_id'));
        if (!$dashboard_url) {
            $dashboard_url = home_url();
        }
        
        wp_send_json_success(array(
            'message' => 'Successfully joined organization',
            'redirect_url' => $dashboard_url,
        ));
    }
    
    /**
     * AJAX handler for sending invitation
     */
    public function ajax_send_invitation() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_invitation_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $org_id = intval($_POST['org_id']);
        $team_id = !empty($_POST['team_id']) ? intval($_POST['team_id']) : null;
        $email = sanitize_email($_POST['email']);
        
        // Check permissions
        if (!UNIVGA_Capabilities::can_manage_org(get_current_user_id(), $org_id)) {
            wp_send_json_error('Permission denied');
        }
        
        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }
        
        $result = self::send_invitation($org_id, $team_id, $email, get_current_user_id());
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Invitation sent successfully');
    }
    
    /**
     * Generate secure token
     */
    private static function generate_token($data) {
        $json = json_encode($data);
        $hash = hash_hmac('sha256', $json, wp_salt('secure_auth'));
        return base64_encode($json . '|' . $hash);
    }
    
    /**
     * Verify token
     */
    private static function verify_token($token) {
        $decoded = base64_decode($token);
        if (!$decoded) {
            return false;
        }
        
        $parts = explode('|', $decoded);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($json, $hash) = $parts;
        $expected_hash = hash_hmac('sha256', $json, wp_salt('secure_auth'));
        
        if (!hash_equals($expected_hash, $hash)) {
            return false;
        }
        
        $data = json_decode($json, true);
        if (!$data) {
            return false;
        }
        
        // Check expiration
        if (isset($data['expires']) && $data['expires'] < time()) {
            return false;
        }
        
        return $data;
    }
}
