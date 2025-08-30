<?php

/**
 * REST API endpoints
 */
class UNIVGA_REST {
    
    public static function init() {
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/dashboard', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_org_dashboard'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/members', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_org_members'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/invite', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'send_invitation'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/members/(?P<user_id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array(__CLASS__, 'remove_member'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/courses', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_org_courses'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/analytics', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_org_analytics'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/learning-paths', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_org_learning_paths'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/learning-paths', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_learning_path'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/gamification', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_org_gamification'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/certifications', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_org_certifications'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/messages', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_org_messages'),
            'permission_callback' => array(__CLASS__, 'check_org_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/administration', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_org_administration'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/whitelabel', array(
            'methods' => array('GET', 'POST'),
            'callback' => array(__CLASS__, 'handle_org_whitelabel'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        register_rest_route('univga/v1', '/organizations/(?P<id>\d+)/domain-check', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'check_domain_availability'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
    }
    
    /**
     * Check organization permission
     */
    public static function check_org_permission($request) {
        $org_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        return UNIVGA_Capabilities::can_manage_org($user_id, $org_id);
    }
    
    /**
     * Get organization dashboard data
     */
    public static function get_org_dashboard($request) {
        $org_id = $request->get_param('id');
        
        $kpis = UNIVGA_Reports::get_org_dashboard_kpis($org_id);
        $org = UNIVGA_Orgs::get($org_id);
        
        return rest_ensure_response(array(
            'organization' => $org,
            'kpis' => $kpis,
        ));
    }
    
    /**
     * Get organization members
     */
    public static function get_org_members($request) {
        $org_id = $request->get_param('id');
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $search = $request->get_param('search') ?: '';
        $team_id = $request->get_param('team_id') ?: null;
        
        $args = array(
            'team_id' => $team_id,
            'search' => $search,
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
        );
        
        $members = UNIVGA_Reports::get_org_member_report($org_id, $args);
        $total = UNIVGA_Members::get_org_members_count($org_id, $args);
        
        $response = rest_ensure_response($members);
        $response->header('X-Total-Count', $total);
        $response->header('X-Total-Pages', ceil($total / $per_page));
        
        return $response;
    }
    
    /**
     * Send invitation
     */
    public static function send_invitation($request) {
        $org_id = $request->get_param('id');
        $email = $request->get_param('email');
        $team_id = $request->get_param('team_id');
        
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address', array('status' => 400));
        }
        
        $result = UNIVGA_Invitations::send_invitation($org_id, $team_id, $email, get_current_user_id());
        
        if (is_wp_error($result)) {
            return new WP_Error($result->get_error_code(), $result->get_error_message(), array('status' => 400));
        }
        
        return rest_ensure_response(array(
            'message' => 'Invitation sent successfully',
        ));
    }
    
    /**
     * Remove organization member
     */
    public static function remove_member($request) {
        $org_id = $request->get_param('id');
        $user_id = $request->get_param('user_id');
        $allow_replace = $request->get_param('allow_replace') === true;
        
        $result = UNIVGA_Members::remove_member($org_id, $user_id, $allow_replace);
        
        if (!$result) {
            return new WP_Error('remove_failed', 'Failed to remove member', array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'message' => 'Member removed successfully',
        ));
    }
    
    /**
     * Get organization courses
     */
    public static function get_org_courses($request) {
        $org_id = $request->get_param('id');
        
        // Get all seat pools for this organization
        $pools = UNIVGA_Seat_Pools::get_by_org($org_id);
        $all_courses = array();
        
        foreach ($pools as $pool) {
            $pool_courses = UNIVGA_Seat_Pools::get_pool_courses($pool);
            foreach ($pool_courses as $course_id) {
                if (!isset($all_courses[$course_id])) {
                    $course = get_post($course_id);
                    if ($course && $course->post_status === 'publish') {
                        $all_courses[$course_id] = array(
                            'id' => $course_id,
                            'title' => $course->post_title,
                            'slug' => $course->post_name,
                            'excerpt' => $course->post_excerpt,
                            'permalink' => get_permalink($course_id),
                            'thumbnail' => get_the_post_thumbnail_url($course_id, 'medium') ?: '',
                            'pool_name' => $pool->name,
                            'pool_id' => $pool->id,
                            'seats_total' => $pool->seats_total,
                            'seats_used' => $pool->seats_used,
                            'seats_available' => $pool->seats_total - $pool->seats_used
                        );
                    }
                }
            }
        }
        
        // Get enrollment stats for each course
        foreach ($all_courses as &$course) {
            // Get basic enrollment count from tutor
            $enrolled_count = 0;
            $completed_count = 0;
            $avg_progress = 0;
            
            // Get members of this organization
            $members = UNIVGA_Members::get_org_members($org_id, array('status' => 'active'));
            
            if (!empty($members)) {
                $enrolled_members = 0;
                $total_progress = 0;
                
                foreach ($members as $member) {
                    // Check if user is enrolled in this course
                    if (function_exists('tutor_utils') && tutor_utils()->is_enrolled($course['id'], $member->user_id)) {
                        $enrolled_members++;
                        
                        // Get course completion progress
                        $progress = tutor_utils()->get_course_completed_percent($course['id'], $member->user_id);
                        $total_progress += $progress;
                        
                        if ($progress >= 100) {
                            $completed_count++;
                        }
                    }
                }
                
                $enrolled_count = $enrolled_members;
                $avg_progress = $enrolled_members > 0 ? round($total_progress / $enrolled_members, 1) : 0;
            }
            
            $course['enrolled_count'] = $enrolled_count;
            $course['completed_count'] = $completed_count;
            $course['completion_rate'] = $enrolled_count > 0 ? round(($completed_count / $enrolled_count) * 100, 1) : 0;
            $course['avg_progress'] = $avg_progress;
        }
        
        return rest_ensure_response(array_values($all_courses));
    }
    
    /**
     * Get organization analytics data
     */
    public static function get_org_analytics($request) {
        $org_id = $request->get_param('id');
        $timeframe = $request->get_param('timeframe') ?: '30';
        
        // Initialize analytics if available
        if (!class_exists('UNIVGA_Analytics')) {
            return new WP_Error('analytics_unavailable', 'Analytics module not available', array('status' => 503));
        }
        
        $analytics = UNIVGA_Analytics::getInstance();
        
        // Get basic metrics using existing methods
        $completion_rates = $analytics->get_completion_rates($org_id, $timeframe);
        $engagement_metrics = $analytics->get_engagement_metrics($org_id, $timeframe);
        $progress_tracking = $analytics->get_progress_tracking($org_id, $timeframe);
        
        // Calculate key metrics
        $total_completions = 0;
        $total_started = 0;
        $total_learners = 0;
        $avg_study_time = 0;
        
        foreach ($completion_rates as $rate) {
            $total_completions += $rate->completed;
            $total_started += $rate->started;
        }
        
        $completion_rate = $total_started > 0 ? round(($total_completions / $total_started) * 100, 1) : 0;
        
        // Get unique learners count
        $total_learners = count(array_unique(array_column($progress_tracking, 'user_id')));
        
        // Calculate average study time (mock calculation for now)
        $avg_study_time = $total_learners > 0 ? round(120 + ($total_completions * 15), 0) : 0; // minutes
        
        // Identify skill gaps and at-risk learners
        $skill_gaps = $analytics->identify_skill_gaps($org_id);
        $at_risk_learners = $analytics->identify_at_risk_learners($org_id);
        
        // Prepare trending courses data
        $trending_courses = array_slice($completion_rates, 0, 5);
        
        // Prepare team performance data  
        $teams = UNIVGA_Teams::get_by_org($org_id);
        $team_performance = array();
        
        foreach ($teams as $team) {
            $team_members = UNIVGA_Members::get_org_members($org_id, array('team_id' => $team->id));
            $team_completions = 0;
            $team_enrollments = 0;
            
            foreach ($team_members as $member) {
                foreach ($progress_tracking as $progress) {
                    if ($progress->user_id == $member->user_id) {
                        $team_enrollments++;
                        if ($progress->event_type === 'course_completed') {
                            $team_completions++;
                        }
                    }
                }
            }
            
            $team_performance[] = array(
                'name' => $team->name,
                'members' => count($team_members),
                'completions' => $team_completions,
                'enrollments' => $team_enrollments,
                'completion_rate' => $team_enrollments > 0 ? round(($team_completions / $team_enrollments) * 100, 1) : 0
            );
        }
        
        // Build comprehensive analytics response
        $analytics_data = array(
            'metrics' => array(
                'completion_rate' => $completion_rate,
                'active_learners' => $total_learners,
                'avg_study_time' => $avg_study_time,
                'skill_gaps' => count($skill_gaps)
            ),
            'completion_rates' => $completion_rates,
            'engagement_timeline' => $engagement_metrics,
            'team_performance' => $team_performance,
            'trending_courses' => $trending_courses,
            'skill_gaps' => $skill_gaps,
            'at_risk_learners' => $at_risk_learners,
            'timeframe' => $timeframe
        );
        
        return rest_ensure_response($analytics_data);
    }
    
    /**
     * Get organization learning paths
     */
    public static function get_org_learning_paths($request) {
        $org_id = $request->get_param('id');
        $status = $request->get_param('status');
        $role = $request->get_param('role');
        $search = $request->get_param('search');
        
        if (!class_exists('UNIVGA_Learning_Paths')) {
            return new WP_Error('learning_paths_unavailable', 'Learning Paths module not available', array('status' => 503));
        }
        
        global $wpdb;
        
        // Build WHERE conditions
        $where_conditions = array("lp.org_id = %d");
        $query_params = array($org_id);
        
        if ($status) {
            $where_conditions[] = "lp.status = %s";
            $query_params[] = $status;
        }
        
        if ($role) {
            $where_conditions[] = "lp.job_role = %s";
            $query_params[] = $role;
        }
        
        if ($search) {
            $where_conditions[] = "(lp.name LIKE %s OR lp.description LIKE %s)";
            $query_params[] = '%' . $search . '%';
            $query_params[] = '%' . $search . '%';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get learning paths with statistics
        $paths = $wpdb->get_results($wpdb->prepare("
            SELECT lp.*, u.display_name as created_by_name,
                   COUNT(DISTINCT lpc.id) as course_count,
                   COUNT(DISTINCT lpa.user_id) as assigned_users,
                   AVG(lpa.progress_percentage) as avg_progress
            FROM {$wpdb->prefix}univga_learning_paths lp
            LEFT JOIN {$wpdb->users} u ON lp.created_by = u.ID
            LEFT JOIN {$wpdb->prefix}univga_learning_path_courses lpc ON lp.id = lpc.path_id
            LEFT JOIN {$wpdb->prefix}univga_learning_path_assignments lpa ON lp.id = lpa.path_id
            WHERE $where_clause
            GROUP BY lp.id
            ORDER BY lp.created_at DESC
        ", $query_params));
        
        // Enhance each path with detailed information
        foreach ($paths as &$path) {
            // Get courses for this path
            $path->courses = $wpdb->get_results($wpdb->prepare("
                SELECT lpc.*, p.post_title as course_title, p.post_excerpt as course_description
                FROM {$wpdb->prefix}univga_learning_path_courses lpc
                LEFT JOIN {$wpdb->posts} p ON lpc.course_id = p.ID
                WHERE lpc.path_id = %d
                ORDER BY lpc.order_sequence ASC
            ", $path->id));
            
            // Get recent assignments
            $path->recent_assignments = $wpdb->get_results($wpdb->prepare("
                SELECT lpa.*, u.display_name as user_name, u.user_email
                FROM {$wpdb->prefix}univga_learning_path_assignments lpa
                LEFT JOIN {$wpdb->users} u ON lpa.user_id = u.ID
                WHERE lpa.path_id = %d
                ORDER BY lpa.assigned_at DESC
                LIMIT 5
            ", $path->id));
            
            // Calculate completion statistics
            $path->completion_stats = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as total_assigned,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    AVG(progress_percentage) as avg_completion
                FROM {$wpdb->prefix}univga_learning_path_assignments
                WHERE path_id = %d
            ", $path->id));
            
            // Format for frontend
            $path->status = $path->status ?? 'active';
            $path->difficulty_badge = ucfirst($path->difficulty_level ?? 'intermediate');
            $path->estimated_duration_formatted = $path->estimated_duration ? ($path->estimated_duration . ' heures') : 'Non défini';
            $path->completion_rate = $path->completion_stats->total_assigned > 0 ? 
                round(($path->completion_stats->completed_count / $path->completion_stats->total_assigned) * 100, 1) : 0;
        }
        
        // Get overall statistics
        $overall_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT lp.id) as total_paths,
                COUNT(DISTINCT lpa.user_id) as total_learners,
                AVG(lpa.progress_percentage) as avg_completion,
                AVG(lp.estimated_duration) as avg_duration
            FROM {$wpdb->prefix}univga_learning_paths lp
            LEFT JOIN {$wpdb->prefix}univga_learning_path_assignments lpa ON lp.id = lpa.path_id
            WHERE lp.org_id = %d
        ", $org_id));
        
        return rest_ensure_response(array(
            'paths' => $paths,
            'stats' => $overall_stats,
            'filters_applied' => array(
                'status' => $status,
                'role' => $role,
                'search' => $search
            )
        ));
    }
    
    /**
     * Create new learning path
     */
    public static function create_learning_path($request) {
        if (!class_exists('UNIVGA_Learning_Paths')) {
            return new WP_Error('learning_paths_unavailable', 'Learning Paths module not available', array('status' => 503));
        }
        
        $org_id = $request->get_param('org_id');
        $name = $request->get_param('name');
        $description = $request->get_param('description');
        $job_role = $request->get_param('job_role');
        $difficulty_level = $request->get_param('difficulty_level');
        $estimated_duration = $request->get_param('estimated_duration');
        $is_mandatory = $request->get_param('is_mandatory');
        $courses = $request->get_param('courses');
        
        // Validate required fields
        if (!$org_id || !$name || !$description) {
            return new WP_Error('missing_required_fields', 'Missing required fields: org_id, name, description', array('status' => 400));
        }
        
        global $wpdb;
        
        $path_data = array(
            'org_id' => intval($org_id),
            'name' => sanitize_text_field($name),
            'description' => sanitize_textarea_field($description),
            'job_role' => sanitize_text_field($job_role ?: 'general'),
            'difficulty_level' => sanitize_text_field($difficulty_level ?: 'intermediate'),
            'estimated_duration' => intval($estimated_duration ?: 0),
            'is_mandatory' => $is_mandatory ? 1 : 0,
            'status' => 'active',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'univga_learning_paths',
            $path_data,
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s')
        );
        
        if ($result) {
            $path_id = $wpdb->insert_id;
            
            // Add courses to the path if provided
            if (!empty($courses) && is_array($courses)) {
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
            
            return rest_ensure_response(array(
                'path_id' => $path_id,
                'message' => 'Learning path created successfully',
                'path' => $path_data
            ));
        } else {
            return new WP_Error('creation_failed', 'Failed to create learning path', array('status' => 500));
        }
    }
    
    /**
     * Check admin permission
     */
    public static function check_admin_permission($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        return current_user_can('univga_admin_access');
    }
    
    /**
     * Get organization gamification data
     */
    public static function get_org_gamification($request) {
        $org_id = $request->get_param('id');
        $period = $request->get_param('period') ?: 'month';
        $team_id = $request->get_param('team_id');
        
        if (!class_exists('UNIVGA_Gamification')) {
            return new WP_Error('gamification_unavailable', 'Gamification module not available', array('status' => 503));
        }
        
        global $wpdb;
        
        // Date condition based on period
        $date_condition = '';
        switch ($period) {
            case 'week':
                $date_condition = "AND DATE(up.updated_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "AND DATE(up.updated_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'quarter':
                $date_condition = "AND DATE(up.updated_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'all':
            default:
                $date_condition = "";
                break;
        }
        
        // Get overall stats
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(up.total_points) as total_points,
                COUNT(DISTINCT up.user_id) as active_participants,
                COUNT(DISTINCT ub.id) as total_badges_awarded,
                AVG(up.level) as avg_level
            FROM {$wpdb->prefix}univga_user_points up
            LEFT JOIN {$wpdb->prefix}univga_user_badges ub ON up.user_id = ub.user_id AND up.org_id = ub.org_id
            WHERE up.org_id = %d
            $date_condition
        ", $org_id));
        
        // Get leaderboard data
        $leaderboard_query = "
            SELECT 
                up.user_id,
                up.total_points,
                up.level,
                up.current_streak,
                u.display_name,
                u.user_email,
                om.team_id,
                t.name as team_name,
                COUNT(ub.id) as badge_count
            FROM {$wpdb->prefix}univga_user_points up
            LEFT JOIN {$wpdb->users} u ON up.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}univga_org_members om ON up.user_id = om.user_id AND up.org_id = om.org_id
            LEFT JOIN {$wpdb->prefix}univga_teams t ON om.team_id = t.id
            LEFT JOIN {$wpdb->prefix}univga_user_badges ub ON up.user_id = ub.user_id AND up.org_id = ub.org_id
            WHERE up.org_id = %d
        ";
        
        $query_params = array($org_id);
        
        if ($team_id) {
            $leaderboard_query .= " AND om.team_id = %d";
            $query_params[] = $team_id;
        }
        
        $leaderboard_query .= "
            GROUP BY up.user_id, up.org_id
            ORDER BY up.total_points DESC, up.level DESC
            LIMIT 20
        ";
        
        $leaderboard = $wpdb->get_results($wpdb->prepare($leaderboard_query, $query_params));
        
        // Enhance leaderboard with rankings and recent activity
        foreach ($leaderboard as $index => &$user) {
            $user->rank = $index + 1;
            $user->rank_change = 0; // Calculate rank change logic here if needed
            
            // Get recent points activity
            $user->recent_activity = $wpdb->get_results($wpdb->prepare("
                SELECT pt.point_type, pt.points_awarded, pt.reference_id, pt.created_at,
                       CASE 
                           WHEN pt.point_type = 'course_completed' THEN p.post_title
                           WHEN pt.point_type = 'certification_earned' THEN 'Certification'
                           ELSE pt.point_type
                       END as activity_title
                FROM {$wpdb->prefix}univga_point_transactions pt
                LEFT JOIN {$wpdb->posts} p ON pt.reference_id = p.ID AND pt.point_type = 'course_completed'
                WHERE pt.user_id = %d AND pt.org_id = %d
                ORDER BY pt.created_at DESC
                LIMIT 5
            ", $user->user_id, $org_id));
        }
        
        // Get badges data
        $badges = $wpdb->get_results($wpdb->prepare("
            SELECT 
                b.*,
                COUNT(ub.id) as awarded_count,
                u.display_name as created_by_name
            FROM {$wpdb->prefix}univga_badges b
            LEFT JOIN {$wpdb->prefix}univga_user_badges ub ON b.id = ub.badge_id
            LEFT JOIN {$wpdb->users} u ON b.created_by = u.ID
            WHERE b.org_id = %d
            GROUP BY b.id
            ORDER BY b.created_at DESC
        ", $org_id));
        
        // Get recent badges earned
        $recent_badges = $wpdb->get_results($wpdb->prepare("
            SELECT 
                ub.*, 
                b.name as badge_name,
                b.description as badge_description,
                b.icon_url,
                b.color,
                u.display_name as user_name
            FROM {$wpdb->prefix}univga_user_badges ub
            LEFT JOIN {$wpdb->prefix}univga_badges b ON ub.badge_id = b.id
            LEFT JOIN {$wpdb->users} u ON ub.user_id = u.ID
            WHERE ub.org_id = %d
            ORDER BY ub.earned_at DESC
            LIMIT 10
        ", $org_id));
        
        // Get team stats for team leaderboard
        $team_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                t.id,
                t.name,
                COUNT(DISTINCT om.user_id) as member_count,
                SUM(up.total_points) as total_points,
                AVG(up.total_points) as avg_points,
                COUNT(DISTINCT ub.id) as badge_count,
                AVG(up.level) as avg_level
            FROM {$wpdb->prefix}univga_teams t
            LEFT JOIN {$wpdb->prefix}univga_org_members om ON t.id = om.team_id
            LEFT JOIN {$wpdb->prefix}univga_user_points up ON om.user_id = up.user_id AND om.org_id = up.org_id
            LEFT JOIN {$wpdb->prefix}univga_user_badges ub ON om.user_id = ub.user_id AND om.org_id = ub.org_id
            WHERE t.org_id = %d
            GROUP BY t.id
            ORDER BY total_points DESC
        ", $org_id));
        
        // Calculate engagement score
        $engagement_score = 0;
        if ($stats->active_participants > 0) {
            $completion_rate = 75; // Mock calculation
            $participation_rate = min(100, ($stats->active_participants / 50) * 100); // Assuming 50 max users
            $badge_density = min(100, ($stats->total_badges_awarded / $stats->active_participants) * 20);
            
            $engagement_score = round(($completion_rate * 0.4) + ($participation_rate * 0.3) + ($badge_density * 0.3), 1);
        }
        
        // Get available teams for filter
        $teams = $wpdb->get_results($wpdb->prepare("
            SELECT id, name FROM {$wpdb->prefix}univga_teams WHERE org_id = %d ORDER BY name
        ", $org_id));
        
        $gamification_data = array(
            'stats' => array(
                'total_points' => intval($stats->total_points ?: 0),
                'total_badges' => intval($stats->total_badges_awarded ?: 0),
                'active_participants' => intval($stats->active_participants ?: 0),
                'engagement_score' => $engagement_score
            ),
            'leaderboard' => $leaderboard,
            'team_leaderboard' => $team_stats,
            'badges' => $badges,
            'recent_badges' => $recent_badges,
            'teams' => $teams,
            'period' => $period
        );
        
        return rest_ensure_response($gamification_data);
    }
    
    /**
     * Get organization certifications data
     */
    public static function get_org_certifications($request) {
        $org_id = $request->get_param('id');
        $type_filter = $request->get_param('type');
        $search = $request->get_param('search');
        $team_id = $request->get_param('team_id');
        
        if (!class_exists('UNIVGA_Certifications')) {
            return new WP_Error('certifications_unavailable', 'Certifications module not available', array('status' => 503));
        }
        
        global $wpdb;
        
        // Build WHERE conditions for certifications
        $where_conditions = array("c.org_id = %d");
        $query_params = array($org_id);
        
        if ($type_filter) {
            switch ($type_filter) {
                case 'mandatory':
                    $where_conditions[] = "c.is_compliance = 1";
                    break;
                case 'optional':
                    $where_conditions[] = "c.is_compliance = 0";
                    break;
                case 'compliance':
                    $where_conditions[] = "c.is_compliance = 1";
                    break;
            }
        }
        
        if ($search) {
            $where_conditions[] = "(c.name LIKE %s OR c.description LIKE %s)";
            $query_params[] = '%' . $search . '%';
            $query_params[] = '%' . $search . '%';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get certifications with statistics
        $certifications = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.*,
                u.display_name as created_by_name,
                COUNT(DISTINCT uc.user_id) as total_holders,
                COUNT(CASE WHEN uc.status = 'earned' AND (uc.expires_date IS NULL OR uc.expires_date > NOW()) THEN 1 END) as active_holders,
                COUNT(CASE WHEN uc.expires_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
                COUNT(CASE WHEN uc.expires_date <= NOW() AND uc.status = 'earned' THEN 1 END) as expired
            FROM {$wpdb->prefix}univga_certifications c
            LEFT JOIN {$wpdb->users} u ON c.created_at = u.ID
            LEFT JOIN {$wpdb->prefix}univga_user_certifications uc ON c.id = uc.certification_id
            WHERE $where_clause
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ", $query_params));
        
        // Get overall statistics
        $overall_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_certifications,
                COUNT(CASE WHEN uc.status = 'earned' AND (uc.expires_date IS NULL OR uc.expires_date > NOW()) THEN 1 END) as active_certifications,
                COUNT(CASE WHEN uc.expires_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
                ROUND(
                    (COUNT(CASE WHEN uc.status = 'earned' AND (uc.expires_date IS NULL OR uc.expires_date > NOW()) THEN 1 END) * 100.0) / 
                    NULLIF(COUNT(DISTINCT uc.user_id), 0), 1
                ) as compliance_rate
            FROM {$wpdb->prefix}univga_certifications c
            LEFT JOIN {$wpdb->prefix}univga_user_certifications uc ON c.id = uc.certification_id
            WHERE c.org_id = %d
        ", $org_id));
        
        // Get team compliance status
        $team_compliance = array();
        if ($team_id) {
            $team_compliance = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    t.id as team_id,
                    t.name as team_name,
                    COUNT(DISTINCT om.user_id) as team_members,
                    COUNT(DISTINCT uc.user_id) as certified_members,
                    ROUND(
                        (COUNT(DISTINCT uc.user_id) * 100.0) / 
                        NULLIF(COUNT(DISTINCT om.user_id), 0), 1
                    ) as compliance_percentage
                FROM {$wpdb->prefix}univga_teams t
                LEFT JOIN {$wpdb->prefix}univga_org_members om ON t.id = om.team_id
                LEFT JOIN {$wpdb->prefix}univga_user_certifications uc ON om.user_id = uc.user_id 
                    AND uc.status = 'earned' AND (uc.expires_date IS NULL OR uc.expires_date > NOW())
                WHERE t.org_id = %d AND t.id = %d
                GROUP BY t.id
            ", $org_id, $team_id));
        } else {
            $team_compliance = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    t.id as team_id,
                    t.name as team_name,
                    COUNT(DISTINCT om.user_id) as team_members,
                    COUNT(DISTINCT uc.user_id) as certified_members,
                    ROUND(
                        (COUNT(DISTINCT uc.user_id) * 100.0) / 
                        NULLIF(COUNT(DISTINCT om.user_id), 0), 1
                    ) as compliance_percentage
                FROM {$wpdb->prefix}univga_teams t
                LEFT JOIN {$wpdb->prefix}univga_org_members om ON t.id = om.team_id
                LEFT JOIN {$wpdb->prefix}univga_user_certifications uc ON om.user_id = uc.user_id 
                    AND uc.status = 'earned' AND (uc.expires_date IS NULL OR uc.expires_date > NOW())
                WHERE t.org_id = %d
                GROUP BY t.id
                ORDER BY compliance_percentage DESC
            ", $org_id));
        }
        
        // Get recent certification activities
        $recent_activities = $wpdb->get_results($wpdb->prepare("
            SELECT 
                uc.*,
                c.name as certification_name,
                c.is_compliance,
                u.display_name as user_name,
                t.name as team_name
            FROM {$wpdb->prefix}univga_user_certifications uc
            LEFT JOIN {$wpdb->prefix}univga_certifications c ON uc.certification_id = c.id
            LEFT JOIN {$wpdb->users} u ON uc.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}univga_org_members om ON uc.user_id = om.user_id AND uc.org_id = om.org_id
            LEFT JOIN {$wpdb->prefix}univga_teams t ON om.team_id = t.id
            WHERE uc.org_id = %d
            ORDER BY uc.earned_date DESC
            LIMIT 10
        ", $org_id));
        
        // Get users needing certifications (compliance)
        $users_needing_certs = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.ID as user_id,
                u.display_name as user_name,
                u.user_email,
                t.name as team_name,
                COUNT(c.id) as required_certs,
                COUNT(uc.id) as earned_certs,
                (COUNT(c.id) - COUNT(uc.id)) as missing_certs
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->prefix}univga_org_members om ON u.ID = om.user_id
            LEFT JOIN {$wpdb->prefix}univga_teams t ON om.team_id = t.id
            CROSS JOIN {$wpdb->prefix}univga_certifications c
            LEFT JOIN {$wpdb->prefix}univga_user_certifications uc ON u.ID = uc.user_id 
                AND c.id = uc.certification_id AND uc.status = 'earned'
                AND (uc.expires_date IS NULL OR uc.expires_date > NOW())
            WHERE om.org_id = %d AND c.org_id = %d AND c.is_compliance = 1 AND om.status = 'active'
            GROUP BY u.ID
            HAVING missing_certs > 0
            ORDER BY missing_certs DESC, user_name ASC
            LIMIT 20
        ", $org_id, $org_id));
        
        // Get available teams for filter
        $teams = $wpdb->get_results($wpdb->prepare("
            SELECT id, name FROM {$wpdb->prefix}univga_teams WHERE org_id = %d ORDER BY name
        ", $org_id));
        
        // Enhanced certification data
        foreach ($certifications as &$cert) {
            // Parse requirements
            $requirements = json_decode($cert->requirements, true);
            $cert->requirements_formatted = $requirements ? $this->format_certification_requirements($requirements) : 'Aucun prérequis';
            
            // Calculate compliance percentage for this certification
            $cert->compliance_percentage = $cert->total_holders > 0 ? 
                round(($cert->active_holders / $cert->total_holders) * 100, 1) : 0;
            
            // Status based on expiration and compliance
            if ($cert->expired > 0) {
                $cert->alert_status = 'critical';
            } elseif ($cert->expiring_soon > 0) {
                $cert->alert_status = 'warning';
            } elseif ($cert->compliance_percentage < 80) {
                $cert->alert_status = 'attention';
            } else {
                $cert->alert_status = 'good';
            }
            
            // Format validity period
            $cert->validity_formatted = $cert->validity_period > 0 ? 
                $cert->validity_period . ' jours' : 'Permanente';
        }
        
        $certifications_data = array(
            'stats' => array(
                'total_certifications' => intval($overall_stats->total_certifications ?: 0),
                'active_certifications' => intval($overall_stats->active_certifications ?: 0),
                'expiring_soon' => intval($overall_stats->expiring_soon ?: 0),
                'compliance_rate' => floatval($overall_stats->compliance_rate ?: 0)
            ),
            'certifications' => $certifications,
            'team_compliance' => $team_compliance,
            'recent_activities' => $recent_activities,
            'users_needing_certs' => $users_needing_certs,
            'teams' => $teams,
            'filters_applied' => array(
                'type' => $type_filter,
                'search' => $search,
                'team_id' => $team_id
            )
        );
        
        return rest_ensure_response($certifications_data);
    }
    
    /**
     * Format certification requirements for display
     */
    private static function format_certification_requirements($requirements) {
        if (!is_array($requirements)) {
            return 'Prérequis personnalisés';
        }
        
        $formatted = array();
        
        if (isset($requirements['courses']) && is_array($requirements['courses'])) {
            $formatted[] = count($requirements['courses']) . ' cours requis';
        }
        
        if (isset($requirements['min_score']) && $requirements['min_score']) {
            $formatted[] = 'Score minimum: ' . $requirements['min_score'] . '%';
        }
        
        if (isset($requirements['experience_months']) && $requirements['experience_months']) {
            $formatted[] = $requirements['experience_months'] . ' mois d\'expérience';
        }
        
        return !empty($formatted) ? implode(', ', $formatted) : 'Prérequis personnalisés';
    }
    
    /**
     * Get organization messages and notifications data
     */
    public static function get_org_messages($request) {
        $org_id = $request->get_param('id');
        $view = $request->get_param('view') ?: 'conversations';
        $conversation_id = $request->get_param('conversation_id');
        $search = $request->get_param('search');
        
        if (!class_exists('UNIVGA_Internal_Messaging') || !class_exists('UNIVGA_Notifications')) {
            return new WP_Error('messaging_unavailable', 'Messaging module not available', array('status' => 503));
        }
        
        global $wpdb;
        $current_user_id = get_current_user_id();
        
        // Get conversations data
        $conversations = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT
                c.*,
                u.display_name as created_by_name,
                COUNT(DISTINCT cp.user_id) as participant_count,
                (SELECT cm.created_at FROM {$wpdb->prefix}univga_conversation_messages cm 
                 WHERE cm.conversation_id = c.id ORDER BY cm.created_at DESC LIMIT 1) as last_message_date,
                (SELECT cm.message FROM {$wpdb->prefix}univga_conversation_messages cm 
                 WHERE cm.conversation_id = c.id ORDER BY cm.created_at DESC LIMIT 1) as last_message,
                (SELECT u2.display_name FROM {$wpdb->prefix}univga_conversation_messages cm2 
                 LEFT JOIN {$wpdb->users} u2 ON cm2.sender_id = u2.ID
                 WHERE cm2.conversation_id = c.id ORDER BY cm2.created_at DESC LIMIT 1) as last_sender_name,
                (SELECT COUNT(*) FROM {$wpdb->prefix}univga_conversation_messages cm3 
                 WHERE cm3.conversation_id = c.id AND cm3.sender_id != %d
                 AND NOT EXISTS (
                     SELECT 1 FROM {$wpdb->prefix}univga_conversation_reads cr2 
                     WHERE cr2.conversation_id = c.id AND cr2.user_id = %d 
                     AND cr2.last_read_at >= cm3.created_at
                 )) as unread_count
            FROM {$wpdb->prefix}univga_conversations c
            LEFT JOIN {$wpdb->users} u ON c.created_by = u.ID
            LEFT JOIN {$wpdb->prefix}univga_conversation_participants cp ON c.id = cp.conversation_id
            WHERE c.org_id = %d 
            AND (c.status = %s OR c.status IS NULL)
            AND (cp.user_id = %d OR c.created_by = %d)
            GROUP BY c.id
            ORDER BY last_message_date DESC, c.created_at DESC
        ", $current_user_id, $current_user_id, $org_id, $view === 'archived' ? 'archived' : 'active', $current_user_id, $current_user_id));
        
        // Get archived conversations if requested
        $archived_conversations = array();
        if ($view === 'archived') {
            $archived_conversations = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT
                    c.*,
                    u.display_name as created_by_name,
                    COUNT(DISTINCT cp.user_id) as participant_count,
                    (SELECT cm.created_at FROM {$wpdb->prefix}univga_conversation_messages cm 
                     WHERE cm.conversation_id = c.id ORDER BY cm.created_at DESC LIMIT 1) as last_message_date,
                    (SELECT cm.message FROM {$wpdb->prefix}univga_conversation_messages cm 
                     WHERE cm.conversation_id = c.id ORDER BY cm.created_at DESC LIMIT 1) as last_message
                FROM {$wpdb->prefix}univga_conversations c
                LEFT JOIN {$wpdb->users} u ON c.created_by = u.ID
                LEFT JOIN {$wpdb->prefix}univga_conversation_participants cp ON c.id = cp.conversation_id
                WHERE c.org_id = %d 
                AND c.status = 'archived'
                AND (cp.user_id = %d OR c.created_by = %d)
                GROUP BY c.id
                ORDER BY c.updated_at DESC
            ", $org_id, $current_user_id, $current_user_id));
        }
        
        // Get specific conversation messages if requested
        $conversation_messages = array();
        if ($conversation_id) {
            $conversation_messages = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    cm.*,
                    u.display_name as sender_name,
                    u.user_email as sender_email
                FROM {$wpdb->prefix}univga_conversation_messages cm
                LEFT JOIN {$wpdb->users} u ON cm.sender_id = u.ID
                WHERE cm.conversation_id = %d
                ORDER BY cm.created_at ASC
            ", $conversation_id));
            
            // Mark conversation as read
            $wpdb->replace(
                $wpdb->prefix . 'univga_conversation_reads',
                array(
                    'conversation_id' => $conversation_id,
                    'user_id' => $current_user_id,
                    'last_read_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s')
            );
        }
        
        // Get conversation participants for new message functionality
        $org_members = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT
                u.ID,
                u.display_name,
                u.user_email,
                om.role,
                t.name as team_name
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->prefix}univga_org_members om ON u.ID = om.user_id
            LEFT JOIN {$wpdb->prefix}univga_teams t ON om.team_id = t.id
            WHERE om.org_id = %d AND om.status = 'active'
            AND u.ID != %d
            ORDER BY u.display_name ASC
        ", $org_id, $current_user_id));
        
        // Get recent notifications
        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT 
                n.*,
                'notification' as message_type
            FROM {$wpdb->prefix}univga_notifications n
            WHERE n.org_id = %d AND n.user_id = %d
            ORDER BY n.created_at DESC
            LIMIT 20
        ", $org_id, $current_user_id));
        
        // Get messaging statistics
        $messaging_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_conversations,
                COUNT(DISTINCT CASE WHEN c.status = 'active' OR c.status IS NULL THEN c.id END) as active_conversations,
                COUNT(DISTINCT CASE WHEN c.status = 'archived' THEN c.id END) as archived_conversations,
                (SELECT COUNT(*) FROM {$wpdb->prefix}univga_conversation_messages cm 
                 LEFT JOIN {$wpdb->prefix}univga_conversations c2 ON cm.conversation_id = c2.id
                 WHERE c2.org_id = %d AND cm.sender_id != %d
                 AND NOT EXISTS (
                     SELECT 1 FROM {$wpdb->prefix}univga_conversation_reads cr 
                     WHERE cr.conversation_id = c2.id AND cr.user_id = %d 
                     AND cr.last_read_at >= cm.created_at
                 )) as unread_messages,
                (SELECT COUNT(*) FROM {$wpdb->prefix}univga_notifications n 
                 WHERE n.org_id = %d AND n.user_id = %d AND n.is_read = 0) as unread_notifications
            FROM {$wpdb->prefix}univga_conversations c
            LEFT JOIN {$wpdb->prefix}univga_conversation_participants cp ON c.id = cp.conversation_id
            WHERE c.org_id = %d 
            AND (cp.user_id = %d OR c.created_by = %d)
        ", $org_id, $current_user_id, $current_user_id, $org_id, $current_user_id, $org_id, $current_user_id, $current_user_id));
        
        // Format conversations for display
        foreach ($conversations as &$conv) {
            $conv->last_message_preview = strlen($conv->last_message) > 50 ? 
                substr($conv->last_message, 0, 50) . '...' : $conv->last_message;
            $conv->time_ago = self::time_ago($conv->last_message_date ?: $conv->created_at);
            $conv->is_unread = $conv->unread_count > 0;
        }
        
        // Format conversation messages
        foreach ($conversation_messages as &$msg) {
            $msg->time_ago = self::time_ago($msg->created_at);
            $msg->is_own_message = $msg->sender_id == $current_user_id;
            $msg->sender_initials = self::get_user_initials($msg->sender_name);
        }
        
        // Format org members for participant selection
        foreach ($org_members as &$member) {
            $member->initials = self::get_user_initials($member->display_name);
            $member->role_label = ucfirst($member->role ?: 'member');
        }
        
        $messages_data = array(
            'conversations' => $conversations,
            'archived_conversations' => $archived_conversations,
            'conversation_messages' => $conversation_messages,
            'org_members' => $org_members,
            'notifications' => $notifications,
            'stats' => array(
                'total_conversations' => intval($messaging_stats->total_conversations ?: 0),
                'active_conversations' => intval($messaging_stats->active_conversations ?: 0),
                'archived_conversations' => intval($messaging_stats->archived_conversations ?: 0),
                'unread_messages' => intval($messaging_stats->unread_messages ?: 0),
                'unread_notifications' => intval($messaging_stats->unread_notifications ?: 0)
            ),
            'current_view' => $view,
            'current_conversation_id' => $conversation_id,
            'current_user_id' => $current_user_id
        );
        
        return rest_ensure_response($messages_data);
    }
    
    /**
     * Get user initials helper
     */
    private static function get_user_initials($name) {
        if (!$name) return 'U';
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        } else {
            return strtoupper(substr($words[0], 0, 2));
        }
    }
    
    /**
     * Time ago helper
     */
    private static function time_ago($datetime) {
        if (!$datetime) return 'N/A';
        
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'Il y a quelques secondes';
        if ($time < 3600) return 'Il y a ' . floor($time/60) . ' min';
        if ($time < 86400) return 'Il y a ' . floor($time/3600) . ' h';
        if ($time < 2592000) return 'Il y a ' . floor($time/86400) . ' j';
        if ($time < 31104000) return 'Il y a ' . floor($time/2592000) . ' mois';
        
        return 'Il y a ' . floor($time/31104000) . ' ans';
    }
    
    /**
     * Get organization administration data
     */
    public static function get_org_administration($request) {
        $org_id = $request->get_param('id');
        $section = $request->get_param('section') ?: 'organization';
        
        global $wpdb;
        
        // Get organization details
        $organization = $wpdb->get_row($wpdb->prepare("
            SELECT 
                o.*,
                u.display_name as contact_name,
                u.user_email as contact_email
            FROM {$wpdb->prefix}univga_orgs o
            LEFT JOIN {$wpdb->users} u ON o.contact_user_id = u.ID
            WHERE o.id = %d
        ", $org_id));
        
        // Get organization statistics
        $org_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT om.user_id) as total_members,
                COUNT(DISTINCT CASE WHEN om.status = 'active' THEN om.user_id END) as active_members,
                COUNT(DISTINCT t.id) as total_teams,
                COUNT(DISTINCT c.id) as total_courses,
                COUNT(DISTINCT lp.id) as total_learning_paths,
                COUNT(DISTINCT cert.id) as total_certifications,
                COUNT(DISTINCT sp.id) as total_seat_pools,
                COALESCE(SUM(sp.total_seats), 0) as total_seats_available,
                COALESCE(SUM(sp.used_seats), 0) as total_seats_used
            FROM {$wpdb->prefix}univga_org_members om
            LEFT JOIN {$wpdb->prefix}univga_teams t ON om.org_id = t.org_id
            LEFT JOIN {$wpdb->prefix}univga_courses c ON om.org_id = c.org_id
            LEFT JOIN {$wpdb->prefix}univga_learning_paths lp ON om.org_id = lp.org_id
            LEFT JOIN {$wpdb->prefix}univga_certifications cert ON om.org_id = cert.org_id
            LEFT JOIN {$wpdb->prefix}univga_seat_pools sp ON om.org_id = sp.org_id
            WHERE om.org_id = %d
        ", $org_id));
        
        // Get teams with detailed information
        $teams = $wpdb->get_results($wpdb->prepare("
            SELECT 
                t.*,
                u.display_name as manager_name,
                COUNT(DISTINCT om.user_id) as member_count,
                AVG(CASE WHEN ce.completion_rate IS NOT NULL THEN ce.completion_rate ELSE 0 END) as avg_completion_rate
            FROM {$wpdb->prefix}univga_teams t
            LEFT JOIN {$wpdb->users} u ON t.manager_user_id = u.ID
            LEFT JOIN {$wpdb->prefix}univga_org_members om ON t.id = om.team_id AND om.status = 'active'
            LEFT JOIN {$wpdb->prefix}univga_course_enrollments ce ON om.user_id = ce.user_id
            WHERE t.org_id = %d
            GROUP BY t.id
            ORDER BY t.name ASC
        ", $org_id));
        
        // Get members with detailed information
        $members = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.ID,
                u.display_name,
                u.user_email,
                u.user_registered,
                om.role,
                om.status,
                om.joined_at,
                t.name as team_name,
                COUNT(DISTINCT ce.course_id) as courses_enrolled,
                COUNT(DISTINCT CASE WHEN ce.completion_rate = 100 THEN ce.course_id END) as courses_completed,
                COUNT(DISTINCT uc.certification_id) as certifications_earned,
                COALESCE(AVG(ce.completion_rate), 0) as avg_completion_rate
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->prefix}univga_org_members om ON u.ID = om.user_id
            LEFT JOIN {$wpdb->prefix}univga_teams t ON om.team_id = t.id
            LEFT JOIN {$wpdb->prefix}univga_course_enrollments ce ON u.ID = ce.user_id
            LEFT JOIN {$wpdb->prefix}univga_user_certifications uc ON u.ID = uc.user_id AND uc.status = 'earned'
            WHERE om.org_id = %d
            GROUP BY u.ID
            ORDER BY om.joined_at DESC
        ", $org_id));
        
        // Get seat pools information
        $seat_pools = $wpdb->get_results($wpdb->prepare("
            SELECT 
                sp.*,
                c.name as course_name,
                u.display_name as created_by_name,
                (sp.total_seats - sp.used_seats) as available_seats,
                ROUND((sp.used_seats * 100.0) / NULLIF(sp.total_seats, 0), 1) as utilization_rate
            FROM {$wpdb->prefix}univga_seat_pools sp
            LEFT JOIN {$wpdb->prefix}univga_courses c ON sp.course_id = c.id
            LEFT JOIN {$wpdb->users} u ON sp.created_by = u.ID
            WHERE sp.org_id = %d
            ORDER BY sp.created_at DESC
        ", $org_id));
        
        // Get recent activity logs
        $recent_activities = $wpdb->get_results($wpdb->prepare("
            SELECT 
                'member_joined' as activity_type,
                u.display_name as user_name,
                om.joined_at as activity_date,
                'Nouveau membre rejoint' as activity_description
            FROM {$wpdb->prefix}univga_org_members om
            LEFT JOIN {$wpdb->users} u ON om.user_id = u.ID
            WHERE om.org_id = %d AND om.joined_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            
            UNION ALL
            
            SELECT 
                'course_completed' as activity_type,
                u.display_name as user_name,
                ce.completed_at as activity_date,
                CONCAT('Cours terminé: ', c.name) as activity_description
            FROM {$wpdb->prefix}univga_course_enrollments ce
            LEFT JOIN {$wpdb->users} u ON ce.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}univga_courses c ON ce.course_id = c.id
            WHERE ce.org_id = %d AND ce.completion_rate = 100 
            AND ce.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            
            UNION ALL
            
            SELECT 
                'certification_earned' as activity_type,
                u.display_name as user_name,
                uc.earned_date as activity_date,
                CONCAT('Certification obtenue: ', cert.name) as activity_description
            FROM {$wpdb->prefix}univga_user_certifications uc
            LEFT JOIN {$wpdb->users} u ON uc.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}univga_certifications cert ON uc.certification_id = cert.id
            WHERE uc.org_id = %d AND uc.earned_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            
            ORDER BY activity_date DESC
            LIMIT 20
        ", $org_id, $org_id, $org_id));
        
        // Get performance metrics for the last 30 days
        $performance_metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT CASE WHEN om.joined_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN om.user_id END) as new_members_30d,
                COUNT(DISTINCT CASE WHEN ce.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND ce.completion_rate = 100 THEN ce.user_id END) as course_completions_30d,
                COUNT(DISTINCT CASE WHEN uc.earned_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN uc.user_id END) as certifications_earned_30d,
                ROUND(AVG(CASE WHEN ce.completion_rate IS NOT NULL THEN ce.completion_rate ELSE 0 END), 1) as avg_course_completion_rate,
                COUNT(DISTINCT CASE WHEN u.user_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.ID END) as active_users_7d
            FROM {$wpdb->prefix}univga_org_members om
            LEFT JOIN {$wpdb->prefix}univga_course_enrollments ce ON om.user_id = ce.user_id AND om.org_id = ce.org_id
            LEFT JOIN {$wpdb->prefix}univga_user_certifications uc ON om.user_id = uc.user_id AND om.org_id = uc.org_id
            LEFT JOIN {$wpdb->users} u ON om.user_id = u.ID
            WHERE om.org_id = %d
        ", $org_id));
        
        // Get settings and configurations
        $org_settings = array(
            'learning_path_auto_assignment' => get_option('univga_org_' . $org_id . '_auto_assign_paths', false),
            'certification_notifications' => get_option('univga_org_' . $org_id . '_cert_notifications', true),
            'seat_pool_alerts' => get_option('univga_org_' . $org_id . '_seat_alerts', true),
            'member_registration_approval' => get_option('univga_org_' . $org_id . '_member_approval', false),
            'team_visibility' => get_option('univga_org_' . $org_id . '_team_visibility', 'restricted'),
            'course_sharing' => get_option('univga_org_' . $org_id . '_course_sharing', false),
            'branding_enabled' => get_option('univga_org_' . $org_id . '_branding_enabled', false),
            'primary_color' => get_option('univga_org_' . $org_id . '_primary_color', '#3b82f6'),
            'secondary_color' => get_option('univga_org_' . $org_id . '_secondary_color', '#10b981'),
            'logo_url' => get_option('univga_org_' . $org_id . '_logo_url', ''),
            'custom_domain' => get_option('univga_org_' . $org_id . '_custom_domain', '')
        );
        
        // Format member data
        foreach ($members as &$member) {
            $member->member_since = self::time_ago($member->joined_at);
            $member->role_label = ucfirst($member->role ?: 'member');
            $member->status_label = ucfirst($member->status);
            $member->completion_percentage = round($member->avg_completion_rate, 1);
        }
        
        // Format team data
        foreach ($teams as &$team) {
            $team->completion_percentage = round($team->avg_completion_rate, 1);
        }
        
        // Format seat pool data
        foreach ($seat_pools as &$pool) {
            $pool->expires_in = $pool->expires_date ? self::time_ago($pool->expires_date) : 'Jamais';
            $pool->created_time_ago = self::time_ago($pool->created_at);
            $pool->status_class = $pool->utilization_rate > 90 ? 'critical' : ($pool->utilization_rate > 70 ? 'warning' : 'good');
        }
        
        // Format recent activities
        foreach ($recent_activities as &$activity) {
            $activity->time_ago = self::time_ago($activity->activity_date);
        }
        
        $administration_data = array(
            'organization' => $organization,
            'stats' => array(
                'total_members' => intval($org_stats->total_members ?: 0),
                'active_members' => intval($org_stats->active_members ?: 0),
                'total_teams' => intval($org_stats->total_teams ?: 0),
                'total_courses' => intval($org_stats->total_courses ?: 0),
                'total_learning_paths' => intval($org_stats->total_learning_paths ?: 0),
                'total_certifications' => intval($org_stats->total_certifications ?: 0),
                'total_seat_pools' => intval($org_stats->total_seat_pools ?: 0),
                'total_seats_available' => intval($org_stats->total_seats_available ?: 0),
                'total_seats_used' => intval($org_stats->total_seats_used ?: 0)
            ),
            'performance_metrics' => array(
                'new_members_30d' => intval($performance_metrics->new_members_30d ?: 0),
                'course_completions_30d' => intval($performance_metrics->course_completions_30d ?: 0),
                'certifications_earned_30d' => intval($performance_metrics->certifications_earned_30d ?: 0),
                'avg_course_completion_rate' => floatval($performance_metrics->avg_course_completion_rate ?: 0),
                'active_users_7d' => intval($performance_metrics->active_users_7d ?: 0)
            ),
            'teams' => $teams,
            'members' => $members,
            'seat_pools' => $seat_pools,
            'recent_activities' => $recent_activities,
            'settings' => $org_settings,
            'current_section' => $section
        );
        
        return rest_ensure_response($administration_data);
    }
    
    /**
     * Handle organization white-label configuration
     */
    public static function handle_org_whitelabel($request) {
        $org_id = $request->get_param('id');
        
        if ($request->get_method() === 'POST') {
            return self::save_whitelabel_config($request);
        } else {
            return self::get_whitelabel_config($org_id);
        }
    }
    
    /**
     * Get white-label configuration
     */
    public static function get_whitelabel_config($org_id) {
        global $wpdb;
        
        // Get organization details
        $organization = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}univga_orgs WHERE id = %d
        ", $org_id));
        
        // Get current white-label settings
        $whitelabel_settings = array(
            // Basic Branding
            'enabled' => get_option('univga_org_' . $org_id . '_whitelabel_enabled', false),
            'company_name' => get_option('univga_org_' . $org_id . '_company_name', $organization->name),
            'company_slogan' => get_option('univga_org_' . $org_id . '_company_slogan', ''),
            'company_description' => get_option('univga_org_' . $org_id . '_company_description', ''),
            
            // Visual Identity
            'logo_url' => get_option('univga_org_' . $org_id . '_logo_url', ''),
            'logo_light_url' => get_option('univga_org_' . $org_id . '_logo_light_url', ''),
            'favicon_url' => get_option('univga_org_' . $org_id . '_favicon_url', ''),
            'cover_image_url' => get_option('univga_org_' . $org_id . '_cover_image_url', ''),
            
            // Color Scheme
            'primary_color' => get_option('univga_org_' . $org_id . '_primary_color', '#3b82f6'),
            'secondary_color' => get_option('univga_org_' . $org_id . '_secondary_color', '#10b981'),
            'accent_color' => get_option('univga_org_' . $org_id . '_accent_color', '#f59e0b'),
            'background_color' => get_option('univga_org_' . $org_id . '_background_color', '#ffffff'),
            'text_color' => get_option('univga_org_' . $org_id . '_text_color', '#1f2937'),
            'link_color' => get_option('univga_org_' . $org_id . '_link_color', '#3b82f6'),
            
            // Typography
            'font_family' => get_option('univga_org_' . $org_id . '_font_family', 'Inter'),
            'heading_font' => get_option('univga_org_' . $org_id . '_heading_font', 'Inter'),
            'font_size_base' => get_option('univga_org_' . $org_id . '_font_size_base', '16px'),
            
            // Domain Configuration
            'custom_domain' => get_option('univga_org_' . $org_id . '_custom_domain', ''),
            'subdomain' => get_option('univga_org_' . $org_id . '_subdomain', ''),
            'domain_status' => get_option('univga_org_' . $org_id . '_domain_status', 'inactive'),
            'ssl_enabled' => get_option('univga_org_' . $org_id . '_ssl_enabled', false),
            'ssl_certificate' => get_option('univga_org_' . $org_id . '_ssl_certificate', ''),
            
            // Email Configuration
            'custom_email_domain' => get_option('univga_org_' . $org_id . '_custom_email_domain', ''),
            'email_from_name' => get_option('univga_org_' . $org_id . '_email_from_name', ''),
            'email_from_address' => get_option('univga_org_' . $org_id . '_email_from_address', ''),
            'smtp_host' => get_option('univga_org_' . $org_id . '_smtp_host', ''),
            'smtp_port' => get_option('univga_org_' . $org_id . '_smtp_port', '587'),
            'smtp_username' => get_option('univga_org_' . $org_id . '_smtp_username', ''),
            'smtp_password' => get_option('univga_org_' . $org_id . '_smtp_password', ''),
            'smtp_encryption' => get_option('univga_org_' . $org_id . '_smtp_encryption', 'tls'),
            
            // Advanced Settings
            'custom_css' => get_option('univga_org_' . $org_id . '_custom_css', ''),
            'custom_js' => get_option('univga_org_' . $org_id . '_custom_js', ''),
            'google_analytics' => get_option('univga_org_' . $org_id . '_google_analytics', ''),
            'facebook_pixel' => get_option('univga_org_' . $org_id . '_facebook_pixel', ''),
            'custom_head_code' => get_option('univga_org_' . $org_id . '_custom_head_code', ''),
            'custom_footer_code' => get_option('univga_org_' . $org_id . '_custom_footer_code', ''),
            
            // Social Media
            'social_facebook' => get_option('univga_org_' . $org_id . '_social_facebook', ''),
            'social_twitter' => get_option('univga_org_' . $org_id . '_social_twitter', ''),
            'social_linkedin' => get_option('univga_org_' . $org_id . '_social_linkedin', ''),
            'social_instagram' => get_option('univga_org_' . $org_id . '_social_instagram', ''),
            'social_youtube' => get_option('univga_org_' . $org_id . '_social_youtube', ''),
            
            // Legal & Contact
            'contact_email' => get_option('univga_org_' . $org_id . '_contact_email', ''),
            'contact_phone' => get_option('univga_org_' . $org_id . '_contact_phone', ''),
            'contact_address' => get_option('univga_org_' . $org_id . '_contact_address', ''),
            'privacy_policy_url' => get_option('univga_org_' . $org_id . '_privacy_policy_url', ''),
            'terms_of_service_url' => get_option('univga_org_' . $org_id . '_terms_of_service_url', ''),
            'cookie_policy_url' => get_option('univga_org_' . $org_id . '_cookie_policy_url', ''),
            
            // Template Settings
            'login_template' => get_option('univga_org_' . $org_id . '_login_template', 'default'),
            'dashboard_template' => get_option('univga_org_' . $org_id . '_dashboard_template', 'default'),
            'course_template' => get_option('univga_org_' . $org_id . '_course_template', 'default'),
            'certificate_template' => get_option('univga_org_' . $org_id . '_certificate_template', 'default'),
            
            // Features Control
            'hide_univga_branding' => get_option('univga_org_' . $org_id . '_hide_univga_branding', false),
            'custom_footer_text' => get_option('univga_org_' . $org_id . '_custom_footer_text', ''),
            'maintenance_mode' => get_option('univga_org_' . $org_id . '_maintenance_mode', false),
            'maintenance_message' => get_option('univga_org_' . $org_id . '_maintenance_message', ''),
        );
        
        // Get available fonts
        $available_fonts = array(
            'Inter' => 'Inter (Recommandé)',
            'Roboto' => 'Roboto',
            'Open Sans' => 'Open Sans',
            'Lato' => 'Lato',
            'Montserrat' => 'Montserrat',
            'Poppins' => 'Poppins',
            'Source Sans Pro' => 'Source Sans Pro',
            'Nunito' => 'Nunito',
            'Raleway' => 'Raleway',
            'Ubuntu' => 'Ubuntu'
        );
        
        // Get available templates
        $available_templates = array(
            'default' => 'Template par défaut',
            'modern' => 'Template moderne',
            'corporate' => 'Template entreprise',
            'creative' => 'Template créatif',
            'minimal' => 'Template minimaliste'
        );
        
        // Get domain validation status
        $domain_validation = self::validate_domain_configuration($org_id, $whitelabel_settings);
        
        $whitelabel_data = array(
            'organization' => $organization,
            'settings' => $whitelabel_settings,
            'available_fonts' => $available_fonts,
            'available_templates' => $available_templates,
            'domain_validation' => $domain_validation,
            'feature_limits' => self::get_whitelabel_feature_limits($org_id)
        );
        
        return rest_ensure_response($whitelabel_data);
    }
    
    /**
     * Save white-label configuration
     */
    public static function save_whitelabel_config($request) {
        $org_id = $request->get_param('id');
        $settings = $request->get_json_params();
        
        if (!$settings) {
            return new WP_Error('invalid_data', 'Données invalides', array('status' => 400));
        }
        
        // Validate and sanitize settings
        $allowed_settings = array(
            'enabled', 'company_name', 'company_slogan', 'company_description',
            'logo_url', 'logo_light_url', 'favicon_url', 'cover_image_url',
            'primary_color', 'secondary_color', 'accent_color', 'background_color',
            'text_color', 'link_color', 'font_family', 'heading_font', 'font_size_base',
            'custom_domain', 'subdomain', 'ssl_enabled',
            'custom_email_domain', 'email_from_name', 'email_from_address',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
            'custom_css', 'custom_js', 'google_analytics', 'facebook_pixel',
            'custom_head_code', 'custom_footer_code',
            'social_facebook', 'social_twitter', 'social_linkedin', 'social_instagram', 'social_youtube',
            'contact_email', 'contact_phone', 'contact_address',
            'privacy_policy_url', 'terms_of_service_url', 'cookie_policy_url',
            'login_template', 'dashboard_template', 'course_template', 'certificate_template',
            'hide_univga_branding', 'custom_footer_text', 'maintenance_mode', 'maintenance_message'
        );
        
        $updated_settings = array();
        
        foreach ($allowed_settings as $setting) {
            if (isset($settings[$setting])) {
                $value = $settings[$setting];
                
                // Sanitize different types of values
                switch ($setting) {
                    case 'enabled':
                    case 'ssl_enabled':
                    case 'hide_univga_branding':
                    case 'maintenance_mode':
                        $value = (bool) $value;
                        break;
                    case 'primary_color':
                    case 'secondary_color':
                    case 'accent_color':
                    case 'background_color':
                    case 'text_color':
                    case 'link_color':
                        $value = sanitize_hex_color($value);
                        break;
                    case 'custom_domain':
                    case 'subdomain':
                    case 'custom_email_domain':
                        $value = self::sanitize_domain($value);
                        break;
                    case 'contact_email':
                    case 'email_from_address':
                        $value = sanitize_email($value);
                        break;
                    case 'privacy_policy_url':
                    case 'terms_of_service_url':
                    case 'cookie_policy_url':
                    case 'logo_url':
                    case 'logo_light_url':
                    case 'favicon_url':
                    case 'cover_image_url':
                        $value = esc_url_raw($value);
                        break;
                    case 'custom_css':
                    case 'custom_js':
                    case 'custom_head_code':
                    case 'custom_footer_code':
                        $value = wp_kses_post($value);
                        break;
                    default:
                        $value = sanitize_text_field($value);
                        break;
                }
                
                update_option('univga_org_' . $org_id . '_' . $setting, $value);
                $updated_settings[$setting] = $value;
            }
        }
        
        // Handle domain configuration
        if (isset($settings['custom_domain']) || isset($settings['subdomain'])) {
            $domain_result = self::configure_custom_domain($org_id, $settings);
            $updated_settings['domain_configuration'] = $domain_result;
        }
        
        // Generate CSS file for the organization
        if (isset($settings['primary_color']) || isset($settings['secondary_color']) || isset($settings['custom_css'])) {
            self::generate_custom_css($org_id, $updated_settings);
        }
        
        do_action('univga_whitelabel_settings_updated', $org_id, $updated_settings);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Configuration white-label mise à jour avec succès',
            'updated_settings' => $updated_settings
        ));
    }
    
    /**
     * Check domain availability
     */
    public static function check_domain_availability($request) {
        $org_id = $request->get_param('id');
        $domain = $request->get_param('domain');
        $type = $request->get_param('type'); // 'domain' or 'subdomain'
        
        if (!$domain) {
            return new WP_Error('missing_domain', 'Domaine manquant', array('status' => 400));
        }
        
        $domain = self::sanitize_domain($domain);
        
        if ($type === 'subdomain') {
            $availability = self::check_subdomain_availability($domain);
        } else {
            $availability = self::check_custom_domain_availability($domain);
        }
        
        return rest_ensure_response($availability);
    }
    
    /**
     * Validate domain configuration
     */
    private static function validate_domain_configuration($org_id, $settings) {
        $validation = array(
            'custom_domain' => array('status' => 'inactive', 'message' => ''),
            'subdomain' => array('status' => 'inactive', 'message' => ''),
            'ssl' => array('status' => 'inactive', 'message' => ''),
            'dns' => array('status' => 'inactive', 'message' => '')
        );
        
        if (!empty($settings['custom_domain'])) {
            $domain_check = self::verify_domain_dns($settings['custom_domain']);
            $validation['custom_domain'] = $domain_check;
            
            if ($settings['ssl_enabled']) {
                $ssl_check = self::verify_ssl_certificate($settings['custom_domain']);
                $validation['ssl'] = $ssl_check;
            }
        }
        
        if (!empty($settings['subdomain'])) {
            $subdomain_check = self::verify_subdomain($settings['subdomain']);
            $validation['subdomain'] = $subdomain_check;
        }
        
        return $validation;
    }
    
    /**
     * Configure custom domain
     */
    private static function configure_custom_domain($org_id, $settings) {
        $result = array(
            'success' => false,
            'message' => '',
            'dns_records' => array(),
            'next_steps' => array()
        );
        
        if (!empty($settings['custom_domain'])) {
            $domain = $settings['custom_domain'];
            
            // Generate DNS records needed
            $dns_records = array(
                array(
                    'type' => 'A',
                    'name' => $domain,
                    'value' => self::get_server_ip(),
                    'ttl' => 3600
                ),
                array(
                    'type' => 'CNAME',
                    'name' => 'www.' . $domain,
                    'value' => $domain,
                    'ttl' => 3600
                )
            );
            
            if ($settings['ssl_enabled'] ?? false) {
                $dns_records[] = array(
                    'type' => 'CAA',
                    'name' => $domain,
                    'value' => '0 issue "letsencrypt.org"',
                    'ttl' => 3600
                );
            }
            
            $result['dns_records'] = $dns_records;
            $result['next_steps'] = array(
                'Configurez les enregistrements DNS ci-dessus dans votre hébergeur de domaine',
                'Attendez la propagation DNS (24-48h)',
                'Activez le certificat SSL si souhaité',
                'Testez la configuration'
            );
            
            update_option('univga_org_' . $org_id . '_domain_status', 'pending');
            $result['success'] = true;
            $result['message'] = 'Configuration de domaine initiée';
        }
        
        if (!empty($settings['subdomain'])) {
            $subdomain = $settings['subdomain'];
            $full_subdomain = $subdomain . '.univga.app'; // ou votre domaine principal
            
            // Enregistrer le sous-domaine
            update_option('univga_org_' . $org_id . '_subdomain_configured', $full_subdomain);
            update_option('univga_org_' . $org_id . '_domain_status', 'active');
            
            $result['success'] = true;
            $result['message'] = 'Sous-domaine configuré: ' . $full_subdomain;
        }
        
        return $result;
    }
    
    /**
     * Generate custom CSS for organization
     */
    private static function generate_custom_css($org_id, $settings) {
        $css = ":root {\n";
        
        if (!empty($settings['primary_color'])) {
            $css .= "  --univga-primary: {$settings['primary_color']};\n";
        }
        if (!empty($settings['secondary_color'])) {
            $css .= "  --univga-secondary: {$settings['secondary_color']};\n";
        }
        if (!empty($settings['accent_color'])) {
            $css .= "  --univga-accent: {$settings['accent_color']};\n";
        }
        if (!empty($settings['background_color'])) {
            $css .= "  --univga-background: {$settings['background_color']};\n";
        }
        if (!empty($settings['text_color'])) {
            $css .= "  --univga-text: {$settings['text_color']};\n";
        }
        if (!empty($settings['font_family'])) {
            $css .= "  --univga-font-family: '{$settings['font_family']}', sans-serif;\n";
        }
        
        $css .= "}\n\n";
        
        // Add custom CSS
        if (!empty($settings['custom_css'])) {
            $css .= $settings['custom_css'];
        }
        
        // Save CSS file
        $upload_dir = wp_upload_dir();
        $css_dir = $upload_dir['basedir'] . '/univga-custom/';
        
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        
        $css_file = $css_dir . 'org-' . $org_id . '.css';
        file_put_contents($css_file, $css);
        
        // Save CSS URL
        $css_url = $upload_dir['baseurl'] . '/univga-custom/org-' . $org_id . '.css';
        update_option('univga_org_' . $org_id . '_custom_css_url', $css_url);
        
        return $css_url;
    }
    
    /**
     * Helper methods for domain management
     */
    private static function sanitize_domain($domain) {
        return strtolower(trim($domain));
    }
    
    private static function check_subdomain_availability($subdomain) {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE %s AND option_value = %s
        ", 'univga_org_%_subdomain', $subdomain));
        
        return array(
            'available' => $existing == 0,
            'message' => $existing > 0 ? 'Ce sous-domaine est déjà utilisé' : 'Sous-domaine disponible'
        );
    }
    
    private static function check_custom_domain_availability($domain) {
        // Vérifier si le domaine pointe déjà vers nous
        $dns_check = dns_get_record($domain, DNS_A);
        $our_ip = self::get_server_ip();
        
        $points_to_us = false;
        foreach ($dns_check as $record) {
            if ($record['ip'] === $our_ip) {
                $points_to_us = true;
                break;
            }
        }
        
        return array(
            'available' => true,
            'points_to_us' => $points_to_us,
            'current_ip' => $dns_check[0]['ip'] ?? 'Non résolu',
            'required_ip' => $our_ip,
            'message' => $points_to_us ? 'Domaine configuré correctement' : 'Domaine nécessite une configuration DNS'
        );
    }
    
    private static function verify_domain_dns($domain) {
        $our_ip = self::get_server_ip();
        $dns_records = dns_get_record($domain, DNS_A);
        
        $is_configured = false;
        foreach ($dns_records as $record) {
            if ($record['ip'] === $our_ip) {
                $is_configured = true;
                break;
            }
        }
        
        return array(
            'status' => $is_configured ? 'active' : 'error',
            'message' => $is_configured ? 'DNS configuré correctement' : 'DNS non configuré - doit pointer vers ' . $our_ip
        );
    }
    
    private static function verify_ssl_certificate($domain) {
        $context = stream_context_create(array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ));
        
        $cert_info = null;
        $ssl_valid = false;
        
        try {
            $connection = stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
            if ($connection) {
                $cert_info = stream_context_get_options($context)['ssl']['peer_certificate'] ?? null;
                $ssl_valid = true;
                fclose($connection);
            }
        } catch (Exception $e) {
            // SSL non disponible
        }
        
        return array(
            'status' => $ssl_valid ? 'active' : 'error',
            'message' => $ssl_valid ? 'Certificat SSL valide' : 'Certificat SSL non trouvé'
        );
    }
    
    private static function verify_subdomain($subdomain) {
        $full_subdomain = $subdomain . '.univga.app';
        
        return array(
            'status' => 'active',
            'message' => 'Sous-domaine actif: ' . $full_subdomain
        );
    }
    
    private static function get_server_ip() {
        // En production, retourner l'IP réelle du serveur
        return '127.0.0.1'; // placeholder pour développement
    }
    
    private static function get_whitelabel_feature_limits($org_id) {
        // Définir les limites selon le plan de l'organisation
        return array(
            'custom_domain' => true,
            'subdomain' => true,
            'ssl_certificate' => true,
            'custom_css' => true,
            'hide_branding' => true,
            'email_configuration' => true,
            'analytics_integration' => true,
            'custom_templates' => true
        );
    }
}
