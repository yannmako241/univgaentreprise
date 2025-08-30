<?php

/**
 * Tutor LMS integration wrapper
 */
class UNIVGA_Tutor {
    
    /**
     * Enroll user in course
     */
    public static function enroll($user_id, $course_id, $source = 'org') {
        // Check if already enrolled
        if (tutor_utils()->is_enrolled($course_id, $user_id)) {
            return true;
        }
        
        // Use Tutor's enrollment function
        return tutor_utils()->do_enroll($course_id, 0, $user_id);
    }
    
    /**
     * Unenroll user from course
     */
    public static function unenroll($user_id, $course_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'tutor_enrollments',
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
            ),
            array('%d', '%d')
        ) !== false;
    }
    
    /**
     * Get user course progress
     */
    public static function get_user_course_progress($user_id, $course_id) {
        return tutor_utils()->get_course_completed_percent($course_id, $user_id);
    }
    
    /**
     * Get user course completion status
     */
    public static function is_course_completed($user_id, $course_id) {
        return tutor_utils()->is_completed_course($course_id, $user_id);
    }
    
    /**
     * Get user course certificate
     */
    public static function get_course_certificate($user_id, $course_id) {
        if (!self::is_course_completed($user_id, $course_id)) {
            return null;
        }
        
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tutor_gradebooks 
             WHERE user_id = %d AND course_id = %d AND grade_point >= 80",
            $user_id, $course_id
        ));
    }
    
    /**
     * Get organization member course statistics
     */
    public static function get_org_course_stats($org_id, $course_ids = null) {
        global $wpdb;
        
        // Get organization members
        $members = UNIVGA_Members::get_org_members($org_id, array('status' => 'active'));
        
        if (empty($members)) {
            return array();
        }
        
        $member_ids = wp_list_pluck($members, 'user_id');
        $member_placeholders = implode(',', array_fill(0, count($member_ids), '%d'));
        
        // Get course IDs if not provided
        if (!$course_ids) {
            $pools = UNIVGA_Seat_Pools::get_by_org($org_id);
            $course_ids = array();
            
            foreach ($pools as $pool) {
                $pool_courses = UNIVGA_Seat_Pools::get_pool_courses($pool);
                $course_ids = array_merge($course_ids, $pool_courses);
            }
            
            $course_ids = array_unique($course_ids);
        }
        
        if (empty($course_ids)) {
            return array();
        }
        
        $course_placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
        
        // Get enrollment data
        $enrollments = $wpdb->get_results($wpdb->prepare(
            "SELECT course_id, user_id, enrollment_date
             FROM {$wpdb->prefix}tutor_enrollments 
             WHERE user_id IN ($member_placeholders) AND course_id IN ($course_placeholders)",
            array_merge($member_ids, $course_ids)
        ), OBJECT_K);
        
        // Get completion data
        $completions = $wpdb->get_results($wpdb->prepare(
            "SELECT course_id, user_id, completion_date
             FROM {$wpdb->prefix}tutor_quiz_attempts 
             WHERE user_id IN ($member_placeholders) AND course_id IN ($course_placeholders) 
             AND attempt_status = 'passed'",
            array_merge($member_ids, $course_ids)
        ), OBJECT_K);
        
        $stats = array();
        
        foreach ($course_ids as $course_id) {
            $course = get_post($course_id);
            if (!$course) continue;
            
            $course_enrollments = array_filter($enrollments, function($e) use ($course_id) {
                return $e->course_id == $course_id;
            });
            
            $course_completions = array_filter($completions, function($c) use ($course_id) {
                return $c->course_id == $course_id;
            });
            
            $enrolled_count = count($course_enrollments);
            $completed_count = count($course_completions);
            
            $stats[$course_id] = array(
                'course_title' => $course->post_title,
                'enrolled_count' => $enrolled_count,
                'completed_count' => $completed_count,
                'completion_rate' => $enrolled_count > 0 ? ($completed_count / $enrolled_count) * 100 : 0,
                'avg_progress' => self::get_course_avg_progress($course_id, array_column($course_enrollments, 'user_id')),
            );
        }
        
        return $stats;
    }
    
    /**
     * Get average course progress for users
     */
    private static function get_course_avg_progress($course_id, $user_ids) {
        if (empty($user_ids)) {
            return 0;
        }
        
        $total_progress = 0;
        $count = 0;
        
        foreach ($user_ids as $user_id) {
            $progress = self::get_user_course_progress($user_id, $course_id);
            if ($progress !== false) {
                $total_progress += $progress;
                $count++;
            }
        }
        
        return $count > 0 ? $total_progress / $count : 0;
    }
    
    /**
     * Get user's last activity in course
     */
    public static function get_user_last_activity($user_id, $course_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(comment_date) 
             FROM {$wpdb->comments} 
             WHERE comment_post_ID = %d AND user_id = %d 
             AND comment_type = 'tutor_course_rating'",
            $course_id, $user_id
        ));
    }
    
    /**
     * Get member course details for dashboard
     */
    public static function get_member_course_details($user_id, $course_ids) {
        $details = array();
        
        foreach ($course_ids as $course_id) {
            $course = get_post($course_id);
            if (!$course) continue;
            
            $enrolled = tutor_utils()->is_enrolled($course_id, $user_id);
            $progress = $enrolled ? self::get_user_course_progress($user_id, $course_id) : 0;
            $completed = self::is_course_completed($user_id, $course_id);
            $certificate = $completed ? self::get_course_certificate($user_id, $course_id) : null;
            $last_activity = self::get_user_last_activity($user_id, $course_id);
            
            $details[$course_id] = array(
                'title' => $course->post_title,
                'enrolled' => $enrolled,
                'progress' => $progress,
                'completed' => $completed,
                'certificate' => $certificate,
                'last_activity' => $last_activity,
                'url' => get_permalink($course_id),
            );
        }
        
        return $details;
    }
}
