<?php
/**
 * Admin View: AI Analytics
 * Fichier suggéré : admin/views/admin-ai.php
 *
 * Cette vue suppose l'existence d'un wrapper IA (UNIVGA_AI_Analytics)
 * et d'un module de reporting (UNIVGA_Reports). Tout est “defensive-coded”
 * pour éviter les fatals si ces classes n'existent pas encore.
 */

if ( ! defined('ABSPATH') ) exit;

// ✅ Accès : Admin WP = accès total ; sinon cap fine.
if ( ! current_user_can('manage_options') && ! current_user_can('univga_ai_view') ) {
    wp_die( __('You do not have sufficient permissions to access this page.', 'univga') );
}

// Chargements défensifs
$includes = [
    'UNIVGA_Orgs'        => 'includes/class-orgs.php',
    'UNIVGA_Teams'       => 'includes/class-teams.php',
    'UNIVGA_Reports'     => 'includes/class-reports.php',
    'UNIVGA_AI_Analytics'=> 'includes/class-ai-analytics.php', // wrapper IA (OpenAI)
];

foreach ($includes as $class => $path) {
    if ( ! class_exists($class) && file_exists(UNIVGA_PLUGIN_DIR.$path) ) {
        require_once UNIVGA_PLUGIN_DIR.$path;
    }
}

// ===== Helpers basiques =====
function univga_ai_get_org_label($org_id){
    if ( ! $org_id || ! class_exists('UNIVGA_Orgs') || ! method_exists('UNIVGA_Orgs','get') ) return '';
    $o = UNIVGA_Orgs::get((int)$org_id);
    return $o && ! empty($o->name) ? $o->name : ('#'.$org_id);
}
function univga_ai_get_team_label($team_id){
    if ( ! $team_id || ! class_exists('UNIVGA_Teams') || ! method_exists('UNIVGA_Teams','get') ) return '';
    $t = UNIVGA_Teams::get((int)$team_id);
    return $t && ! empty($t->name) ? $t->name : ('#'.$team_id);
}

// ===== Lecture filtres UI =====
$org_id   = isset($_GET['org_id']) ? absint($_GET['org_id']) : 0;
$team_id  = isset($_GET['team_id']) ? absint($_GET['team_id']) : 0;
$period   = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30d'; // 7d|30d|90d|all
$mode     = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : 'summary'; // summary|risk|path

// ===== Gestion actions (analyse IA / export / email) =====
$ai_output   = '';
$ai_error    = '';
$raw_context = null; // données agrégées non sensibles envoyées à l’IA (résumées côté serveur)

if ( isset($_POST['univga_ai_analyze']) ) {
    check_admin_referer('univga_ai_analyze_nonce');

    // 1) Récupérer un contexte “safe” depuis UNIVGA_Reports (pas d'emails nominatives si possible)
    if ( class_exists('UNIVGA_Reports') && method_exists('UNIVGA_Reports','ai_context') ) {
        try {
            $raw_context = UNIVGA_Reports::ai_context([
                'org_id'  => $org_id,
                'team_id' => $team_id,
                'period'  => $period,
                'mode'    => $mode,
            ]);
        } catch ( Exception $e ) {
            $ai_error = sprintf(__('Erreur lors de la préparation des données: %s', 'univga'), $e->getMessage());
        }
    } else {
        $ai_error = __('Module de rapports indisponible (UNIVGA_Reports::ai_context manquant).', 'univga');
    }

    // 2) Appel IA (via wrapper)
    if ( empty($ai_error) ) {
        if ( class_exists('UNIVGA_AI_Analytics') && method_exists('UNIVGA_AI_Analytics','analyze') ) {
            try {
                // Conseil : le wrapper devrait gérer : clé API, prompts, rate-limit, cache transients.
                $ai_output = UNIVGA_AI_Analytics::analyze($raw_context, [
                    'mode'   => $mode,
                    'org_id' => $org_id,
                    'team_id'=> $team_id,
                    'period' => $period,
                ]);
                if ( is_wp_error($ai_output) ) {
                    $ai_error = $ai_output->get_error_message();
                    $ai_output = '';
                }
            } catch ( Exception $e ) {
                $ai_error = sprintf(__('Erreur IA: %s', 'univga'), $e->getMessage());
            }
        } else {
            $ai_error = __('Module IA indisponible (UNIVGA_AI_Analytics::analyze manquant).', 'univga');
        }
    }
}

// Export TXT (contenu IA)
if ( isset($_POST['univga_ai_export_txt']) && ! empty($_POST['ai_text']) ) {
    check_admin_referer('univga_ai_export_nonce');
    $content = wp_kses_post( wp_unslash($_POST['ai_text']) );
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="univga-ai-analytics.txt"');
    echo $content;
    exit;
}

// Envoi email (résumé IA aux managers)
if ( isset($_POST['univga_ai_send_email']) && ! empty($_POST['ai_text']) ) {
    check_admin_referer('univga_ai_email_nonce');
    $content = wp_kses_post( wp_unslash($_POST['ai_text']) );
    // À implémenter côté plugin : récupérer emails des managers d’org/team
    $recipients = [];
    if ( class_exists('UNIVGA_Orgs') && method_exists('UNIVGA_Orgs','manager_emails') ) {
        $recipients = UNIVGA_Orgs::manager_emails($org_id, $team_id);
    }
    if ( empty($recipients) ) {
        $ai_error = __('Aucun destinataire manager détecté (vérifiez vos responsables RH).', 'univga');
    } else {
        $subject = sprintf( __('[UNIVGA] Analytics IA — %s', 'univga'),
            $org_id ? univga_ai_get_org_label($org_id) : __('Organisation non spécifiée','univga')
        );
        $ok = wp_mail( $recipients, $subject, $content );
        if ( $ok ) {
            add_settings_error('univga_ai', 'ai_mail_ok', __('Résumé IA envoyé aux managers.', 'univga'), 'updated');
        } else {
            add_settings_error('univga_ai', 'ai_mail_err', __('Échec d’envoi email.', 'univga'), 'error');
        }
    }
}

?>
<div class="wrap univga-ai">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Analytics IA', 'univga'); ?></h1>
    <hr class="wp-header-end" />

    <?php settings_errors('univga_ai'); ?>

    <!-- Alerte configuration IA -->
    <?php if ( class_exists('UNIVGA_AI_Analytics') && method_exists('UNIVGA_AI_Analytics','is_configured_static') ): ?>
        <?php if ( ! UNIVGA_AI_Analytics::is_configured_static() ): ?>
            <div class="notice notice-warning">
                <p><strong><?php _e('Configuration IA requise', 'univga'); ?></strong> —
                    <?php _e('Renseignez votre clé API et les paramètres IA dans les Réglages.', 'univga'); ?>
                    <?php if ( current_user_can('manage_options') ): ?>
                        <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=univga-settings#ai') ); ?>">
                            <?php _e('Aller aux réglages IA', 'univga'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Filtres -->
    <form method="get" action="">
        <input type="hidden" name="page" value="univga-ai" />
        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <label for="org_id"><?php _e('Organisation','univga'); ?></label><br/>
                <input type="number" name="org_id" id="org_id" value="<?php echo esc_attr($org_id); ?>" style="width:120px" />
                <?php if ($org_id): ?>
                    <span class="description">— <?php echo esc_html(univga_ai_get_org_label($org_id)); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <label for="team_id"><?php _e('Équipe','univga'); ?></label><br/>
                <input type="number" name="team_id" id="team_id" value="<?php echo esc_attr($team_id); ?>" style="width:120px" />
                <?php if ($team_id): ?>
                    <span class="description">— <?php echo esc_html(univga_ai_get_team_label($team_id)); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <label for="period"><?php _e('Période','univga'); ?></label><br/>
                <select name="period" id="period">
                    <option value="7d"  <?php selected($period,'7d'); ?>><?php _e('7 jours','univga'); ?></option>
                    <option value="30d" <?php selected($period,'30d'); ?>><?php _e('30 jours','univga'); ?></option>
                    <option value="90d" <?php selected($period,'90d'); ?>><?php _e('90 jours','univga'); ?></option>
                    <option value="all" <?php selected($period,'all'); ?>><?php _e('Tout','univga'); ?></option>
                </select>
            </div>
            <div>
                <label for="mode"><?php _e('Mode d’analyse','univga'); ?></label><br/>
                <select name="mode" id="mode">
                    <option value="summary" <?php selected($mode,'summary'); ?>><?php _e('Résumé & insights', 'univga'); ?></option>
                    <option value="risk"    <?php selected($mode,'risk');    ?>><?php _e('Risques & actions', 'univga'); ?></option>
                    <option value="path"    <?php selected($mode,'path');    ?>><?php _e('Recommandations parcours', 'univga'); ?></option>
                </select>
            </div>
            <div style="align-self:flex-end;">
                <button class="button"><?php _e('Appliquer filtres', 'univga'); ?></button>
            </div>
        </div>
    </form>

    <!-- Lancer l’analyse IA -->
    <form method="post" action="" style="margin-bottom:16px;">
        <?php wp_nonce_field('univga_ai_analyze_nonce'); ?>
        <input type="hidden" name="org_id" value="<?php echo esc_attr($org_id); ?>" />
        <input type="hidden" name="team_id" value="<?php echo esc_attr($team_id); ?>" />
        <input type="hidden" name="period" value="<?php echo esc_attr($period); ?>" />
        <input type="hidden" name="mode" value="<?php echo esc_attr($mode); ?>" />
        <button class="button button-primary" name="univga_ai_analyze" value="1">
            <?php _e('Générer les insights IA', 'univga'); ?>
        </button>
    </form>

    <!-- Résultat IA -->
    <div style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <h2><?php _e('Insights IA','univga'); ?></h2>

        <?php if ( $ai_error ): ?>
            <div class="notice notice-error"><p><?php echo esc_html($ai_error); ?></p></div>
        <?php elseif ( $ai_output ): ?>
            <form method="post" action="" style="margin-bottom:12px;">
                <?php wp_nonce_field('univga_ai_export_nonce'); ?>
                <textarea name="ai_text" rows="12" style="width:100%;"><?php echo esc_textarea($ai_output); ?></textarea>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <button class="button" name="univga_ai_export_txt" value="1"><?php _e('Exporter (.txt)', 'univga'); ?></button>
                    <?php wp_nonce_field('univga_ai_email_nonce'); ?>
                    <button class="button button-secondary" name="univga_ai_send_email" value="1"><?php _e('Envoyer aux managers', 'univga'); ?></button>
                </div>
            </form>
        <?php else: ?>
            <p class="description">
                <?php _e('Choisissez une organisation/équipe, la période et le mode, puis cliquez sur “Générer les insights IA”.', 'univga'); ?>
            </p>
        <?php endif; ?>
    </div>

    <?php
    /**
     * Hook d’extension pour ajouter des widgets IA (ex: graphiques, matrices compétences)
     */
    do_action('univga_admin_ai_after_insights', $org_id, $team_id, $period, $mode, $ai_output);
    ?>
</div>