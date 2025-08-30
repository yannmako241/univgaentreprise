<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit'])) {
    check_admin_referer('univga_settings');
    
    // Save basic settings
    update_option('univga_default_allow_replace', isset($_POST['default_allow_replace']) ? 1 : 0);
    update_option('univga_debug_mode', isset($_POST['debug_mode']) ? 1 : 0);
    update_option('univga_dashboard_page_id', intval($_POST['dashboard_page_id']));
    update_option('univga_invitation_expire_days', intval($_POST['invitation_expire_days']));
    update_option('univga_remove_data_on_uninstall', isset($_POST['univga_remove_data_on_uninstall']) ? 1 : 0);
    
    // Save AI Analytics settings
    if (isset($_POST['openai_api_key'])) {
        update_option('univga_openai_api_key', sanitize_text_field($_POST['openai_api_key']));
    }
    if (isset($_POST['ai_model'])) {
        update_option('univga_ai_model', sanitize_text_field($_POST['ai_model']));
    }
    if (isset($_POST['ai_temperature'])) {
        update_option('univga_ai_temperature', floatval($_POST['ai_temperature']));
    }
    if (isset($_POST['ai_max_tokens'])) {
        update_option('univga_ai_max_tokens', intval($_POST['ai_max_tokens']));
    }
    update_option('univga_ai_cache_enabled', isset($_POST['ai_cache_enabled']) ? 1 : 0);
    if (isset($_POST['ai_cache_duration'])) {
        update_option('univga_ai_cache_duration', intval($_POST['ai_cache_duration']));
    }
    
    // Save Email settings
    update_option('univga_email_enabled', isset($_POST['email_enabled']) ? 1 : 0);
    if (isset($_POST['email_from_name'])) {
        update_option('univga_email_from_name', sanitize_text_field($_POST['email_from_name']));
    }
    if (isset($_POST['email_from_address'])) {
        update_option('univga_email_from_address', sanitize_email($_POST['email_from_address']));
    }
    update_option('univga_auto_email_reports', isset($_POST['auto_email_reports']) ? 1 : 0);
    if (isset($_POST['email_frequency'])) {
        update_option('univga_email_frequency', sanitize_text_field($_POST['email_frequency']));
    }
    
    // Save Performance settings
    if (isset($_POST['data_retention_days'])) {
        update_option('univga_data_retention_days', intval($_POST['data_retention_days']));
    }
    if (isset($_POST['batch_size'])) {
        update_option('univga_batch_size', intval($_POST['batch_size']));
    }
    update_option('univga_enable_analytics_cache', isset($_POST['enable_analytics_cache']) ? 1 : 0);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Paramètres sauvegardés avec succès.', UNIVGA_TEXT_DOMAIN) . '</p></div>';
}

// Get current settings
$default_allow_replace = get_option('univga_default_allow_replace', 0);
$debug_mode = get_option('univga_debug_mode', 0);
$dashboard_page_id = get_option('univga_dashboard_page_id', 0);
$invitation_expire_days = get_option('univga_invitation_expire_days', 7);

// Get AI Analytics settings
$openai_api_key = get_option('univga_openai_api_key', '');
$ai_model = get_option('univga_ai_model', 'gpt-4o');
$ai_temperature = get_option('univga_ai_temperature', 0.3);
$ai_max_tokens = get_option('univga_ai_max_tokens', 1000);
$ai_cache_enabled = get_option('univga_ai_cache_enabled', 1);
$ai_cache_duration = get_option('univga_ai_cache_duration', 60);

// Get Email settings
$email_enabled = get_option('univga_email_enabled', 1);
$email_from_name = get_option('univga_email_from_name', get_bloginfo('name'));
$email_from_address = get_option('univga_email_from_address', get_option('admin_email'));
$auto_email_reports = get_option('univga_auto_email_reports', 0);
$email_frequency = get_option('univga_email_frequency', 'weekly');

// Get Performance settings
$data_retention_days = get_option('univga_data_retention_days', 365);
$batch_size = get_option('univga_batch_size', 100);
$enable_analytics_cache = get_option('univga_enable_analytics_cache', 1);

// Get cron status
$last_cron = get_option('univga_last_cron_summary', array());
$next_cron = wp_next_scheduled('univga_org_resync');

// Check AI configuration status
$ai_configured = !empty($openai_api_key);
?>

<div class="wrap">
    <h1><?php _e('Paramètres UNIVGA', UNIVGA_TEXT_DOMAIN); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('univga_settings'); ?>
        
        <!-- Basic Settings -->
        <h2><?php _e('Paramètres Généraux', UNIVGA_TEXT_DOMAIN); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Paramètres par Défaut', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="default_allow_replace" value="1" <?php checked($default_allow_replace); ?>>
                            <?php _e('Permettre le remplacement des sièges par défaut', UNIVGA_TEXT_DOMAIN); ?>
                        </label>
                        <p class="description"><?php _e('Quand activé, la suppression d\'un membre libère son siège pour réutilisation', UNIVGA_TEXT_DOMAIN); ?></p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="dashboard_page_id"><?php _e('Page du Tableau de Bord', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'dashboard_page_id',
                        'id' => 'dashboard_page_id',
                        'selected' => $dashboard_page_id,
                        'show_option_none' => __('Sélectionner une page...', UNIVGA_TEXT_DOMAIN),
                        'option_none_value' => 0,
                    ));
                    ?>
                    <p class="description">
                        <?php _e('Page où les gestionnaires d\'organisation accèderont à leur tableau de bord. Ajoutez le shortcode [univga_org_dashboard] à cette page.', UNIVGA_TEXT_DOMAIN); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="invitation_expire_days"><?php _e('Expiration des Invitations', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <input type="number" name="invitation_expire_days" id="invitation_expire_days" 
                           value="<?php echo $invitation_expire_days; ?>" min="1" max="30" class="small-text">
                    <?php _e('jours', UNIVGA_TEXT_DOMAIN); ?>
                    <p class="description"><?php _e('Nombre de jours avant expiration des liens d\'invitation', UNIVGA_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Mode Debug', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="debug_mode" value="1" <?php checked($debug_mode); ?>>
                            <?php _e('Activer les logs de débogage', UNIVGA_TEXT_DOMAIN); ?>
                        </label>
                        <p class="description"><?php _e('Enregistre des informations détaillées pour le dépannage. Consultez vos logs d\'erreurs.', UNIVGA_TEXT_DOMAIN); ?></p>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <!-- AI Analytics Configuration -->
        <h2 id="ai"><?php _e('Configuration Analytics IA', UNIVGA_TEXT_DOMAIN); ?></h2>
        <?php if ($ai_configured): ?>
            <div class="notice notice-success inline">
                <p><strong><?php _e('✅ Configuration IA Active', UNIVGA_TEXT_DOMAIN); ?></strong> — <?php _e('Les fonctionnalités Analytics IA sont opérationnelles.', UNIVGA_TEXT_DOMAIN); ?></p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning inline">
                <p><strong><?php _e('⚠️ Configuration IA Requise', UNIVGA_TEXT_DOMAIN); ?></strong> — <?php _e('Configurez votre clé OpenAI pour activer les Analytics IA.', UNIVGA_TEXT_DOMAIN); ?></p>
            </div>
        <?php endif; ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="openai_api_key"><?php _e('Clé API OpenAI', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <input type="password" name="openai_api_key" id="openai_api_key" 
                           value="<?php echo esc_attr($openai_api_key); ?>" class="regular-text" 
                           placeholder="sk-...">
                    <button type="button" onclick="togglePassword('openai_api_key')" class="button-secondary">
                        <?php _e('Afficher', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                    <p class="description">
                        <?php _e('Votre clé API OpenAI pour les analyses intelligentes. ', UNIVGA_TEXT_DOMAIN); ?>
                        <a href="https://platform.openai.com/api-keys" target="_blank"><?php _e('Obtenir une clé API', UNIVGA_TEXT_DOMAIN); ?></a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ai_model"><?php _e('Modèle IA', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <select name="ai_model" id="ai_model">
                        <option value="gpt-4o" <?php selected($ai_model, 'gpt-4o'); ?>>GPT-4o (Recommandé)</option>
                        <option value="gpt-4o-mini" <?php selected($ai_model, 'gpt-4o-mini'); ?>>GPT-4o Mini (Économique)</option>
                        <option value="gpt-4" <?php selected($ai_model, 'gpt-4'); ?>>GPT-4</option>
                        <option value="gpt-3.5-turbo" <?php selected($ai_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                    </select>
                    <p class="description"><?php _e('Modèle OpenAI utilisé pour les analyses. GPT-4o offre la meilleure qualité.', UNIVGA_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ai_temperature"><?php _e('Créativité IA', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <input type="number" name="ai_temperature" id="ai_temperature" 
                           value="<?php echo $ai_temperature; ?>" min="0" max="1" step="0.1" class="small-text">
                    <p class="description"><?php _e('Entre 0 (très précis) et 1 (très créatif). Recommandé: 0.3', UNIVGA_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ai_max_tokens"><?php _e('Taille maximale des réponses', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <input type="number" name="ai_max_tokens" id="ai_max_tokens" 
                           value="<?php echo $ai_max_tokens; ?>" min="100" max="4000" class="small-text">
                    <?php _e('tokens', UNIVGA_TEXT_DOMAIN); ?>
                    <p class="description"><?php _e('Limite de longueur des analyses IA. Recommandé: 1000 tokens', UNIVGA_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Cache IA', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="ai_cache_enabled" value="1" <?php checked($ai_cache_enabled); ?>>
                            <?php _e('Activer le cache des analyses IA', UNIVGA_TEXT_DOMAIN); ?>
                        </label>
                        <p class="description"><?php _e('Réduit les coûts en mettant en cache les analyses récentes', UNIVGA_TEXT_DOMAIN); ?></p>
                        
                        <label for="ai_cache_duration" style="margin-top: 10px; display: block;">
                            <?php _e('Durée du cache:', UNIVGA_TEXT_DOMAIN); ?>
                            <input type="number" name="ai_cache_duration" id="ai_cache_duration" 
                                   value="<?php echo $ai_cache_duration; ?>" min="5" max="1440" class="small-text">
                            <?php _e('minutes', UNIVGA_TEXT_DOMAIN); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <!-- Email Configuration -->
        <h2><?php _e('Configuration Email', UNIVGA_TEXT_DOMAIN); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Emails activés', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="email_enabled" value="1" <?php checked($email_enabled); ?>>
                            <?php _e('Activer l\'envoi d\'emails', UNIVGA_TEXT_DOMAIN); ?>
                        </label>
                        <p class="description"><?php _e('Permet l\'envoi des rapports Analytics IA par email', UNIVGA_TEXT_DOMAIN); ?></p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="email_from_name"><?php _e('Nom expéditeur', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <input type="text" name="email_from_name" id="email_from_name" 
                           value="<?php echo esc_attr($email_from_name); ?>" class="regular-text">
                    <p class="description"><?php _e('Nom affiché comme expéditeur des emails', UNIVGA_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="email_from_address"><?php _e('Email expéditeur', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <input type="email" name="email_from_address" id="email_from_address" 
                           value="<?php echo esc_attr($email_from_address); ?>" class="regular-text">
                    <p class="description"><?php _e('Adresse email utilisée comme expéditeur', UNIVGA_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Rapports automatiques', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="auto_email_reports" value="1" <?php checked($auto_email_reports); ?>>
                            <?php _e('Envoi automatique des rapports Analytics IA', UNIVGA_TEXT_DOMAIN); ?>
                        </label>
                        <p class="description"><?php _e('Envoie automatiquement les analyses aux managers d\'organisation', UNIVGA_TEXT_DOMAIN); ?></p>
                        
                        <label for="email_frequency" style="margin-top: 10px; display: block;">
                            <?php _e('Fréquence:', UNIVGA_TEXT_DOMAIN); ?>
                            <select name="email_frequency" id="email_frequency">
                                <option value="daily" <?php selected($email_frequency, 'daily'); ?>><?php _e('Quotidien', UNIVGA_TEXT_DOMAIN); ?></option>
                                <option value="weekly" <?php selected($email_frequency, 'weekly'); ?>><?php _e('Hebdomadaire', UNIVGA_TEXT_DOMAIN); ?></option>
                                <option value="monthly" <?php selected($email_frequency, 'monthly'); ?>><?php _e('Mensuel', UNIVGA_TEXT_DOMAIN); ?></option>
                            </select>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <!-- Performance Settings -->
        <h2><?php _e('Paramètres de Performance', UNIVGA_TEXT_DOMAIN); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="data_retention_days"><?php _e('Rétention des données', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <input type="number" name="data_retention_days" id="data_retention_days" 
                           value="<?php echo $data_retention_days; ?>" min="30" max="3650" class="small-text">
                    <?php _e('jours', UNIVGA_TEXT_DOMAIN); ?>
                    <p class="description"><?php _e('Durée de conservation des données analytics. Recommandé: 365 jours', UNIVGA_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="batch_size"><?php _e('Taille des lots de traitement', UNIVGA_TEXT_DOMAIN); ?></label>
                </th>
                <td>
                    <input type="number" name="batch_size" id="batch_size" 
                           value="<?php echo $batch_size; ?>" min="10" max="1000" class="small-text">
                    <?php _e('éléments', UNIVGA_TEXT_DOMAIN); ?>
                    <p class="description"><?php _e('Nombre d\'éléments traités simultanément. Recommandé: 100', UNIVGA_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Cache Analytics', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="enable_analytics_cache" value="1" <?php checked($enable_analytics_cache); ?>>
                            <?php _e('Activer le cache des données analytics', UNIVGA_TEXT_DOMAIN); ?>
                        </label>
                        <p class="description"><?php _e('Améliore les performances en mettant en cache les calculs analytiques', UNIVGA_TEXT_DOMAIN); ?></p>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <!-- Cron Status -->
        <h2><?php _e('Statut des Tâches Automatiques', UNIVGA_TEXT_DOMAIN); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Prochaine Synchronisation', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <?php
                    if ($next_cron) {
                        echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_cron);
                    } else {
                        echo '<span style="color: #d63384;">' . __('Not scheduled', UNIVGA_TEXT_DOMAIN) . '</span>';
                    }
                    ?>
                    
                    <p>
                        <a href="<?php echo wp_nonce_url(add_query_arg('action', 'manual_resync'), 'manual_resync'); ?>" 
                           class="button-secondary"
                           onclick="return confirm('<?php _e('This will resync all organization data. Continue?', UNIVGA_TEXT_DOMAIN); ?>')">
                            <?php _e('Trigger Manual Resync', UNIVGA_TEXT_DOMAIN); ?>
                        </a>
                    </p>
                </td>
            </tr>
            
            <?php if (!empty($last_cron)): ?>
            <tr>
                <th scope="row"><?php _e('Last Sync Results', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <ul>
                        <li><?php printf(__('Organizations processed: %d', UNIVGA_TEXT_DOMAIN), $last_cron['processed_orgs']); ?></li>
                        <li><?php printf(__('Expired pools: %d', UNIVGA_TEXT_DOMAIN), $last_cron['expired_pools']); ?></li>
                        <li><?php printf(__('Warnings sent: %d', UNIVGA_TEXT_DOMAIN), $last_cron['warnings_sent']); ?></li>
                        <li><?php printf(__('Errors: %d', UNIVGA_TEXT_DOMAIN), $last_cron['errors']); ?></li>
                        <li><?php printf(__('Duration: %d seconds', UNIVGA_TEXT_DOMAIN), $last_cron['duration']); ?></li>
                    </ul>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <!-- Uninstall Options -->
        <h2><?php _e('Options de Désinstallation', UNIVGA_TEXT_DOMAIN); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Suppression complète des données', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="univga_remove_data_on_uninstall" value="1" <?php checked(get_option('univga_remove_data_on_uninstall', false)); ?>>
                            <?php _e('Supprimer TOUTES les données lors de la suppression du plugin', UNIVGA_TEXT_DOMAIN); ?>
                        </label>
                        <p class="description">
                            <strong style="color: #d63638;"><?php _e('ATTENTION:', UNIVGA_TEXT_DOMAIN); ?></strong> 
                            <?php _e('Si cette option est activée, TOUTES les données du plugin (organisations, équipes, membres, rapports, analytics IA, etc.) seront définitivement supprimées lors de la désinstallation. Cette action est irréversible !', UNIVGA_TEXT_DOMAIN); ?>
                        </p>
                        <p class="description">
                            <?php _e('Si elle n\'est pas cochée, seuls les caches temporaires seront nettoyés lors de la désinstallation.', UNIVGA_TEXT_DOMAIN); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <!-- System Information -->
        <h2><?php _e('Informations Système', UNIVGA_TEXT_DOMAIN); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Plugin Version', UNIVGA_TEXT_DOMAIN); ?></th>
                <td><?php echo UNIVGA_PLUGIN_VERSION; ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('WordPress Version', UNIVGA_TEXT_DOMAIN); ?></th>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('WooCommerce Version', UNIVGA_TEXT_DOMAIN); ?></th>
                <td><?php echo class_exists('WooCommerce') ? WC()->version : __('Not active', UNIVGA_TEXT_DOMAIN); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Tutor LMS Version', UNIVGA_TEXT_DOMAIN); ?></th>
                <td><?php echo function_exists('tutor') ? TUTOR_VERSION : __('Not active', UNIVGA_TEXT_DOMAIN); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Statut Analytics IA', UNIVGA_TEXT_DOMAIN); ?></th>
                <td>
                    <?php if ($ai_configured): ?>
                        <span style="color: #00a32a;">✅ <?php _e('Configuré et opérationnel', UNIVGA_TEXT_DOMAIN); ?></span>
                    <?php else: ?>
                        <span style="color: #d63638;">❌ <?php _e('Configuration requise', UNIVGA_TEXT_DOMAIN); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" class="button-primary" value="<?php _e('Sauvegarder les Paramètres', UNIVGA_TEXT_DOMAIN); ?>">
        </p>
    </form>
    
    <!-- Shortcodes Information -->
    <h2><?php _e('Shortcodes Disponibles', UNIVGA_TEXT_DOMAIN); ?></h2>
    <div class="univga-shortcodes-info">
        <p><strong>[univga_org_dashboard]</strong> - <?php _e('Affiche le tableau de bord d\'organisation pour les gestionnaires connectés', UNIVGA_TEXT_DOMAIN); ?></p>
        <p><strong>[univga_team_dashboard team_id="123"]</strong> - <?php _e('Affiche le tableau de bord d\'équipe pour une équipe spécifique', UNIVGA_TEXT_DOMAIN); ?></p>
        <p><strong>[univga_analytics_widget org_id="123"]</strong> - <?php _e('Widget d\'analytics pour une organisation', UNIVGA_TEXT_DOMAIN); ?></p>
    </div>
    
</div>

<style>
.univga-shortcodes-info {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 15px;
    margin-top: 20px;
    border-radius: 4px;
}
.univga-shortcodes-info p {
    margin: 5px 0;
}
.notice.inline {
    margin: 5px 0 15px 0;
    padding: 8px 12px;
}
.form-table th {
    font-weight: 600;
}
h2 {
    border-bottom: 1px solid #ccd0d4;
    padding-bottom: 8px;
    margin-top: 30px;
}
h2:first-of-type {
    margin-top: 20px;
}
</style>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    
    if (field.type === 'password') {
        field.type = 'text';
        button.textContent = '<?php _e('Masquer', UNIVGA_TEXT_DOMAIN); ?>';
    } else {
        field.type = 'password';
        button.textContent = '<?php _e('Afficher', UNIVGA_TEXT_DOMAIN); ?>';
    }
}
</script>