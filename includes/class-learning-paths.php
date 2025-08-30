<?php

/**
 * UNIVGA Learning Paths Class
 * Learning Path Builder with prerequisites and dependencies
 */

if (!defined('ABSPATH')) {
    exit;
}

class UNIVGA_Learning_Paths {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_univga_create_learning_path', array($this, 'create_learning_path'));
        add_action('wp_ajax_univga_get_learning_paths', array($this, 'get_learning_paths'));
        add_action('wp_ajax_univga_assign_learning_path', array($this, 'assign_learning_path'));
        add_action('wp_ajax_univga_check_prerequisites', array($this, 'check_prerequisites'));
        add_action('univga_course_completed', array($this, 'check_path_progression'), 10, 3);
    }
    
    /**
     * Create a new learning path
     */
    public function create_learning_path() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_admin_access')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $path_data = array(
            'org_id' => intval($_POST['org_id']),
            'name' => sanitize_text_field($_POST['path_name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'job_role' => sanitize_text_field($_POST['job_role']),
            'difficulty_level' => sanitize_text_field($_POST['difficulty_level']),
            'estimated_duration' => intval($_POST['estimated_duration']),
            'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_learning_paths',
            $path_data,
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s')
        );
        
        if ($result) {
            $path_id = $wpdb->insert_id;
            
            // Add courses to the path
            if (!empty($_POST['courses'])) {
                $this->add_courses_to_path($path_id, $_POST['courses']);
            }
            
            wp_send_json_success(array('path_id' => $path_id, 'message' => 'Learning path created successfully'));
        } else {
            wp_send_json_error('Failed to create learning path');
        }
    }
    
    /**
     * Add courses to learning path with order and prerequisites
     */
    private function add_courses_to_path($path_id, $courses) {
        global $wpdb;
        
        foreach ($courses as $order => $course) {
            $course_data = array(
                'path_id' => $path_id,
                'course_id' => intval($course['course_id']),
                'order_sequence' => intval($order) + 1,
                'is_required' => isset($course['is_required']) ? 1 : 0,
                'prerequisites' => !empty($course['prerequisites']) ? json_encode($course['prerequisites']) : null,
                'unlock_condition' => sanitize_text_field($course['unlock_condition'] ?? 'previous_complete'),
                'created_at' => current_time('mysql')
            );
            
            $wpdb->insert(
                $wpdb->prefix . 'univga_learning_path_courses',
                $course_data,
                array('%d', '%d', '%d', '%d', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Get learning paths for organization
     */
    public function get_learning_paths() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $org_id = intval($_POST['org_id']);
        global $wpdb;
        
        $paths = $wpdb->get_results($wpdb->prepare("
            SELECT lp.*, u.display_name as created_by_name,
                   COUNT(lpc.id) as course_count
            FROM {$wpdb->prefix}univga_learning_paths lp
            LEFT JOIN {$wpdb->users} u ON lp.created_by = u.ID
            LEFT JOIN {$wpdb->prefix}univga_learning_path_courses lpc ON lp.id = lpc.path_id
            WHERE lp.org_id = %d
            GROUP BY lp.id
            ORDER BY lp.created_at DESC
        ", $org_id));
        
        // Get course details for each path
        foreach ($paths as &$path) {
            $path->courses = $wpdb->get_results($wpdb->prepare("
                SELECT lpc.*, p.post_title as course_title
                FROM {$wpdb->prefix}univga_learning_path_courses lpc
                LEFT JOIN {$wpdb->posts} p ON lpc.course_id = p.ID
                WHERE lpc.path_id = %d
                ORDER BY lpc.order_sequence ASC
            ", $path->id));
        }
        
        wp_send_json_success($paths);
    }
    
    /**
     * Assign learning path to users
     */
    public function assign_learning_path() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        if (!current_user_can('univga_org_manage')) {
            wp_die(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        global $wpdb;
        
        $path_id = intval($_POST['path_id']);
        $user_ids = array_map('intval', $_POST['user_ids']);
        $assign_type = sanitize_text_field($_POST['assign_type']); // individual, team, organization
        
        $assignments_created = 0;
        
        foreach ($user_ids as $user_id) {
            $assignment_data = array(
                'path_id' => $path_id,
                'user_id' => $user_id,
                'assigned_by' => get_current_user_id(),
                'status' => 'assigned',
                'due_date' => !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null,
                'assigned_at' => current_time('mysql')
            );
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'univga_learning_path_assignments',
                $assignment_data,
                array('%d', '%d', '%d', '%s', '%s', '%s')
            );
            
            if ($result) {
                $assignments_created++;
                
                // Auto-enroll user in first available course
                $this->enroll_next_course($path_id, $user_id);
            }
        }
        
        wp_send_json_success(array(
            'assignments_created' => $assignments_created,
            'message' => sprintf('%d users assigned to learning path', $assignments_created)
        ));
    }
    
    /**
     * Check prerequisites for course access
     */
    public function check_prerequisites() {
        check_ajax_referer('univga_nonce', 'nonce');
        
        $user_id = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $path_id = intval($_POST['path_id']);
        
        $can_access = $this->user_can_access_course($user_id, $course_id, $path_id);
        
        wp_send_json_success(array(
            'can_access' => $can_access,
            'next_required' => $can_access ? null : $this->get_next_required_course($user_id, $path_id)
        ));
    }
    
    /**
     * Check if user can access a specific course in path
     */
    private function user_can_access_course($user_id, $course_id, $path_id) {
        global $wpdb;
        
        // Get course requirements in path
        $course_requirements = $wpdb->get_row($wpdb->prepare("
            SELECT prerequisites, unlock_condition, order_sequence
            FROM {$wpdb->prefix}univga_learning_path_courses
            WHERE path_id = %d AND course_id = %d
        ", $path_id, $course_id));
        
        if (!$course_requirements) {
            return false;
        }
        
        // Check if this is the first course (always accessible)
        if ($course_requirements->order_sequence == 1) {
            return true;
        }
        
        // Check unlock conditions
        switch ($course_requirements->unlock_condition) {
            case 'previous_complete':
                return $this->has_completed_previous_courses($user_id, $path_id, $course_requirements->order_sequence);
                
            case 'all_prerequisites':
                if ($course_requirements->prerequisites) {
                    $prerequisites = json_decode($course_requirements->prerequisites, true);
                    return $this->has_completed_prerequisites($user_id, $prerequisites);
                }
                return true;
                
            case 'immediate':
                return true;
                
            default:
                return false;
        }
    }
    
    /**
     * Check if user completed previous courses in sequence
     */
    private function has_completed_previous_courses($user_id, $path_id, $current_order) {
        global $wpdb;
        
        $previous_courses = $wpdb->get_results($wpdb->prepare("
            SELECT course_id
            FROM {$wpdb->prefix}univga_learning_path_courses
            WHERE path_id = %d AND order_sequence < %d AND is_required = 1
        ", $path_id, $current_order));
        
        foreach ($previous_courses as $course) {
            if (!$this->is_course_completed($user_id, $course->course_id)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if user completed specific prerequisites
     */
    private function has_completed_prerequisites($user_id, $prerequisites) {
        foreach ($prerequisites as $course_id) {
            if (!$this->is_course_completed($user_id, $course_id)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Check if user completed a course
     */
    private function is_course_completed($user_id, $course_id) {
        global $wpdb;
        
        $completed = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}univga_analytics_events
            WHERE user_id = %d AND course_id = %d AND event_type = 'course_completed'
        ", $user_id, $course_id));
        
        return $completed > 0;
    }
    
    /**
     * Handle course completion and check for path progression
     */
    public function check_path_progression($user_id, $course_id, $org_id) {
        global $wpdb;
        
        // Find active learning paths for this user
        $active_paths = $wpdb->get_results($wpdb->prepare("
            SELECT lpa.path_id
            FROM {$wpdb->prefix}univga_learning_path_assignments lpa
            WHERE lpa.user_id = %d AND lpa.status IN ('assigned', 'in_progress')
        ", $user_id));
        
        foreach ($active_paths as $path) {
            // Update path progress
            $this->update_path_progress($path->path_id, $user_id);
            
            // Enroll in next available course
            $this->enroll_next_course($path->path_id, $user_id);
        }
    }
    
    /**
     * Enroll user in next available course in path
     */
    private function enroll_next_course($path_id, $user_id) {
        global $wpdb;
        
        // Get next course that user can access
        $next_courses = $wpdb->get_results($wpdb->prepare("
            SELECT lpc.*
            FROM {$wpdb->prefix}univga_learning_path_courses lpc
            WHERE lpc.path_id = %d
            AND lpc.course_id NOT IN (
                SELECT course_id 
                FROM {$wpdb->prefix}univga_analytics_events 
                WHERE user_id = %d AND event_type IN ('course_started', 'course_completed')
            )
            ORDER BY lpc.order_sequence ASC
        ", $path_id, $user_id));
        
        foreach ($next_courses as $course) {
            if ($this->user_can_access_course($user_id, $course->course_id, $path_id)) {
                // Enroll user in course (integrate with Tutor LMS)
                if (function_exists('tutor_utils')) {
                    tutor_utils()->do_enroll($course->course_id, $user_id);
                }
                break; // Only enroll in one course at a time
            }
        }
    }
    
    /**
     * Update learning path progress
     */
    private function update_path_progress($path_id, $user_id) {
        global $wpdb;
        
        // Calculate progress percentage
        $total_courses = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}univga_learning_path_courses 
            WHERE path_id = %d AND is_required = 1
        ", $path_id));
        
        $completed_courses = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT lpc.course_id)
            FROM {$wpdb->prefix}univga_learning_path_courses lpc
            INNER JOIN {$wpdb->prefix}univga_analytics_events ae ON lpc.course_id = ae.course_id
            WHERE lpc.path_id = %d AND ae.user_id = %d 
            AND ae.event_type = 'course_completed' AND lpc.is_required = 1
        ", $path_id, $user_id));
        
        $progress = $total_courses > 0 ? ($completed_courses / $total_courses) * 100 : 0;
        $status = $progress == 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'assigned');
        
        // Update assignment progress
        $wpdb->update(
            $wpdb->prefix . 'univga_learning_path_assignments',
            array(
                'progress_percentage' => $progress,
                'status' => $status,
                'completed_at' => $progress == 100 ? current_time('mysql') : null,
                'updated_at' => current_time('mysql')
            ),
            array('path_id' => $path_id, 'user_id' => $user_id),
            array('%f', '%s', '%s', '%s'),
            array('%d', '%d')
        );
    }
    
    /**
     * Get personalized learning recommendations
     */
    public function get_recommendations($user_id, $org_id) {
        global $wpdb;
        
        // Get user's completed courses and skill gaps
        $completed_courses = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT course_id
            FROM {$wpdb->prefix}univga_analytics_events
            WHERE user_id = %d AND event_type = 'course_completed'
        ", $user_id));
        
        // Get recommended learning paths based on job role and performance
        $user_profile = get_user_meta($user_id, 'univga_profile', true);
        $job_role = $user_profile['job_role'] ?? '';
        
        $recommendations = $wpdb->get_results($wpdb->prepare("
            SELECT lp.*, COUNT(lpc.id) as course_count,
                   AVG(als.completion_rate) as avg_completion_rate
            FROM {$wpdb->prefix}univga_learning_paths lp
            LEFT JOIN {$wpdb->prefix}univga_learning_path_courses lpc ON lp.id = lpc.path_id
            LEFT JOIN {$wpdb->prefix}univga_analytics_summary als ON lpc.course_id = als.course_id
            WHERE lp.org_id = %d 
            AND lp.job_role = %s
            AND lp.id NOT IN (
                SELECT path_id 
                FROM {$wpdb->prefix}univga_learning_path_assignments 
                WHERE user_id = %d
            )
            GROUP BY lp.id
            ORDER BY avg_completion_rate DESC, course_count ASC
            LIMIT 5
        ", $org_id, $job_role, $user_id));
        
        return $recommendations;
    }
}