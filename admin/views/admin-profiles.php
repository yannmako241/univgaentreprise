<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions - Administrator bypass
if (!current_user_can('manage_options') && !current_user_can('univga_profiles_manage')) {
    wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', UNIVGA_TEXT_DOMAIN));
}

// Get all WordPress users safely
$users = array();
$profiles_data = array();

try {
    $users = get_users(array('number' => 500)); // Limit to 500 users for performance
    
    // Get profile definitions if available
    if (class_exists('UNIVGA_User_Profiles')) {
        $profiles_data = UNIVGA_User_Profiles::get_all_profiles();
    } else {
        // Fallback profile definitions
        $profiles_data = array(
            'admin' => __('Administrateur', UNIVGA_TEXT_DOMAIN),
            'hr' => __('Ressources Humaines', UNIVGA_TEXT_DOMAIN),
            'accountant' => __('Comptable', UNIVGA_TEXT_DOMAIN),
            'manager' => __('Manager', UNIVGA_TEXT_DOMAIN),
            'member' => __('Membre', UNIVGA_TEXT_DOMAIN)
        );
    }
} catch (Exception $e) {
    $users = array();
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('UNIVGA Profiles Error: ' . $e->getMessage());
    }
}

// Handle AJAX profile update
if (isset($_POST['action']) && $_POST['action'] === 'update_user_profile' && wp_verify_nonce($_POST['nonce'], 'univga_profiles_action')) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_profile = sanitize_text_field($_POST['profile'] ?? '');
    
    if ($user_id && $new_profile && array_key_exists($new_profile, $profiles_data)) {
        try {
            if (class_exists('UNIVGA_User_Profiles') && method_exists('UNIVGA_User_Profiles', 'set_user_profile')) {
                $result = UNIVGA_User_Profiles::set_user_profile($user_id, $new_profile);
                if ($result) {
                    wp_send_json_success(array('message' => __('Profil mis à jour avec succès', UNIVGA_TEXT_DOMAIN)));
                } else {
                    wp_send_json_error(array('message' => __('Erreur lors de la mise à jour', UNIVGA_TEXT_DOMAIN)));
                }
            } else {
                // Fallback to user meta
                update_user_meta($user_id, 'univga_user_profile', $new_profile);
                wp_send_json_success(array('message' => __('Profil mis à jour (mode basique)', UNIVGA_TEXT_DOMAIN)));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Erreur technique', UNIVGA_TEXT_DOMAIN)));
        }
    }
    wp_send_json_error(array('message' => __('Données invalides', UNIVGA_TEXT_DOMAIN)));
}

// Count users per profile
$profile_counts = array();
foreach ($profiles_data as $profile_key => $profile_name) {
    $profile_counts[$profile_key] = 0;
}

foreach ($users as $user) {
    $user_profile = 'member'; // default
    try {
        if (class_exists('UNIVGA_User_Profiles') && method_exists('UNIVGA_User_Profiles', 'get_user_profile')) {
            $user_profile = UNIVGA_User_Profiles::get_user_profile($user->ID);
        } else {
            $user_profile = get_user_meta($user->ID, 'univga_user_profile', true) ?: 'member';
        }
    } catch (Exception $e) {
        $user_profile = 'member';
    }
    
    if (array_key_exists($user_profile, $profile_counts)) {
        $profile_counts[$user_profile]++;
    }
}
?>

<div class="univga-admin-wrap univga-fade-in" data-page="profiles">
    <!-- Clean Admin Header -->
    <div class="univga-admin-header">
        <div>
            <h1 class="univga-admin-title">
                <span class="dashicons dashicons-admin-users"></span>
                <?php _e('Profils Utilisateur', UNIVGA_TEXT_DOMAIN); ?>
            </h1>
            <p class="univga-admin-subtitle"><?php _e('Gérer les profils et permissions des utilisateurs (Admin, RH, Comptable, Manager, Membre)', UNIVGA_TEXT_DOMAIN); ?></p>
        </div>
        <div class="univga-admin-actions">
            <button class="univga-btn univga-btn-secondary" onclick="location.reload()">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Actualiser', UNIVGA_TEXT_DOMAIN); ?>
            </button>
        </div>
    </div>

    <!-- Profile Statistics -->
    <div class="univga-metrics-grid">
        <?php foreach ($profiles_data as $profile_key => $profile_name): ?>
        <?php 
        $profile_class = '';
        switch ($profile_key) {
            case 'admin': $profile_class = 'danger'; break;
            case 'hr': $profile_class = 'success'; break;
            case 'accountant': $profile_class = 'warning'; break;
            default: $profile_class = ''; break;
        }
        ?>
        <div class="univga-metric-card <?php echo $profile_class; ?>">
            <div class="univga-metric-label"><?php echo esc_html($profile_name); ?></div>
            <div class="univga-metric-value"><?php echo $profile_counts[$profile_key]; ?></div>
            <div class="univga-metric-change">
                <span class="profile-badge <?php echo $profile_key; ?>"><?php echo strtoupper($profile_key); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Users Management -->
    <div class="univga-admin-card">
        <div class="univga-card-header">
            <h3 class="univga-card-title"><?php _e('Gestion des Utilisateurs', UNIVGA_TEXT_DOMAIN); ?></h3>
            <div class="header-actions">
                <input type="text" id="user-search" placeholder="<?php _e('Rechercher utilisateurs...', UNIVGA_TEXT_DOMAIN); ?>" class="search-input">
            </div>
        </div>
        
        <div class="univga-card-body">
            <?php if (!empty($users)): ?>
            <table class="wp-list-table widefat fixed striped users-table">
                <thead>
                    <tr>
                        <th><?php _e('Utilisateur', UNIVGA_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Email', UNIVGA_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Profil Actuel', UNIVGA_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Changer Profil', UNIVGA_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Actions', UNIVGA_TEXT_DOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php 
                    $current_profile = 'member';
                    try {
                        if (class_exists('UNIVGA_User_Profiles') && method_exists('UNIVGA_User_Profiles', 'get_user_profile')) {
                            $current_profile = UNIVGA_User_Profiles::get_user_profile($user->ID);
                        } else {
                            $current_profile = get_user_meta($user->ID, 'univga_user_profile', true) ?: 'member';
                        }
                    } catch (Exception $e) {
                        $current_profile = 'member';
                    }
                    
                    $profile_display = isset($profiles_data[$current_profile]) ? $profiles_data[$current_profile] : __('Inconnu', UNIVGA_TEXT_DOMAIN);
                    ?>
                    <tr data-user-id="<?php echo $user->ID; ?>" class="user-row">
                        <td>
                            <div class="user-info">
                                <?php echo get_avatar($user->ID, 32); ?>
                                <div class="user-details">
                                    <strong><?php echo esc_html($user->display_name); ?></strong>
                                    <small class="user-login">@<?php echo esc_html($user->user_login); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td>
                            <span class="profile-badge <?php echo $current_profile; ?>">
                                <?php echo esc_html($profile_display); ?>
                            </span>
                        </td>
                        <td>
                            <select class="profile-selector" data-user-id="<?php echo $user->ID; ?>">
                                <?php foreach ($profiles_data as $profile_key => $profile_name): ?>
                                <option value="<?php echo $profile_key; ?>" <?php selected($current_profile, $profile_key); ?>>
                                    <?php echo esc_html($profile_name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button class="button button-primary button-small update-profile-btn" 
                                    data-user-id="<?php echo $user->ID; ?>" 
                                    style="display:none;">
                                <?php _e('Sauvegarder', UNIVGA_TEXT_DOMAIN); ?>
                            </button>
                            <span class="update-status"></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php _e('Aucun utilisateur trouvé.', UNIVGA_TEXT_DOMAIN); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Reference Card -->
    <div class="univga-admin-card">
        <div class="univga-card-header">
            <h3 class="univga-card-title">
                <span class="dashicons dashicons-admin-network"></span>
                <?php _e('Référence des Profils', UNIVGA_TEXT_DOMAIN); ?>
            </h3>
        </div>
        <div class="univga-card-body">
            <div class="profiles-reference">
                <?php foreach ($profiles_data as $profile_key => $profile_name): ?>
                <div class="profile-reference-item">
                    <div class="profile-header">
                        <span class="profile-badge <?php echo $profile_key; ?>"><?php echo esc_html($profile_name); ?></span>
                        <span class="profile-count"><?php echo $profile_counts[$profile_key]; ?> <?php _e('utilisateurs', UNIVGA_TEXT_DOMAIN); ?></span>
                    </div>
                    <div class="profile-description">
                        <?php
                        $descriptions = array(
                            'admin' => __('Accès complet au système, gestion financière et technique', UNIVGA_TEXT_DOMAIN),
                            'hr' => __('Gestion des utilisateurs, équipes et formations RH', UNIVGA_TEXT_DOMAIN),
                            'accountant' => __('Accès aux rapports financiers et facturations', UNIVGA_TEXT_DOMAIN),
                            'manager' => __('Supervision d\'équipes et reporting standard', UNIVGA_TEXT_DOMAIN),
                            'member' => __('Accès utilisateur standard aux formations', UNIVGA_TEXT_DOMAIN)
                        );
                        echo isset($descriptions[$profile_key]) ? $descriptions[$profile_key] : __('Profil standard', UNIVGA_TEXT_DOMAIN);
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Search functionality
    $('#user-search').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.user-row').each(function() {
            const userText = $(this).text().toLowerCase();
            $(this).toggle(userText.includes(searchTerm));
        });
    });

    // Profile selector change
    $('.profile-selector').on('change', function() {
        const $select = $(this);
        const $button = $select.closest('tr').find('.update-profile-btn');
        const originalValue = $select.data('original-value') || $select.find('option:selected').val();
        
        if (!$select.data('original-value')) {
            $select.data('original-value', originalValue);
        }
        
        if ($select.val() !== originalValue) {
            $button.show();
        } else {
            $button.hide();
        }
    });

    // Update profile
    $('.update-profile-btn').on('click', function() {
        const $button = $(this);
        const userId = $button.data('user-id');
        const $select = $button.closest('tr').find('.profile-selector');
        const newProfile = $select.val();
        const $status = $button.closest('tr').find('.update-status');
        
        $button.prop('disabled', true).text('<?php _e('Mise à jour...', UNIVGA_TEXT_DOMAIN); ?>');
        $status.text('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'update_user_profile',
                user_id: userId,
                profile: newProfile,
                nonce: '<?php echo wp_create_nonce('univga_profiles_action'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">✓</span>').fadeOut(3000);
                    $select.data('original-value', newProfile);
                    $button.hide();
                    
                    // Update profile badge
                    const $badge = $button.closest('tr').find('.profile-badge');
                    $badge.removeClass().addClass('profile-badge ' + newProfile);
                    $badge.text($select.find('option:selected').text());
                } else {
                    $status.html('<span style="color: red;">✗</span>');
                }
                $button.prop('disabled', false).text('<?php _e('Sauvegarder', UNIVGA_TEXT_DOMAIN); ?>');
            },
            error: function() {
                $status.html('<span style="color: red;">✗</span>');
                $button.prop('disabled', false).text('<?php _e('Sauvegarder', UNIVGA_TEXT_DOMAIN); ?>');
            }
        });
    });
});
</script>

<style>
.univga-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}

.univga-metric-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
}

.univga-metric-card.success { border-left: 4px solid #00a32a; }
.univga-metric-card.warning { border-left: 4px solid #f56e28; }
.univga-metric-card.danger { border-left: 4px solid #d63638; }

.univga-metric-label {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.univga-metric-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: #1d2327;
    margin-bottom: 0.5rem;
}

.profile-badge {
    display: inline-block;
    padding: 0.25em 0.5em;
    font-size: 0.75em;
    font-weight: 600;
    border-radius: 0.25rem;
    text-transform: uppercase;
}

.profile-badge.admin { background-color: #d63638; color: white; }
.profile-badge.hr { background-color: #00a32a; color: white; }
.profile-badge.accountant { background-color: #f56e28; color: white; }
.profile-badge.manager { background-color: #007cba; color: white; }
.profile-badge.member { background-color: #666; color: white; }

.header-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-input {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 200px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-info img {
    border-radius: 50%;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-login {
    color: #666;
    font-size: 0.85em;
}

.profile-selector {
    padding: 0.25rem 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    min-width: 140px;
}

.profiles-reference {
    display: grid;
    gap: 1rem;
}

.profile-reference-item {
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #f9f9f9;
}

.profile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.profile-count {
    font-size: 0.85em;
    color: #666;
}

.profile-description {
    font-size: 0.9em;
    color: #555;
    line-height: 1.4;
}

.update-status {
    margin-left: 0.5rem;
    font-weight: bold;
}
</style>