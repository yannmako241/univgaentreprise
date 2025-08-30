<?php

/**
 * UNIVGA Gamification Class
 * Gamification & Engagement Engine with points, badges, and leaderboards
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_Gamification {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX actions
        add_action('wp_ajax_univga_get_user_points', array($this, 'get_user_points'));
        add_action('wp_ajax_univga_get_leaderboard', array($this, 'get_leaderboard'));
        add_action('wp_ajax_univga_get_badges', array($this, 'get_badges'));
        add_action('wp_ajax_univga_create_badge', array($this, 'create_badge'));
        add_action('wp_ajax_univga_award_badge', array($this, 'award_badge'));
        
        // Hook into learning events
        add_action('univga_course_completed', array($this, 'award_course_completion_points'), 10, 3);
        add_action('univga_certification_earned', array($this, 'award_certification_points'), 10, 2);
        add_action('univga_learning_path_completed', array($this, 'award_path_completion_points'), 10, 3);
        add_action('univga_award_points', array($this, 'award_points'), 10, 3);
        
        // Badge criteria checking
        add_action('univga_points_updated', array($this, 'check_badge_criteria'), 10, 2);
    }
    
    /**
     * Award points to user
     */
    public function award_points($user_id, $point_type, $reference_id = null) {
        $point_values = array(
            'course_completed' => 100,
            'certification_earned' => 500,
            'learning_path_completed' => 1000,
            'first_login' => 25,
            'streak_7_days' => 200,
            'streak_30_days' => 500,
            'quiz_perfect_score' => 150,
            'mentor_session_completed' => 75,
            'forum_participation' => 10
        );
        
        $points = apply_filters('univga_point_values', $point_values);
        $award_points = $points[$point_type] ?? 50;
        
        global $wpdb;
        
        // Get user's organization(s)
        $org_ids = $wpdb->get_col($wpdb->prepare("
            SELECT org_id FROM {$wpdb->prefix}univga_org_members 
            WHERE user_id = %d AND status = 'active'
        ", $user_id));
        
        foreach ($org_ids as $org_id) {
            // Update or create user points record
            $existing = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}univga_user_points 
                WHERE user_id = %d AND org_id = %d
            ", $user_id, $org_id));
            
            if ($existing) {
                $new_total = $existing->points_earned + $award_points;
                $new_level = $this->calculate_level($new_total);
                
                $wpdb->update(
                    $wpdb->prefix . 'univga_user_points',
                    array(
                        'points_earned' => $new_total,
                        'level_id' => $new_level,
                        'last_activity' => current_time('mysql')
                    ),
                    array('user_id' => $user_id, 'org_id' => $org_id),
                    array('%d', '%d', '%s'),
                    array('%d', '%d')
                );
            } else {
                $level = $this->calculate_level($award_points);
                
                $wpdb->insert(
                    $wpdb->prefix . 'univga_user_points',
                    array(
                        'user_id' => $user_id,
                        'org_id' => $org_id,
                        'points_earned' => $award_points,
                        'level_id' => $level,
                        'last_activity' => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%d', '%s')
                );
            }
            
            // Trigger badge criteria check
            do_action('univga_points_updated', $user_id, $org_id);
            
            // Create notification
            $this->notify_points_earned($user_id, $org_id, $award_points, $point_type);
        }
        
        return true;
    }
    
    /**
     * Calculate user level based on points
     */
    private function calculate_level($points) {
        $levels = array(
            1 => 0,      // Novice
            2 => 200,    // Beginner
            3 => 500,    // Intermediate
            4 => 1000,   // Advanced
            5 => 2000,   // Expert
            6 => 4000,   // Master
            7 => 8000,   // Champion
            8 => 15000,  // Legend
            9 => 25000,  // Elite
            10 => 50000  // Ultimate
        );
        
        $level = 1;
        foreach ($levels as $l => $required_points) {
            if ($points >= $required_points) {
                $level = $l;
            } else {
                break;
            }
        }
        
        return $level;
    }
    
    /**
     * Get user points and level
     */
    public function get_user_points() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id']);
        
        global $wpdb;
        
        $user_points = $wpdb->get_row($wpdb->prepare("
            SELECT up.*, 
                   COUNT(ub.id) as badges_earned,
                   RANK() OVER (ORDER BY up.points_earned DESC) as leaderboard_rank
            FROM {$wpdb->prefix}univga_user_points up
            LEFT JOIN {$wpdb->prefix}univga_user_badges ub ON up.user_id = ub.user_id
            WHERE up.user_id = %d AND up.org_id = %d
            GROUP BY up.user_id, up.org_id
        ", $user_id, $org_id));
        
        if (!$user_points) {
            $user_points = (object) array(
                'points_earned' => 0,
                'level_id' => 1,
                'badges_earned' => 0,
                'leaderboard_rank' => null
            );
        }
        
        // Get level info
        $level_info = $this->get_level_info($user_points->level_id);
        $next_level = $this->get_level_info($user_points->level_id + 1);
        
        // Recent badges
        $recent_badges = $wpdb->get_results($wpdb->prepare("
            SELECT b.*, ub.earned_date
            FROM {$wpdb->prefix}univga_user_badges ub
            LEFT JOIN {$wpdb->prefix}univga_badges b ON ub.badge_id = b.id
            WHERE ub.user_id = %d AND b.org_id = %d
            ORDER BY ub.earned_date DESC
            LIMIT 5
        ", $user_id, $org_id));
        
        wp_send_json_success(array(
            'points' => $user_points,
            'level_info' => $level_info,
            'next_level' => $next_level,
            'recent_badges' => $recent_badges
        ));
    }
    
    /**
     * Get level information
     */
    private function get_level_info($level_id) {
        $levels = array(
            1 => array('name' => 'Novice', 'points_required' => 0, 'color' => '#8e8e93'),
            2 => array('name' => 'Beginner', 'points_required' => 200, 'color' => '#34c759'),
            3 => array('name' => 'Intermediate', 'points_required' => 500, 'color' => '#007aff'),
            4 => array('name' => 'Advanced', 'points_required' => 1000, 'color' => '#5856d6'),
            5 => array('name' => 'Expert', 'points_required' => 2000, 'color' => '#af52de'),
            6 => array('name' => 'Master', 'points_required' => 4000, 'color' => '#ff9500'),
            7 => array('name' => 'Champion', 'points_required' => 8000, 'color' => '#ff3b30'),
            8 => array('name' => 'Legend', 'points_required' => 15000, 'color' => '#ff2d92'),
            9 => array('name' => 'Elite', 'points_required' => 25000, 'color' => '#ffd60a'),
            10 => array('name' => 'Ultimate', 'points_required' => 50000, 'color' => '#30d158')
        );
        
        return $levels[$level_id] ?? $levels[1];
    }
    
    /**
     * Get leaderboard
     */
    public function get_leaderboard() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? 'all_time');
        $limit = intval($_POST['limit'] ?? 50);
        
        global $wpdb;
        
        $date_condition = '';
        switch ($timeframe) {
            case 'weekly':
                $date_condition = 'AND up.last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'monthly':
                $date_condition = 'AND up.last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }
        
        $leaderboard = $wpdb->get_results($wpdb->prepare("
            SELECT 
                up.*,
                u.display_name,
                u.user_email,
                COUNT(ub.id) as badges_count,
                ROW_NUMBER() OVER (ORDER BY up.points_earned DESC) as rank
            FROM {$wpdb->prefix}univga_user_points up
            LEFT JOIN {$wpdb->users} u ON up.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}univga_user_badges ub ON up.user_id = ub.user_id
            WHERE up.org_id = %d {$date_condition}
            GROUP BY up.user_id
            ORDER BY up.points_earned DESC
            LIMIT %d
        ", $org_id, $limit));
        
        wp_send_json_success($leaderboard);
    }
    
    /**
     * Create badge
     */
    public function create_badge() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $badge_data = array(
            'org_id' => intval($_POST['org_id']),
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'icon_url' => esc_url($_POST['icon_url'] ?? ''),
            'criteria' => json_encode($_POST['criteria']),
            'points_value' => intval($_POST['points_value']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_badges',
            $badge_data,
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'badge_id' => $wpdb->insert_id,
                'message' => 'Badge created successfully'
            ));
        } else {
            wp_send_json_error('Failed to create badge');
        }
    }
    
    /**
     * Get badges
     */
    public function get_badges() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        $user_id = intval($_POST['user_id'] ?? 0);
        
        global $wpdb;
        
        if ($user_id) {
            // Get badges for specific user
            $badges = $wpdb->get_results($wpdb->prepare("
                SELECT b.*, 
                       ub.earned_date,
                       CASE WHEN ub.id IS NOT NULL THEN 1 ELSE 0 END as earned
                FROM {$wpdb->prefix}univga_badges b
                LEFT JOIN {$wpdb->prefix}univga_user_badges ub ON b.id = ub.badge_id AND ub.user_id = %d
                WHERE b.org_id = %d AND b.is_active = 1
                ORDER BY earned DESC, b.points_value DESC
            ", $user_id, $org_id));
        } else {
            // Get all badges for organization
            $badges = $wpdb->get_results($wpdb->prepare("
                SELECT b.*,
                       COUNT(ub.id) as earned_count
                FROM {$wpdb->prefix}univga_badges b
                LEFT JOIN {$wpdb->prefix}univga_user_badges ub ON b.id = ub.badge_id
                WHERE b.org_id = %d
                GROUP BY b.id
                ORDER BY b.points_value DESC
            ", $org_id));
        }
        
        wp_send_json_success($badges);
    }
    
    /**
     * Award badge to user
     */
    public function award_badge() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_org_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $user_id = intval($_POST['user_id']);
        $badge_id = intval($_POST['badge_id']);
        
        $result = $this->award_badge_to_user($user_id, $badge_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Badge awarded successfully'));
        } else {
            wp_send_json_error('Failed to award badge or badge already earned');
        }
    }
    
    /**
     * Award badge to user (internal method)
     */
    private function award_badge_to_user($user_id, $badge_id) {
        global $wpdb;
        
        // Check if badge already earned
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}univga_user_badges 
            WHERE user_id = %d AND badge_id = %d
        ", $user_id, $badge_id));
        
        if ($existing > 0) {
            return false;
        }
        
        // Get badge info
        $badge = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_badges WHERE id = %d
        ", $badge_id));
        
        if (!$badge) {
            return false;
        }
        
        // Award badge
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_user_badges',
            array(
                'user_id' => $user_id,
                'badge_id' => $badge_id,
                'earned_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s')
        );
        
        if ($result) {
            // Update badge count
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}univga_user_points 
                SET badges_count = badges_count + 1 
                WHERE user_id = %d AND org_id = %d
            ", $user_id, $badge->org_id));
            
            // Award points if badge has point value
            if ($badge->points_value > 0) {
                $this->award_points($user_id, 'badge_earned', $badge_id);
            }
            
            // Send notification
            $this->notify_badge_earned($user_id, $badge);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check badge criteria when points are updated
     */
    public function check_badge_criteria($user_id, $org_id) {
        global $wpdb;
        
        // Get active badges for organization
        $badges = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_badges 
            WHERE org_id = %d AND is_active = 1
        ", $org_id));
        
        foreach ($badges as $badge) {
            // Skip if user already has this badge
            $has_badge = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}univga_user_badges 
                WHERE user_id = %d AND badge_id = %d
            ", $user_id, $badge->id));
            
            if ($has_badge > 0) {
                continue;
            }
            
            // Check criteria
            if ($this->user_meets_badge_criteria($user_id, $org_id, json_decode($badge->criteria, true))) {
                $this->award_badge_to_user($user_id, $badge->id);
            }
        }
    }
    
    /**
     * Check if user meets badge criteria
     */
    private function user_meets_badge_criteria($user_id, $org_id, $criteria) {
        global $wpdb;
        
        foreach ($criteria as $criterion) {
            switch ($criterion['type']) {
                case 'points_earned':
                    $user_points = $wpdb->get_var($wpdb->prepare("
                        SELECT points_earned FROM {$wpdb->prefix}univga_user_points 
                        WHERE user_id = %d AND org_id = %d
                    ", $user_id, $org_id));
                    
                    if ($user_points < $criterion['value']) {
                        return false;
                    }
                    break;
                    
                case 'courses_completed':
                    $completed_count = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(DISTINCT course_id) 
                        FROM {$wpdb->prefix}univga_analytics_events 
                        WHERE user_id = %d AND org_id = %d AND event_type = 'course_completed'
                    ", $user_id, $org_id));
                    
                    if ($completed_count < $criterion['value']) {
                        return false;
                    }
                    break;
                    
                case 'certifications_earned':
                    $cert_count = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) 
                        FROM {$wpdb->prefix}univga_user_certifications 
                        WHERE user_id = %d AND org_id = %d AND status = 'earned'
                    ", $user_id, $org_id));
                    
                    if ($cert_count < $criterion['value']) {
                        return false;
                    }
                    break;
                    
                case 'learning_streak':
                    // Check for consecutive days of activity
                    $streak_days = $this->calculate_learning_streak($user_id, $org_id);
                    if ($streak_days < $criterion['value']) {
                        return false;
                    }
                    break;
            }
        }
        
        return true;
    }
    
    /**
     * Calculate learning streak
     */
    private function calculate_learning_streak($user_id, $org_id) {
        global $wpdb;
        
        $activities = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT DATE(created_at) as activity_date
            FROM {$wpdb->prefix}univga_analytics_events
            WHERE user_id = %d AND org_id = %d
            ORDER BY activity_date DESC
        ", $user_id, $org_id));
        
        if (empty($activities)) {
            return 0;
        }
        
        $streak = 0;
        $today = date('Y-m-d');
        $expected_date = $today;
        
        foreach ($activities as $activity_date) {
            if ($activity_date === $expected_date) {
                $streak++;
                $expected_date = date('Y-m-d', strtotime($expected_date . ' -1 day'));
            } else {
                break;
            }
        }
        
        return $streak;
    }
    
    /**
     * Hook into course completion
     */
    public function award_course_completion_points($user_id, $course_id, $org_id) {
        $this->award_points($user_id, 'course_completed', $course_id);
    }
    
    /**
     * Hook into certification earned
     */
    public function award_certification_points($user_id, $certification_id) {
        $this->award_points($user_id, 'certification_earned', $certification_id);
    }
    
    /**
     * Hook into learning path completion
     */
    public function award_path_completion_points($user_id, $path_id, $org_id) {
        $this->award_points($user_id, 'learning_path_completed', $path_id);
    }
    
    /**
     * Notify user of points earned
     */
    private function notify_points_earned($user_id, $org_id, $points, $point_type) {
        $point_descriptions = array(
            'course_completed' => 'completing a course',
            'certification_earned' => 'earning a certification',
            'learning_path_completed' => 'completing a learning path',
            'first_login' => 'your first login',
            'streak_7_days' => '7-day learning streak',
            'streak_30_days' => '30-day learning streak'
        );
        
        $description = $point_descriptions[$point_type] ?? 'your activity';
        
        $title = sprintf(__('You earned %d points!', UNIVGA_TEXT_DOMAIN), $points);
        $message = sprintf(__('Great job! You earned %d points for %s.', UNIVGA_TEXT_DOMAIN), $points, $description);
        
        // Use notification system
        if (class_exists('UNIVGA_Notifications')) {
            $notifications = UNIVGA_Notifications::getInstance();
            $notifications->create_notification($user_id, $org_id, 'points_earned', $title, $message);
        }
    }
    
    /**
     * Notify user of badge earned
     */
    private function notify_badge_earned($user_id, $badge) {
        $title = sprintf(__('Badge Earned: %s', UNIVGA_TEXT_DOMAIN), $badge->name);
        $message = sprintf(__('Congratulations! You\'ve earned the "%s" badge. %s', UNIVGA_TEXT_DOMAIN), $badge->name, $badge->description);
        
        // Use notification system
        if (class_exists('UNIVGA_Notifications')) {
            $notifications = UNIVGA_Notifications::getInstance();
            $notifications->create_notification($user_id, $badge->org_id, 'badge_earned', $title, $message);
        }
    }
}