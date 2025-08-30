<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Rapport RH Mensuel', UNIVGA_TEXT_DOMAIN); ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #3b82f6, #10b981); color: white; padding: 30px; text-align: center; }
        .content { background: #f9fafb; padding: 30px; }
        .metric-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 25px 0; }
        .metric-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .metric-value { font-size: 28px; font-weight: bold; color: #3b82f6; }
        .metric-label { font-size: 13px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-change { font-size: 12px; margin-top: 5px; }
        .positive { color: #10b981; }
        .negative { color: #ef4444; }
        .section { margin: 30px 0; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 1px 5px rgba(0,0,0,0.1); }
        .section h3 { color: #1f2937; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; margin-bottom: 20px; }
        .chart-placeholder { background: #f3f4f6; height: 200px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #666; }
        .recommendations { background: #fef3cd; border: 1px solid #fbbf24; border-radius: 8px; padding: 20px; }
        .recommendation-item { margin: 10px 0; padding: 8px 0; }
        .footer { text-align: center; padding: 30px; color: #666; font-size: 12px; }
        .trends-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .trends-table th, .trends-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .trends-table th { background: #f9fafb; font-weight: 600; }
        .compliance-status { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .compliance-item { padding: 15px; border-radius: 8px; }
        .compliance-ok { background: #d1fae5; border-left: 4px solid #10b981; }
        .compliance-warning { background: #fef3cd; border-left: 4px solid #f59e0b; }
        .compliance-danger { background: #fee2e2; border-left: 4px solid #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><?php _e('Rapport RH Mensuel', UNIVGA_TEXT_DOMAIN); ?></h1>
            <p style="font-size: 18px; margin: 10px 0;"><?php echo $report_data['period']; ?></p>
            <p style="opacity: 0.9;"><?php _e('Analyse complète des activités de formation', UNIVGA_TEXT_DOMAIN); ?></p>
        </div>

        <!-- Content -->
        <div class="content">
            <p style="font-size: 16px; margin-bottom: 20px;">
                <?php printf(__('Bonjour %s,', UNIVGA_TEXT_DOMAIN), $manager->display_name); ?>
            </p>
            <p><?php _e('Voici votre rapport mensuel détaillé avec les tendances et analyses de performance :', UNIVGA_TEXT_DOMAIN); ?></p>

            <!-- Executive Summary -->
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-value"><?php echo $report_data['monthly_stats']['total_learners']; ?></div>
                    <div class="metric-label"><?php _e('Apprenants Actifs', UNIVGA_TEXT_DOMAIN); ?></div>
                    <div class="metric-change positive">+<?php echo $report_data['trends']['learners_growth']; ?>%</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $report_data['monthly_stats']['completions']; ?></div>
                    <div class="metric-label"><?php _e('Formations Terminées', UNIVGA_TEXT_DOMAIN); ?></div>
                    <div class="metric-change positive">+<?php echo $report_data['trends']['completion_growth']; ?>%</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo number_format($report_data['monthly_stats']['avg_score'], 1); ?>%</div>
                    <div class="metric-label"><?php _e('Score Moyen', UNIVGA_TEXT_DOMAIN); ?></div>
                    <div class="metric-change"><?php echo $report_data['trends']['score_change']; ?>pts</div>
                </div>
            </div>

            <!-- Performance Trends -->
            <div class="section">
                <h3><?php _e('Tendances de Performance', UNIVGA_TEXT_DOMAIN); ?></h3>
                <div class="chart-placeholder">
                    <?php _e('Graphique des tendances mensuelles', UNIVGA_TEXT_DOMAIN); ?><br>
                    <small><?php _e('(Intégration Chart.js prévue)', UNIVGA_TEXT_DOMAIN); ?></small>
                </div>
                
                <table class="trends-table">
                    <thead>
                        <tr>
                            <th><?php _e('Métrique', UNIVGA_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Ce Mois', UNIVGA_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Mois Précédent', UNIVGA_TEXT_DOMAIN); ?></th>
                            <th><?php _e('Évolution', UNIVGA_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php _e('Inscriptions', UNIVGA_TEXT_DOMAIN); ?></td>
                            <td><?php echo $report_data['trends']['current_enrollments']; ?></td>
                            <td><?php echo $report_data['trends']['previous_enrollments']; ?></td>
                            <td class="<?php echo $report_data['trends']['enrollment_change'] > 0 ? 'positive' : 'negative'; ?>">
                                <?php echo ($report_data['trends']['enrollment_change'] > 0 ? '+' : '') . $report_data['trends']['enrollment_change']; ?>%
                            </td>
                        </tr>
                        <tr>
                            <td><?php _e('Temps d\'étude', UNIVGA_TEXT_DOMAIN); ?></td>
                            <td><?php echo $report_data['trends']['current_study_time']; ?>h</td>
                            <td><?php echo $report_data['trends']['previous_study_time']; ?>h</td>
                            <td class="positive"><?php echo $report_data['trends']['study_time_change']; ?>%</td>
                        </tr>
                        <tr>
                            <td><?php _e('Certificats', UNIVGA_TEXT_DOMAIN); ?></td>
                            <td><?php echo $report_data['trends']['current_certificates']; ?></td>
                            <td><?php echo $report_data['trends']['previous_certificates']; ?></td>
                            <td class="positive"><?php echo $report_data['trends']['certificate_change']; ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Compliance Status -->
            <div class="section">
                <h3><?php _e('État de Conformité', UNIVGA_TEXT_DOMAIN); ?></h3>
                <div class="compliance-status">
                    <div class="compliance-item <?php echo $report_data['compliance_status']['mandatory_rate'] >= 90 ? 'compliance-ok' : ($report_data['compliance_status']['mandatory_rate'] >= 70 ? 'compliance-warning' : 'compliance-danger'); ?>">
                        <strong><?php _e('Formations Obligatoires', UNIVGA_TEXT_DOMAIN); ?></strong><br>
                        <?php echo $report_data['compliance_status']['mandatory_rate']; ?>% complétées
                        (<?php echo $report_data['compliance_status']['mandatory_completed']; ?>/<?php echo $report_data['compliance_status']['mandatory_total']; ?>)
                    </div>
                    <div class="compliance-item <?php echo $report_data['compliance_status']['certifications_expiring'] == 0 ? 'compliance-ok' : ($report_data['compliance_status']['certifications_expiring'] <= 5 ? 'compliance-warning' : 'compliance-danger'); ?>">
                        <strong><?php _e('Certifications', UNIVGA_TEXT_DOMAIN); ?></strong><br>
                        <?php echo $report_data['compliance_status']['certifications_expiring']; ?> expirent dans 30 jours
                        <br><small><?php echo $report_data['compliance_status']['certifications_active']; ?> actives au total</small>
                    </div>
                </div>
            </div>

            <!-- Budget Utilization -->
            <div class="section">
                <h3><?php _e('Utilisation du Budget Formation', UNIVGA_TEXT_DOMAIN); ?></h3>
                <div style="background: #f3f4f6; padding: 20px; border-radius: 8px;">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div>
                            <strong><?php _e('Budget Alloué', UNIVGA_TEXT_DOMAIN); ?></strong><br>
                            <span style="font-size: 24px; color: #3b82f6;"><?php echo number_format($report_data['budget_utilization']['allocated'], 0, ',', ' '); ?>€</span>
                        </div>
                        <div>
                            <strong><?php _e('Budget Utilisé', UNIVGA_TEXT_DOMAIN); ?></strong><br>
                            <span style="font-size: 24px; color: #10b981;"><?php echo number_format($report_data['budget_utilization']['used'], 0, ',', ' '); ?>€</span>
                            <small>(<?php echo $report_data['budget_utilization']['usage_percentage']; ?>%)</small>
                        </div>
                    </div>
                    <div style="margin-top: 15px;">
                        <div style="background: #e5e7eb; height: 10px; border-radius: 5px; overflow: hidden;">
                            <div style="background: #10b981; height: 100%; width: <?php echo $report_data['budget_utilization']['usage_percentage']; ?>%; transition: width 0.3s;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="section">
                <h3><?php _e('Recommandations', UNIVGA_TEXT_DOMAIN); ?></h3>
                <div class="recommendations">
                    <?php if (!empty($report_data['recommendations'])): ?>
                        <?php foreach ($report_data['recommendations'] as $recommendation): ?>
                        <div class="recommendation-item">
                            <strong>• <?php echo esc_html($recommendation['title']); ?></strong><br>
                            <small><?php echo esc_html($recommendation['description']); ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="recommendation-item">
                            <strong>• <?php _e('Excellente performance', UNIVGA_TEXT_DOMAIN); ?></strong><br>
                            <small><?php _e('Continuez sur cette lancée ! Tous les indicateurs sont au vert.', UNIVGA_TEXT_DOMAIN); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="section" style="text-align: center; background: linear-gradient(135deg, #3b82f6, #10b981); color: white;">
                <h3 style="color: white; border: none;"><?php _e('Actions Recommandées', UNIVGA_TEXT_DOMAIN); ?></h3>
                <p><?php _e('Consultez le tableau de bord détaillé pour des analyses approfondies', UNIVGA_TEXT_DOMAIN); ?></p>
                <a href="<?php echo admin_url('admin.php?page=univga-hr-dashboards'); ?>" 
                   style="display: inline-block; background: white; color: #3b82f6; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 15px 0; font-weight: bold;">
                    <?php _e('Accéder au Dashboard RH', UNIVGA_TEXT_DOMAIN); ?>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong><?php _e('Ce rapport a été généré automatiquement par UNIVGA LMS', UNIVGA_TEXT_DOMAIN); ?></strong></p>
            <p><?php printf(__('Pour modifier vos préférences ou exporter des données, %saccédez aux paramètres%s', UNIVGA_TEXT_DOMAIN), '<a href="' . admin_url('admin.php?page=univga-settings') . '">', '</a>'); ?></p>
            <p style="margin-top: 15px; font-size: 10px; color: #9ca3af;">
                <?php _e('Rapport généré le', UNIVGA_TEXT_DOMAIN); ?> <?php echo date('d/m/Y à H:i'); ?> | 
                <?php _e('Version', UNIVGA_TEXT_DOMAIN); ?> <?php echo UNIVGA_PLUGIN_VERSION; ?>
            </p>
        </div>
    </div>
</body>
</html>