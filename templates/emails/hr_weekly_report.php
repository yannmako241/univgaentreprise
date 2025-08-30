<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Rapport RH Hebdomadaire', UNIVGA_TEXT_DOMAIN); ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #3b82f6; color: white; padding: 20px; text-align: center; }
        .content { background: #f9fafb; padding: 20px; }
        .metric-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
        .metric-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .metric-value { font-size: 24px; font-weight: bold; color: #3b82f6; }
        .metric-label { font-size: 12px; color: #666; text-transform: uppercase; }
        .section { margin: 20px 0; }
        .section h3 { color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        .top-courses { background: white; padding: 15px; border-radius: 8px; }
        .course-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><?php _e('Rapport RH Hebdomadaire', UNIVGA_TEXT_DOMAIN); ?></h1>
            <p><?php echo $report_data['period']; ?></p>
        </div>

        <!-- Content -->
        <div class="content">
            <p><?php printf(__('Bonjour %s,', UNIVGA_TEXT_DOMAIN), $manager->display_name); ?></p>
            <p><?php _e('Voici votre résumé hebdomadaire des activités de formation :', UNIVGA_TEXT_DOMAIN); ?></p>

            <!-- Key Metrics -->
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-value"><?php echo $report_data['new_enrollments']; ?></div>
                    <div class="metric-label"><?php _e('Nouvelles Inscriptions', UNIVGA_TEXT_DOMAIN); ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $report_data['completions']; ?></div>
                    <div class="metric-label"><?php _e('Formations Terminées', UNIVGA_TEXT_DOMAIN); ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $report_data['certificates_earned']; ?></div>
                    <div class="metric-label"><?php _e('Certificats Obtenus', UNIVGA_TEXT_DOMAIN); ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $report_data['active_learners']; ?></div>
                    <div class="metric-label"><?php _e('Apprenants Actifs', UNIVGA_TEXT_DOMAIN); ?></div>
                </div>
            </div>

            <!-- Top Courses -->
            <div class="section">
                <h3><?php _e('Cours Les Plus Populaires', UNIVGA_TEXT_DOMAIN); ?></h3>
                <div class="top-courses">
                    <?php if (!empty($report_data['top_courses'])): ?>
                        <?php foreach (array_slice($report_data['top_courses'], 0, 5) as $course): ?>
                        <div class="course-item">
                            <span><?php echo esc_html($course['title']); ?></span>
                            <span><strong><?php echo $course['enrollments']; ?></strong> inscriptions</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p><?php _e('Aucune nouvelle inscription cette semaine.', UNIVGA_TEXT_DOMAIN); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Team Performance -->
            <div class="section">
                <h3><?php _e('Performance par Équipe', UNIVGA_TEXT_DOMAIN); ?></h3>
                <div class="top-courses">
                    <?php if (!empty($report_data['team_performance'])): ?>
                        <?php foreach ($report_data['team_performance'] as $team): ?>
                        <div class="course-item">
                            <span><?php echo esc_html($team['name']); ?></span>
                            <span>
                                <strong><?php echo $team['avg_progress']; ?>%</strong> progression moyenne
                                (<?php echo $team['completions']; ?> complétions)
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p><?php _e('Aucune activité d\'équipe cette semaine.', UNIVGA_TEXT_DOMAIN); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Items -->
            <div class="section">
                <h3><?php _e('Points d\'Attention', UNIVGA_TEXT_DOMAIN); ?></h3>
                <ul>
                    <?php if ($report_data['completions'] == 0): ?>
                    <li><?php _e('Aucune formation terminée cette semaine - envisager des rappels', UNIVGA_TEXT_DOMAIN); ?></li>
                    <?php endif; ?>
                    
                    <?php if ($report_data['active_learners'] < 5): ?>
                    <li><?php _e('Faible engagement des apprenants - planifier des sessions de motivation', UNIVGA_TEXT_DOMAIN); ?></li>
                    <?php endif; ?>
                    
                    <?php if (empty($report_data['certificates_earned'])): ?>
                    <li><?php _e('Aucun certificat obtenu - vérifier les examens en cours', UNIVGA_TEXT_DOMAIN); ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Call to Action -->
            <div class="section" style="text-align: center; background: white; padding: 20px; border-radius: 8px;">
                <p><strong><?php _e('Accéder au tableau de bord complet', UNIVGA_TEXT_DOMAIN); ?></strong></p>
                <a href="<?php echo admin_url('admin.php?page=univga-hr-dashboards'); ?>" 
                   style="display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px 0;">
                    <?php _e('Voir les Détails', UNIVGA_TEXT_DOMAIN); ?>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><?php _e('Ce rapport a été généré automatiquement par UNIVGA LMS', UNIVGA_TEXT_DOMAIN); ?></p>
            <p><?php printf(__('Pour modifier vos préférences de notification, %saccédez aux paramètres%s', UNIVGA_TEXT_DOMAIN), '<a href="' . admin_url('admin.php?page=univga-settings') . '">', '</a>'); ?></p>
        </div>
    </div>
</body>
</html>