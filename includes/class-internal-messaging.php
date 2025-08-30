<?php
/**
 * UNIVGA Internal Messaging System
 * Handles HR-to-employee messaging and internal communications
 */

defined('ABSPATH') || exit;

class UNIVGA_Internal_Messaging {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX handlers for messaging
        add_action('wp_ajax_univga_create_conversation', array($this, 'ajax_create_conversation'));
        add_action('wp_ajax_univga_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_univga_get_conversations', array($this, 'ajax_get_conversations'));
        add_action('wp_ajax_univga_get_conversation_messages', array($this, 'ajax_get_conversation_messages'));
        add_action('wp_ajax_univga_mark_conversation_read', array($this, 'ajax_mark_conversation_read'));
        add_action('wp_ajax_univga_archive_conversation', array($this, 'ajax_archive_conversation'));
        
        // User-facing AJAX (non-admin)
        add_action('wp_ajax_nopriv_univga_get_user_conversations', array($this, 'ajax_get_user_conversations'));
        add_action('wp_ajax_nopriv_univga_get_user_messages', array($this, 'ajax_get_user_messages'));
        add_action('wp_ajax_nopriv_univga_reply_to_message', array($this, 'ajax_reply_to_message'));
        add_action('wp_ajax_nopriv_univga_mark_user_conversation_read', array($this, 'ajax_mark_user_conversation_read'));
    }
    
    /**
     * Check if user can send messages in organization
     */
    public function can_send_messages($user_id, $org_id) {
        // Use existing capabilities system
        if (!class_exists('UNIVGA_Capabilities')) {
            return false;
        }
        
        // HR admins can send messages if they can manage the organization
        if (UNIVGA_Capabilities::can_manage_org($user_id, $org_id)) {
            return true;
        }
        
        // Team managers can send messages to their team members
        if (user_can($user_id, 'univga_team_manage')) {
            return true;
        }
        
        // Additional messaging-specific capability
        if (user_can($user_id, 'univga_messaging_send')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user can participate in conversation
     */
    public function can_participate($user_id, $conversation_id) {
        // Check if user is a participant
        if ($this->is_participant($conversation_id, $user_id)) {
            return true;
        }
        
        // HR admins can join any conversation in organizations they manage
        global $wpdb;
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT org_id FROM {$wpdb->prefix}univga_conversations WHERE id = %d",
            $conversation_id
        ));
        
        if ($conversation && UNIVGA_Capabilities::can_manage_org($user_id, $conversation->org_id)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Create a new conversation
     */
    public function create_conversation($org_id, $subject, $created_by, $participants = array()) {
        global $wpdb;
        
        // Check permissions
        if (!$this->can_send_messages($created_by, $org_id)) {
            return false;
        }
        
        // Create conversation
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_conversations',
            array(
                'org_id' => $org_id,
                'subject' => sanitize_text_field($subject),
                'created_by' => $created_by,
                'last_activity' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s')
        );
        
        if ($result) {
            $conversation_id = $wpdb->insert_id;
            
            // Add creator as admin participant
            $this->add_participant($conversation_id, $created_by, 'admin');
            
            // Add other participants
            foreach ($participants as $participant_id) {
                if ($participant_id != $created_by) {
                    $this->add_participant($conversation_id, $participant_id, 'member');
                }
            }
            
            return $conversation_id;
        }
        
        return false;
    }
    
    /**
     * Add participant to conversation
     */
    private function add_participant($conversation_id, $user_id, $role = 'member') {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'univga_conversation_participants',
            array(
                'conversation_id' => $conversation_id,
                'user_id' => $user_id,
                'role' => $role,
                'joined_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s')
        );
    }
    
    /**
     * Send message in conversation
     */
    public function send_message($conversation_id, $sender_id, $message, $message_type = 'text', $is_priority = false) {
        global $wpdb;
        
        // Verify sender can participate
        if (!$this->can_participate($sender_id, $conversation_id)) {
            return false;
        }
        
        // Insert message
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_messages',
            array(
                'conversation_id' => $conversation_id,
                'sender_id' => $sender_id,
                'message' => wp_kses_post($message),
                'message_type' => $message_type,
                'is_priority' => $is_priority ? 1 : 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            $message_id = $wpdb->insert_id;
            
            // Update conversation last activity
            $wpdb->update(
                $wpdb->prefix . 'univga_conversations',
                array('last_activity' => current_time('mysql')),
                array('id' => $conversation_id),
                array('%s'),
                array('%d')
            );
            
            // Send notifications to all participants except sender
            $this->notify_participants($conversation_id, $sender_id, $message_type, $is_priority);
            
            return $message_id;
        }
        
        return false;
    }
    
    /**
     * Check if user is participant in conversation
     */
    private function is_participant($conversation_id, $user_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}univga_conversation_participants 
            WHERE conversation_id = %d AND user_id = %d
        ", $conversation_id, $user_id));
        
        return $count > 0;
    }
    
    /**
     * Notify participants about new message
     */
    private function notify_participants($conversation_id, $sender_id, $message_type, $is_priority) {
        global $wpdb;
        
        // Get conversation details
        $conversation = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_conversations 
            WHERE id = %d
        ", $conversation_id));
        
        if (!$conversation) return;
        
        // Get all participants except sender
        $participants = $wpdb->get_results($wpdb->prepare("
            SELECT cp.user_id, u.user_email, u.display_name
            FROM {$wpdb->prefix}univga_conversation_participants cp
            JOIN {$wpdb->prefix}users u ON cp.user_id = u.ID
            WHERE cp.conversation_id = %d AND cp.user_id != %d
        ", $conversation_id, $sender_id));
        
        $sender = get_user_by('ID', $sender_id);
        $sender_name = $sender ? $sender->display_name : __('System', UNIVGA_TEXT_DOMAIN);
        
        $notification_type = $message_type === 'announcement' ? 'internal_announcement' : 'internal_message';
        $priority_text = $is_priority ? '[URGENT] ' : '';
        
        foreach ($participants as $participant) {
            // Create in-app notification using existing system
            $notifications = UNIVGA_Notifications::getInstance();
            $notifications->create_notification(
                $participant->user_id,
                $conversation->org_id,
                $notification_type,
                $priority_text . sprintf(__('New message in: %s', UNIVGA_TEXT_DOMAIN), $conversation->subject),
                sprintf(__('%s sent a message in "%s"', UNIVGA_TEXT_DOMAIN), $sender_name, $conversation->subject),
                admin_url('admin.php?page=univga-dashboard&tab=messages&conversation=' . $conversation_id)
            );
            
            // Send email notification if priority or announcement
            if ($is_priority || $message_type === 'announcement') {
                $this->send_email_notification(
                    $participant->user_email,
                    $priority_text . sprintf(__('New message: %s', UNIVGA_TEXT_DOMAIN), $conversation->subject),
                    sprintf(
                        __('%s sent you a message in "%s". Please check your dashboard for details.', UNIVGA_TEXT_DOMAIN),
                        $sender_name,
                        $conversation->subject
                    ),
                    admin_url('admin.php?page=univga-dashboard&tab=messages&conversation=' . $conversation_id)
                );
            }
        }
    }
    
    /**
     * Send email notification
     */
    private function send_email_notification($to_email, $subject, $message, $action_url) {
        $email_body = sprintf('
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #333;">%s</h2>
                <p>%s</p>
                <p><a href="%s" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">%s</a></p>
                <hr>
                <p style="font-size: 12px; color: #666;">%s</p>
            </div>
        ', 
            $subject, 
            $message, 
            $action_url, 
            __('View Message', UNIVGA_TEXT_DOMAIN),
            __('This is an automated message from your organization\'s learning platform.', UNIVGA_TEXT_DOMAIN)
        );
        
        wp_mail(
            $to_email,
            $subject,
            $email_body,
            array('Content-Type: text/html; charset=UTF-8')
        );
    }
    
    /**
     * Get conversations for organization
     */
    public function get_conversations($org_id, $page = 1, $per_page = 20, $archived = false) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        $archived_clause = $archived ? '1' : '0';
        
        $conversations = $wpdb->get_results($wpdb->prepare("
            SELECT c.*, 
                   u.display_name as creator_name,
                   (SELECT COUNT(*) FROM {$wpdb->prefix}univga_conversation_participants cp WHERE cp.conversation_id = c.id) as participant_count,
                   (SELECT COUNT(*) FROM {$wpdb->prefix}univga_messages m WHERE m.conversation_id = c.id) as message_count
            FROM {$wpdb->prefix}univga_conversations c
            LEFT JOIN {$wpdb->prefix}users u ON c.created_by = u.ID
            WHERE c.org_id = %d AND c.is_archived = %d
            ORDER BY c.last_activity DESC
            LIMIT %d OFFSET %d
        ", $org_id, $archived_clause, $per_page, $offset));
        
        return $conversations;
    }
    
    /**
     * Get messages for a conversation
     */
    public function get_conversation_messages($conversation_id, $user_id, $page = 1, $per_page = 50) {
        global $wpdb;
        
        // Verify user can access this conversation
        if (!$this->is_participant($conversation_id, $user_id)) {
            return false;
        }
        
        $offset = ($page - 1) * $per_page;
        
        $messages = $wpdb->get_results($wpdb->prepare("
            SELECT m.*, u.display_name as sender_name, u.user_email as sender_email
            FROM {$wpdb->prefix}univga_messages m
            LEFT JOIN {$wpdb->prefix}users u ON m.sender_id = u.ID
            WHERE m.conversation_id = %d
            ORDER BY m.created_at ASC
            LIMIT %d OFFSET %d
        ", $conversation_id, $per_page, $offset));
        
        return $messages;
    }
    
    /**
     * Mark conversation as read for user
     */
    public function mark_conversation_read($conversation_id, $user_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'univga_conversation_participants',
            array('last_read_at' => current_time('mysql')),
            array('conversation_id' => $conversation_id, 'user_id' => $user_id),
            array('%s'),
            array('%d', '%d')
        );
    }
    
    /**
     * Get unread message count for user
     */
    public function get_unread_count($user_id, $org_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT c.id)
            FROM {$wpdb->prefix}univga_conversations c
            JOIN {$wpdb->prefix}univga_conversation_participants cp ON c.id = cp.conversation_id
            JOIN {$wpdb->prefix}univga_messages m ON c.id = m.conversation_id
            WHERE cp.user_id = %d 
            AND c.org_id = %d 
            AND c.is_archived = 0
            AND (cp.last_read_at IS NULL OR m.created_at > cp.last_read_at)
        ", $user_id, $org_id));
        
        return intval($count);
    }
    
    /**
     * Get all members of an organization for messaging
     */
    private function get_organization_members($org_id) {
        if (!class_exists('UNIVGA_Members')) {
            return array();
        }
        
        $members = UNIVGA_Members::get_organization_members($org_id);
        $member_ids = array();
        
        if ($members) {
            foreach ($members as $member) {
                $member_ids[] = $member->user_id;
            }
        }
        
        return $member_ids;
    }
    
    // ======== AJAX HANDLERS ========
    
    /**
     * AJAX: Create new conversation
     */
    public function ajax_create_conversation() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        $user_id = get_current_user_id();
        
        // Check messaging permissions
        if (!$this->can_send_messages($user_id, $org_id)) {
            wp_send_json_error(__('You do not have permission to send messages in this organization', UNIVGA_TEXT_DOMAIN));
        }
        
        $subject = sanitize_text_field($_POST['subject']);
        $participants = array_map('intval', $_POST['participants']);
        $initial_message = wp_kses_post($_POST['initial_message']);
        $message_type = sanitize_text_field($_POST['message_type'] ?? 'text');
        $is_priority = isset($_POST['is_priority']);
        
        // If participants is 'all', get all organization members
        if ($participants === 'all' || (is_array($participants) && in_array('all', $participants))) {
            $participants = $this->get_organization_members($org_id);
        }
        
        $conversation_id = $this->create_conversation($org_id, $subject, $user_id, $participants);
        
        if ($conversation_id) {
            // Send initial message
            $message_id = $this->send_message($conversation_id, get_current_user_id(), $initial_message, $message_type, $is_priority);
            
            wp_send_json_success(array(
                'conversation_id' => $conversation_id,
                'message_id' => $message_id,
                'message' => __('Conversation created successfully', UNIVGA_TEXT_DOMAIN)
            ));
        } else {
            wp_send_json_error(__('Failed to create conversation', UNIVGA_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX: Send message to conversation
     */
    public function ajax_send_message() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $conversation_id = intval($_POST['conversation_id']);
        $message = wp_kses_post($_POST['message']);
        $message_type = sanitize_text_field($_POST['message_type'] ?? 'text');
        $is_priority = isset($_POST['is_priority']);
        
        $message_id = $this->send_message($conversation_id, get_current_user_id(), $message, $message_type, $is_priority);
        
        if ($message_id) {
            wp_send_json_success(array(
                'message_id' => $message_id,
                'message' => __('Message sent successfully', UNIVGA_TEXT_DOMAIN)
            ));
        } else {
            wp_send_json_error(__('Failed to send message', UNIVGA_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX: Get conversations
     */
    public function ajax_get_conversations() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $page = intval($_POST['page'] ?? 1);
        $archived = isset($_POST['archived']) && $_POST['archived'];
        
        $conversations = $this->get_conversations($org_id, $page, 20, $archived);
        
        wp_send_json_success($conversations);
    }
    
    /**
     * AJAX: Get conversation messages
     */
    public function ajax_get_conversation_messages() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $conversation_id = intval($_POST['conversation_id']);
        $page = intval($_POST['page'] ?? 1);
        
        $messages = $this->get_conversation_messages($conversation_id, get_current_user_id(), $page);
        
        if ($messages !== false) {
            wp_send_json_success($messages);
        } else {
            wp_send_json_error(__('Access denied', UNIVGA_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX: Mark conversation as read
     */
    public function ajax_mark_conversation_read() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $conversation_id = intval($_POST['conversation_id']);
        
        $result = $this->mark_conversation_read($conversation_id, get_current_user_id());
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to mark as read', UNIVGA_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX: Archive conversation
     */
    public function ajax_archive_conversation() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $conversation_id = intval($_POST['conversation_id']);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'univga_conversations',
            array('is_archived' => 1),
            array('id' => $conversation_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to archive conversation', UNIVGA_TEXT_DOMAIN));
        }
    }
}

// Initialize the messaging system
add_action('init', function() {
    UNIVGA_Internal_Messaging::getInstance();
});