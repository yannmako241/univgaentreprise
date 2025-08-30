<?php

/**
 * Admin interface management
 */
class UNIVGA_Admin {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        
        // Frontend admin AJAX handlers
        add_action('wp_ajax_univga_update_organization_frontend', array($this, 'ajax_update_organization_frontend'));
        add_action('wp_ajax_univga_save_branding_frontend', array($this, 'ajax_save_branding_frontend'));
        add_action('wp_ajax_univga_get_admin_teams', array($this, 'ajax_get_admin_teams'));
        add_action('wp_ajax_univga_get_admin_members', array($this, 'ajax_get_admin_members'));
        add_action('wp_ajax_univga_get_admin_seat_pools', array($this, 'ajax_get_admin_seat_pools'));
        add_action('wp_ajax_univga_get_admin_settings', array($this, 'ajax_get_admin_settings'));
        add_action('wp_ajax_univga_load_dashboard_metrics', array($this, 'ajax_load_dashboard_metrics'));
        add_action('wp_ajax_univga_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_univga_quick_action', array($this, 'ajax_quick_action'));
        add_action('wp_ajax_univga_bulk_action', array($this, 'ajax_bulk_action'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Ensure administrators have the required capabilities
        $this->ensure_admin_capabilities();
        
        // Always use manage_options for administrators - this is the safest approach
        $admin_cap = 'manage_options';
        
        // Main menu with dashboard (changed slug to avoid conflicts)
        add_menu_page(
            __('Tableau de Bord UNIVGA', UNIVGA_TEXT_DOMAIN),
            __('UNIVGA ENTREPRISES', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-admin-hub',
            array($this, 'display_dashboard_page'),
            'dashicons-dashboard',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'univga-admin-hub',
            __('Tableau de Bord', UNIVGA_TEXT_DOMAIN),
            __('Tableau de Bord', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-admin-hub',
            array($this, 'display_dashboard_page')
        );
        
        add_submenu_page(
            'univga-admin-hub',
            __('Organisations', UNIVGA_TEXT_DOMAIN),
            __('Organisations', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-organizations',
            array($this, 'display_organizations_page')
        );
        
        add_submenu_page(
            'univga-admin-hub',
            __('Équipes', UNIVGA_TEXT_DOMAIN),
            __('Équipes', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-teams',
            array($this, 'display_teams_page')
        );
        
        add_submenu_page(
            'univga-admin-hub',
            __('Membres', UNIVGA_TEXT_DOMAIN),
            __('Membres', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-members',
            array($this, 'display_members_page')
        );
        
        add_submenu_page(
            'univga-admin-hub',
            __('Pools de Sièges', UNIVGA_TEXT_DOMAIN),
            __('Pools de Sièges', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-pools',
            array($this, 'display_pools_page')
        );
        
        // User Profiles & Permissions - ALL ADMINS can access
        add_submenu_page(
            'univga-admin-hub',
            __('Profils Utilisateurs', UNIVGA_TEXT_DOMAIN),
            __('Profils Utilisateurs', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-profiles',
            array($this, 'display_profiles_page')
        );
        
        // HR Dashboards - ALL ADMINS can access
        add_submenu_page(
            'univga-admin-hub',
            __('Suivi & Reporting RH', UNIVGA_TEXT_DOMAIN),
            __('Reporting RH', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-hr-dashboards',
            array($this, 'display_hr_dashboards_page')
        );
        
        // AI Analytics - ALL ADMINS can access
        add_submenu_page(
            'univga-admin-hub',
            __('Analytics Intelligents', UNIVGA_TEXT_DOMAIN),
            __('Analytics IA', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-ai-analytics',
            array($this, 'display_ai_analytics_page')
        );
        
        add_submenu_page(
            'univga-admin-hub',
            __('Paramètres', UNIVGA_TEXT_DOMAIN),
            __('Paramètres', UNIVGA_TEXT_DOMAIN),
            $admin_cap,
            'univga-settings',
            array($this, 'display_settings_page')
        );
    }
    
    /**
     * Ensure admin capabilities are properly set
     */
    private function ensure_admin_capabilities() {
        // Only run for administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $admin_role = get_role('administrator');
        if ($admin_role) {
            // Add all UNIVGA capabilities to administrator role
            $capabilities = array(
                // Core admin capabilities
                'univga_admin_access',
                
                // Organization management
                'univga_org_create',
                'univga_org_edit',
                'univga_org_delete',
                'univga_org_view',
                'univga_org_manage',
                
                // Team management
                'univga_team_create',
                'univga_team_edit',
                'univga_team_delete',
                'univga_team_view',
                'univga_team_manage',
                'univga_team_assign_members',
                
                // Member management
                'univga_member_invite',
                'univga_member_remove',
                'univga_member_edit',
                'univga_member_view_all',
                'univga_member_view_team',
                
                // Seat pool management
                'univga_seats_create',
                'univga_seats_edit',
                'univga_seats_assign',
                'univga_seats_revoke',
                'univga_seats_view_usage',
                'univga_seats_manage',
                
                // Analytics and reporting
                'univga_analytics_basic',
                'univga_analytics_advanced',
                'univga_reports_generate',
                'univga_reports_export',
                'univga_reports_view_team',
                'univga_reports_view_org',
                'univga_reports_view',
                
                // Learning paths
                'univga_learning_paths_create',
                'univga_learning_paths_assign',
                'univga_learning_paths_manage',
                
                // Certifications
                'univga_cert_create',
                'univga_cert_award',
                'univga_cert_revoke',
                'univga_cert_view_all',
                
                // Bulk operations
                'univga_bulk_import',
                'univga_bulk_enroll',
                'univga_bulk_operations',
                
                // Gamification
                'univga_gamification_manage',
                'univga_badges_create',
                'univga_badges_award',
                'univga_points_adjust',
                
                // Notifications
                'univga_notifications_send',
                'univga_notifications_broadcast',
                'univga_templates_manage',
                
                // Integrations
                'univga_integrations_manage',
                'univga_sso_configure',
                'univga_hr_sync',
                
                // Branding
                'univga_branding_manage',
                'univga_custom_css',
                'univga_custom_domain',
                
                // System settings
                'univga_settings_global',
                'univga_permissions_manage',
                'univga_audit_logs',
                'univga_system_health',
                
                // Internal messaging
                'univga_messaging_send',
                'univga_messaging_moderate',
                'univga_messaging_view_all',
                'univga_messaging_admin'
            );
            
            foreach ($capabilities as $cap) {
                if (!$admin_role->has_cap($cap)) {
                    $admin_role->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!isset($_GET['page']) || strpos($_GET['page'], 'univga-') !== 0) {
            return;
        }
        
        // Check admin permissions before handling any actions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (!isset($_GET['action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['action']);
        
        switch ($action) {
            case 'create_org':
                $this->handle_create_organization();
                break;
            case 'edit_org':
                $this->handle_edit_organization();
                break;
            case 'delete_org':
                $this->handle_delete_organization();
                break;
            case 'create_team':
                $this->handle_create_team();
                break;
            case 'edit_team':
                $this->handle_edit_team();
                break;
            case 'delete_team':
                $this->handle_delete_team();
                break;
            case 'manual_resync':
                $this->handle_manual_resync();
                break;
            case 'remove_admin':
            case 'remove_admin':
                $this->handle_remove_admin_from_organization();
                break;
        }
    }
    
    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Tableau de Bord UNIVGA', UNIVGA_TEXT_DOMAIN) . '</h1>';
        echo '<div class="univga-dashboard">';
        echo '<div class="welcome-panel">';
        echo '<h2>' . __('Bienvenue dans UNIVGA Business Pro', UNIVGA_TEXT_DOMAIN) . '</h2>';
        echo '<p>' . __('Gérez vos organisations, équipes et utilisateurs depuis ce tableau de bord.', UNIVGA_TEXT_DOMAIN) . '</p>';
        echo '</div>';
        
        // Quick stats
        $org_count = UNIVGA_Orgs::get_count();
        echo '<div class="univga-stats-grid">';
        echo '<div class="univga-stat-card">';
        echo '<h3>' . $org_count . '</h3>';
        echo '<p>' . __('Organisations', UNIVGA_TEXT_DOMAIN) . '</p>';
        echo '</div>';
        echo '</div>';
        
        // Quick actions
        echo '<div class="univga-quick-actions">';
        echo '<h3>' . __('Actions Rapides', UNIVGA_TEXT_DOMAIN) . '</h3>';
        echo '<a href="' . admin_url('admin.php?page=univga-organizations&action=create') . '" class="button button-primary">';
        echo __('Créer une Organisation', UNIVGA_TEXT_DOMAIN);
        echo '</a>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        // Add some basic CSS
        echo '<style>
        .univga-dashboard { margin-top: 20px; }
        .welcome-panel { background: #f1f1f1; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .univga-stats-grid { display: flex; gap: 20px; margin-bottom: 20px; }
        .univga-stat-card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
        .univga-stat-card h3 { font-size: 2em; margin: 0; color: #0073aa; }
        .univga-quick-actions { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        </style>';
    }
    
    /**
     * Display organizations page
     */
    public function display_organizations_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'create':
                $this->display_create_organization_form();
                break;
            case 'edit':
                $this->display_edit_organization_form();
                break;
            default:
                $this->display_organizations_list();
                break;
        }
    }
    
    /**
     * Display organizations list
     */
    private function display_organizations_list() {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Organisations', UNIVGA_TEXT_DOMAIN) . '</h1>';
        echo '<a href="' . admin_url('admin.php?page=univga-organizations&action=create') . '" class="page-title-action">' . __('Ajouter', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '<hr class="wp-header-end">';
        
        $organizations = UNIVGA_Orgs::get_all();
        
        echo '<div class="tablenav top">';
        echo '<p class="search-box">';
        echo '<input type="search" id="organization-search-input" placeholder="' . __('Rechercher les organisations...', UNIVGA_TEXT_DOMAIN) . '">';
        echo '</p>';
        echo '</div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Nom', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Description', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Domaine Email', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Statut', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Actions', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if (empty($organizations)) {
            echo '<tr><td colspan="5">' . __('Aucune organisation trouvée.', UNIVGA_TEXT_DOMAIN) . '</td></tr>';
        } else {
            foreach ($organizations as $org) {
                $edit_url = admin_url('admin.php?page=univga-organizations&action=edit&org_id=' . $org->id);
                $delete_url = wp_nonce_url(
                    admin_url('admin.php?page=univga-organizations&action=delete_org&org_id=' . $org->id),
                    'delete_org_' . $org->id
                );
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($org->name) . '</strong></td>';
                echo '<td>' . esc_html(isset($org->description) ? $org->description : '') . '</td>';
                echo '<td>' . esc_html($org->email_domain) . '</td>';
                echo '<td>' . ($org->status == 1 ? __('Actif', UNIVGA_TEXT_DOMAIN) : __('Inactif', UNIVGA_TEXT_DOMAIN)) . '</td>';
                echo '<td>';
                echo '<a href="' . $edit_url . '" class="button button-small">' . __('Modifier', UNIVGA_TEXT_DOMAIN) . '</a> ';
                echo '<a href="' . $delete_url . '" class="button button-small button-link-delete" onclick="return confirm(\'' . __('Êtes-vous sûr de vouloir supprimer cette organisation ?', UNIVGA_TEXT_DOMAIN) . '\')">' . __('Supprimer', UNIVGA_TEXT_DOMAIN) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Display create organization form
     */
    private function display_create_organization_form() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Créer une Organisation', UNIVGA_TEXT_DOMAIN) . '</h1>';
        
        echo '<form method="post" action="' . admin_url('admin.php?page=univga-organizations&action=create_org') . '">';
        wp_nonce_field('create_org');
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="name">' . __('Nom de l\'organisation', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="text" name="name" id="name" class="regular-text" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="description">' . __('Description', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><textarea name="description" id="description" class="large-text" rows="3"></textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="legal_id">' . __('Identifiant légal', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="text" name="legal_id" id="legal_id" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="email_domain">' . __('Domaine email', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="text" name="email_domain" id="email_domain" class="regular-text" placeholder="example.com"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="max_seats">' . __('Nombre maximum de sièges', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="number" name="max_seats" id="max_seats" class="small-text" value="100" min="1"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="status">' . __('Statut', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="status" id="status">';
        echo '<option value="1">' . __('Actif', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="0">' . __('Inactif', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button-primary" value="' . __('Créer l\'organisation', UNIVGA_TEXT_DOMAIN) . '">';
        echo ' <a href="' . admin_url('admin.php?page=univga-organizations') . '" class="button">' . __('Annuler', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Display edit organization form
     */
    private function display_edit_organization_form() {
        if (!isset($_GET['org_id'])) {
            wp_die(__('ID d\'organisation manquant', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_GET['org_id']);
        $org = UNIVGA_Orgs::get($org_id);
        
        if (!$org) {
            wp_die(__('Organisation introuvable', UNIVGA_TEXT_DOMAIN));
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Modifier l\'Organisation', UNIVGA_TEXT_DOMAIN) . '</h1>';
        
        echo '<form method="post" action="' . admin_url('admin.php?page=univga-organizations&action=edit_org') . '">';
        wp_nonce_field('edit_org_' . $org_id);
        echo '<input type="hidden" name="org_id" value="' . $org_id . '">';
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="name">' . __('Nom de l\'organisation', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="text" name="name" id="name" class="regular-text" value="' . esc_attr($org->name) . '" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="description">' . __('Description', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><textarea name="description" id="description" class="large-text" rows="3">' . esc_textarea(isset($org->description) ? $org->description : '') . '</textarea></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="legal_id">' . __('Identifiant légal', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="text" name="legal_id" id="legal_id" class="regular-text" value="' . esc_attr($org->legal_id) . '"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="email_domain">' . __('Domaine email', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="text" name="email_domain" id="email_domain" class="regular-text" value="' . esc_attr($org->email_domain) . '"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="max_seats">' . __('Nombre maximum de sièges', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="number" name="max_seats" id="max_seats" class="small-text" value="' . esc_attr(isset($org->max_seats) ? $org->max_seats : 100) . '" min="1"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="status">' . __('Statut', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="status" id="status">';
        echo '<option value="1"' . selected($org->status, 1, false) . '>' . __('Actif', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="0"' . selected($org->status, 0, false) . '>' . __('Inactif', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        // Show organization administrators
        $admins = UNIVGA_Orgs::get_organization_admins($org_id);
        if (!empty($admins)) {
            echo '<h3>' . __('Administrateurs de l\'organisation', UNIVGA_TEXT_DOMAIN) . '</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Utilisateur', UNIVGA_TEXT_DOMAIN) . '</th>';
            echo '<th>' . __('Email', UNIVGA_TEXT_DOMAIN) . '</th>';
            echo '<th>' . __('Actions', UNIVGA_TEXT_DOMAIN) . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($admins as $admin) {
                $user = get_userdata($admin);
                if ($user) {
                    echo '<tr>';
                    echo '<td>' . esc_html($user->display_name) . '</td>';
                    echo '<td>' . esc_html($user->user_email) . '</td>';
                    echo '<td>';
                    
                    if ($user->ID != get_current_user_id()) {
                        $remove_url = wp_nonce_url(
                            admin_url('admin.php?page=univga-organizations&action=remove_admin&org_id=' . $org_id . '&user_id=' . $user->ID),
                            'remove_admin_' . $org_id . '_' . $user->ID
                        );
                        echo '<a href="' . $remove_url . '" class="button button-small button-link-delete" onclick="return confirm(\'' . __('Êtes-vous sûr de vouloir retirer cet administrateur ?', UNIVGA_TEXT_DOMAIN) . '\')">' . __('Retirer', UNIVGA_TEXT_DOMAIN) . '</a>';
                    } else {
                        echo '<em>' . __('Vous-même', UNIVGA_TEXT_DOMAIN) . '</em>';
                    }
                    
                    echo '</td>';
                    echo '</tr>';
                }
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button-primary" value="' . __('Mettre à jour l\'organisation', UNIVGA_TEXT_DOMAIN) . '">';
        echo ' <a href="' . admin_url('admin.php?page=univga-organizations') . '" class="button">' . __('Retour à la liste', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Handle removing admin from organization
     */
    private function handle_remove_admin_from_organization() {
        if (!isset($_GET['org_id'], $_GET['user_id'])) {
            wp_die(__('Missing parameters', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_GET['org_id']);
        $user_id = intval($_GET['user_id']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'remove_admin_' . $org_id . '_' . $user_id)) {
            wp_die(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        // Prevent self-removal
        if ($user_id == get_current_user_id()) {
            $redirect_url = add_query_arg(array(
                'action' => 'edit',
                'org_id' => $org_id,
                'message' => __('You cannot remove yourself from the organization', UNIVGA_TEXT_DOMAIN),
                'message_type' => 'error',
            ), admin_url('admin.php?page=univga-organizations'));
        } else {
            $result = UNIVGA_Orgs::remove_admin_from_organization($org_id, $user_id);
            
            if (is_wp_error($result)) {
                $redirect_url = add_query_arg(array(
                    'action' => 'edit',
                    'org_id' => $org_id,
                    'message' => $result->get_error_message(),
                    'message_type' => 'error',
                ), admin_url('admin.php?page=univga-organizations'));
            } else {
                $redirect_url = add_query_arg(array(
                    'action' => 'edit',
                    'org_id' => $org_id,
                    'message' => __('Administrator removed from organization successfully', UNIVGA_TEXT_DOMAIN),
                ), admin_url('admin.php?page=univga-organizations'));
            }
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if (strpos($hook_suffix, 'univga-') === false) {
            return;
        }
        
        wp_enqueue_style('univga-admin', UNIVGA_PLUGIN_URL . 'admin/css/admin.css', array(), UNIVGA_PLUGIN_VERSION);
        wp_enqueue_script('univga-admin', UNIVGA_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), UNIVGA_PLUGIN_VERSION, true);
        
        wp_localize_script('univga-admin', 'univga_admin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('univga_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', UNIVGA_TEXT_DOMAIN),
                'confirm_resync' => __('This will resync all organization data. Continue?', UNIVGA_TEXT_DOMAIN),
            ),
        ));
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $message_type = isset($_GET['message_type']) ? sanitize_text_field($_GET['message_type']) : 'success';
            
            $class = ($message_type === 'error') ? 'notice-error' : 'notice-success';
            
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Handle organization creation
     */
    private function handle_create_organization() {
        if (!isset($_POST['submit']) || !wp_verify_nonce($_POST['_wpnonce'], 'create_org')) {
            wp_die(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'legal_id' => sanitize_text_field($_POST['legal_id']),
            'email_domain' => sanitize_email($_POST['email_domain']),
            'max_seats' => intval($_POST['max_seats']),
            'status' => intval($_POST['status'])
        );
        
        $result = UNIVGA_Orgs::create($org_data);
        
        if (is_wp_error($result)) {
            $redirect_url = add_query_arg(array(
                'message' => $result->get_error_message(),
                'message_type' => 'error',
            ), admin_url('admin.php?page=univga-organizations'));
        } else {
            $redirect_url = add_query_arg(array(
                'message' => __('Organization created successfully', UNIVGA_TEXT_DOMAIN),
            ), admin_url('admin.php?page=univga-organizations'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle organization editing
     */
    private function handle_edit_organization() {
        if (!isset($_POST['submit'], $_POST['org_id']) || !wp_verify_nonce($_POST['_wpnonce'], 'edit_org_' . $_POST['org_id'])) {
            wp_die(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_POST['org_id']);
        $org_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'legal_id' => sanitize_text_field($_POST['legal_id']),
            'email_domain' => sanitize_email($_POST['email_domain']),
            'max_seats' => intval($_POST['max_seats']),
            'status' => intval($_POST['status'])
        );
        
        $result = UNIVGA_Orgs::update($org_id, $org_data);
        
        if (is_wp_error($result)) {
            $redirect_url = add_query_arg(array(
                'action' => 'edit',
                'org_id' => $org_id,
                'message' => $result->get_error_message(),
                'message_type' => 'error',
            ), admin_url('admin.php?page=univga-organizations'));
        } else {
            $redirect_url = add_query_arg(array(
                'action' => 'edit',
                'org_id' => $org_id,
                'message' => __('Organization updated successfully', UNIVGA_TEXT_DOMAIN),
            ), admin_url('admin.php?page=univga-organizations'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle organization deletion
     */
    private function handle_delete_organization() {
        if (!isset($_GET['org_id']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_org_' . $_GET['org_id'])) {
            wp_die(__('Security check failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $org_id = intval($_GET['org_id']);
        $result = UNIVGA_Orgs::delete($org_id);
        
        if (is_wp_error($result)) {
            $redirect_url = add_query_arg(array(
                'message' => $result->get_error_message(),
                'message_type' => 'error',
            ), admin_url('admin.php?page=univga-organizations'));
        } else {
            $redirect_url = add_query_arg(array(
                'message' => __('Organization deleted successfully', UNIVGA_TEXT_DOMAIN),
            ), admin_url('admin.php?page=univga-organizations'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle team creation
     */
    private function handle_create_team() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'create_team')) {
            wp_die(__('Nonce verification failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $team_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'org_id' => intval($_POST['org_id']),
            'manager_user_id' => !empty($_POST['manager_user_id']) ? intval($_POST['manager_user_id']) : null,
        );
        
        $result = UNIVGA_Teams::create($team_data);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        $redirect_url = admin_url('admin.php?page=univga-teams&message=team_created');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle team editing
     */
    private function handle_edit_team() {
        $team_id = intval($_POST['team_id']);
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'edit_team_' . $team_id)) {
            wp_die(__('Nonce verification failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $team_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'manager_user_id' => !empty($_POST['manager_user_id']) ? intval($_POST['manager_user_id']) : null,
        );
        
        $result = UNIVGA_Teams::update($team_id, $team_data);
        
        if (!$result) {
            wp_die(__('Failed to update team', UNIVGA_TEXT_DOMAIN));
        }
        
        $redirect_url = admin_url('admin.php?page=univga-teams&message=team_updated');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle team deletion
     */
    private function handle_delete_team() {
        $team_id = intval($_GET['team_id']);
        
        $result = UNIVGA_Teams::delete($team_id);
        
        if (!$result) {
            wp_die(__('Failed to delete team', UNIVGA_TEXT_DOMAIN));
        }
        
        $redirect_url = admin_url('admin.php?page=univga-teams&message=team_deleted');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle manual resync
     */
    private function handle_manual_resync() {
        // Implementation for manual resync
        wp_die(__('Manual resync not implemented yet', UNIVGA_TEXT_DOMAIN));
    }
    
    /**
     * Display teams page
     */
    public function display_teams_page() {
        // Handle team actions
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_team':
                    $this->handle_create_team();
                    break;
                case 'edit_team':
                    $this->handle_edit_team();
                    break;
                case 'delete_team':
                    if (isset($_GET['team_id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_team_' . $_GET['team_id'])) {
                        $this->handle_delete_team();
                    }
                    break;
            }
        }
        
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        switch ($action) {
            case 'new':
                $this->display_create_team_form();
                break;
            case 'edit':
                $this->display_edit_team_form();
                break;
            default:
                $this->display_teams_list();
                break;
        }
    }
    
    /**
     * Display teams list
     */
    private function display_teams_list() {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Gestion des Équipes', UNIVGA_TEXT_DOMAIN) . '</h1>';
        echo '<a href="' . admin_url('admin.php?page=univga-teams&action=new') . '" class="page-title-action">' . __('Ajouter une équipe', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '<hr class="wp-header-end">';
        
        $teams = UNIVGA_Teams::get_all();
        
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        echo '<select name="filter_org" id="filter_org">';
        echo '<option value="">' . __('Toutes les organisations', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $organizations = UNIVGA_Orgs::get_all();
        foreach ($organizations as $org) {
            echo '<option value="' . $org->id . '">' . esc_html($org->name) . '</option>';
        }
        
        echo '</select>';
        echo '<input type="submit" name="filter_action" id="doaction" class="button action" value="' . __('Filtrer', UNIVGA_TEXT_DOMAIN) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Nom', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Organisation', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Manager', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Membres', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Actions', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if (empty($teams)) {
            echo '<tr><td colspan="5">' . __('Aucune équipe trouvée.', UNIVGA_TEXT_DOMAIN) . '</td></tr>';
        } else {
            foreach ($teams as $team) {
                $edit_url = admin_url('admin.php?page=univga-teams&action=edit&team_id=' . $team->id);
                $delete_url = wp_nonce_url(
                    admin_url('admin.php?page=univga-teams&action=delete_team&team_id=' . $team->id),
                    'delete_team_' . $team->id
                );
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($team->name) . '</strong></td>';
                echo '<td>' . esc_html($team->org_name) . '</td>';
                echo '<td>' . esc_html($team->manager_name ?: __('Non assigné', UNIVGA_TEXT_DOMAIN)) . '</td>';
                echo '<td>' . intval($team->member_count) . '</td>';
                echo '<td>';
                echo '<a href="' . $edit_url . '" class="button button-small">' . __('Modifier', UNIVGA_TEXT_DOMAIN) . '</a> ';
                echo '<a href="' . $delete_url . '" class="button button-small button-link-delete" onclick="return confirm(\'' . __('Êtes-vous sûr de vouloir supprimer cette équipe ?', UNIVGA_TEXT_DOMAIN) . '\')">' . __('Supprimer', UNIVGA_TEXT_DOMAIN) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Display create team form
     */
    private function display_create_team_form() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Créer une Équipe', UNIVGA_TEXT_DOMAIN) . '</h1>';
        
        echo '<form method="post" action="' . admin_url('admin.php?page=univga-teams') . '">';
        wp_nonce_field('create_team');
        echo '<input type="hidden" name="action" value="create_team">';
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="name">' . __('Nom de l\'équipe', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="text" name="name" id="name" class="regular-text" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="org_id">' . __('Organisation', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="org_id" id="org_id" required>';
        echo '<option value="">' . __('Sélectionner une organisation', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $organizations = UNIVGA_Orgs::get_all();
        foreach ($organizations as $org) {
            echo '<option value="' . $org->id . '">' . esc_html($org->name) . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="manager_user_id">' . __('Manager de l\'équipe', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="manager_user_id" id="manager_user_id">';
        echo '<option value="">' . __('Sélectionner un manager', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $users = get_users(array('role__in' => array('administrator', 'team_lead', 'org_manager')));
        foreach ($users as $user) {
            echo '<option value="' . $user->ID . '">' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button-primary" value="' . __('Créer l\'équipe', UNIVGA_TEXT_DOMAIN) . '">';
        echo ' <a href="' . admin_url('admin.php?page=univga-teams') . '" class="button">' . __('Annuler', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Display edit team form
     */
    private function display_edit_team_form() {
        if (!isset($_GET['team_id'])) {
            wp_die(__('ID d\'équipe manquant', UNIVGA_TEXT_DOMAIN));
        }
        
        $team_id = intval($_GET['team_id']);
        $team = UNIVGA_Teams::get_with_details($team_id);
        
        if (!$team) {
            wp_die(__('Équipe introuvable', UNIVGA_TEXT_DOMAIN));
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Modifier l\'Équipe', UNIVGA_TEXT_DOMAIN) . '</h1>';
        
        echo '<form method="post" action="' . admin_url('admin.php?page=univga-teams') . '">';
        wp_nonce_field('edit_team_' . $team_id);
        echo '<input type="hidden" name="action" value="edit_team">';
        echo '<input type="hidden" name="team_id" value="' . $team_id . '">';
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="name">' . __('Nom de l\'équipe', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="text" name="name" id="name" class="regular-text" value="' . esc_attr($team->name) . '" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>' . __('Organisation', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<td><strong>' . esc_html($team->org_name) . '</strong></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="manager_user_id">' . __('Manager de l\'équipe', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="manager_user_id" id="manager_user_id">';
        echo '<option value="">' . __('Sélectionner un manager', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $users = get_users(array('role__in' => array('administrator', 'team_lead', 'org_manager')));
        foreach ($users as $user) {
            $selected = selected($team->manager_user_id, $user->ID, false);
            echo '<option value="' . $user->ID . '"' . $selected . '>' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>' . __('Nombre de membres', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<td><strong>' . intval($team->member_count) . '</strong></td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button-primary" value="' . __('Mettre à jour l\'équipe', UNIVGA_TEXT_DOMAIN) . '">';
        echo ' <a href="' . admin_url('admin.php?page=univga-teams') . '" class="button">' . __('Annuler', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Display members page
     */
    public function display_members_page() {
        // Handle member actions
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_member':
                    $this->handle_create_member();
                    break;
                case 'edit_member':
                    $this->handle_edit_member();
                    break;
                case 'delete_member':
                    if (isset($_GET['member_id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_member_' . $_GET['member_id'])) {
                        $this->handle_delete_member();
                    }
                    break;
            }
        }
        
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        switch ($action) {
            case 'new':
                $this->display_create_member_form();
                break;
            case 'edit':
                $this->display_edit_member_form();
                break;
            default:
                $this->display_members_list();
                break;
        }
    }
    
    /**
     * Display members list
     */
    private function display_members_list() {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Gestion des Membres', UNIVGA_TEXT_DOMAIN) . '</h1>';
        echo '<a href="' . admin_url('admin.php?page=univga-members&action=new') . '" class="page-title-action">' . __('Ajouter un membre', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '<hr class="wp-header-end">';
        
        $members = UNIVGA_Members::get_all();
        
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        echo '<select name="filter_org" id="filter_org">';
        echo '<option value="">' . __('Toutes les organisations', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $organizations = UNIVGA_Orgs::get_all();
        foreach ($organizations as $org) {
            echo '<option value="' . $org->id . '">' . esc_html($org->name) . '</option>';
        }
        
        echo '</select>';
        echo '<select name="filter_status" id="filter_status">';
        echo '<option value="">' . __('Tous les statuts', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="active">' . __('Actif', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="removed">' . __('Supprimé', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '<input type="submit" name="filter_action" id="doaction" class="button action" value="' . __('Filtrer', UNIVGA_TEXT_DOMAIN) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Utilisateur', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Email', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Organisation', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Équipe', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Statut', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Date d\'ajout', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Actions', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if (empty($members)) {
            echo '<tr><td colspan="7">' . __('Aucun membre trouvé.', UNIVGA_TEXT_DOMAIN) . '</td></tr>';
        } else {
            foreach ($members as $member) {
                $edit_url = admin_url('admin.php?page=univga-members&action=edit&member_id=' . $member->id);
                $delete_url = wp_nonce_url(
                    admin_url('admin.php?page=univga-members&action=delete_member&member_id=' . $member->id),
                    'delete_member_' . $member->id
                );
                
                $status_class = $member->status === 'active' ? 'active' : 'removed';
                $status_text = $member->status === 'active' ? __('Actif', UNIVGA_TEXT_DOMAIN) : __('Supprimé', UNIVGA_TEXT_DOMAIN);
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($member->user_display_name ?: $member->user_login) . '</strong></td>';
                echo '<td>' . esc_html($member->user_email) . '</td>';
                echo '<td>' . esc_html($member->org_name) . '</td>';
                echo '<td>' . esc_html($member->team_name ?: __('Aucune équipe', UNIVGA_TEXT_DOMAIN)) . '</td>';
                echo '<td><span class="status-' . $status_class . '">' . $status_text . '</span></td>';
                $join_date = !empty($member->joined_at) ? date_i18n(get_option('date_format'), strtotime($member->joined_at)) : __('Date inconnue', UNIVGA_TEXT_DOMAIN);
                echo '<td>' . esc_html($join_date) . '</td>';
                echo '<td>';
                echo '<a href="' . $edit_url . '" class="button button-small">' . __('Modifier', UNIVGA_TEXT_DOMAIN) . '</a> ';
                if ($member->status === 'active') {
                    echo '<a href="' . $delete_url . '" class="button button-small button-link-delete" onclick="return confirm(\'' . __('Êtes-vous sûr de vouloir supprimer ce membre ?', UNIVGA_TEXT_DOMAIN) . '\')">' . __('Supprimer', UNIVGA_TEXT_DOMAIN) . '</a>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Display create member form
     */
    private function display_create_member_form() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Ajouter un Membre', UNIVGA_TEXT_DOMAIN) . '</h1>';
        
        echo '<form method="post" action="' . admin_url('admin.php?page=univga-members') . '">';
        wp_nonce_field('create_member');
        echo '<input type="hidden" name="action" value="create_member">';
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="user_id">' . __('Utilisateur', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="user_id" id="user_id" required>';
        echo '<option value="">' . __('Sélectionner un utilisateur', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $users = get_users();
        foreach ($users as $user) {
            echo '<option value="' . $user->ID . '">' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="org_id">' . __('Organisation', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="org_id" id="org_id" required>';
        echo '<option value="">' . __('Sélectionner une organisation', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $organizations = UNIVGA_Orgs::get_all();
        foreach ($organizations as $org) {
            echo '<option value="' . $org->id . '">' . esc_html($org->name) . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="team_id">' . __('Équipe (optionnel)', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="team_id" id="team_id">';
        echo '<option value="">' . __('Aucune équipe', UNIVGA_TEXT_DOMAIN) . '</option>';
        // Teams will be loaded via AJAX based on organization selection
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="status">' . __('Statut', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="status" id="status">';
        echo '<option value="active">' . __('Actif', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="removed">' . __('Supprimé', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button-primary" value="' . __('Ajouter le membre', UNIVGA_TEXT_DOMAIN) . '">';
        echo ' <a href="' . admin_url('admin.php?page=univga-members') . '" class="button">' . __('Annuler', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Display edit member form
     */
    private function display_edit_member_form() {
        if (!isset($_GET['member_id'])) {
            wp_die(__('ID de membre manquant', UNIVGA_TEXT_DOMAIN));
        }
        
        $member_id = intval($_GET['member_id']);
        $member = UNIVGA_Members::get_with_details($member_id);
        
        if (!$member) {
            wp_die(__('Membre introuvable', UNIVGA_TEXT_DOMAIN));
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Modifier le Membre', UNIVGA_TEXT_DOMAIN) . '</h1>';
        
        echo '<form method="post" action="' . admin_url('admin.php?page=univga-members') . '">';
        wp_nonce_field('edit_member_' . $member_id);
        echo '<input type="hidden" name="action" value="edit_member">';
        echo '<input type="hidden" name="member_id" value="' . $member_id . '">';
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th>' . __('Utilisateur', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<td><strong>' . esc_html($member->user_display_name . ' (' . $member->user_email . ')') . '</strong></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>' . __('Organisation', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<td><strong>' . esc_html($member->org_name) . '</strong></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="team_id">' . __('Équipe', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="team_id" id="team_id">';
        echo '<option value="">' . __('Aucune équipe', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $teams = UNIVGA_Teams::get_by_org($member->org_id);
        foreach ($teams as $team) {
            $selected = selected($member->team_id, $team->id, false);
            echo '<option value="' . $team->id . '"' . $selected . '>' . esc_html($team->name) . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="status">' . __('Statut', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="status" id="status">';
        echo '<option value="active"' . selected($member->status, 'active', false) . '>' . __('Actif', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="removed"' . selected($member->status, 'removed', false) . '>' . __('Supprimé', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button-primary" value="' . __('Mettre à jour le membre', UNIVGA_TEXT_DOMAIN) . '">';
        echo ' <a href="' . admin_url('admin.php?page=univga-members') . '" class="button">' . __('Annuler', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Handle member creation
     */
    private function handle_create_member() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'create_member')) {
            wp_die(__('Nonce verification failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $member_data = array(
            'user_id' => intval($_POST['user_id']),
            'org_id' => intval($_POST['org_id']),
            'team_id' => !empty($_POST['team_id']) ? intval($_POST['team_id']) : null,
            'status' => sanitize_text_field($_POST['status']),
        );
        
        $result = UNIVGA_Members::add_member(
            $member_data['org_id'],
            $member_data['team_id'],
            $member_data['user_id'],
            $member_data['status']
        );
        
        if (!$result) {
            wp_die(__('Failed to create member', UNIVGA_TEXT_DOMAIN));
        }
        
        $redirect_url = admin_url('admin.php?page=univga-members&message=member_created');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle member editing
     */
    private function handle_edit_member() {
        $member_id = intval($_POST['member_id']);
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'edit_member_' . $member_id)) {
            wp_die(__('Nonce verification failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $member_data = array(
            'team_id' => !empty($_POST['team_id']) ? intval($_POST['team_id']) : null,
            'status' => sanitize_text_field($_POST['status']),
        );
        
        $result = UNIVGA_Members::update($member_id, $member_data);
        
        if (!$result) {
            wp_die(__('Failed to update member', UNIVGA_TEXT_DOMAIN));
        }
        
        $redirect_url = admin_url('admin.php?page=univga-members&message=member_updated');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle member deletion
     */
    private function handle_delete_member() {
        $member_id = intval($_GET['member_id']);
        
        $member = UNIVGA_Members::get($member_id);
        if (!$member) {
            wp_die(__('Member not found', UNIVGA_TEXT_DOMAIN));
        }
        
        $result = UNIVGA_Members::remove_member($member->org_id, $member->user_id);
        
        if (!$result) {
            wp_die(__('Failed to delete member', UNIVGA_TEXT_DOMAIN));
        }
        
        $redirect_url = admin_url('admin.php?page=univga-members&message=member_deleted');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Display profiles page
     */
    public function display_profiles_page() {
        // Include the profiles view file
        $view_file = UNIVGA_PLUGIN_DIR . 'admin/views/admin-profiles.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Profils Utilisateurs', UNIVGA_TEXT_DOMAIN) . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Fichier de vue introuvable.', UNIVGA_TEXT_DOMAIN) . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Display HR dashboards page
     */
    public function display_hr_dashboards_page() {
        // Include the HR dashboards view file
        $view_file = UNIVGA_PLUGIN_DIR . 'admin/views/admin-hr-dashboards.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Suivi & Reporting RH', UNIVGA_TEXT_DOMAIN) . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Fichier de vue introuvable.', UNIVGA_TEXT_DOMAIN) . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Display AI analytics page
     */
    public function display_ai_analytics_page() {
        // Include the AI analytics view file
        $view_file = UNIVGA_PLUGIN_DIR . 'admin/views/admin-ai-analytics.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Analyse IA', UNIVGA_TEXT_DOMAIN) . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Fichier de vue introuvable.', UNIVGA_TEXT_DOMAIN) . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        // Include the settings view file
        $view_file = UNIVGA_PLUGIN_DIR . 'admin/views/admin-settings.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Paramètres', UNIVGA_TEXT_DOMAIN) . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Fichier de vue introuvable.', UNIVGA_TEXT_DOMAIN) . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Display pools page
     */
    public function display_pools_page() {
        // Handle pool actions
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_pool':
                    $this->handle_create_pool();
                    break;
                case 'edit_pool':
                    $this->handle_edit_pool();
                    break;
                case 'delete_pool':
                    if (isset($_GET['pool_id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_pool_' . $_GET['pool_id'])) {
                        $this->handle_delete_pool();
                    }
                    break;
            }
        }
        
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        switch ($action) {
            case 'new':
                $this->display_create_pool_form();
                break;
            case 'edit':
                $this->display_edit_pool_form();
                break;
            default:
                $this->display_pools_list();
                break;
        }
    }
    
    /**
     * Display pools list
     */
    private function display_pools_list() {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Gestion des Pools de Sièges', UNIVGA_TEXT_DOMAIN) . '</h1>';
        echo '<a href="' . admin_url('admin.php?page=univga-pools&action=new') . '" class="page-title-action">' . __('Ajouter un pool', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '<hr class="wp-header-end">';
        
        $pools_query = UNIVGA_Seat_Pools::query(array(
            'per_page' => 20,
            'paged' => isset($_GET['paged']) ? intval($_GET['paged']) : 1
        ));
        
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        echo '<select name="filter_org" id="filter_org">';
        echo '<option value="">' . __('Toutes les organisations', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $organizations = UNIVGA_Orgs::get_all();
        foreach ($organizations as $org) {
            echo '<option value="' . $org->id . '">' . esc_html($org->name) . '</option>';
        }
        
        echo '</select>';
        echo '<select name="filter_scope" id="filter_scope">';
        echo '<option value="">' . __('Tous les types', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="course">' . __('Cours', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="category">' . __('Catégorie', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '<input type="submit" name="filter_action" id="doaction" class="button action" value="' . __('Filtrer', UNIVGA_TEXT_DOMAIN) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Pool', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Organisation', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Type', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Sièges', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Utilisés', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Disponibles', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Mode', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<th>' . __('Actions', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if (empty($pools_query->items)) {
            echo '<tr><td colspan="8">' . __('Aucun pool trouvé.', UNIVGA_TEXT_DOMAIN) . '</td></tr>';
        } else {
            foreach ($pools_query->items as $pool) {
                $edit_url = admin_url('admin.php?page=univga-pools&action=edit&pool_id=' . $pool->id);
                $delete_url = wp_nonce_url(
                    admin_url('admin.php?page=univga-pools&action=delete_pool&pool_id=' . $pool->id),
                    'delete_pool_' . $pool->id
                );
                
                $org = UNIVGA_Orgs::get($pool->org_id);
                $org_name = $org ? $org->name : __('Organisation inconnue', UNIVGA_TEXT_DOMAIN);
                
                $seats_available = max(0, $pool->seats_total - $pool->seats_used);
                $usage_percentage = $pool->seats_total > 0 ? round(($pool->seats_used / $pool->seats_total) * 100, 1) : 0;
                
                echo '<tr>';
                echo '<td><strong>' . sprintf(__('Pool #%d', UNIVGA_TEXT_DOMAIN), intval($pool->id)) . '</strong></td>';
                echo '<td>' . esc_html($org_name) . '</td>';
                echo '<td>' . esc_html(ucfirst($pool->scope_type)) . '</td>';
                echo '<td>' . intval($pool->seats_total) . '</td>';
                echo '<td>' . intval($pool->seats_used) . ' (' . $usage_percentage . '%)</td>';
                echo '<td>' . $seats_available . '</td>';
                echo '<td><span class="pool-status-' . ($pool->auto_enroll ? 'active' : 'inactive') . '">' . ($pool->auto_enroll ? __('Auto-inscription', UNIVGA_TEXT_DOMAIN) : __('Manuel', UNIVGA_TEXT_DOMAIN)) . '</span></td>';
                echo '<td>';
                echo '<a href="' . $edit_url . '" class="button button-small">' . __('Modifier', UNIVGA_TEXT_DOMAIN) . '</a> ';
                echo '<a href="' . $delete_url . '" class="button button-small button-link-delete" onclick="return confirm(\'' . __('Êtes-vous sûr de vouloir supprimer ce pool ?', UNIVGA_TEXT_DOMAIN) . '\')">' . __('Supprimer', UNIVGA_TEXT_DOMAIN) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Pagination
        if ($pools_query->total > $pools_query->per_page) {
            $total_pages = ceil($pools_query->total / $pools_query->per_page);
            echo '<div class="tablenav bottom">';
            echo '<div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => admin_url('admin.php?page=univga-pools&paged=%#%'),
                'format' => '',
                'current' => $pools_query->paged,
                'total' => $total_pages,
                'prev_text' => '‹',
                'next_text' => '›',
            ));
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Display create pool form
     */
    private function display_create_pool_form() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Ajouter un Pool de Sièges', UNIVGA_TEXT_DOMAIN) . '</h1>';
        
        echo '<form method="post" action="' . admin_url('admin.php?page=univga-pools') . '">';
        wp_nonce_field('create_pool');
        echo '<input type="hidden" name="action" value="create_pool">';
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="pool_description">' . __('Description du pool', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="text" name="pool_description" id="pool_description" class="regular-text" placeholder="' . __('Pool pour organisation', UNIVGA_TEXT_DOMAIN) . '"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="org_id">' . __('Organisation', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="org_id" id="org_id" required>';
        echo '<option value="">' . __('Sélectionner une organisation', UNIVGA_TEXT_DOMAIN) . '</option>';
        
        $organizations = UNIVGA_Orgs::get_all();
        foreach ($organizations as $org) {
            echo '<option value="' . $org->id . '">' . esc_html($org->name) . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="team_id">' . __('Équipe (optionnel)', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="team_id" id="team_id">';
        echo '<option value="">' . __('Toutes les équipes', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="seats_total">' . __('Nombre total de sièges', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="number" name="seats_total" id="seats_total" min="1" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="scope_type">' . __('Type de contenu', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="scope_type" id="scope_type" required>';
        echo '<option value="">' . __('Sélectionner le type', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="course">' . __('Cours spécifiques', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="category">' . __('Catégorie de cours', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="auto_enroll">' . __('Inscription automatique', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="auto_enroll" id="auto_enroll">';
        echo '<option value="1">' . __('Activée', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="0">' . __('Désactivée', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="allow_replace">' . __('Remplacement de sièges', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="allow_replace" id="allow_replace">';
        echo '<option value="0">' . __('Non autorisé', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="1">' . __('Autorisé', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button-primary" value="' . __('Créer le pool', UNIVGA_TEXT_DOMAIN) . '">';
        echo ' <a href="' . admin_url('admin.php?page=univga-pools') . '" class="button">' . __('Annuler', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Display edit pool form
     */
    private function display_edit_pool_form() {
        if (!isset($_GET['pool_id'])) {
            wp_die(__('ID de pool manquant', UNIVGA_TEXT_DOMAIN));
        }
        
        $pool_id = intval($_GET['pool_id']);
        $pool = UNIVGA_Seat_Pools::get($pool_id);
        
        if (!$pool) {
            wp_die(__('Pool introuvable', UNIVGA_TEXT_DOMAIN));
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Modifier le Pool de Sièges', UNIVGA_TEXT_DOMAIN) . '</h1>';
        
        echo '<form method="post" action="' . admin_url('admin.php?page=univga-pools') . '">';
        wp_nonce_field('edit_pool_' . $pool_id);
        echo '<input type="hidden" name="action" value="edit_pool">';
        echo '<input type="hidden" name="pool_id" value="' . $pool_id . '">';
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th>' . __('ID du pool', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<td><strong>#' . intval($pool->id) . '</strong></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>' . __('Organisation', UNIVGA_TEXT_DOMAIN) . '</th>';
        $org = UNIVGA_Orgs::get($pool->org_id);
        echo '<td><strong>' . esc_html($org ? $org->name : __('Organisation inconnue', UNIVGA_TEXT_DOMAIN)) . '</strong></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="seats_total">' . __('Nombre total de sièges', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td><input type="number" name="seats_total" id="seats_total" min="1" value="' . intval($pool->seats_total) . '" required></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th>' . __('Sièges utilisés', UNIVGA_TEXT_DOMAIN) . '</th>';
        echo '<td><strong>' . intval($pool->seats_used) . '</strong></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="auto_enroll">' . __('Inscription automatique', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="auto_enroll" id="auto_enroll">';
        echo '<option value="1"' . selected($pool->auto_enroll, 1, false) . '>' . __('Activée', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="0"' . selected($pool->auto_enroll, 0, false) . '>' . __('Désactivée', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="allow_replace">' . __('Remplacement de sièges', UNIVGA_TEXT_DOMAIN) . '</label></th>';
        echo '<td>';
        echo '<select name="allow_replace" id="allow_replace">';
        echo '<option value="0"' . selected($pool->allow_replace, 0, false) . '>' . __('Non autorisé', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '<option value="1"' . selected($pool->allow_replace, 1, false) . '>' . __('Autorisé', UNIVGA_TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button-primary" value="' . __('Mettre à jour le pool', UNIVGA_TEXT_DOMAIN) . '">';
        echo ' <a href="' . admin_url('admin.php?page=univga-pools') . '" class="button">' . __('Annuler', UNIVGA_TEXT_DOMAIN) . '</a>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Handle pool creation
     */
    private function handle_create_pool() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'create_pool')) {
            wp_die(__('Nonce verification failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $pool_data = array(
            'org_id' => intval($_POST['org_id']),
            'team_id' => !empty($_POST['team_id']) ? intval($_POST['team_id']) : null,
            'seats_total' => intval($_POST['seats_total']),
            'scope_type' => sanitize_text_field($_POST['scope_type']),
            'auto_enroll' => intval($_POST['auto_enroll']),
            'allow_replace' => intval($_POST['allow_replace']),
        );
        
        $result = UNIVGA_Seat_Pools::create($pool_data);
        
        if (!$result) {
            wp_die(__('Failed to create pool', UNIVGA_TEXT_DOMAIN));
        }
        
        $redirect_url = admin_url('admin.php?page=univga-pools&message=pool_created');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle pool editing
     */
    private function handle_edit_pool() {
        $pool_id = intval($_POST['pool_id']);
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'edit_pool_' . $pool_id)) {
            wp_die(__('Nonce verification failed', UNIVGA_TEXT_DOMAIN));
        }
        
        $pool_data = array(
            'seats_total' => intval($_POST['seats_total']),
            'auto_enroll' => intval($_POST['auto_enroll']),
            'allow_replace' => intval($_POST['allow_replace']),
        );
        
        $result = UNIVGA_Seat_Pools::update($pool_id, $pool_data);
        
        if (!$result) {
            wp_die(__('Failed to update pool', UNIVGA_TEXT_DOMAIN));
        }
        
        $redirect_url = admin_url('admin.php?page=univga-pools&message=pool_updated');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle pool deletion
     */
    private function handle_delete_pool() {
        $pool_id = intval($_GET['pool_id']);
        
        $result = UNIVGA_Seat_Pools::delete($pool_id);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        $redirect_url = admin_url('admin.php?page=univga-pools&message=pool_deleted');
        wp_redirect($redirect_url);
        exit;
    }
}
