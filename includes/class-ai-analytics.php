<?php

/**
 * AI-Powered Analytics System for UNIVGA LMS
 * 
 * Generates intelligent insights, performance summaries, actionable recommendations,
 * and predictive analysis using OpenAI GPT-5 for HR teams and managers
 */
class UNIVGA_AI_Analytics {
    
    private static $instance = null;
    private $openai_api_key;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->openai_api_key = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
        
        // Only initialize if OpenAI is configured - Feature Flag
        if (empty($this->openai_api_key)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('UNIVGA AI Analytics: OpenAI API key not configured');
            }
            return; // Don't add hooks if not properly configured
        }
        
        // AJAX handlers
        add_action('wp_ajax_univga_generate_ai_summary', array($this, 'generate_ai_summary'));
        add_action('wp_ajax_univga_get_smart_recommendations', array($this, 'get_smart_recommendations'));
        add_action('wp_ajax_univga_analyze_churn_risk', array($this, 'analyze_churn_risk'));
        add_action('wp_ajax_univga_generate_weekly_insights', array($this, 'generate_weekly_insights'));
        
        // Scheduled AI analysis
        add_action('univga_daily_ai_analysis', array($this, 'run_daily_ai_analysis'));
        if (!wp_next_scheduled('univga_daily_ai_analysis')) {
            wp_schedule_event(strtotime('tomorrow 6:00am'), 'daily', 'univga_daily_ai_analysis');
        }
    }
    
    /**
     * Check if AI Analytics is properly configured
     */
    public function is_configured() {
        return !empty($this->openai_api_key);
    }
    
    /**
     * Generate AI-powered performance summary
     */
    public function generate_ai_summary() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_reports_view')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? 'week');
        
        // Get raw performance data
        $performance_data = $this->gather_performance_data($org_id, $timeframe);
        
        // Generate AI summary
        $ai_summary = $this->call_openai_for_summary($performance_data, $timeframe);
        
        // Store the analysis for future reference
        $this->store_ai_analysis($org_id, 'summary', $ai_summary, $performance_data);
        
        wp_send_json_success(array(
            'summary' => $ai_summary,
            'generated_at' => current_time('mysql'),
            'data_points' => count($performance_data)
        ));
    }
    
    /**
     * Get smart actionable recommendations
     */
    public function get_smart_recommendations() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_team_view')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        
        // Get organizational data for recommendations
        $org_data = $this->gather_organization_insights($org_id);
        
        // Generate smart recommendations via OpenAI
        $recommendations = $this->call_openai_for_recommendations($org_data);
        
        // Prioritize and format recommendations
        $formatted_recommendations = $this->format_recommendations($recommendations);
        
        wp_send_json_success(array(
            'recommendations' => $formatted_recommendations,
            'priority_actions' => array_slice($formatted_recommendations, 0, 3),
            'generated_at' => current_time('mysql')
        ));
    }
    
    /**
     * Analyze churn risk using AI prediction
     */
    public function analyze_churn_risk() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        if (!current_user_can('univga_member_view_team')) {
            wp_send_json_error(__('Insufficient permissions', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        
        // Get member engagement data
        $members_data = $this->gather_member_engagement_data($org_id);
        
        // AI-powered churn prediction
        $churn_analysis = $this->call_openai_for_churn_prediction($members_data);
        
        // Process results
        $at_risk_members = $this->process_churn_results($churn_analysis, $members_data);
        
        wp_send_json_success(array(
            'at_risk_count' => count($at_risk_members),
            'at_risk_members' => $at_risk_members,
            'risk_factors' => $this->identify_common_risk_factors($at_risk_members),
            'prevention_actions' => $this->suggest_retention_actions($at_risk_members)
        ));
    }
    
    /**
     * Generate weekly insights for automated reports
     */
    public function generate_weekly_insights() {
        if (!wp_verify_nonce($_POST['nonce'], 'univga_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        
        // Comprehensive data gathering
        $weekly_data = array(
            'performance' => $this->gather_performance_data($org_id, 'week'),
            'engagement' => $this->gather_engagement_metrics($org_id, 'week'),
            'compliance' => $this->gather_compliance_data($org_id),
            'team_dynamics' => $this->gather_team_performance($org_id, 'week')
        );
        
        // Generate comprehensive AI analysis
        $insights = $this->call_openai_for_comprehensive_analysis($weekly_data);
        
        wp_send_json_success(array(
            'insights' => $insights,
            'trend_analysis' => $insights['trends'] ?? [],
            'strategic_recommendations' => $insights['strategy'] ?? [],
            'immediate_actions' => $insights['actions'] ?? []
        ));
    }
    
    /**
     * Call OpenAI API for performance summary
     */
    private function call_openai_for_summary($data, $timeframe) {
        if (!$this->openai_api_key) {
            return "Configuration OpenAI manquante. Veuillez configurer votre clé API.";
        }
        
        $prompt = $this->build_summary_prompt($data, $timeframe);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->openai_api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4o', // Using GPT-4o as it's widely available
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Vous êtes un expert en analytics RH pour entreprises. Générez des résumés clairs, précis et actionnables en français professionnel.'
                ],
                [
                    'role' => 'user', 
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 800
        ]));
        
        $response = curl_exec($ch);
        
        if (curl_error($ch)) {
            error_log('OpenAI API Error: ' . curl_error($ch));
            return "Erreur de connexion à l'IA. Résumé automatique indisponible.";
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
        
        error_log('OpenAI API Response Error: ' . $response);
        return "Erreur lors de la génération du résumé IA.";
    }
    
    /**
     * Call OpenAI API for actionable recommendations
     */
    private function call_openai_for_recommendations($org_data) {
        if (!$this->openai_api_key) {
            return [];
        }
        
        $prompt = $this->build_recommendations_prompt($org_data);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->openai_api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Vous êtes un consultant RH expert. Analysez les données et proposez des actions concrètes et prioritaires. Répondez en JSON structuré en français.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
            'max_tokens' => 1000
        ]));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return json_decode($result['choices'][0]['message']['content'], true) ?? [];
        }
        
        return [];
    }
    
    /**
     * Call OpenAI for churn prediction analysis
     */
    private function call_openai_for_churn_prediction($members_data) {
        if (!$this->openai_api_key) {
            return [];
        }
        
        $prompt = $this->build_churn_prediction_prompt($members_data);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->openai_api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Vous êtes un data scientist spécialisé dans l\'analyse prédictive RH. Identifiez les employés à risque d\'abandon de formation. Répondez en JSON avec scores de risque.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
            'max_tokens' => 1200
        ]));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return json_decode($result['choices'][0]['message']['content'], true) ?? [];
        }
        
        return [];
    }
    
    /**
     * Build prompt for performance summary
     */
    private function build_summary_prompt($data, $timeframe) {
        $timeframe_fr = $timeframe === 'week' ? 'cette semaine' : 'ce mois';
        
        $prompt = "Analysez ces données de performance de formation pour {$timeframe_fr} et générez un résumé exécutif professionnel :\n\n";
        
        $prompt .= "DONNÉES BRUTES :\n";
        $prompt .= "- Agents formés : " . ($data['agents_trained'] ?? 0) . "\n";
        $prompt .= "- Formations terminées : " . ($data['completions'] ?? 0) . "\n"; 
        $prompt .= "- Taux de complétion global : " . ($data['completion_rate'] ?? 0) . "%\n";
        $prompt .= "- Certificats obtenus : " . ($data['certificates_earned'] ?? 0) . "\n";
        $prompt .= "- Temps moyen par formation : " . ($data['avg_time_spent'] ?? 0) . " minutes\n";
        
        if (!empty($data['team_performance'])) {
            $prompt .= "\nPERFORMANCE PAR ÉQUIPE :\n";
            foreach ($data['team_performance'] as $team) {
                $prompt .= "- {$team['name']} : {$team['completion_rate']}% complétion ({$team['members_active']}/{$team['total_members']} actifs)\n";
            }
        }
        
        if (!empty($data['course_stats'])) {
            $prompt .= "\nTOP COURS :\n";
            foreach (array_slice($data['course_stats'], 0, 5) as $course) {
                $prompt .= "- {$course['title']} : {$course['completions']} terminés, {$course['avg_score']}% moyenne\n";
            }
        }
        
        $prompt .= "\nGÉNÉREZ UN RÉSUMÉ qui :\n";
        $prompt .= "1. Commence par les chiffres clés les plus importants\n";
        $prompt .= "2. Identifie les équipes/départements en retard avec pourcentages précis\n";
        $prompt .= "3. Met en avant les succès et points d'amélioration\n";
        $prompt .= "4. Reste factuel et professionnel (style rapport RH)\n";
        $prompt .= "5. Maximum 200 mots\n";
        
        return $prompt;
    }
    
    /**
     * Build prompt for actionable recommendations
     */
    private function build_recommendations_prompt($org_data) {
        $prompt = "Analysez ces données organisationnelles et proposez des recommandations actionnables spécifiques :\n\n";
        
        $prompt .= "DONNÉES ORGANISATION :\n";
        $prompt .= "- Employés inactifs (30+ jours) : " . ($org_data['inactive_users'] ?? 0) . "\n";
        $prompt .= "- Formations en retard : " . ($org_data['overdue_trainings'] ?? 0) . "\n";
        $prompt .= "- Taux d'engagement faible : " . count($org_data['low_engagement'] ?? []) . " personnes\n";
        $prompt .= "- Certificats expirant : " . ($org_data['expiring_certs'] ?? 0) . "\n";
        $prompt .= "- Budget formation utilisé : " . ($org_data['budget_used'] ?? 0) . "%\n";
        
        if (!empty($org_data['problematic_teams'])) {
            $prompt .= "\nÉQUIPES PROBLÉMATIQUES :\n";
            foreach ($org_data['problematic_teams'] as $team) {
                $prompt .= "- {$team['name']} : {$team['issue']} ({$team['metric']})\n";
            }
        }
        
        $prompt .= "\nGÉNÉREZ des recommandations au format JSON :\n";
        $prompt .= '{"recommendations": [{"action": "action concrète", "priority": "haute/moyenne/basse", "target": "qui", "expected_result": "résultat attendu", "timeline": "délai"}]}';
        $prompt .= "\n\nCONCENTREZ-VOUS sur :\n";
        $prompt .= "- Actions immédiates réalisables\n";
        $prompt .= "- Cibles spécifiques (équipes, individus)\n";
        $prompt .= "- Résultats mesurables\n";
        $prompt .= "- Priorités business\n";
        
        return $prompt;
    }
    
    /**
     * Build prompt for churn prediction
     */
    private function build_churn_prediction_prompt($members_data) {
        $prompt = "Analysez ces profils d'employés et identifiez ceux à risque d'abandon de formation :\n\n";
        
        foreach ($members_data as $member) {
            $prompt .= "EMPLOYÉ {$member['id']} :\n";
            $prompt .= "- Dernière connexion : " . ($member['last_login'] ?? 'jamais') . "\n";
            $prompt .= "- Formations commencées : " . ($member['started_courses'] ?? 0) . "\n";
            $prompt .= "- Formations terminées : " . ($member['completed_courses'] ?? 0) . "\n";
            $prompt .= "- Progression moyenne : " . ($member['avg_progress'] ?? 0) . "%\n";
            $prompt .= "- Temps passé total : " . ($member['total_time'] ?? 0) . " minutes\n";
            $prompt .= "- Équipe : " . ($member['team'] ?? 'Aucune') . "\n\n";
        }
        
        $prompt .= "GÉNÉREZ une analyse au format JSON :\n";
        $prompt .= '{"churn_analysis": [{"employee_id": "ID", "risk_score": 0-100, "risk_level": "faible/moyen/élevé", "risk_factors": ["facteur1", "facteur2"], "recommended_action": "action préventive"}]}';
        
        $prompt .= "\n\nCRITÈRES DE RISQUE :\n";
        $prompt .= "- Inactivité prolongée (score élevé)\n";
        $prompt .= "- Faible progression malgré inscriptions\n";
        $prompt .= "- Abandon de formations multiples\n";
        $prompt .= "- Temps d'engagement très faible\n";
        
        return $prompt;
    }
    
    /**
     * Gather performance data for AI analysis
     */
    private function gather_performance_data($org_id, $timeframe = 'week') {
        global $wpdb;
        
        $date_condition = $timeframe === 'week' ? 'DATE_SUB(NOW(), INTERVAL 7 DAY)' : 'DATE_SUB(NOW(), INTERVAL 30 DAY)';
        
        // Basic metrics
        $agents_trained = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT ae.user_id)
            FROM {$wpdb->prefix}univga_analytics_events ae
            WHERE ae.org_id = %d 
            AND ae.created_at >= {$date_condition}
            AND ae.event_type IN ('course_started', 'course_completed')
        ", $org_id));
        
        $completions = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}univga_analytics_events ae
            WHERE ae.org_id = %d 
            AND ae.created_at >= {$date_condition}
            AND ae.event_type = 'course_completed'
        ", $org_id));
        
        $total_started = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}univga_analytics_events ae
            WHERE ae.org_id = %d 
            AND ae.created_at >= {$date_condition}
            AND ae.event_type = 'course_started'
        ", $org_id));
        
        $completion_rate = $total_started > 0 ? round(($completions / $total_started) * 100, 1) : 0;
        
        // Team performance
        $team_performance = $wpdb->get_results($wpdb->prepare("
            SELECT 
                t.name,
                COUNT(DISTINCT m.user_id) as total_members,
                COUNT(DISTINCT ae.user_id) as members_active,
                COUNT(CASE WHEN ae.event_type = 'course_completed' THEN 1 END) as completions,
                COUNT(CASE WHEN ae.event_type = 'course_started' THEN 1 END) as started,
                ROUND((COUNT(CASE WHEN ae.event_type = 'course_completed' THEN 1 END) / 
                       NULLIF(COUNT(CASE WHEN ae.event_type = 'course_started' THEN 1 END), 0)) * 100, 1) as completion_rate
            FROM {$wpdb->prefix}univga_teams t
            LEFT JOIN {$wpdb->prefix}univga_org_members m ON t.id = m.team_id
            LEFT JOIN {$wpdb->prefix}univga_analytics_events ae ON m.user_id = ae.user_id AND ae.created_at >= {$date_condition}
            WHERE t.org_id = %d
            GROUP BY t.id, t.name
            ORDER BY completion_rate DESC
        ", $org_id), ARRAY_A);
        
        return array(
            'agents_trained' => intval($agents_trained),
            'completions' => intval($completions),
            'completion_rate' => $completion_rate,
            'certificates_earned' => $this->count_recent_certificates($org_id, $timeframe),
            'avg_time_spent' => $this->calculate_avg_time_spent($org_id, $timeframe),
            'team_performance' => $team_performance,
            'course_stats' => $this->get_top_courses_stats($org_id, $timeframe)
        );
    }
    
    /**
     * Gather organization insights for recommendations
     */
    private function gather_organization_insights($org_id) {
        global $wpdb;
        
        // Inactive users (30+ days)
        $inactive_users = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT m.user_id)
            FROM {$wpdb->prefix}univga_org_members m
            LEFT JOIN {$wpdb->prefix}univga_analytics_events ae ON m.user_id = ae.user_id
            WHERE m.org_id = %d 
            AND m.status = 'active'
            AND (ae.created_at IS NULL OR ae.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
        ", $org_id));
        
        // Overdue trainings (example logic)
        $overdue_trainings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}univga_learning_path_assignments lpa
            WHERE lpa.org_id = %d 
            AND lpa.due_date < NOW()
            AND lpa.status != 'completed'
        ", $org_id));
        
        return array(
            'inactive_users' => intval($inactive_users),
            'overdue_trainings' => intval($overdue_trainings),
            'low_engagement' => $this->identify_low_engagement_users($org_id),
            'expiring_certs' => $this->count_expiring_certificates($org_id),
            'budget_used' => $this->calculate_budget_usage($org_id),
            'problematic_teams' => $this->identify_problematic_teams($org_id)
        );
    }
    
    /**
     * Gather member engagement data for churn analysis
     */
    private function gather_member_engagement_data($org_id, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                m.user_id as id,
                u.display_name as name,
                t.name as team,
                MAX(ae.created_at) as last_login,
                COUNT(CASE WHEN ae.event_type = 'course_started' THEN 1 END) as started_courses,
                COUNT(CASE WHEN ae.event_type = 'course_completed' THEN 1 END) as completed_courses,
                AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(ae.event_data, '$.progress')) AS DECIMAL(5,2))) as avg_progress,
                SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(ae.event_data, '$.time_spent')) AS UNSIGNED)) as total_time
            FROM {$wpdb->prefix}univga_org_members m
            LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}univga_teams t ON m.team_id = t.id
            LEFT JOIN {$wpdb->prefix}univga_analytics_events ae ON m.user_id = ae.user_id
            WHERE m.org_id = %d AND m.status = 'active'
            GROUP BY m.user_id
            ORDER BY last_login ASC
            LIMIT %d
        ", $org_id, $limit), ARRAY_A);
    }
    
    // Additional helper methods would continue here...
    // For brevity, showing key structure and main functionality
    
    /**
     * Store AI analysis results
     */
    private function store_ai_analysis($org_id, $type, $analysis, $raw_data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'univga_ai_analytics',
            array(
                'org_id' => $org_id,
                'analysis_type' => $type,
                'ai_result' => wp_json_encode($analysis),
                'raw_data' => wp_json_encode($raw_data),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Format recommendations for display
     */
    private function format_recommendations($recommendations) {
        if (!is_array($recommendations) || !isset($recommendations['recommendations'])) {
            return [];
        }
        
        $formatted = [];
        foreach ($recommendations['recommendations'] as $rec) {
            $formatted[] = array(
                'action' => $rec['action'] ?? '',
                'priority' => $rec['priority'] ?? 'moyenne',
                'target' => $rec['target'] ?? '',
                'expected_result' => $rec['expected_result'] ?? '',
                'timeline' => $rec['timeline'] ?? '',
                'icon' => $this->get_priority_icon($rec['priority'] ?? 'moyenne')
            );
        }
        
        // Sort by priority
        usort($formatted, function($a, $b) {
            $priority_order = ['haute' => 3, 'moyenne' => 2, 'basse' => 1];
            return ($priority_order[$b['priority']] ?? 1) - ($priority_order[$a['priority']] ?? 1);
        });
        
        return $formatted;
    }
    
    private function get_priority_icon($priority) {
        switch ($priority) {
            case 'haute': return 'dashicons-warning';
            case 'moyenne': return 'dashicons-info';
            case 'basse': return 'dashicons-lightbulb';
            default: return 'dashicons-admin-generic';
        }
    }
    
    // Helper methods for data gathering
    private function count_recent_certificates($org_id, $timeframe) {
        // Implementation for certificate counting
        return 0; // Placeholder
    }
    
    private function calculate_avg_time_spent($org_id, $timeframe) {
        // Implementation for time calculation
        return 0; // Placeholder
    }
    
    private function get_top_courses_stats($org_id, $timeframe) {
        // Implementation for course stats
        return []; // Placeholder
    }
    
    private function identify_low_engagement_users($org_id) {
        // Implementation for low engagement identification
        return []; // Placeholder
    }
    
    private function count_expiring_certificates($org_id) {
        // Implementation for expiring certs
        return 0; // Placeholder
    }
    
    private function calculate_budget_usage($org_id) {
        // Implementation for budget calculation
        return 0; // Placeholder
    }
    
    private function identify_problematic_teams($org_id) {
        // Implementation for team issues identification
        return []; // Placeholder
    }

    /**
     * Static method to check if AI Analytics is properly configured
     */
    public static function is_configured_static() {
        $instance = self::getInstance();
        return $instance->is_configured();
    }
    
    /**
     * Main analyze method for AI Analytics page
     */
    public static function analyze($context_data, $options = array()) {
        $instance = self::getInstance();
        
        if (!$instance->is_configured()) {
            return new WP_Error('ai_not_configured', __('Configuration IA manquante. Veuillez configurer votre clé API.', UNIVGA_TEXT_DOMAIN));
        }
        
        $mode = isset($options['mode']) ? $options['mode'] : 'summary';
        
        try {
            switch ($mode) {
                case 'risk':
                    return $instance->analyze_risk_mode($context_data, $options);
                case 'path':
                    return $instance->analyze_path_mode($context_data, $options);
                case 'summary':
                default:
                    return $instance->analyze_summary_mode($context_data, $options);
            }
        } catch (Exception $e) {
            return new WP_Error('ai_analysis_error', sprintf(__('Erreur lors de l\'analyse IA: %s', UNIVGA_TEXT_DOMAIN), $e->getMessage()));
        }
    }
    
    /**
     * Analyze in summary mode
     */
    private function analyze_summary_mode($context_data, $options) {
        if (!$context_data || empty($context_data)) {
            return __('Aucune donnée disponible pour l\'analyse.', UNIVGA_TEXT_DOMAIN);
        }
        
        $prompt = $this->build_analysis_prompt($context_data, 'summary', $options);
        return $this->call_openai_analysis($prompt);
    }
    
    /**
     * Analyze in risk mode
     */
    private function analyze_risk_mode($context_data, $options) {
        if (!$context_data || empty($context_data)) {
            return __('Aucune donnée disponible pour l\'analyse des risques.', UNIVGA_TEXT_DOMAIN);
        }
        
        $prompt = $this->build_analysis_prompt($context_data, 'risk', $options);
        return $this->call_openai_analysis($prompt);
    }
    
    /**
     * Analyze in learning path mode
     */
    private function analyze_path_mode($context_data, $options) {
        if (!$context_data || empty($context_data)) {
            return __('Aucune donnée disponible pour l\'analyse des parcours.', UNIVGA_TEXT_DOMAIN);
        }
        
        $prompt = $this->build_analysis_prompt($context_data, 'path', $options);
        return $this->call_openai_analysis($prompt);
    }
    
    /**
     * Build analysis prompt based on mode
     */
    private function build_analysis_prompt($context_data, $mode, $options) {
        $org_id = isset($options['org_id']) ? intval($options['org_id']) : 0;
        $period = isset($options['period']) ? $options['period'] : '30d';
        
        $base_context = sprintf(
            "Contexte: Organisation #%d, Période: %s\nDonnées d'analyse: %s\n\n",
            $org_id,
            $period,
            wp_json_encode($context_data, JSON_PRETTY_PRINT)
        );
        
        switch ($mode) {
            case 'risk':
                return $base_context . 
                    "Analysez ces données pour identifier les risques principaux (abandons, baisse d'engagement, problèmes de formation). " .
                    "Proposez des actions préventives concrètes et prioritaires. " .
                    "Format: - **Risque identifié**: Description\n- **Action recommandée**: Action précise\n- **Priorité**: Haute/Moyenne/Basse";
                    
            case 'path':
                return $base_context . 
                    "Analysez ces données pour recommander des parcours de formation optimaux. " .
                    "Identifiez les lacunes de compétences et proposez des améliorations de parcours. " .
                    "Format: - **Parcours recommandé**: Nom du parcours\n- **Justification**: Pourquoi ce parcours\n- **Compétences ciblées**: Liste des compétences";
                    
            case 'summary':
            default:
                return $base_context . 
                    "Générez un résumé exécutif en français avec insights clés, tendances principales, " .
                    "points d'amélioration et recommandations stratégiques. Soyez concis et actionnable.";
        }
    }
    
    /**
     * Call OpenAI for analysis
     */
    private function call_openai_analysis($prompt) {
        if (!$this->openai_api_key) {
            return __('Configuration OpenAI manquante. Veuillez configurer votre clé API.', UNIVGA_TEXT_DOMAIN);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->openai_api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Vous êtes un expert en analytics RH et formation d\'entreprise. Générez des analyses claires, précises et actionnables en français professionnel.'
                ],
                [
                    'role' => 'user', 
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 1000
        ]));
        
        $response = curl_exec($ch);
        
        if (curl_error($ch)) {
            error_log('OpenAI API Error: ' . curl_error($ch));
            return __('Erreur de connexion à l\'IA. Analyse automatique indisponible.', UNIVGA_TEXT_DOMAIN);
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
        
        error_log('OpenAI API Response Error: ' . $response);
        return __('Erreur lors de la génération de l\'analyse IA.', UNIVGA_TEXT_DOMAIN);
    }
}

// Initialize AI Analytics
UNIVGA_AI_Analytics::getInstance();