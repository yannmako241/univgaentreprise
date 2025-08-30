<?php
/**
 * Admin Dashboard View
 * Fichier : admin/views/admin-dashboard.php
 */

if ( ! defined('ABSPATH') ) exit;

// ✅ Vérification permissions : Admin WP = accès total
if ( ! current_user_can('manage_options') && ! current_user_can('univga_org_view') ) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'univga'));
}

// Charger classes nécessaires (défensif)
if ( ! class_exists('UNIVGA_Reports') && file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-reports.php') ) {
    require_once UNIVGA_PLUGIN_DIR . 'includes/class-reports.php';
}
if ( ! class_exists('UNIVGA_SeatPools') && file_exists(UNIVGA_PLUGIN_DIR . 'includes/class-seat-pools.php') ) {
    require_once UNIVGA_PLUGIN_DIR . 'includes/class-seat-pools.php';
}

// Récupérer données de base
$orgs_count     = function_exists('UNIVGA_Orgs::count') ? UNIVGA_Orgs::count() : 0;
$teams_count    = function_exists('UNIVGA_Teams::count') ? UNIVGA_Teams::count() : 0;
$members_count  = function_exists('UNIVGA_Members::count') ? UNIVGA_Members::count() : 0;
$pools_count    = function_exists('UNIVGA_SeatPools::count') ? UNIVGA_SeatPools::count() : 0;

// KPIs exemple : progression moyenne & certificats
$kpi = [
    'progression_moyenne' => method_exists('UNIVGA_Reports','get_avg_progress') ? UNIVGA_Reports::get_avg_progress() : 0,
    'certificats_total'   => method_exists('UNIVGA_Reports','get_total_certificates') ? UNIVGA_Reports::get_total_certificates() : 0,
];

?>

<div class="wrap univga-dashboard">
    <h1><?php echo esc_html__('Tableau de bord UNIVGA Business Pro', 'univga'); ?></h1>
    <p class="description">
        <?php echo esc_html__('Vue d’ensemble des organisations, équipes et formations en cours.', 'univga'); ?>
    </p>

    <!-- Cartes KPI -->
    <div class="univga-cards" style="display:flex; gap:20px; flex-wrap:wrap; margin-top:20px;">
        <div class="card" style="flex:1; min-width:180px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <h3><?php _e('Organisations', 'univga'); ?></h3>
            <p style="font-size:24px; font-weight:bold;"><?php echo esc_html($orgs_count); ?></p>
        </div>
        <div class="card" style="flex:1; min-width:180px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <h3><?php _e('Équipes', 'univga'); ?></h3>
            <p style="font-size:24px; font-weight:bold;"><?php echo esc_html($teams_count); ?></p>
        </div>
        <div class="card" style="flex:1; min-width:180px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <h3><?php _e('Membres', 'univga'); ?></h3>
            <p style="font-size:24px; font-weight:bold;"><?php echo esc_html($members_count); ?></p>
        </div>
        <div class="card" style="flex:1; min-width:180px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <h3><?php _e('Pools de sièges', 'univga'); ?></h3>
            <p style="font-size:24px; font-weight:bold;"><?php echo esc_html($pools_count); ?></p>
        </div>
    </div>

    <!-- Bloc Analytics -->
    <div style="margin-top:40px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Analytique Formation', 'univga'); ?></h2>
        <ul>
            <li><?php printf(__('Progression moyenne : %s%%', 'univga'), esc_html($kpi['progression_moyenne'])); ?></li>
            <li><?php printf(__('Certificats délivrés : %s', 'univga'), esc_html($kpi['certificats_total'])); ?></li>
        </ul>
        <?php if ( class_exists('UNIVGA_AI_Analytics') ) : ?>
            <div class="ai-analytics" style="margin-top:20px;">
                <h3><?php _e('Insights IA', 'univga'); ?></h3>
                <?php
                try {
                    $ai = UNIVGA_AI_Analytics::get_instance();
                    echo '<p>' . esc_html( $ai->generate_summary() ) . '</p>';
                } catch ( Exception $e ) {
                    echo '<p style="color:red;">' . esc_html__('IA Analytics indisponible : ', 'univga') . esc_html($e->getMessage()) . '</p>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tableau résumé -->
    <div style="margin-top:40px; background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Résumé des activités récentes', 'univga'); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'univga'); ?></th>
                    <th><?php _e('Événement', 'univga'); ?></th>
                    <th><?php _e('Utilisateur', 'univga'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ( class_exists('UNIVGA_SeatEvents') ) {
                    $events = UNIVGA_SeatEvents::get_recent(10);
                    if ( $events ) {
                        foreach ( $events as $ev ) {
                            echo '<tr>';
                            echo '<td>' . esc_html( $ev->created_at ) . '</td>';
                            echo '<td>' . esc_html( ucfirst($ev->type) ) . '</td>';
                            echo '<td>' . esc_html( $ev->user_id ? get_userdata($ev->user_id)->user_login : '-' ) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3">' . esc_html__('Aucun événement récent.', 'univga') . '</td></tr>';
                    }
                } else {
                    echo '<tr><td colspan="3">' . esc_html__('Module SeatEvents non disponible.', 'univga') . '</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>