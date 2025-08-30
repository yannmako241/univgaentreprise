<?php
/**
 * Admin View: Seat Pools
 * Fichier suggéré : admin/views/admin-pools.php
 */

if ( ! defined('ABSPATH') ) exit;

// ✅ Accès : Admin WP = toujours autorisé. Sinon cap fine.
if ( ! current_user_can('manage_options') && ! current_user_can('univga_seats_view_usage') ) {
    wp_die( __('You do not have sufficient permissions to access this page.', 'univga') );
}

// Chargements défensifs
$includes = [
    'UNIVGA_SeatPools' => 'includes/class-seat-pools.php',
    'UNIVGA_Orgs'      => 'includes/class-orgs.php',
    'UNIVGA_Teams'     => 'includes/class-teams.php',
    'UNIVGA_Members'   => 'includes/class-members.php',
    'UNIVGA_Courses'   => 'includes/class-courses.php', // resolve_scope() etc.
];

foreach ($includes as $class => $path) {
    if ( ! class_exists($class) && file_exists(UNIVGA_PLUGIN_DIR . $path) ) {
        require_once UNIVGA_PLUGIN_DIR . $path;
    }
}

if ( ! class_exists('UNIVGA_SeatPools') ) {
    echo '<div class="notice notice-error"><p>'
        . esc_html__('Le module Seat Pools (UNIVGA_SeatPools) est indisponible. Vérifiez les includes.', 'univga')
        . '</p></div>';
    return;
}

// === Helpers de données ===
function univga_admin_pools_get_list( $args = [] ){
    $defaults = [
        'org_id'    => 0,
        'team_id'   => 0,
        'search'    => '',    // recherche par course id/nom si implémenté côté query
        'scope'     => '',    // course|category|bundle
        'paged'     => 1,
        'per_page'  => 20,
        'orderby'   => 'created_at',
        'order'     => 'DESC',
    ];
    $args = wp_parse_args($args, $defaults);

    if ( method_exists('UNIVGA_SeatPools','query') ) {
        return UNIVGA_SeatPools::query($args);
    }

    // Fallback si pas de query() : retourner structure vide
    return (object)['items'=>[], 'total'=>0, 'per_page'=>(int)$args['per_page'], 'paged'=>(int)$args['paged']];
}

function univga_admin_get_org_label($org_id){
    if ( ! $org_id || ! class_exists('UNIVGA_Orgs') || ! method_exists('UNIVGA_Orgs','get') ) return '—';
    $o = UNIVGA_Orgs::get((int)$org_id);
    return $o && ! empty($o->name) ? $o->name : ('#'.$org_id);
}

function univga_admin_get_team_label($team_id){
    if ( ! $team_id || ! class_exists('UNIVGA_Teams') || ! method_exists('UNIVGA_Teams','get') ) return '—';
    $t = UNIVGA_Teams::get((int)$team_id);
    return $t && ! empty($t->name) ? $t->name : ('#'.$team_id);
}

function univga_admin_scope_label($pool){
    $type = isset($pool->scope_type) ? $pool->scope_type : '';
    $ids  = isset($pool->scope_ids) ? $pool->scope_ids : '';
    if ( is_string($ids) && $ids && $ids[0] === '[' ) { // JSON en base ?
        $ids = json_decode($ids, true);
    }
    $ids = is_array($ids) ? $ids : (array)$ids;

    $label = strtoupper($type ?: '?');
    $detail = '';

    if ( class_exists('UNIVGA_Courses') && method_exists('UNIVGA_Courses','labels_from_scope') ) {
        // Laisse la classe résoudre des labels lisibles
        $detail = UNIVGA_Courses::labels_from_scope($type, $ids);
    } else {
        $detail = implode(',', array_map('intval', $ids));
    }

    return $label . ( $detail ? ' — ' . $detail : '' );
}

// === Actions (delete / quick-assign) ===
$can_manage = current_user_can('manage_options') || current_user_can('univga_seats_manage');

// Suppression pool
if ( isset($_GET['action'], $_GET['pool_id']) && $_GET['action']==='delete' && $can_manage ) {
    $pool_id = absint($_GET['pool_id']);
    check_admin_referer('univga_pool_delete_' . $pool_id);
    if ( method_exists('UNIVGA_SeatPools','delete') ) {
        $ok = UNIVGA_SeatPools::delete($pool_id);
        if ( is_wp_error($ok) ) {
            add_settings_error('univga_pools', 'pool_delete_err', $ok->get_error_message(), 'error');
        } else {
            add_settings_error('univga_pools', 'pool_delete_ok', __('Pool supprimé.', 'univga'), 'updated');
        }
    } else {
        add_settings_error('univga_pools', 'pool_delete_missing', __('Suppression indisponible (méthode manquante).', 'univga'), 'error');
    }
}

// Assignation rapide d’un utilisateur au pool (consommer 1 siège)
if ( isset($_POST['univga_quick_assign']) && $can_manage ) {
    check_admin_referer('univga_quick_assign_nonce');
    $pool_id = isset($_POST['pool_id']) ? absint($_POST['pool_id']) : 0;
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

    if ( $pool_id && $user_id && method_exists('UNIVGA_SeatPools','assign_user') ) {
        $res = UNIVGA_SeatPools::assign_user($pool_id, $user_id); // doit faire consume + enroll
        if ( is_wp_error($res) ) {
            add_settings_error('univga_pools', 'assign_err', $res->get_error_message(), 'error');
        } else {
            add_settings_error('univga_pools', 'assign_ok', __('Utilisateur assigné et enrôlé avec succès.', 'univga'), 'updated');
        }
    } else {
        add_settings_error('univga_pools', 'assign_missing', __('Paramètres invalides ou méthode indisponible.', 'univga'), 'error');
    }
}

// === Filtres / UI params ===
$org_id   = isset($_GET['org_id']) ? absint($_GET['org_id']) : 0;
$team_id  = isset($_GET['team_id']) ? absint($_GET['team_id']) : 0;
$scope    = isset($_GET['scope']) ? sanitize_text_field($_GET['scope']) : '';
$search   = isset($_GET['s']) ? sanitize_text_field( wp_unslash($_GET['s']) ) : '';
$paged    = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page = isset($_GET['per_page']) ? min(100, max(5, absint($_GET['per_page']))) : 20;

$res = univga_admin_pools_get_list([
    'org_id'   => $org_id,
    'team_id'  => $team_id,
    'scope'    => $scope,
    'search'   => $search,
    'paged'    => $paged,
    'per_page' => $per_page,
]);

$items = isset($res->items) ? (array)$res->items : [];
$total = isset($res->total) ? (int)$res->total : 0;
$pages = $per_page ? (int)ceil($total / $per_page) : 1;

?>
<div class="wrap univga-pools">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Pools de sièges', 'univga'); ?></h1>

    <?php if ( $can_manage ): ?>
        <a href="<?php echo esc_url( admin_url('admin.php?page=univga-pools&action=new') ); ?>"
           class="page-title-action"><?php echo esc_html__('Ajouter', 'univga'); ?></a>
    <?php endif; ?>

    <hr class="wp-header-end" />

    <?php settings_errors('univga_pools'); ?>

    <!-- Filtres -->
    <form method="get" action="">
        <input type="hidden" name="page" value="univga-pools" />
        <div class="filters" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <div>
                <label for="org_id"><?php _e('Organisation', 'univga'); ?></label>
                <input type="number" min="0" name="org_id" id="org_id" value="<?php echo esc_attr($org_id); ?>" style="width:100px" />
                <?php if ($org_id): ?>
                    <span class="description"> — <?php echo esc_html( univga_admin_get_org_label($org_id) ); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <label for="team_id"><?php _e('Équipe', 'univga'); ?></label>
                <input type="number" min="0" name="team_id" id="team_id" value="<?php echo esc_attr($team_id); ?>" style="width:100px" />
                <?php if ($team_id): ?>
                    <span class="description"> — <?php echo esc_html( univga_admin_get_team_label($team_id) ); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <label for="scope"><?php _e('Scope', 'univga'); ?></label>
                <select name="scope" id="scope">
                    <option value=""><?php _e('Tous', 'univga'); ?></option>
                    <option value="course"   <?php selected($scope,'course'); ?>><?php _e('Cours', 'univga'); ?></option>
                    <option value="category" <?php selected($scope,'category'); ?>><?php _e('Catégorie', 'univga'); ?></option>
                    <option value="bundle"   <?php selected($scope,'bundle'); ?>><?php _e('Bundle', 'univga'); ?></option>
                </select>
            </div>
            <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
                <label for="per_page"><?php _e('Par page', 'univga'); ?></label>
                <input type="number" min="5" max="100" step="5" name="per_page" id="per_page" value="<?php echo esc_attr($per_page); ?>" style="width:80px" />
            </div>
        </div>
        <p class="search-box" style="display:flex; gap:8px; align-items:center; margin-top:10px;">
            <label class="screen-reader-text" for="pool-search-input"><?php _e('Rechercher pools:', 'univga'); ?></label>
            <input type="search" id="pool-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Cours / ID / Note', 'univga'); ?>" />
            <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Filtrer', 'univga'); ?>" />
        </p>
    </form>

    <!-- Table -->
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th style="width:56px">#</th>
                <th><?php _e('Organisation', 'univga'); ?></th>
                <th><?php _e('Équipe', 'univga'); ?></th>
                <th><?php _e('Scope', 'univga'); ?></th>
                <th><?php _e('Sièges', 'univga'); ?></th>
                <th><?php _e('Utilisés', 'univga'); ?></th>
                <th><?php _e('Restants', 'univga'); ?></th>
                <th><?php _e('Expiration', 'univga'); ?></th>
                <th style="width:260px"><?php _e('Actions', 'univga'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty($items) ): ?>
            <tr><td colspan="9"><?php _e('Aucun pool trouvé.', 'univga'); ?></td></tr>
        <?php else: ?>
            <?php foreach ( $items as $pool ): ?>
                <?php
                $id          = isset($pool->id) ? (int)$pool->id : 0;
                $org         = isset($pool->org_id) ? (int)$pool->org_id : 0;
                $team        = isset($pool->team_id) ? (int)$pool->team_id : 0;
                $type        = isset($pool->scope_type) ? $pool->scope_type : '';
                $total       = isset($pool->seats_total) ? (int)$pool->seats_total : 0;
                $used        = isset($pool->seats_used) ? (int)$pool->seats_used : 0;
                $left        = max(0, $total - $used);
                $expires_at  = !empty($pool->expires_at) ? $pool->expires_at : '—';
                $scope_label = univga_admin_scope_label($pool);

                $base = admin_url('admin.php?page=univga-pools');
                $view_url = add_query_arg(['action'=>'view','pool_id'=>$id], $base);
                $edit_url = add_query_arg(['action'=>'edit','pool_id'=>$id], $base);
                $org_url  = admin_url('admin.php?page=univga-organizations&action=view&org_id='.$org);
                $team_url = $team ? admin_url('admin.php?page=univga-teams&action=view&team_id='.$team) : '';
                $del_url  = wp_nonce_url( add_query_arg(['action'=>'delete','pool_id'=>$id], $base), 'univga_pool_delete_'.$id );
                ?>
                <tr>
                    <td><?php echo esc_html($id); ?></td>
                    <td>
                        <?php if ($org): ?>
                            <a href="<?php echo esc_url($org_url); ?>"><strong><?php echo esc_html( univga_admin_get_org_label($org) ); ?></strong></a>
                        <?php else: echo '—'; endif; ?>
                    </td>
                    <td>
                        <?php if ($team): ?>
                            <a href="<?php echo esc_url($team_url); ?>"><?php echo esc_html( univga_admin_get_team_label($team) ); ?></a>
                        <?php else: echo '—'; endif; ?>
                    </td>
                    <td><?php echo esc_html($scope_label); ?></td>
                    <td><?php echo esc_html( number_format_i18n($total) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n($used) ); ?></td>
                    <td>
                        <span style="font-weight:bold; color:<?php echo ($left>0?'#18794e':'#a61b1b'); ?>">
                            <?php echo esc_html( number_format_i18n($left) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($expires_at); ?></td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url($view_url); ?>"><?php _e('Voir', 'univga'); ?></a>
                        <?php if ( $can_manage ): ?>
                            <a class="button button-small" href="<?php echo esc_url($edit_url); ?>"><?php _e('Modifier', 'univga'); ?></a>
                            <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=univga-profiles&org_id='.$org.'&pool_id='.$id) ); ?>"><?php _e('Membres', 'univga'); ?></a>
                            <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=univga-hr&org_id='.$org.'&pool_id='.$id) ); ?>"><?php _e('Reporting', 'univga'); ?></a>
                            <a class="button button-small button-link-delete" href="<?php echo esc_url($del_url); ?>"
                               onclick="return confirm('<?php echo esc_js( __('Supprimer ce pool ? Cette action libérera son quota et l’historique restera en logs.', 'univga') ); ?>');">
                                <?php _e('Supprimer', 'univga'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php if ( $can_manage ): ?>
                    <!-- Assignation rapide d’un utilisateur -->
                    <tr class="univga-quick-assign">
                        <td colspan="9" style="background:#fbfbfb;">
                            <form method="post" action="" style="display:flex; gap:8px; align-items:center;">
                                <?php wp_nonce_field('univga_quick_assign_nonce'); ?>
                                <input type="hidden" name="pool_id" value="<?php echo esc_attr($id); ?>" />
                                <label><?php _e('Assignation rapide à ce pool (ID utilisateur) :', 'univga'); ?></label>
                                <input type="number" name="user_id" min="1" placeholder="<?php esc_attr_e('user_id', 'univga'); ?>" style="width:120px" />
                                <button class="button button-secondary" name="univga_quick_assign" value="1"><?php _e('Assigner & Enrôler', 'univga'); ?></button>
                                <span class="description"><?php _e('Consomme 1 siège et inscrit aux cours du scope.', 'univga'); ?></span>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>

            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th>#</th>
                <th><?php _e('Organisation', 'univga'); ?></th>
                <th><?php _e('Équipe', 'univga'); ?></th>
                <th><?php _e('Scope', 'univga'); ?></th>
                <th><?php _e('Sièges', 'univga'); ?></th>
                <th><?php _e('Utilisés', 'univga'); ?></th>
                <th><?php _e('Restants', 'univga'); ?></th>
                <th><?php _e('Expiration', 'univga'); ?></th>
                <th><?php _e('Actions', 'univga'); ?></th>
            </tr>
        </tfoot>
    </table>

    <!-- Pagination -->
    <?php if ( $pages > 1 ): ?>
        <div class="tablenav bottom" style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
            <div class="tablenav-pages">
                <?php
                $base_url = remove_query_arg(['paged'], set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']));
                $base_url = add_query_arg( 'paged', '%#%', $base_url );
                echo paginate_links( [
                    'base'      => esc_url( $base_url ),
                    'format'    => '',
                    'prev_text' => __('&laquo; Préc.', 'univga'),
                    'next_text' => __('Suiv. &raquo;', 'univga'),
                    'total'     => $pages,
                    'current'   => $paged,
                ] );
                ?>
            </div>
            <div class="displaying-num">
                <?php printf( esc_html__('%1$s éléments', 'univga'), number_format_i18n($total) ); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php do_action('univga_admin_pools_after_table'); ?>
</div>

<style>
.univga-pools .button-link-delete { color:#a00; }
.univga-pools .button-link-delete:hover { color:#f00; }
.univga-quick-assign td { border-top:1px solid #eee; }
</style>