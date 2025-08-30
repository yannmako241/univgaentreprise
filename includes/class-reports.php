<?php

/**
 * Reports and analytics
 */
class UNIVGA_Reports {
    
    /**
     * Get organization dashboard KPIs
     */
    public static function get_org_dashboard_kpis($org_id) {
        // Get seat pool statistics
        $pools = UNIVGA_Seat_Pools::get_by_org($org_id);
        
        $total_seats = 0;
        $used_seats = 0;
        $expired_pools = 0;
        $expiring_soon = 0;
        $courses_covered = array();
        
        foreach ($pools as $pool) {
            $total_seats += $pool->seats_total;
            $used_seats += $pool->seats_used;
            
            // Check expiration
            if ($pool->expires_at) {
                $expires_timestamp = strtotime($pool->expires_at);
                $now = time();
                
                if ($expires_timestamp < $now) {
                    $expired_pools++;
                } elseif ($expires_timestamp < ($now + (7 * 24 * 60 * 60))) {
                    $expiring_soon++;
                }
            }
            
            // Count unique courses
            $pool_courses = UNIVGA_Seat_Pools::get_pool_courses($pool);
            $courses_covered = array_merge($courses_covered, $pool_courses);
        }
        
        $courses_covered = array_unique($courses_covered);
        
        // Get member statistics
        $member_count = UNIVGA_Members::get_org_members_count($org_id, array('status' => 'active'));
        
        // Get course progress statistics
        $course_stats = array();
        if (class_exists('UNIVGA_Tutor') && method_exists('UNIVGA_Tutor', 'get_org_course_stats')) {
            $course_stats = UNIVGA_Tutor::get_org_course_stats($org_id, $courses_covered);
        }
        
        $total_enrollments = 0;
        $total_completions = 0;
        $avg_progress = 0;
        
        foreach ($course_stats as $stats) {
            $total_enrollments += $stats['enrolled_count'];
            $total_completions += $stats['completed_count'];
            $avg_progress += $stats['avg_progress'];
        }
        
        $avg_progress = count($course_stats) > 0 ? $avg_progress / count($course_stats) : 0;
        $completion_rate = $total_enrollments > 0 ? ($total_completions / $total_enrollments) * 100 : 0;
        
        return array(
            'seats' => array(
                'total' => $total_seats,
                'used' => $used_seats,
                'available' => $total_seats - $used_seats,
                'utilization_rate' => $total_seats > 0 ? ($used_seats / $total_seats) * 100 : 0,
            ),
            'expiration' => array(
                'expired_pools' => $expired_pools,
                'expiring_soon' => $expiring_soon,
            ),
            'members' => array(
                'total' => $member_count,
                'enrolled' => $total_enrollments,
            ),
            'courses' => array(
                'covered' => count($courses_covered),
                'avg_progress' => $avg_progress,
                'completion_rate' => $completion_rate,
                'total_completions' => $total_completions,
            ),
        );
    }
    
    /**
     * Get organization member report
     */
    public static function get_org_member_report($org_id, $args = array()) {
        $defaults = array(
            'team_id' => null,
            'search' => '',
            'limit' => 50,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Get members
        $members = UNIVGA_Members::get_org_members($org_id, $args);
        
        // Get all organization courses
        $pools = UNIVGA_Seat_Pools::get_by_org($org_id);
        $all_courses = array();
        
        foreach ($pools as $pool) {
            $pool_courses = UNIVGA_Seat_Pools::get_pool_courses($pool);
            $all_courses = array_merge($all_courses, $pool_courses);
        }
        
        $all_courses = array_unique($all_courses);
        
        // Enrich member data with course details
        foreach ($members as &$member) {
            $course_details = UNIVGA_Tutor::get_member_course_details($member->user_id, $all_courses);
            
            $member->courses = $course_details;
            $member->enrolled_courses = count(array_filter($course_details, function($c) { return $c['enrolled']; }));
            $member->completed_courses = count(array_filter($course_details, function($c) { return $c['completed']; }));
            
            // Calculate average progress
            $enrolled_courses = array_filter($course_details, function($c) { return $c['enrolled']; });
            $total_progress = array_sum(array_column($enrolled_courses, 'progress'));
            $member->avg_progress = count($enrolled_courses) > 0 ? $total_progress / count($enrolled_courses) : 0;
            
            // Get last activity across all courses
            $last_activities = array_filter(array_column($course_details, 'last_activity'));
            $member->last_activity = !empty($last_activities) ? max($last_activities) : null;
        }
        
        return $members;
    }
    
    /**
     * Get organization course report
     */
    public static function get_org_course_report($org_id, $args = array()) {
        $defaults = array(
            'team_id' => null,
            'course_id' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Get course IDs
        $course_ids = array();
        
        if ($args['course_id']) {
            $course_ids = array($args['course_id']);
        } else {
            $pools = UNIVGA_Seat_Pools::get_by_org($org_id, array('team_id' => $args['team_id']));
            
            foreach ($pools as $pool) {
                $pool_courses = UNIVGA_Seat_Pools::get_pool_courses($pool);
                $course_ids = array_merge($course_ids, $pool_courses);
            }
            
            $course_ids = array_unique($course_ids);
        }
        
        return UNIVGA_Tutor::get_org_course_stats($org_id, $course_ids);
    }
    
    /**
     * Export member report to CSV
     */
    public static function export_member_report_csv($org_id, $args = array()) {
        $members = self::get_org_member_report($org_id, array_merge($args, array('limit' => null)));
        
        $filename = 'members-report-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'Name',
            'Email',
            'Team',
            'Joined Date',
            'Enrolled Courses',
            'Completed Courses',
            'Average Progress (%)',
            'Last Activity',
        ));
        
        // CSV data
        foreach ($members as $member) {
            fputcsv($output, array(
                $member->display_name,
                $member->user_email,
                $member->team_name ?: 'No Team',
                date('Y-m-d', strtotime($member->joined_at)),
                $member->enrolled_courses,
                $member->completed_courses,
                number_format($member->avg_progress, 1),
                $member->last_activity ? date('Y-m-d H:i', strtotime($member->last_activity)) : 'Never',
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export course report to CSV
     */
    public static function export_course_report_csv($org_id, $args = array()) {
        $courses = self::get_org_course_report($org_id, $args);
        
        $filename = 'courses-report-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'Course Title',
            'Enrolled Members',
            'Completed Members',
            'Completion Rate (%)',
            'Average Progress (%)',
        ));
        
        // CSV data
        foreach ($courses as $course_id => $stats) {
            fputcsv($output, array(
                $stats['course_title'],
                $stats['enrolled_count'],
                $stats['completed_count'],
                number_format($stats['completion_rate'], 1),
                number_format($stats['avg_progress'], 1),
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get seat usage over time
     */
    public static function get_seat_usage_chart($org_id, $days = 30) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(e.created_at) as date,
                e.type,
                COUNT(*) as count
             FROM {$wpdb->prefix}univga_seat_events e
             JOIN {$wpdb->prefix}univga_seat_pools p ON e.pool_id = p.id
             WHERE p.org_id = %d AND e.created_at >= %s
             AND e.type IN ('consume', 'release')
             GROUP BY DATE(e.created_at), e.type
             ORDER BY date ASC",
            $org_id, $start_date
        ));
        
        $chart_data = array();
        $current_date = $start_date;
        $end_date = date('Y-m-d');
        
        while ($current_date <= $end_date) {
            $chart_data[$current_date] = array(
                'date' => $current_date,
                'consumed' => 0,
                'released' => 0,
            );
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        foreach ($events as $event) {
            if (isset($chart_data[$event->date])) {
                if ($event->type === 'consume') {
                    $chart_data[$event->date]['consumed'] = intval($event->count);
                } elseif ($event->type === 'release') {
                    $chart_data[$event->date]['released'] = intval($event->count);
                }
            }
        }
        
        return array_values($chart_data);
    }


    /**
     * Get AI context data for analysis
     */
    public static function ai_context($args = array()) {
        $defaults = array(
            'org_id' => 0,
            'team_id' => 0,
            'period' => '30d',
            'mode' => 'summary'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $org_id = intval($args['org_id']);
        $team_id = intval($args['team_id']);
        $period = sanitize_text_field($args['period']);
        
        // Calculate date range based on period
        $end_date = current_time('mysql');
        switch ($period) {
            case '7d':
                $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30d':
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '90d':
                $start_date = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            case 'all':
            default:
                $start_date = '2020-01-01 00:00:00'; // Far back enough
                break;
        }
        
        $context = array();
        
        // Organization overview
        if ($org_id) {
            $org = UNIVGA_Orgs::get($org_id);
            if ($org) {
                $context['organization'] = array(
                    'id' => $org->id,
                    'name' => $org->name,
                    'member_count' => UNIVGA_Members::get_org_members_count($org_id, array('status' => 'active')),
                    'team_count' => count(UNIVGA_Teams::get_by_org($org_id))
                );
            }
        }
        
        // Seat pool data
        $pools_data = array();
        if ($org_id) {
            $pools = UNIVGA_Seat_Pools::get_by_org($org_id);
            $total_seats = 0;
            $used_seats = 0;
            $pool_count = 0;
            
            foreach ($pools as $pool) {
                $total_seats += $pool->seats_total;
                $used_seats += $pool->seats_used;
                $pool_count++;
            }
            
            $pools_data = array(
                'total_pools' => $pool_count,
                'total_seats' => $total_seats,
                'used_seats' => $used_seats,
                'available_seats' => $total_seats - $used_seats,
                'utilization_rate' => $total_seats > 0 ? round(($used_seats / $total_seats) * 100, 2) : 0
            );
        }
        
        $context['seat_pools'] = $pools_data;
        
        // Team performance (if specific team requested)
        if ($team_id) {
            $team = UNIVGA_Teams::get($team_id);
            if ($team) {
                $team_members = UNIVGA_Members::get_by_team($team_id);
                $context['team'] = array(
                    'id' => $team->id,
                    'name' => $team->name,
                    'member_count' => count($team_members),
                    'org_id' => $team->org_id
                );
            }
        }
        
        // Learning analytics (if available)
        if (class_exists('UNIVGA_Analytics')) {
            global $wpdb;
            
            // Get basic learning analytics from database directly
            $analytics_data = array();
            
            // Get course completion stats
            $completion_stats = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(CASE WHEN event_type = 'course_started' THEN 1 END) as courses_started,
                    COUNT(CASE WHEN event_type = 'course_completed' THEN 1 END) as courses_completed,
                    COUNT(DISTINCT user_id) as active_learners
                FROM {$wpdb->prefix}univga_analytics_events
                WHERE org_id = %d AND created_at >= %s AND created_at <= %s
            ", $org_id, $start_date, $end_date));
            
            if ($completion_stats) {
                $analytics_data = array(
                    'courses_started' => intval($completion_stats->courses_started),
                    'courses_completed' => intval($completion_stats->courses_completed),
                    'active_learners' => intval($completion_stats->active_learners),
                    'completion_rate' => $completion_stats->courses_started > 0 ? 
                        round(($completion_stats->courses_completed / $completion_stats->courses_started) * 100, 2) : 0
                );
                $context['learning_analytics'] = $analytics_data;
            }
        }
        
        // Engagement metrics
        $context['engagement'] = array(
            'period' => $period,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'data_points' => count($context)
        );
        
        // Add summary statistics
        $context['summary'] = array(
            'generated_at' => current_time('mysql'),
            'mode' => $args['mode'],
            'has_org_data' => !empty($context['organization']),
            'has_team_data' => !empty($context['team']),
            'has_learning_data' => !empty($context['learning_analytics'])
        );
        
        return $context;
    }
}
