<?php
/**
 * Admin View: Organizations
 * Fichier suggéré : admin/views/admin-organizations.php
 */

if ( ! defined('ABSPATH') ) exit;

// ✅ Accès : Admin WP = toujours autorisé. Sinon, au moins la cap de vue.
if ( ! current_user_can('manage_options') && ! current_user_can('univga_org_view') ) {
    wp_die( __('You do not have sufficient permissions to access this page.', 'univga') );
}

// Chargements défensifs (pas de fatal si les classes ne sont pas encore incluses)
$includes = [
    'UNIVGA_Orgs'  => 'includes/class-orgs.php',
    'UNIVGA_Teams' => 'includes/class-teams.php',
];

foreach ($includes as $class => $path) {
    if ( ! class_exists($class) && file_exists(UNIVGA_PLUGIN_DIR . $path) ) {
        require_once UNIVGA_PLUGIN_DIR . $path;
    }
}

// Alerte si le module Orgs n’est pas dispo
if ( ! class_exists('UNIVGA_Orgs') ) {
    echo '<div class="notice notice-error"><p>'
        . esc_html__('Le module Organisations (UNIVGA_Orgs) est indisponible. Vérifiez les includes.', 'univga')
        . '</p></div>';
    return;
}

/**
 * Helper: récupère la liste des organisations
 * On privilégie une méthode centrale si elle existe, sinon on fallback.
 */
function univga_admin_orgs_get_list( $args = [] ) {
    $defaults = [
        'search'   => '',
        'paged'    => 1,
        'per_page' => 20,
        'order'    => 'DESC',
        'orderby'  => 'created_at',
        // Ajouts optionnels : status, domain, etc.
    ];
    $args = wp_parse_args($args, $defaults);

    if ( method_exists('UNIVGA_Orgs', 'query') ) {
        return UNIVGA_Orgs::query($args);
    }

    // Fallback minimal si pas de méthode query :
    if ( method_exists('UNIVGA_Orgs', 'all') ) {
        $all = UNIVGA_Orgs::all();
        // Filtre basique search (sur le nom)
        if ( ! empty($args['search']) ) {
            $key = mb_strtolower( $args['search'] );
            $all = array_filter($all, function($o) use ($key){
                $name = isset($o->name) ? mb_strtolower($o->name) : '';
                return ( $name && strpos($name, $key) !== false );
            });
        }
        // Pagination basique
        $total = count($all);
        $offset = max(0, ( (int)$args['paged'] - 1 ) * (int)$args['per_page']);
        $items = array_slice($all, $offset, (int)$args['per_page']);

        return (object)[
            'items' => $items,
            'total' => $total,
            'per_page' => (int)$args['per_page'],
            'paged' => (int)$args['paged'],
        ];
    }

    // Rien : retourner structure vide
    return (object)[ 'items'=>[], 'total'=>0, 'per_page'=>20, 'paged'=>1 ];
}

/**
 * Actions (delete) — sécurisées par nonce + capability manage
 */
if ( isset($_GET['action'], $_GET['org_id']) && $_GET['action']==='delete' ) {
    if ( current_user_can('manage_options') || current_user_can('univga_org_manage') ) {
        check_admin_referer( 'univga_org_delete_' . absint($_GET['org_id']) );
        if ( method_exists('UNIVGA_Orgs','delete') ) {
            $deleted = UNIVGA_Orgs::delete( absint($_GET['org_id']) );
            if ( is_wp_error($deleted) ) {
                add_settings_error('univga_orgs', 'org_delete_err', $deleted->get_error_message(), 'error');
            } else {
                add_settings_error('univga_orgs', 'org_delete_ok', __('Organisation supprimée.', 'univga'), 'updated');
            }
        } else {
            add_settings_error('univga_orgs', 'org_delete_missing', __('Suppression indisponible (méthode manquante).', 'univga'), 'error');
        }
    } else {
        add_settings_error('univga_orgs', 'org_delete_perm', __('Accès refusé : gestion des organisations.', 'univga'), 'error');
    }
}

/**
 * Lecture paramètres UI (recherche, pagination)
 */
$search   = isset($_GET['s']) ? sanitize_text_field( wp_unslash($_GET['s']) ) : '';
$paged    = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page = isset($_GET['per_page']) ? min(100, max(5, absint($_GET['per_page']))) : 20;

$results  = univga_admin_orgs_get_list([
    'search'   => $search,
    'paged'    => $paged,
    'per_page' => $per_page,
]);
$items    = isset($results->items) ? (array)$results->items : [];
$total    = isset($results->total) ? (int)$results->total : 0;
$pages    = $per_page ? (int)ceil( $total / $per_page ) : 1;

// Capacité de gestion (pour bouton “Ajouter” et actions)
$can_manage = current_user_can('manage_options') || current_user_can('univga_org_manage');

?>

<div class="wrap univga-organizations">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Organisations partenaires', 'univga'); ?></h1>

    <?php if ( $can_manage ): ?>
        <a href="<?php echo esc_url( admin_url('admin.php?page=univga-organizations&action=new') ); ?>"
           class="page-title-action"><?php echo esc_html__('Ajouter', 'univga'); ?></a>
    <?php endif; ?>

    <hr class="wp-header-end" />

    <?php settings_errors('univga_orgs'); ?>

    <!-- Filtres / Recherche -->
    <form method="get" action="">
        <input type="hidden" name="page" value="univga-organizations" />
        <p class="search-box" style="display:flex;gap:8px;align-items:center;">
            <label class="screen-reader-text" for="org-search-input"><?php _e('Rechercher organisations:', 'univga'); ?></label>
            <input type="search" id="org-search-input" name="s" value="<?php echo esc_attr($search); ?>" />
            <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Rechercher', 'univga'); ?>" />
            <span style="margin-left:auto; display:flex; gap:6px; align-items:center;">
                <label for="per_page"><?php _e('Par page', 'univga'); ?></label>
                <input type="number" min="5" max="100" step="5" name="per_page" id="per_page" value="<?php echo esc_attr($per_page); ?>" style="width:80px" />
            </span>
        </p>
    </form>

    <!-- Table -->
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th style="width:36px">#</th>
                <th><?php _e('Nom', 'univga'); ?></th>
                <th><?php _e('Identifiant légal', 'univga'); ?></th>
                <th><?php _e('Contact', 'univga'); ?></th>
                <th><?php _e('Domaine email', 'univga'); ?></th>
                <th><?php _e('Statut', 'univga'); ?></th>
                <th><?php _e('Créée le', 'univga'); ?></th>
                <th style="width:220px"><?php _e('Actions', 'univga'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty($items) ): ?>
            <tr><td colspan="8"><?php _e('Aucune organisation trouvée.', 'univga'); ?></td></tr>
        <?php else: ?>
            <?php foreach ( $items as $index => $org ): ?>
                <?php
                // Sécuriser champs
                $id       = isset($org->id) ? (int)$org->id : 0;
                $name     = isset($org->name) ? $org->name : '';
                $legal_id = isset($org->legal_id) ? $org->legal_id : '';
                $contact  = '';
                if ( ! empty($org->contact_user_id) ) {
                    $u = get_userdata( (int)$org->contact_user_id );
                    $contact = $u ? $u->display_name . ' <' . $u->user_email . '>' : '';
                }
                $domain   = isset($org->email_domain) ? $org->email_domain : '';
                $status   = isset($org->status) ? (int)$org->status : 1;
                $created  = isset($org->created_at) ? $org->created_at : '';

                $status_label = $status ? __('Actif','univga') : __('Inactif','univga');

                // URLs d’actions
                $base = admin_url('admin.php?page=univga-organizations');
                $view_url  = add_query_arg(['action'=>'view','org_id'=>$id], $base);
                $edit_url  = add_query_arg(['action'=>'edit','org_id'=>$id], $base);
                $teams_url = admin_url('admin.php?page=univga-teams&org_id='.$id);
                $del_url   = wp_nonce_url( add_query_arg(['action'=>'delete','org_id'=>$id], $base), 'univga_org_delete_'.$id );
                ?>
                <tr>
                    <td><?php echo esc_html( $id ); ?></td>
                    <td><strong><a href="<?php echo esc_url($view_url); ?>"><?php echo esc_html($name ?: '#'.$id); ?></a></strong></td>
                    <td><?php echo esc_html($legal_id ?: '—'); ?></td>
                    <td><?php echo esc_html($contact ?: '—'); ?></td>
                    <td><?php echo esc_html($domain ?: '—'); ?></td>
                    <td>
                        <span class="badge" style="padding:2px 8px;border-radius:999px; background:<?php echo $status? '#e6f7ed' : '#fdeaea'; ?>; color:<?php echo $status? '#18794e' : '#a61b1b'; ?>">
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($created ?: '—'); ?></td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url($view_url); ?>"><?php _e('Voir', 'univga'); ?></a>
                        <?php if ( $can_manage ): ?>
                            <a class="button button-small" href="<?php echo esc_url($edit_url); ?>"><?php _e('Modifier', 'univga'); ?></a>
                            <a class="button button-small" href="<?php echo esc_url($teams_url); ?>"><?php _e('Équipes', 'univga'); ?></a>
                            <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=univga-pools&org_id='.$id) ); ?>"><?php _e('Pools', 'univga'); ?></a>
                            <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=univga-profiles&org_id='.$id) ); ?>"><?php _e('Profils', 'univga'); ?></a>
                            <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=univga-hr&org_id='.$id) ); ?>"><?php _e('Reporting RH', 'univga'); ?></a>
                            <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=univga-ai&org_id='.$id) ); ?>"><?php _e('Analytics IA', 'univga'); ?></a>
                            <a class="button button-small button-link-delete" href="<?php echo esc_url($del_url); ?>"
                               onclick="return confirm('<?php echo esc_js( __('Supprimer cette organisation ? Cette action est irréversible.', 'univga') ); ?>');">
                                <?php _e('Supprimer', 'univga'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th>#</th>
                <th><?php _e('Nom', 'univga'); ?></th>
                <th><?php _e('Identifiant légal', 'univga'); ?></th>
                <th><?php _e('Contact', 'univga'); ?></th>
                <th><?php _e('Domaine email', 'univga'); ?></th>
                <th><?php _e('Statut', 'univga'); ?></th>
                <th><?php _e('Créée le', 'univga'); ?></th>
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
                <?php
                printf(
                    esc_html__( '%1$s éléments', 'univga' ),
                    number_format_i18n( $total )
                );
                ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    /**
     * Hook d’extension pour blocs custom (ex: stats org, CTA, import CSV)
     * @since 1.0.0
     */
    do_action('univga_admin_organizations_after_table');
    ?>
</div>

<style>
.univga-organizations .button-link-delete {
    color:#a00;
}
.univga-organizations .button-link-delete:hover {
    color:#f00;
}
</style>
<?php
// HANDLE ORGANIZATION CREATION AND EDITING FORMS
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$org_id = isset($_GET['org_id']) ? absint($_GET['org_id']) : 0;

if ($action === 'new' && $can_manage) {
    // CREATE NEW ORGANIZATION FORM
    ?>
    <div class="wrap">
        <h1><?php _e('Créer une nouvelle organisation', 'univga'); ?></h1>
        
        <form method="post" action="<?php echo admin_url('admin.php?page=univga-organizations&action=create_org'); ?>">
            <?php wp_nonce_field('create_org'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="name"><?php _e('Nom de l\'organisation', 'univga'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" id="name" name="name" required class="regular-text" />
                        <p class="description"><?php _e('Nom complet de l\'organisation', 'univga'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="legal_id"><?php _e('Identifiant légal', 'univga'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="legal_id" name="legal_id" class="regular-text" />
                        <p class="description"><?php _e('SIRET, numéro d\'enregistrement, etc.', 'univga'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="contact_user_id"><?php _e('Contact principal', 'univga'); ?></label>
                    </th>
                    <td>
                        <select id="contact_user_id" name="contact_user_id">
                            <option value=""><?php _e('Sélectionner un utilisateur', 'univga'); ?></option>
                            <?php
                            $users = get_users(array('role' => 'administrator'));
                            foreach ($users as $user) {
                                echo '<option value="' . $user->ID . '">' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php _e('Administrateur responsable de cette organisation', 'univga'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_domain"><?php _e('Domaine email', 'univga'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="email_domain" name="email_domain" class="regular-text" placeholder="exemple.com" />
                        <p class="description"><?php _e('Domaine email de l\'organisation (sans @)', 'univga'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="status"><?php _e('Statut', 'univga'); ?></label>
                    </th>
                    <td>
                        <select id="status" name="status">
                            <option value="1"><?php _e('Actif', 'univga'); ?></option>
                            <option value="0"><?php _e('Inactif', 'univga'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e('Créer l\'organisation', 'univga'); ?>" />
                <a href="<?php echo admin_url('admin.php?page=univga-organizations'); ?>" class="button"><?php _e('Annuler', 'univga'); ?></a>
            </p>
            
            <div class="notice notice-info">
                <p><strong><?php _e('Note importante :', 'univga'); ?></strong> <?php _e('Tous les administrateurs WordPress seront automatiquement ajoutés comme membres de cette organisation.', 'univga'); ?></p>
            </div>
        </form>
    </div>
    <?php
    return; // Stop processing to show only the form
}

if ($action === 'edit' && $org_id && $can_manage) {
    // EDIT ORGANIZATION FORM
    $org = UNIVGA_Orgs::get($org_id);
    if (!$org) {
        echo '<div class="notice notice-error"><p>' . __('Organisation introuvable.', 'univga') . '</p></div>';
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php printf(__('Modifier l\'organisation : %s', 'univga'), esc_html($org->name)); ?></h1>
        
        <form method="post" action="<?php echo admin_url('admin.php?page=univga-organizations&action=edit_org'); ?>">
            <?php wp_nonce_field('edit_org'); ?>
            <input type="hidden" name="org_id" value="<?php echo $org_id; ?>" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="name"><?php _e('Nom de l\'organisation', 'univga'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" id="name" name="name" required class="regular-text" value="<?php echo esc_attr($org->name); ?>" />
                        <p class="description"><?php _e('Nom complet de l\'organisation', 'univga'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="legal_id"><?php _e('Identifiant légal', 'univga'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="legal_id" name="legal_id" class="regular-text" value="<?php echo esc_attr($org->legal_id); ?>" />
                        <p class="description"><?php _e('SIRET, numéro d\'enregistrement, etc.', 'univga'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="contact_user_id"><?php _e('Contact principal', 'univga'); ?></label>
                    </th>
                    <td>
                        <select id="contact_user_id" name="contact_user_id">
                            <option value=""><?php _e('Sélectionner un utilisateur', 'univga'); ?></option>
                            <?php
                            $users = get_users(array('role' => 'administrator'));
                            foreach ($users as $user) {
                                $selected = ($user->ID == $org->contact_user_id) ? 'selected="selected"' : '';
                                echo '<option value="' . $user->ID . '" ' . $selected . '>' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php _e('Administrateur responsable de cette organisation', 'univga'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_domain"><?php _e('Domaine email', 'univga'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="email_domain" name="email_domain" class="regular-text" value="<?php echo esc_attr($org->email_domain); ?>" placeholder="exemple.com" />
                        <p class="description"><?php _e('Domaine email de l\'organisation (sans @)', 'univga'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="status"><?php _e('Statut', 'univga'); ?></label>
                    </th>
                    <td>
                        <select id="status" name="status">
                            <option value="1" <?php selected($org->status, 1); ?>><?php _e('Actif', 'univga'); ?></option>
                            <option value="0" <?php selected($org->status, 0); ?>><?php _e('Inactif', 'univga'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e('Mettre à jour l\'organisation', 'univga'); ?>" />
                <a href="<?php echo admin_url('admin.php?page=univga-organizations'); ?>" class="button"><?php _e('Annuler', 'univga'); ?></a>
            </p>
        </form>
        
        <hr />
        
        <!-- SECTION: Gestion des administrateurs de l'organisation -->
        <h2><?php _e('Administrateurs de cette organisation', 'univga'); ?></h2>
        
        <?php
        $org_admins = UNIVGA_Orgs::get_organization_admins($org_id);
        if (!empty($org_admins)) {
            echo '<table class="widefat">';
            echo '<thead><tr><th>' . __('Nom', 'univga') . '</th><th>' . __('Email', 'univga') . '</th><th>' . __('Actions', 'univga') . '</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($org_admins as $admin) {
                echo '<tr>';
                echo '<td>' . esc_html($admin->display_name) . '</td>';
                echo '<td>' . esc_html($admin->user_email) . '</td>';
                echo '<td>';
                
                // Allow removal of other admins (not themselves)
                if ($admin->ID != get_current_user_id()) {
                    $remove_url = wp_nonce_url(
                        admin_url("admin.php?page=univga-organizations&action=remove_admin&org_id=$org_id&user_id=" . $admin->ID),
                        'remove_admin_' . $org_id . '_' . $admin->ID
                    );
                    echo '<a href="' . esc_url($remove_url) . '" class="button button-small button-link-delete" ';
                    echo 'onclick="return confirm(\'' . esc_js(__('Supprimer cet administrateur de l\'organisation ?', 'univga')) . '\');">';
                    echo __('Supprimer', 'univga') . '</a>';
                } else {
                    echo '<em>' . __('(Vous-même)', 'univga') . '</em>';
                }
                
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('Aucun administrateur trouvé pour cette organisation.', 'univga') . '</p>';
        }
        ?>
    </div>
    <?php
    return; // Stop processing to show only the form
}
?>
