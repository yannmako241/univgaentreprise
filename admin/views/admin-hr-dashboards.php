<?php
/**
 * Admin View: HR Reporting
 * Fichier suggéré : admin/views/admin-hr.php
 */

if ( ! defined('ABSPATH') ) exit;

// ✅ Accès : admin WP = accès total
if ( ! current_user_can('manage_options') ) {
    if ( ! current_user_can('univga_hr_manager') && ! current_user_can('univga_team_lead') ) {
        wp_die(__('Access restricted to HR managers and team leaders.', 'univga'));
    }
}

// Chargement défensif
$includes = [
    'UNIVGA_Orgs'     => 'includes/class-orgs.php',
    'UNIVGA_Teams'    => 'includes/class-teams.php',
    'UNIVGA_Members'  => 'includes/class-members.php',
    'UNIVGA_Reports'  => 'includes/class-reports.php',
];

foreach ($includes as $class => $path) {
    if ( ! class_exists($class) && file_exists(UNIVGA_PLUGIN_DIR.$path) ) {
        require_once UNIVGA_PLUGIN_DIR.$path;
    }
}

// Helpers simples
function univga_hr_get_org_label($org_id){
    return ( class_exists('UNIVGA_Orgs') && method_exists('UNIVGA_Orgs','get') )
        ? (UNIVGA_Orgs::get($org_id)->name ?? '#'.$org_id)
        : '#'.$org_id;
}

function univga_hr_get_team_label($team_id){
    return ( class_exists('UNIVGA_Teams') && method_exists('UNIVGA_Teams','get') )
        ? (UNIVGA_Teams::get($team_id)->name ?? '#'.$team_id)
        : '#'.$team_id;
}

// Filtres
$org_id   = isset($_GET['org_id']) ? absint($_GET['org_id']) : 0;
$team_id  = isset($_GET['team_id']) ? absint($_GET['team_id']) : 0;
$period   = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30d'; // ex: 7d,30d,90d
$search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

// Récupération données
$members = [];
if ( class_exists('UNIVGA_Reports') && method_exists('UNIVGA_Reports','members_progress') ) {
    $members = UNIVGA_Reports::members_progress([
        'org_id'  => $org_id,
        'team_id' => $team_id,
        'period'  => $period,
        'search'  => $search,
    ]);
}

$total_members = is_array($members) ? count($members) : 0;
$avg_progress  = $total_members && function_exists('wp_list_pluck')
    ? round(array_sum(wp_list_pluck($members, 'progress')) / $total_members, 1)
    : 0;

?>
<div class="wrap univga-hr">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Reporting RH', 'univga'); ?></h1>
    <hr class="wp-header-end" />

    <!-- Filtres -->
    <form method="get" action="">
        <input type="hidden" name="page" value="univga-hr" />
        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px;">
            <div>
                <label for="org_id"><?php _e('Organisation', 'univga'); ?></label><br/>
                <input type="number" min="0" name="org_id" id="org_id" value="<?php echo esc_attr($org_id); ?>" style="width:120px" />
                <?php if ($org_id): ?>
                    <span class="description">— <?php echo esc_html(univga_hr_get_org_label($org_id)); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <label for="team_id"><?php _e('Équipe', 'univga'); ?></label><br/>
                <input type="number" min="0" name="team_id" id="team_id" value="<?php echo esc_attr($team_id); ?>" style="width:120px" />
                <?php if ($team_id): ?>
                    <span class="description">— <?php echo esc_html(univga_hr_get_team_label($team_id)); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <label for="period"><?php _e('Période', 'univga'); ?></label><br/>
                <select name="period" id="period">
                    <option value="7d"  <?php selected($period,'7d'); ?>><?php _e('7 derniers jours','univga'); ?></option>
                    <option value="30d" <?php selected($period,'30d'); ?>><?php _e('30 derniers jours','univga'); ?></option>
                    <option value="90d" <?php selected($period,'90d'); ?>><?php _e('90 derniers jours','univga'); ?></option>
                    <option value="all" <?php selected($period,'all'); ?>><?php _e('Tout','univga'); ?></option>
                </select>
            </div>
            <div style="margin-left:auto;">
                <label for="s"><?php _e('Recherche', 'univga'); ?></label><br/>
                <input type="search" name="s" id="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Nom ou email', 'univga'); ?>" />
                <button class="button"><?php _e('Filtrer', 'univga'); ?></button>
            </div>
        </div>
    </form>

    <!-- KPIs -->
    <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
        <div style="flex:1; min-width:160px; background:#fff; padding:16px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
            <h3><?php _e('Employés suivis','univga'); ?></h3>
            <p style="font-size:24px;font-weight:bold;"><?php echo esc_html($total_members); ?></p>
        </div>
        <div style="flex:1; min-width:160px; background:#fff; padding:16px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
            <h3><?php _e('Progression moyenne','univga'); ?></h3>
            <p style="font-size:24px;font-weight:bold;"><?php echo esc_html($avg_progress); ?>%</p>
        </div>
    </div>

    <!-- Tableau des employés -->
    <div style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <h2><?php _e('Suivi individuel','univga'); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Employé','univga'); ?></th>
                    <th><?php _e('Email','univga'); ?></th>
                    <th><?php _e('Équipe','univga'); ?></th>
                    <th><?php _e('Cours inscrits','univga'); ?></th>
                    <th><?php _e('Progression','univga'); ?></th>
                    <th><?php _e('Dernier accès','univga'); ?></th>
                    <th><?php _e('Certificats','univga'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty($members) ): ?>
                <tr><td colspan="7"><?php _e('Aucun membre trouvé pour ces critères.', 'univga'); ?></td></tr>
            <?php else: foreach ( $members as $m ): ?>
                <tr>
                    <td><?php echo esc_html($m->name ?? '#'.$m->user_id); ?></td>
                    <td><?php echo esc_html($m->email ?? ''); ?></td>
                    <td><?php echo esc_html($m->team_id ? univga_hr_get_team_label($m->team_id) : '—'); ?></td>
                    <td><?php echo esc_html($m->courses_count ?? 0); ?></td>
                    <td>
                        <span style="font-weight:bold; color:<?php echo ($m->progress>=50?'#18794e':'#a61b1b'); ?>">
                            <?php echo esc_html(round($m->progress,1)); ?>%
                        </span>
                    </td>
                    <td><?php echo esc_html($m->last_access ?? '—'); ?></td>
                    <td><?php echo esc_html($m->certificates_count ?? 0); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Exports / Actions -->
    <div style="margin-top:20px;">
        <?php if ( class_exists('UNIVGA_Reports') && method_exists('UNIVGA_Reports','export_csv') ): ?>
            <form method="post">
                <?php wp_nonce_field('univga_hr_export_csv'); ?>
                <input type="hidden" name="org_id" value="<?php echo esc_attr($org_id); ?>" />
                <input type="hidden" name="team_id" value="<?php echo esc_attr($team_id); ?>" />
                <input type="hidden" name="period" value="<?php echo esc_attr($period); ?>" />
                <button class="button button-primary" name="univga_hr_export" value="csv"><?php _e('Exporter CSV','univga'); ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php do_action('univga_admin_hr_after_table', $members, $org_id, $team_id, $period); ?>
</div>