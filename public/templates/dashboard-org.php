<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$kpis = UNIVGA_Reports::get_org_dashboard_kpis($org->id);
$teams = UNIVGA_Teams::get_by_org($org->id);

// Get current user info for profile section
$current_user = wp_get_current_user();
$user_name = $current_user->display_name ?: $current_user->user_login;

// Get organization branding if available
$org_branding = null;
$org_logo = '';
if (class_exists('UNIVGA_WhiteLabel')) {
    $whitelabel = UNIVGA_WhiteLabel::getInstance();
    $org_branding = $whitelabel->get_org_branding($org->id);
}

// Use organization logo if available, fallback to default
if ($org_branding && !empty($org_branding->logo_url)) {
    $org_logo = $org_branding->logo_url;
} else {
    // Default organization icon (SVG)
    $org_logo = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" fill="#ffffff" viewBox="0 0 16 16"><path d="M6 2a.5.5 0 0 1 .47.33L10 12H5.5a.5.5 0 0 1-.48-.36L1.89 2.38A.5.5 0 0 1 2.37 2H6zm2.5 0a.5.5 0 0 1 .47.33L12.5 12H8a.5.5 0 0 1-.47-.33L4 2h4.5z"/></svg>');
}

$cover_image = '';
if ($org_branding && !empty($org_branding->email_header)) {
    $cover_image = 'background-image: url(' . esc_url($org_branding->email_header) . ');';
} else {
    // Default gradient background
    $cover_image = 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);';
}
?>

<div class="univga-dashboard" data-org-id="<?php echo $org->id; ?>">
    <!-- Enhanced Header with Cover and Profile -->
    <div class="univga-header-enhanced">
        <div class="univga-header-cover" style="<?php echo $cover_image; ?>">
            <div class="univga-header-overlay">
                <div class="univga-header-content">
                    <div class="univga-header-info">
                        <div class="univga-profile-section">
                            <div class="univga-profile-avatar">
                                <img src="<?php echo esc_url($org_logo); ?>" alt="<?php echo esc_attr($org->name); ?>" class="univga-avatar univga-org-logo">
                            </div>
                            <div class="univga-profile-details">
                                <div class="univga-profile-name"><?php echo esc_html($user_name); ?></div>
                                <div class="univga-profile-role"><?php _e('Gestionnaire d\'Organisation', UNIVGA_TEXT_DOMAIN); ?></div>
                            </div>
                        </div>
                        <div class="univga-org-info">
                            <h1 class="univga-org-title" style="color: white; display: inline-block; margin-right: 15px;"><?php echo esc_html($org->name); ?></h1>
                            <span class="univga-org-subtitle" style="color: white; display: inline-block; opacity: 0.9;"><?php _e('Tableau de Bord de Gestion d\'Apprentissage', UNIVGA_TEXT_DOMAIN); ?></span>
                        </div>
                    </div>
                    <div class="univga-header-actions">

                        <button type="button" class="univga-btn univga-btn-primary" data-action="invite-member">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                            </svg>
                            <?php _e('Inviter un Membre', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="univga-kpis">
        <div class="univga-kpi-card">
            <div class="univga-kpi-icon univga-kpi-seats">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                </svg>
            </div>
            <div class="univga-kpi-content">
                <div class="univga-kpi-value"><?php echo $kpis['seats']['used']; ?> / <?php echo $kpis['seats']['total']; ?></div>
                <div class="univga-kpi-label"><?php _e('Sièges Utilisés', UNIVGA_TEXT_DOMAIN); ?></div>
                <div class="univga-kpi-progress">
                    <div class="univga-progress-bar">
                        <div class="univga-progress-fill" style="width: <?php echo number_format($kpis['seats']['utilization_rate'], 1); ?>%"></div>
                    </div>
                    <span class="univga-progress-text"><?php echo number_format($kpis['seats']['utilization_rate'], 1); ?>%</span>
                </div>
            </div>
        </div>

        <div class="univga-kpi-card">
            <div class="univga-kpi-icon univga-kpi-members">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                    <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                    <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                </svg>
            </div>
            <div class="univga-kpi-content">
                <div class="univga-kpi-value"><?php echo $kpis['members']['total']; ?></div>
                <div class="univga-kpi-label"><?php _e('Membres Actifs', UNIVGA_TEXT_DOMAIN); ?></div>
                <div class="univga-kpi-meta"><?php printf(__('%d inscrits aux cours', UNIVGA_TEXT_DOMAIN), $kpis['members']['enrolled']); ?></div>
            </div>
        </div>

        <div class="univga-kpi-card">
            <div class="univga-kpi-icon univga-kpi-courses">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                </svg>
            </div>
            <div class="univga-kpi-content">
                <div class="univga-kpi-value"><?php echo $kpis['courses']['covered']; ?></div>
                <div class="univga-kpi-label"><?php _e('Cours Disponibles', UNIVGA_TEXT_DOMAIN); ?></div>
                <div class="univga-kpi-meta"><?php echo number_format($kpis['courses']['avg_progress'], 1); ?>% <?php _e('progrès moyen', UNIVGA_TEXT_DOMAIN); ?></div>
            </div>
        </div>

        <div class="univga-kpi-card">
            <div class="univga-kpi-icon univga-kpi-completion">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M10.067.87a2.89 2.89 0 0 0-4.134 0l-.622.638-.89-.011a2.89 2.89 0 0 0-2.924 2.924l.01.89-.636.622a2.89 2.89 0 0 0 0 4.134l.637.622-.011.89a2.89 2.89 0 0 0 2.924 2.924l.89-.01.622.636a2.89 2.89 0 0 0 4.134 0l.622-.637.89.011a2.89 2.89 0 0 0 2.924-2.924l-.01-.89.636-.622a2.89 2.89 0 0 0 0-4.134l-.637-.622.011-.89a2.89 2.89 0 0 0-2.924-2.924l-.89.01-.622-.636zm.287 5.984-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7 8.793l2.646-2.647a.5.5 0 0 1 .708.708z"/>
                </svg>
            </div>
            <div class="univga-kpi-content">
                <div class="univga-kpi-value"><?php echo $kpis['courses']['total_completions']; ?></div>
                <div class="univga-kpi-label"><?php _e('Achèvements', UNIVGA_TEXT_DOMAIN); ?></div>
                <div class="univga-kpi-meta"><?php echo number_format($kpis['courses']['completion_rate'], 1); ?>% <?php _e('taux d\'achèvement', UNIVGA_TEXT_DOMAIN); ?></div>
            </div>
        </div>

        <div class="univga-kpi-card">
            <div class="univga-kpi-icon univga-kpi-teams">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002A.274.274 0 0 1 15 13H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
            </div>
            <div class="univga-kpi-content">
                <div class="univga-kpi-value"><?php echo count($teams); ?></div>
                <div class="univga-kpi-label"><?php _e('Équipes Actives', UNIVGA_TEXT_DOMAIN); ?></div>
                <div class="univga-kpi-meta"><?php _e('Groupes organisés', UNIVGA_TEXT_DOMAIN); ?></div>
            </div>
        </div>

        <div class="univga-kpi-card">
            <div class="univga-kpi-icon univga-kpi-performance">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z"/>
                </svg>
            </div>
            <div class="univga-kpi-content">
                <div class="univga-kpi-value"><?php echo number_format(($kpis['seats']['utilization_rate'] + $kpis['courses']['avg_progress']) / 2, 1); ?>%</div>
                <div class="univga-kpi-label"><?php _e('Performance Globale', UNIVGA_TEXT_DOMAIN); ?></div>
                <div class="univga-kpi-meta"><?php _e('Indicateur combiné', UNIVGA_TEXT_DOMAIN); ?></div>
            </div>
        </div>
    </div>

    <!-- Warning Cards -->
    <?php if ($kpis['expiration']['expired_pools'] > 0 || $kpis['expiration']['expiring_soon'] > 0): ?>
    <div class="univga-warnings">
        <?php if ($kpis['expiration']['expired_pools'] > 0): ?>
        <div class="univga-warning-card univga-warning-danger">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
            </svg>
            <div>
                <strong><?php _e('Pools de Sièges Expirés', UNIVGA_TEXT_DOMAIN); ?></strong>
                <p><?php printf(__('Vous avez %d pool(s) de sièges expirés. Les nouveaux membres ne peuvent pas être inscrits automatiquement.', UNIVGA_TEXT_DOMAIN), $kpis['expiration']['expired_pools']); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($kpis['expiration']['expiring_soon'] > 0): ?>
        <div class="univga-warning-card univga-warning-warning">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
            </svg>
            <div>
                <strong><?php _e('Expire Bientôt', UNIVGA_TEXT_DOMAIN); ?></strong>
                <p><?php printf(__('Vous avez %d pool(s) de sièges expirant dans les 7 jours.', UNIVGA_TEXT_DOMAIN), $kpis['expiration']['expiring_soon']); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Main Content Tabs -->
    <div class="univga-tabs">
        <div class="univga-tab-nav">
            <button class="univga-tab-btn active" data-tab="members">
                <div class="tab-icon">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                        <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                        <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                    </svg>
                </div>
                <span class="tab-label"><?php _e('Membres', UNIVGA_TEXT_DOMAIN); ?></span>
            </button>
            <button class="univga-tab-btn" data-tab="courses">
                <div class="tab-icon">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                    </svg>
                </div>
                <span class="tab-label"><?php _e('Cours', UNIVGA_TEXT_DOMAIN); ?></span>
            </button>
            <button class="univga-tab-btn" data-tab="analytics">
                <div class="tab-icon">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z"/>
                    </svg>
                </div>
                <span class="tab-label"><?php _e('Analytics', UNIVGA_TEXT_DOMAIN); ?></span>
            </button>
            <button class="univga-tab-btn" data-tab="learning-paths">
                <div class="tab-icon">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1.002 1.002 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4.018 4.018 0 0 1-.128-1.287z"/>
                        <path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243L6.586 4.672z"/>
                    </svg>
                </div>
                <span class="tab-label"><?php _e('Learning Paths', UNIVGA_TEXT_DOMAIN); ?></span>
            </button>
            <button class="univga-tab-btn" data-tab="gamification">
                <div class="tab-icon">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M6 0C5.2 0 4.6.4 4.3 1.1L1.1 8.7c-.2.4-.1.9.2 1.3.3.3.8.5 1.2.5h2.4c.6 0 1.1-.4 1.3-.9L7.5 6c.1-.2.4-.2.5 0l1.3 3.6c.2.5.7.9 1.3.9h2.4c.4 0 .9-.2 1.2-.5.3-.4.4-.9.2-1.3L11.7 1.1C11.4.4 10.8 0 10 0H6z"/>
                        <path fill-rule="evenodd" d="M11.5 1a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V2H9v8.5a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V2H5v3.5a.5.5 0 0 1-1 0v-4a.5.5 0 0 1 .5-.5h7z"/>
                    </svg>
                </div>
                <span class="tab-label"><?php _e('Gamification', UNIVGA_TEXT_DOMAIN); ?></span>
            </button>
            <button class="univga-tab-btn" data-tab="certifications">
                <div class="tab-icon">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002A.274.274 0 0 1 15 13H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                    </svg>
                </div>
                <span class="tab-label"><?php _e('Certifications', UNIVGA_TEXT_DOMAIN); ?></span>
            </button>
            <button class="univga-tab-btn" data-tab="messages">
                <div class="tab-icon">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H7l-3 3V10c-1 0-2-1-2-2V2z"/>
                    </svg>
                </div>
                <span class="tab-label"><?php _e('Messages', UNIVGA_TEXT_DOMAIN); ?></span>
            </button>
            <button class="univga-tab-btn" data-tab="admin">
                <div class="tab-icon">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zM3 6a3 3 0 0 1 3-3v4a4 4 0 0 1 2 3.464V7h3V5a3 3 0 0 1 3 3v4.5A1.5 1.5 0 0 1 12.5 14h-9A1.5 1.5 0 0 1 2 12.5V6zm6 2.5V9a1 1 0 0 1-2 0v-.5a1 1 0 0 1 1-1 1 1 0 0 1 1 1z"/>
                    </svg>
                </div>
                <span class="tab-label"><?php _e('Administration', UNIVGA_TEXT_DOMAIN); ?></span>
            </button>
            <button class="univga-tab-btn" data-tab="whitelabel">
                <div class="tab-icon">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M2 1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h9.586a2 2 0 0 1 1.414.586l2 2V2a1 1 0 0 0-1-1H2zm0-1a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h9.586l3 3a1 1 0 0 0 1.707-.707L13.414 11H2a1 1 0 0 1-1-1V2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8.414l-3-3H2z"/>
                        <path d="M8.5 3.5a.5.5 0 0 0-1 0V4H6a.5.5 0 0 0 0 1h1.5v.5a.5.5 0 0 0 1 0V5H10a.5.5 0 0 0 0-1H8.5V3.5z"/>
                    </svg>
                </div>
                <span class="tab-label"><?php _e('White-Label', UNIVGA_TEXT_DOMAIN); ?></span>
            </button>
        </div>

        <!-- Members Tab -->
        <div class="univga-tab-content active" id="tab-members">
            <div class="univga-tab-header">
                <div class="univga-filters">
                    <select id="team-filter">
                        <option value="">Toutes les équipes</option>
                        <?php foreach ($teams as $team): ?>
                        <option value="<?php echo $team->id; ?>"><?php echo esc_html($team->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="search" id="member-search" placeholder="Rechercher des membres...">
                </div>
                <div class="univga-actions">
                    <button type="button" class="univga-btn univga-btn-secondary" data-action="export-members">
                        Exporter CSV
                    </button>
                </div>
            </div>

            <div class="univga-table-container">
                <table class="univga-table" id="members-table">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Équipe</th>
                            <th>Cours</th>
                            <th>Progrès</th>
                            <th>Dernière activité</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="members-tbody">
                        <tr class="loading">
                            <td colspan="6">Chargement des membres...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="univga-pagination" id="members-pagination"></div>
        </div>

        <!-- Courses Tab -->
        <div class="univga-tab-content" id="tab-courses">
            <div class="univga-section-header">
                <h3><?php _e('Cours Disponibles', UNIVGA_TEXT_DOMAIN); ?></h3>
                <button type="button" class="univga-btn univga-btn-secondary" id="refresh-courses">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                        <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                    </svg>
                    <?php _e('Actualiser', UNIVGA_TEXT_DOMAIN); ?>
                </button>
            </div>
            <div class="univga-courses-grid" id="courses-grid">
                <div class="loading"><?php _e('Chargement des cours...', UNIVGA_TEXT_DOMAIN); ?></div>
            </div>
        </div>

        <!-- Analytics Tab -->
        <div class="univga-tab-content" id="tab-analytics">
            <div class="univga-analytics-dashboard">
                <!-- Analytics Header -->
                <div class="univga-analytics-header">
                    <div class="univga-analytics-title">
                        <h3><?php _e('Tableau de Bord Analytique d\'Apprentissage', UNIVGA_TEXT_DOMAIN); ?></h3>
                        <p class="univga-analytics-subtitle"><?php _e('Insights en temps réel et métriques de performance pour votre organisation', UNIVGA_TEXT_DOMAIN); ?></p>
                    </div>
                    <div class="univga-analytics-controls">
                        <select id="analytics-timeframe">
                            <option value="7"><?php _e('7 derniers jours', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="30" selected><?php _e('30 derniers jours', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="90"><?php _e('3 derniers mois', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="365"><?php _e('Dernière année', UNIVGA_TEXT_DOMAIN); ?></option>
                        </select>
                        <button type="button" class="univga-btn univga-btn-secondary" id="refresh-analytics">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                            </svg>
                            <?php _e('Actualiser', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>

                <!-- Key Metrics Cards -->
                <div class="univga-analytics-metrics" id="analytics-metrics">
                    <div class="univga-metric-card loading">
                        <div class="univga-metric-icon">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                            </svg>
                        </div>
                        <div class="univga-metric-content">
                            <div class="univga-metric-value">--</div>
                            <div class="univga-metric-label"><?php _e('Completion Rate', UNIVGA_TEXT_DOMAIN); ?></div>
                            <div class="univga-metric-change"></div>
                        </div>
                    </div>

                    <div class="univga-metric-card loading">
                        <div class="univga-metric-icon">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                            </svg>
                        </div>
                        <div class="univga-metric-content">
                            <div class="univga-metric-value">--</div>
                            <div class="univga-metric-label"><?php _e('Active Learners', UNIVGA_TEXT_DOMAIN); ?></div>
                            <div class="univga-metric-change"></div>
                        </div>
                    </div>

                    <div class="univga-metric-card loading">
                        <div class="univga-metric-icon">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8.5 2.687c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                            </svg>
                        </div>
                        <div class="univga-metric-content">
                            <div class="univga-metric-value">--</div>
                            <div class="univga-metric-label"><?php _e('Avg. Study Time', UNIVGA_TEXT_DOMAIN); ?></div>
                            <div class="univga-metric-change"></div>
                        </div>
                    </div>

                    <div class="univga-metric-card loading">
                        <div class="univga-metric-icon">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a1.99 1.99 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3zm-8.322.12C1.72 3.042 1.95 3 2.19 3h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139z"/>
                            </svg>
                        </div>
                        <div class="univga-metric-content">
                            <div class="univga-metric-value">--</div>
                            <div class="univga-metric-label"><?php _e('Skill Gaps', UNIVGA_TEXT_DOMAIN); ?></div>
                            <div class="univga-metric-change"></div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="univga-analytics-charts">
                    <div class="univga-analytics-row">
                        <!-- Completion Rates Chart -->
                        <div class="univga-chart-container">
                            <div class="univga-chart-header">
                                <h4><?php _e('Taux d\'Achèvement des Cours', UNIVGA_TEXT_DOMAIN); ?></h4>
                                <div class="univga-chart-legend" id="completion-legend"></div>
                            </div>
                            <div class="univga-chart-content">
                                <canvas id="completion-rates-chart" width="400" height="300"></canvas>
                                <div class="univga-chart-loading">
                                    <div class="univga-loading-spinner"></div>
                                    <p><?php _e('Chargement des données d\'achèvement...', UNIVGA_TEXT_DOMAIN); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Engagement Timeline Chart -->
                        <div class="univga-chart-container">
                            <div class="univga-chart-header">
                                <h4><?php _e('Learning Activity Timeline', UNIVGA_TEXT_DOMAIN); ?></h4>
                                <div class="univga-chart-controls">
                                    <button type="button" class="univga-chart-btn active" data-chart-view="daily"><?php _e('Daily', UNIVGA_TEXT_DOMAIN); ?></button>
                                    <button type="button" class="univga-chart-btn" data-chart-view="weekly"><?php _e('Weekly', UNIVGA_TEXT_DOMAIN); ?></button>
                                </div>
                            </div>
                            <div class="univga-chart-content">
                                <canvas id="engagement-timeline-chart" width="400" height="300"></canvas>
                                <div class="univga-chart-loading">
                                    <div class="univga-loading-spinner"></div>
                                    <p><?php _e('Chargement des données d\'engagement...', UNIVGA_TEXT_DOMAIN); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="univga-analytics-row">
                        <!-- Team Performance Chart -->
                        <div class="univga-chart-container univga-chart-wide">
                            <div class="univga-chart-header">
                                <h4><?php _e('Team Performance Overview', UNIVGA_TEXT_DOMAIN); ?></h4>
                                <div class="univga-chart-info">
                                    <span class="univga-info-tooltip" title="<?php _e('Shows average completion rate and engagement score by team', UNIVGA_TEXT_DOMAIN); ?>">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                            <path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94z"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <div class="univga-chart-content">
                                <canvas id="team-performance-chart" width="800" height="300"></canvas>
                                <div class="univga-chart-loading">
                                    <div class="univga-loading-spinner"></div>
                                    <p><?php _e('Chargement des données de performance d\'équipe...', UNIVGA_TEXT_DOMAIN); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Insights and Alerts -->
                <div class="univga-analytics-insights">
                    <div class="univga-insights-section">
                        <h4><?php _e('At-Risk Learners', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-at-risk-learners" id="at-risk-learners">
                            <div class="loading"><?php _e('Analyse des données d\'apprentissage...', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-insights-section">
                        <h4><?php _e('Skill Gap Analysis', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-skill-gaps" id="skill-gaps">
                            <div class="loading"><?php _e('Identification des lacunes de compétences...', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-insights-section">
                        <h4><?php _e('Cours Tendance', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-trending-courses" id="trending-courses">
                            <div class="loading"><?php _e('Chargement des tendances de cours...', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Export and Actions -->
                <div class="univga-analytics-actions">
                    <button type="button" class="univga-btn univga-btn-primary" id="export-analytics">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/>
                        </svg>
                        <?php _e('Export Analytics Report', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="univga-btn univga-btn-secondary" id="schedule-report">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                        </svg>
                        <?php _e('Schedule Reports', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Learning Paths Tab -->
        <div class="univga-tab-content" id="tab-learning-paths">
            <div class="univga-learning-paths-dashboard">
                <!-- Learning Paths Header -->
                <div class="univga-learning-paths-header">
                    <div class="univga-learning-paths-title">
                        <h3><?php _e('Gestion des Parcours d\'Apprentissage', UNIVGA_TEXT_DOMAIN); ?></h3>
                        <p class="univga-learning-paths-subtitle"><?php _e('Créez des parcours d\'apprentissage structurés avec prérequis et séquençage automatisé pour vos équipes', UNIVGA_TEXT_DOMAIN); ?></p>
                    </div>
                    <div class="univga-learning-paths-actions">
                        <button type="button" class="univga-btn univga-btn-primary" id="create-learning-path">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                            </svg>
                            <?php _e('Créer un Parcours', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" class="univga-btn univga-btn-secondary" id="import-path">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                            </svg>
                            <?php _e('Importer un Modèle', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>

                <!-- Learning Paths Statistics -->
                <div class="univga-learning-paths-stats" id="learning-paths-stats">
                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1.002 1.002 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4.018 4.018 0 0 1-.128-1.287z"/>
                                <path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243L6.586 4.672z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="total-paths">--</div>
                            <div class="univga-stat-label"><?php _e('Parcours Totaux', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="active-learners">--</div>
                            <div class="univga-stat-label"><?php _e('Apprenants Actifs', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="completion-rate">--</div>
                            <div class="univga-stat-label"><?php _e('Avg. Completion', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="avg-duration">--</div>
                            <div class="univga-stat-label"><?php _e('Avg. Duration', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Learning Paths Filters and Search -->
                <div class="univga-learning-paths-filters">
                    <div class="univga-filters-group">
                        <select id="path-status-filter">
                            <option value=""><?php _e('All Statuses', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="active"><?php _e('Active', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="draft"><?php _e('Draft', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="archived"><?php _e('Archived', UNIVGA_TEXT_DOMAIN); ?></option>
                        </select>
                        
                        <select id="path-difficulty-filter">
                            <option value=""><?php _e('All Levels', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="beginner"><?php _e('Beginner', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="intermediate"><?php _e('Intermediate', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="advanced"><?php _e('Advanced', UNIVGA_TEXT_DOMAIN); ?></option>
                        </select>
                        
                        <select id="path-role-filter">
                            <option value=""><?php _e('All Roles', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="developer"><?php _e('Developer', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="designer"><?php _e('Designer', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="manager"><?php _e('Manager', UNIVGA_TEXT_DOMAIN); ?></option>
                            <option value="sales"><?php _e('Sales', UNIVGA_TEXT_DOMAIN); ?></option>
                        </select>
                    </div>
                    
                    <div class="univga-search-group">
                        <input type="search" id="path-search" placeholder="<?php _e('Search learning paths...', UNIVGA_TEXT_DOMAIN); ?>">
                        <button type="button" class="univga-btn univga-btn-secondary" id="reset-filters">
                            <?php _e('Reset', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>

                <!-- Learning Paths List -->
                <div class="univga-learning-paths-grid" id="learning-paths-grid">
                    <div class="loading"><?php _e('Chargement des parcours d\'apprentissage...', UNIVGA_TEXT_DOMAIN); ?></div>
                </div>

                <!-- Pagination -->
                <div class="univga-pagination" id="paths-pagination"></div>
            </div>
        </div>

        <!-- Gamification Tab -->
        <div class="univga-tab-content" id="tab-gamification">
            <div class="univga-gamification-dashboard">
                <!-- Gamification Header -->
                <div class="univga-gamification-header">
                    <div class="univga-gamification-title">
                        <h3><?php _e('Gamification et Engagement', UNIVGA_TEXT_DOMAIN); ?></h3>
                        <p class="univga-gamification-subtitle"><?php _e('Motivez vos équipes avec des points, badges, classements et récompenses pour stimuler l\'engagement d\'apprentissage', UNIVGA_TEXT_DOMAIN); ?></p>
                    </div>
                    <div class="univga-gamification-actions">
                        <button type="button" class="univga-btn univga-btn-primary" id="create-badge">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M9.669.864 8 0 6.331.864l-1.858.282-.842 1.68-1.337 1.32L2.6 6l-.306 1.854 1.337 1.32.842 1.68 1.858.282L8 12l1.669-.864 1.858-.282.842-1.68 1.337-1.32L13.4 6l.306-1.854-1.337-1.32-.842-1.68L9.669.864zm1.196 1.193.684 1.365 1.086 1.072L12.387 6l.248 1.506-1.086 1.072-.684 1.365-1.51.229L8 10.874l-1.355-.702-1.51-.229-.684-1.365-1.086-1.072L3.614 6l-.25-1.506 1.087-1.072.684-1.365 1.51-.229L8 1.126l1.356.702 1.509.229z"/>
                                <path d="M4 11.794V16l4-1 4 1v-4.206l-2.018.306L8 13.126 6.018 12.1 4 11.794z"/>
                            </svg>
                            <?php _e('Créer un Badge', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" class="univga-btn univga-btn-secondary" id="manage-rewards">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>
                            </svg>
                            <?php _e('Gérer les Récompenses', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>

                <!-- Gamification Overview Stats -->
                <div class="univga-gamification-stats" id="gamification-stats">
                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="total-points">--</div>
                            <div class="univga-stat-label"><?php _e('Points Totaux Gagnés', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M9.669.864 8 0 6.331.864l-1.858.282-.842 1.68-1.337 1.32L2.6 6l-.306 1.854 1.337 1.32.842 1.68 1.858.282L8 12l1.669-.864 1.858-.282.842-1.68 1.337-1.32L13.4 6l.306-1.854-1.337-1.32-.842-1.68L9.669.864z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="total-badges">--</div>
                            <div class="univga-stat-label"><?php _e('Badges Attribués', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="active-participants">--</div>
                            <div class="univga-stat-label"><?php _e('Active Participants', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="avg-engagement">--</div>
                            <div class="univga-stat-label"><?php _e('Engagement Score', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Main Gamification Content -->
                <div class="univga-gamification-content">
                    <!-- Leaderboards Section -->
                    <div class="univga-gamification-section">
                        <div class="univga-section-header">
                            <h4><?php _e('Team Leaderboards', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-leaderboard-controls">
                                <select id="leaderboard-period">
                                    <option value="week"><?php _e('This Week', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="month" selected><?php _e('This Month', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="quarter"><?php _e('This Quarter', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="all"><?php _e('All Time', UNIVGA_TEXT_DOMAIN); ?></option>
                                </select>
                                <select id="leaderboard-team">
                                    <option value=""><?php _e('All Teams', UNIVGA_TEXT_DOMAIN); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="univga-leaderboard-container" id="leaderboard-container">
                            <div class="loading"><?php _e('Chargement du classement...', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <!-- Badges Gallery Section -->
                    <div class="univga-gamification-section">
                        <div class="univga-section-header">
                            <h4><?php _e('Achievement Badges', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-badges-controls">
                                <select id="badge-category">
                                    <option value=""><?php _e('All Categories', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="learning"><?php _e('Learning', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="engagement"><?php _e('Engagement', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="leadership"><?php _e('Leadership', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="collaboration"><?php _e('Collaboration', UNIVGA_TEXT_DOMAIN); ?></option>
                                </select>
                                <input type="search" id="badge-search" placeholder="<?php _e('Search badges...', UNIVGA_TEXT_DOMAIN); ?>">
                            </div>
                        </div>
                        
                        <div class="univga-badges-grid" id="badges-grid">
                            <div class="loading"><?php _e('Chargement des badges...', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Points Activity Feed -->
                <div class="univga-gamification-section">
                    <div class="univga-section-header">
                        <h4><?php _e('Recent Point Activities', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <button type="button" class="univga-btn univga-btn-secondary" id="refresh-activities">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                            </svg>
                            <?php _e('Actualiser', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                    
                    <div class="univga-activities-feed" id="activities-feed">
                        <div class="loading"><?php _e('Chargement des activités...', UNIVGA_TEXT_DOMAIN); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certifications Tab -->
        <div class="univga-tab-content" id="tab-certifications">
            <div class="univga-certifications-dashboard">
                <!-- Certifications Header -->
                <div class="univga-certifications-header">
                    <div class="univga-certifications-title">
                        <h3><?php _e('Certifications et Conformité', UNIVGA_TEXT_DOMAIN); ?></h3>
                        <p class="univga-certifications-subtitle"><?php _e('Gérez les certifications d\'équipe, suivez les exigences de conformité et surveillez les dates d\'expiration pour la conformité réglementaire', UNIVGA_TEXT_DOMAIN); ?></p>
                    </div>
                    <div class="univga-certifications-actions">
                        <button type="button" class="univga-btn univga-btn-primary" id="create-certification">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                            </svg>
                            <?php _e('Créer une Certification', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" class="univga-btn univga-btn-secondary" id="compliance-report">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                                <path d="M4.603 14.087a.81.81 0 0 1-.438-.42c-.195-.388-.13-.776.08-1.102.198-.307.526-.568.897-.787a7.68 7.68 0 0 1 1.482-.645 19.697 19.697 0 0 0 1.062-.33 2.679 2.679 0 0 0 .945-.62c.18-.16.338-.3.465-.438.127-.138.193-.248.193-.306a.255.255 0 0 0-.023-.07c-.015-.024-.024-.035-.006-.047.015-.013.033-.019.077-.019a.284.284 0 0 1 .118.024c.064.025.108.068.108.133 0 .074-.049.18-.168.345-.12.166-.283.356-.495.589-.212.234-.479.498-.8.796-.32.298-.693.634-1.12.995-.428.36-.906.745-1.435 1.155-.529.41-1.108.858-1.738 1.342-.63.484-1.31.1001.598 1.598-.29.598-.62.12-.92.24-2.76.24-.54 0-1.002-.086-1.385-.256-.383-.17-.65-.382-.8-.635a1.17 1.17 0 0 1-.149-.5c0-.15.025-.299.075-.448.05-.15.123-.299.22-.448.096-.148.216-.295.36-.44.145-.144.316-.287.512-.427a12.72 12.72 0 0 1 .658-.45 33.15 33.15 0 0 1 .746-.479 27.724 27.724 0 0 1 .814-.497c.283-.17.593-.349.93-.537.337-.188.697-.391 1.08-.607.383-.216.798-.449 1.245-.699.447-.25.927-.517 1.44-.801.513-.284 1.068-.585 1.665-.903.597-.318 1.237-.654 1.92-1.008.683-.354 1.409-.726 2.178-1.116.77-.39 1.583-.798 2.44-1.224.856-.426 1.756-.87 2.7-1.332.943-.462 1.932-.942 2.966-1.44 1.034-.498 2.114-1.014 3.24-1.548a48.103 48.103 0 0 0 3.478-1.635z"/>
                            </svg>
                            <?php _e('Rapport de Conformité', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>

                <!-- Compliance Overview Stats -->
                <div class="univga-certifications-stats" id="certifications-stats">
                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002A.274.274 0 0 1 15 13H7.022Z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="total-certifications">--</div>
                            <div class="univga-stat-label"><?php _e('Certifications Totales', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="active-certifications">--</div>
                            <div class="univga-stat-label"><?php _e('Actives Valides', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="expiring-soon">--</div>
                            <div class="univga-stat-label"><?php _e('Expiring Soon', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <div class="univga-stat-card">
                        <div class="univga-stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M11.46.146A.5.5 0 0 0 11.107 0H4.893a.5.5 0 0 0-.353.146L.146 4.54A.5.5 0 0 0 0 4.893v6.214a.5.5 0 0 0 .146.353l4.394 4.394a.5.5 0 0 0 .353.146h6.214a.5.5 0 0 0 .353-.146l4.394-4.394a.5.5 0 0 0 .146-.353V4.893a.5.5 0 0 0-.146-.353L11.46.146zM8 4c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995A.905.905 0 0 1 8 4zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                            </svg>
                        </div>
                        <div class="univga-stat-content">
                            <div class="univga-stat-value" id="compliance-rate">--</div>
                            <div class="univga-stat-label"><?php _e('Compliance Rate', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="univga-certifications-content">
                    <!-- Certifications Management Section -->
                    <div class="univga-certifications-section">
                        <div class="univga-section-header">
                            <h4><?php _e('Certification Management', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-certifications-controls">
                                <select id="certification-type-filter">
                                    <option value=""><?php _e('All Types', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="mandatory"><?php _e('Mandatory', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="optional"><?php _e('Optional', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="compliance"><?php _e('Compliance', UNIVGA_TEXT_DOMAIN); ?></option>
                                </select>
                                <input type="search" id="certification-search" placeholder="<?php _e('Search certifications...', UNIVGA_TEXT_DOMAIN); ?>">
                            </div>
                        </div>
                        
                        <div class="univga-certifications-list" id="certifications-list">
                            <div class="loading"><?php _e('Chargement des certifications...', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>

                    <!-- Compliance Dashboard Section -->
                    <div class="univga-certifications-section">
                        <div class="univga-section-header">
                            <h4><?php _e('Team Compliance Status', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-compliance-controls">
                                <select id="compliance-team-filter">
                                    <option value=""><?php _e('All Teams', UNIVGA_TEXT_DOMAIN); ?></option>
                                </select>
                                <select id="compliance-status-filter">
                                    <option value=""><?php _e('All Status', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="compliant"><?php _e('Compliant', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="non-compliant"><?php _e('Non-Compliant', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="expiring"><?php _e('Expiring', UNIVGA_TEXT_DOMAIN); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="univga-compliance-grid" id="compliance-grid">
                            <div class="loading"><?php _e('Chargement des données de conformité...', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Expiring Certifications Alert Section -->
                <div class="univga-certifications-section univga-expiring-section">
                    <div class="univga-section-header">
                        <h4 class="univga-expiring-title">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                            </svg>
                            <?php _e('Certification Expiration Alerts', UNIVGA_TEXT_DOMAIN); ?>
                        </h4>
                        <button type="button" class="univga-btn univga-btn-secondary" id="send-renewal-reminders">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555ZM0 4.697v7.104l5.803-3.558L0 4.697ZM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757Zm3.436-.586L16 11.801V4.697l-5.803 3.546Z"/>
                            </svg>
                            <?php _e('Send Reminders', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                    
                    <div class="univga-expiring-alerts" id="expiring-alerts">
                        <div class="loading"><?php _e('Loading expiring certifications...', UNIVGA_TEXT_DOMAIN); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Branding/White-Label Tab -->
        <div class="univga-tab-content" id="tab-branding">
            <div class="univga-whitelabel-dashboard">
                <!-- Header avec navigation secondaire -->
                <div class="univga-wl-header">
                    <div class="univga-wl-title">
                        <h3><?php _e('Personnalisation White-Label Premium', UNIVGA_TEXT_DOMAIN); ?></h3>
                        <p class="univga-wl-subtitle"><?php _e('Transformez votre plateforme avec votre identité de marque complète', UNIVGA_TEXT_DOMAIN); ?></p>
                    </div>
                    <div class="univga-wl-nav">
                        <button class="univga-wl-nav-btn active" data-wl-section="identity">
                            <span class="wl-nav-icon">🎨</span>
                            <?php _e('Identité', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="domain">
                            <span class="wl-nav-icon">🌐</span>
                            <?php _e('Domaine', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="email">
                            <span class="wl-nav-icon">📧</span>
                            <?php _e('Email', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="templates">
                            <span class="wl-nav-icon">📄</span>
                            <?php _e('Templates', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="advanced">
                            <span class="wl-nav-icon">⚙️</span>
                            <?php _e('Avancé', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>

                <!-- Section Identité & Branding -->
                <div class="univga-wl-section active" id="wl-identity">
                    <div class="univga-wl-grid">
                        <!-- Upload de logos -->
                        <div class="univga-wl-card">
                            <h4>🖼️ <?php _e('Logos et Assets Visuels', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-assets-grid">
                                <div class="univga-asset-item">
                                    <div class="univga-asset-preview" id="logo-preview-wl">
                                        <div class="upload-placeholder">
                                            <svg width="48" height="48" fill="#ccc" viewBox="0 0 16 16">
                                                <path d="M.002 3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-12a2 2 0 0 1-2-2V3zm1 9v1a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12zm5-6.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0z"/>
                                            </svg>
                                            <p><?php _e('Logo Principal', UNIVGA_TEXT_DOMAIN); ?></p>
                                        </div>
                                    </div>
                                    <div class="univga-asset-controls">
                                        <input type="file" id="logo-upload-wl" accept="image/*" style="display: none;">
                                        <button type="button" class="univga-btn univga-btn-primary" onclick="document.getElementById('logo-upload-wl').click()">
                                            <?php _e('Télécharger Logo', UNIVGA_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="univga-asset-item">
                                    <div class="univga-asset-preview small" id="logo-light-preview-wl">
                                        <div class="upload-placeholder">
                                            <svg width="32" height="32" fill="#ccc" viewBox="0 0 16 16">
                                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                            </svg>
                                            <p><?php _e('Logo Clair', UNIVGA_TEXT_DOMAIN); ?></p>
                                        </div>
                                    </div>
                                    <div class="univga-asset-controls">
                                        <input type="file" id="logo-light-upload-wl" accept="image/*" style="display: none;">
                                        <button type="button" class="univga-btn univga-btn-secondary" onclick="document.getElementById('logo-light-upload-wl').click()">
                                            <?php _e('Télécharger', UNIVGA_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="univga-asset-item">
                                    <div class="univga-asset-preview small" id="favicon-preview-wl">
                                        <div class="upload-placeholder">
                                            <svg width="24" height="24" fill="#ccc" viewBox="0 0 16 16">
                                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                            </svg>
                                            <p><?php _e('Favicon', UNIVGA_TEXT_DOMAIN); ?></p>
                                        </div>
                                    </div>
                                    <div class="univga-asset-controls">
                                        <input type="file" id="favicon-upload-wl" accept="image/*" style="display: none;">
                                        <button type="button" class="univga-btn univga-btn-secondary" onclick="document.getElementById('favicon-upload-wl').click()">
                                            <?php _e('Télécharger', UNIVGA_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="univga-asset-item">
                                    <div class="univga-asset-preview" id="cover-preview-wl">
                                        <div class="upload-placeholder">
                                            <svg width="48" height="32" fill="#ccc" viewBox="0 0 16 16">
                                                <path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                                            </svg>
                                            <p><?php _e('Image de Couverture', UNIVGA_TEXT_DOMAIN); ?></p>
                                        </div>
                                    </div>
                                    <div class="univga-asset-controls">
                                        <input type="file" id="cover-upload-wl" accept="image/*" style="display: none;">
                                        <button type="button" class="univga-btn univga-btn-secondary" onclick="document.getElementById('cover-upload-wl').click()">
                                            <?php _e('Télécharger', UNIVGA_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Information d'entreprise -->
                        <div class="univga-wl-card">
                            <h4>🏢 <?php _e('Informations d\'Entreprise', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-grid">
                                <div class="univga-form-group">
                                    <label for="wl-company-name"><?php _e('Nom de l\'Entreprise', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-company-name" placeholder="<?php _e('Votre Entreprise', UNIVGA_TEXT_DOMAIN); ?>">
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-company-slogan"><?php _e('Slogan', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-company-slogan" placeholder="<?php _e('Votre slogan accrocheur', UNIVGA_TEXT_DOMAIN); ?>">
                                </div>
                                <div class="univga-form-group univga-full-width">
                                    <label for="wl-company-description"><?php _e('Description', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <textarea id="wl-company-description" rows="3" placeholder="<?php _e('Description de votre entreprise et de sa mission...', UNIVGA_TEXT_DOMAIN); ?>"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Palette de couleurs -->
                    <div class="univga-wl-card">
                        <h4>🎨 <?php _e('Palette de Couleurs', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-color-grid">
                            <div class="univga-color-item">
                                <label for="wl-primary-color"><?php _e('Couleur Principale', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-primary-color" value="#3b82f6">
                                    <input type="text" class="color-hex" value="#3b82f6">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-secondary-color"><?php _e('Couleur Secondaire', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-secondary-color" value="#10b981">
                                    <input type="text" class="color-hex" value="#10b981">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-accent-color"><?php _e('Couleur d\'Accent', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-accent-color" value="#f59e0b">
                                    <input type="text" class="color-hex" value="#f59e0b">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-background-color"><?php _e('Arrière-plan', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-background-color" value="#ffffff">
                                    <input type="text" class="color-hex" value="#ffffff">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-text-color"><?php _e('Couleur du Texte', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-text-color" value="#1f2937">
                                    <input type="text" class="color-hex" value="#1f2937">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-link-color"><?php _e('Couleur des Liens', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-link-color" value="#3b82f6">
                                    <input type="text" class="color-hex" value="#3b82f6">
                                </div>
                            </div>
                        </div>

                        <!-- Thèmes prédéfinis -->
                        <div class="univga-preset-themes">
                            <h5><?php _e('Thèmes Prédéfinis', UNIVGA_TEXT_DOMAIN); ?></h5>
                            <div class="univga-themes-grid">
                                <div class="univga-theme-item active" data-theme="blue">
                                    <div class="theme-colors">
                                        <span style="background: #3b82f6"></span>
                                        <span style="background: #10b981"></span>
                                        <span style="background: #f59e0b"></span>
                                    </div>
                                    <span><?php _e('Bleu Pro', UNIVGA_TEXT_DOMAIN); ?></span>
                                </div>
                                <div class="univga-theme-item" data-theme="purple">
                                    <div class="theme-colors">
                                        <span style="background: #8b5cf6"></span>
                                        <span style="background: #06b6d4"></span>
                                        <span style="background: #f59e0b"></span>
                                    </div>
                                    <span><?php _e('Violet', UNIVGA_TEXT_DOMAIN); ?></span>
                                </div>
                                <div class="univga-theme-item" data-theme="green">
                                    <div class="theme-colors">
                                        <span style="background: #10b981"></span>
                                        <span style="background: #059669"></span>
                                        <span style="background: #f59e0b"></span>
                                    </div>
                                    <span><?php _e('Vert Nature', UNIVGA_TEXT_DOMAIN); ?></span>
                                </div>
                                <div class="univga-theme-item" data-theme="red">
                                    <div class="theme-colors">
                                        <span style="background: #ef4444"></span>
                                        <span style="background: #dc2626"></span>
                                        <span style="background: #f59e0b"></span>
                                    </div>
                                    <span><?php _e('Rouge Énergie', UNIVGA_TEXT_DOMAIN); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Typographie -->
                    <div class="univga-wl-card">
                        <h4>✏️ <?php _e('Typographie', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group">
                                <label for="wl-font-family"><?php _e('Police Principale', UNIVGA_TEXT_DOMAIN); ?></label>
                                <select id="wl-font-family">
                                    <option value="Inter">Inter (Recommandé)</option>
                                    <option value="Roboto">Roboto</option>
                                    <option value="Open Sans">Open Sans</option>
                                    <option value="Lato">Lato</option>
                                    <option value="Montserrat">Montserrat</option>
                                    <option value="Poppins">Poppins</option>
                                </select>
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-heading-font"><?php _e('Police des Titres', UNIVGA_TEXT_DOMAIN); ?></label>
                                <select id="wl-heading-font">
                                    <option value="Inter">Inter (Recommandé)</option>
                                    <option value="Roboto">Roboto</option>
                                    <option value="Open Sans">Open Sans</option>
                                    <option value="Lato">Lato</option>
                                    <option value="Montserrat">Montserrat</option>
                                    <option value="Poppins">Poppins</option>
                                </select>
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-font-size"><?php _e('Taille de Police de Base', UNIVGA_TEXT_DOMAIN); ?></label>
                                <select id="wl-font-size">
                                    <option value="14px">14px - Petit</option>
                                    <option value="16px" selected>16px - Normal</option>
                                    <option value="18px">18px - Grand</option>
                                    <option value="20px">20px - Très Grand</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Domaine -->
                <div class="univga-wl-section" id="wl-domain">
                    <div class="univga-wl-card">
                        <h4>🌐 <?php _e('Configuration de Domaine Personnalisé', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group">
                                <label for="wl-custom-domain"><?php _e('Domaine Personnalisé', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="text" id="wl-custom-domain" placeholder="votre-entreprise.com">
                                <small><?php _e('Configurez votre propre domaine pour une expérience entièrement white-label', UNIVGA_TEXT_DOMAIN); ?></small>
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-subdomain"><?php _e('Sous-domaine', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="text" id="wl-subdomain" placeholder="formation">
                                <small><?php _e('Ex: formation.votre-entreprise.com', UNIVGA_TEXT_DOMAIN); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Email -->
                <div class="univga-wl-section" id="wl-email">
                    <div class="univga-wl-card">
                        <h4>📧 <?php _e('Configuration Email', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group">
                                <label for="wl-from-email"><?php _e('Adresse Email Expéditeur', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="email" id="wl-from-email" placeholder="no-reply@votre-entreprise.com">
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-from-name"><?php _e('Nom Expéditeur', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="text" id="wl-from-name" placeholder="Votre Entreprise">
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-reply-to"><?php _e('Email de Réponse', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="email" id="wl-reply-to" placeholder="support@votre-entreprise.com">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Templates -->
                <div class="univga-wl-section" id="wl-templates">
                    <div class="univga-wl-card">
                        <h4>📄 <?php _e('Templates Email', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group univga-full-width">
                                <label for="wl-email-header"><?php _e('En-tête Email', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-email-header" rows="4" placeholder="Personnalisez l'en-tête de vos emails..."></textarea>
                            </div>
                            <div class="univga-form-group univga-full-width">
                                <label for="wl-email-footer"><?php _e('Pied de page Email', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-email-footer" rows="4" placeholder="Personnalisez le pied de page de vos emails..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Avancé -->
                <div class="univga-wl-section" id="wl-advanced">
                    <div class="univga-wl-card">
                        <h4>⚙️ <?php _e('Paramètres Avancés', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group">
                                <label for="wl-css-custom"><?php _e('CSS Personnalisé', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-css-custom" rows="6" placeholder="/* Votre CSS personnalisé ici */"></textarea>
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-js-custom"><?php _e('JavaScript Personnalisé', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-js-custom" rows="6" placeholder="// Votre JavaScript personnalisé ici"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aperçu en temps réel -->
                <div class="univga-wl-preview" id="wl-preview">
                    <h4><?php _e('Aperçu en Temps Réel', UNIVGA_TEXT_DOMAIN); ?></h4>
                    <div class="preview-container">
                        <div style="padding: 20px; text-align: center;">
                            <h3 style="color: #3b82f6;">Aperçu de votre marque</h3>
                            <p>Ceci est un exemple de texte avec votre palette de couleurs.</p>
                            <p style="color: #10b981;">Texte secondaire avec accent</p>
                            <a href="#" style="color: #3b82f6;">Lien d'exemple</a>
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="univga-wl-actions">
                    <button type="button" class="univga-btn univga-btn-secondary" id="wl-preview-btn">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                            <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                        </svg>
                        <?php _e('Aperçu', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="univga-btn univga-btn-primary" id="wl-save-btn">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9.5a1 1 0 0 0-1 1v7.293l2.646-2.647a.5.5 0 0 1 .708.708l-3.5 3.5a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L8.5 8.293V1A1 1 0 0 0 7.5 0h-5z"/>
                        </svg>
                        <?php _e('Sauvegarder Configuration', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>

                <!-- Branding Content Grid -->
                <div class="univga-branding-grid">
                    <!-- Brand Identity Section -->
                    <div class="univga-branding-section">
                        <div class="univga-section-header">
                            <h4><?php _e('Brand Identity & Logos', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <p class="univga-section-subtitle"><?php _e('Upload your organization logos and brand assets', UNIVGA_TEXT_DOMAIN); ?></p>
                        </div>
                        
                        <div class="univga-brand-assets">
                            <!-- Main Logo Upload -->
                            <div class="univga-asset-upload">
                                <div class="univga-asset-preview" id="logo-preview">
                                    <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M.002 3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-12a2 2 0 0 1-2-2V3zm1 9v1a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12zm5-6.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0z"/>
                                    </svg>
                                    <p><?php _e('Main Logo', UNIVGA_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="univga-asset-controls">
                                    <input type="file" id="logo-upload" accept="image/*" style="display: none;">
                                    <button type="button" class="univga-btn univga-btn-secondary" onclick="$('#logo-upload').click()">
                                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                        </svg>
                                        <?php _e('Upload Logo', UNIVGA_TEXT_DOMAIN); ?>
                                    </button>
                                    <button type="button" class="univga-btn univga-btn-outline" id="remove-logo" style="display: none;">
                                        <?php _e('Remove', UNIVGA_TEXT_DOMAIN); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Favicon Upload -->
                            <div class="univga-asset-upload">
                                <div class="univga-asset-preview small" id="favicon-preview">
                                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                    </svg>
                                    <p><?php _e('Favicon', UNIVGA_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="univga-asset-controls">
                                    <input type="file" id="favicon-upload" accept="image/*" style="display: none;">
                                    <button type="button" class="univga-btn univga-btn-secondary" onclick="$('#favicon-upload').click()">
                                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                        </svg>
                                        <?php _e('Upload', UNIVGA_TEXT_DOMAIN); ?>
                                    </button>
                                    <button type="button" class="univga-btn univga-btn-outline" id="remove-favicon" style="display: none;">
                                        <?php _e('Remove', UNIVGA_TEXT_DOMAIN); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Color Theme Section -->
                    <div class="univga-branding-section">
                        <div class="univga-section-header">
                            <h4><?php _e('Color Theme & Styling', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <p class="univga-section-subtitle"><?php _e('Customize colors and visual styling to match your brand', UNIVGA_TEXT_DOMAIN); ?></p>
                        </div>
                        
                        <div class="univga-color-controls">
                            <!-- Primary Colors -->
                            <div class="univga-color-group">
                                <h5><?php _e('Primary Colors', UNIVGA_TEXT_DOMAIN); ?></h5>
                                <div class="univga-color-inputs">
                                    <div class="univga-color-input">
                                        <label for="primary-color"><?php _e('Primary Color', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <div class="univga-color-picker">
                                            <input type="color" id="primary-color" value="#3b82f6">
                                            <span class="univga-color-hex">#3b82f6</span>
                                        </div>
                                    </div>
                                    <div class="univga-color-input">
                                        <label for="secondary-color"><?php _e('Secondary Color', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <div class="univga-color-picker">
                                            <input type="color" id="secondary-color" value="#10b981">
                                            <span class="univga-color-hex">#10b981</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Accent Colors -->
                            <div class="univga-color-group">
                                <h5><?php _e('Accent Colors', UNIVGA_TEXT_DOMAIN); ?></h5>
                                <div class="univga-color-inputs">
                                    <div class="univga-color-input">
                                        <label for="accent-color"><?php _e('Accent Color', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <div class="univga-color-picker">
                                            <input type="color" id="accent-color" value="#f59e0b">
                                            <span class="univga-color-hex">#f59e0b</span>
                                        </div>
                                    </div>
                                    <div class="univga-color-input">
                                        <label for="text-color"><?php _e('Text Color', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <div class="univga-color-picker">
                                            <input type="color" id="text-color" value="#374151">
                                            <span class="univga-color-hex">#374151</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Predefined Themes -->
                            <div class="univga-color-group">
                                <h5><?php _e('Quick Themes', UNIVGA_TEXT_DOMAIN); ?></h5>
                                <div class="univga-theme-presets">
                                    <div class="univga-theme-preset active" data-theme="blue">
                                        <div class="univga-theme-colors">
                                            <span style="background: #3b82f6"></span>
                                            <span style="background: #10b981"></span>
                                            <span style="background: #f59e0b"></span>
                                        </div>
                                        <span><?php _e('Blue', UNIVGA_TEXT_DOMAIN); ?></span>
                                    </div>
                                    <div class="univga-theme-preset" data-theme="purple">
                                        <div class="univga-theme-colors">
                                            <span style="background: #8b5cf6"></span>
                                            <span style="background: #06b6d4"></span>
                                            <span style="background: #f59e0b"></span>
                                        </div>
                                        <span><?php _e('Purple', UNIVGA_TEXT_DOMAIN); ?></span>
                                    </div>
                                    <div class="univga-theme-preset" data-theme="green">
                                        <div class="univga-theme-colors">
                                            <span style="background: #10b981"></span>
                                            <span style="background: #059669"></span>
                                            <span style="background: #f59e0b"></span>
                                        </div>
                                        <span><?php _e('Green', UNIVGA_TEXT_DOMAIN); ?></span>
                                    </div>
                                    <div class="univga-theme-preset" data-theme="red">
                                        <div class="univga-theme-colors">
                                            <span style="background: #ef4444"></span>
                                            <span style="background: #dc2626"></span>
                                            <span style="background: #f59e0b"></span>
                                        </div>
                                        <span><?php _e('Red', UNIVGA_TEXT_DOMAIN); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Domain Section -->
                    <div class="univga-branding-section">
                        <div class="univga-section-header">
                            <h4><?php _e('Custom Domain & URL', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <p class="univga-section-subtitle"><?php _e('Configure your custom domain for a fully branded experience', UNIVGA_TEXT_DOMAIN); ?></p>
                        </div>
                        
                        <div class="univga-domain-settings">
                            <div class="univga-domain-input">
                                <label for="custom-domain"><?php _e('Custom Domain', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="text" id="custom-domain" placeholder="learning.yourcompany.com" class="univga-input">
                                <small class="univga-help-text"><?php _e('Enter your custom domain without http:// or https://', UNIVGA_TEXT_DOMAIN); ?></small>
                            </div>
                            
                            <div class="univga-domain-status">
                                <div class="univga-status-item">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566z"/>
                                    </svg>
                                    <span><?php _e('DNS not configured', UNIVGA_TEXT_DOMAIN); ?></span>
                                </div>
                            </div>
                            
                            <div class="univga-domain-instructions">
                                <h6><?php _e('Setup Instructions', UNIVGA_TEXT_DOMAIN); ?></h6>
                                <ol>
                                    <li><?php _e('Add a CNAME record pointing to: app.univga.com', UNIVGA_TEXT_DOMAIN); ?></li>
                                    <li><?php _e('Wait for DNS propagation (up to 24 hours)', UNIVGA_TEXT_DOMAIN); ?></li>
                                    <li><?php _e('SSL certificate will be automatically generated', UNIVGA_TEXT_DOMAIN); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced CSS Section -->
                    <div class="univga-branding-section">
                        <div class="univga-section-header">
                            <h4><?php _e('Advanced CSS Customization', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <p class="univga-section-subtitle"><?php _e('Add custom CSS for advanced styling and branding', UNIVGA_TEXT_DOMAIN); ?></p>
                        </div>
                        
                        <div class="univga-css-editor">
                            <textarea id="custom-css" class="univga-css-textarea" placeholder="/* Add your custom CSS here */&#10;.custom-header {&#10;    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);&#10;    color: white;&#10;}"></textarea>
                            <div class="univga-css-controls">
                                <button type="button" class="univga-btn univga-btn-secondary" id="validate-css">
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                                    </svg>
                                    <?php _e('Validate CSS', UNIVGA_TEXT_DOMAIN); ?>
                                </button>
                                <button type="button" class="univga-btn univga-btn-outline" id="reset-css">
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                        <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                    </svg>
                                    <?php _e('Reset', UNIVGA_TEXT_DOMAIN); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Preview Section -->
                <div class="univga-branding-section univga-preview-section">
                    <div class="univga-section-header">
                        <h4><?php _e('Live Preview', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <p class="univga-section-subtitle"><?php _e('See how your customizations look in real-time', UNIVGA_TEXT_DOMAIN); ?></p>
                    </div>
                    
                    <div class="univga-preview-container" id="branding-preview">
                        <div class="univga-preview-mockup">
                            <div class="univga-mockup-header">
                                <div class="univga-mockup-logo">Your Logo</div>
                                <div class="univga-mockup-nav">
                                    <span>Dashboard</span>
                                    <span>Cours</span>
                                    <span>Reports</span>
                                </div>
                            </div>
                            <div class="univga-mockup-content">
                                <div class="univga-mockup-card">
                                    <h5>Sample Dashboard Card</h5>
                                    <p>This shows how your branding will look across the platform</p>
                                    <button class="univga-mockup-btn">Action Button</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Tab -->
        <div class="univga-tab-content" id="tab-reports">
            <div class="univga-reports-section">
                <h3><?php _e('Export Reports', UNIVGA_TEXT_DOMAIN); ?></h3>
                <div class="univga-export-options">
                    <button type="button" class="univga-btn univga-btn-secondary" data-export="members">
                        <?php _e('Members Report (CSV)', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="univga-btn univga-btn-secondary" data-export="courses">
                        <?php _e('Rapport des Cours (CSV)', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
            
            <!-- Bulk Operations Section -->
            <div class="univga-admin-section" id="admin-bulk-operations">
                <div class="univga-admin-header">
                    <h2><?php _e('Bulk Operations', UNIVGA_TEXT_DOMAIN); ?></h2>
                    <p class="univga-admin-description"><?php _e('Import users via CSV, enroll in courses, assign teams and manage seat pools in bulk.', UNIVGA_TEXT_DOMAIN); ?></p>
                </div>
                
                <div class="univga-bulk-operations-container">
                    <!-- Operations Navigation -->
                    <div class="univga-bulk-nav">
                        <button class="univga-bulk-nav-btn active" data-bulk-section="import">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                            </svg>
                            <?php _e('CSV Import', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-bulk-nav-btn" data-bulk-section="enroll">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8.5 2.687c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                            </svg>
                            <?php _e('Bulk Enrollment', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-bulk-nav-btn" data-bulk-section="teams">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                                <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                            </svg>
                            <?php _e('Team Assignment', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                    
                    <!-- CSV Import Section -->
                    <div class="univga-bulk-section active" id="bulk-import">
                        <div class="univga-form-section">
                            <h4><?php _e('Import Users from CSV', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <p class="univga-form-help"><?php _e('Upload a CSV file to import multiple users at once. Required columns: email, display_name, team (optional).', UNIVGA_TEXT_DOMAIN); ?></p>
                            
                            <form id="csv-import-form" enctype="multipart/form-data">
                                <div class="univga-form-group">
                                    <label for="csv-file"><?php _e('Choose CSV File', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-file-upload">
                                        <input type="file" id="csv-file" name="bulk_file" accept=".csv,.xlsx,.xls" required>
                                        <div class="univga-file-upload-area" id="csv-upload-area">
                                            <div class="univga-file-upload-icon">
                                                <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                                </svg>
                                            </div>
                                            <div class="univga-file-upload-text">
                                                <strong><?php _e('Click to upload', UNIVGA_TEXT_DOMAIN); ?></strong>
                                                <span><?php _e('or drag and drop', UNIVGA_TEXT_DOMAIN); ?></span>
                                            </div>
                                            <div class="univga-file-upload-hint">
                                                <?php _e('CSV, XLSX files up to 10MB', UNIVGA_TEXT_DOMAIN); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="univga-form-row">
                                    <div class="univga-form-group">
                                        <label for="default-team"><?php _e('Default Team (Optional)', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <select id="default-team">
                                            <option value=""><?php _e('No Default Team', UNIVGA_TEXT_DOMAIN); ?></option>
                                            <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team->id; ?>"><?php echo esc_html($team->name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="univga-form-help"><?php _e('Team assigned to users without a team column', UNIVGA_TEXT_DOMAIN); ?></small>
                                    </div>
                                    <div class="univga-form-group">
                                        <label for="send-invitations"><?php _e('Send Invitations', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <select id="send-invitations">
                                            <option value="yes"><?php _e('Send email invitations', UNIVGA_TEXT_DOMAIN); ?></option>
                                            <option value="no"><?php _e('Import without emails', UNIVGA_TEXT_DOMAIN); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </form>
                            
                            <!-- CSV Preview -->
                            <div id="csv-preview" class="univga-csv-preview" style="display: none;">
                                <h5><?php _e('File Preview', UNIVGA_TEXT_DOMAIN); ?></h5>
                                <div class="univga-csv-stats"></div>
                                <div class="univga-csv-table-container">
                                    <table class="univga-csv-table">
                                        <thead id="csv-preview-header"></thead>
                                        <tbody id="csv-preview-body"></tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="univga-form-actions">
                                <button type="button" class="univga-btn univga-btn-primary" id="start-import" disabled>
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                        <path d="M6.271 5.055a.5.5 0 0 1 .52.444L7.5 8.5h1L8.791 5.5a.5.5 0 1 1 .958.218L9.27 8.75h1.48a.75.75 0 0 1 0 1.5H9.5v1.25a.5.5 0 0 1-1 0V10.25H7.25a.75.75 0 0 1 0-1.5h1.23L8.02 5.718a.5.5 0 0 1 .52-.444z"/>
                                    </svg>
                                    <?php _e('Import Users', UNIVGA_TEXT_DOMAIN); ?>
                                </button>
                                <button type="button" class="univga-btn univga-btn-secondary" id="download-template">
                                    <?php _e('Download Template', UNIVGA_TEXT_DOMAIN); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bulk Enrollment Section -->
                    <div class="univga-bulk-section" id="bulk-enroll">
                        <div class="univga-form-section">
                            <h4><?php _e('Bulk Course Enrollment', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <p class="univga-form-help"><?php _e('Enroll multiple users in courses simultaneously.', UNIVGA_TEXT_DOMAIN); ?></p>
                            
                            <form id="bulk-enroll-form">
                                <div class="univga-form-row">
                                    <div class="univga-form-group">
                                        <label><?php _e('Select Users', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <div class="univga-member-selector">
                                            <input type="text" id="user-search" placeholder="<?php _e('Search users to enroll...', UNIVGA_TEXT_DOMAIN); ?>">
                                            <div class="univga-selected-users" id="selected-users"></div>
                                        </div>
                                    </div>
                                    <div class="univga-form-group">
                                        <label><?php _e('Select Courses', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <div class="univga-course-selector">
                                            <input type="text" id="course-search" placeholder="<?php _e('Search courses...', UNIVGA_TEXT_DOMAIN); ?>">
                                            <div class="univga-selected-courses" id="selected-courses"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="univga-form-group">
                                    <label for="enrollment-mode"><?php _e('Enrollment Mode', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <select id="enrollment-mode">
                                        <option value="immediate"><?php _e('Immediate Access', UNIVGA_TEXT_DOMAIN); ?></option>
                                        <option value="scheduled"><?php _e('Scheduled Start', UNIVGA_TEXT_DOMAIN); ?></option>
                                    </select>
                                </div>
                                
                                <div class="univga-form-group" id="schedule-date-group" style="display: none;">
                                    <label for="start-date"><?php _e('Start Date', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="date" id="start-date" name="start_date">
                                </div>
                                
                                <div class="univga-enrollment-summary" id="enrollment-summary" style="display: none;">
                                    <h5><?php _e('Enrollment Summary', UNIVGA_TEXT_DOMAIN); ?></h5>
                                    <div class="univga-summary-stats"></div>
                                </div>
                                
                                <div class="univga-form-actions">
                                    <button type="button" class="univga-btn univga-btn-primary" id="start-enrollment" disabled>
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                            <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                                        </svg>
                                        <?php _e('Enroll Users', UNIVGA_TEXT_DOMAIN); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Team Assignment Section -->
                    <div class="univga-bulk-section" id="bulk-teams">
                        <div class="univga-form-section">
                            <h4><?php _e('Bulk Team Assignment', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <p class="univga-form-help"><?php _e('Assign multiple users to teams or reassign teams in bulk.', UNIVGA_TEXT_DOMAIN); ?></p>
                            
                            <form id="bulk-teams-form">
                                <div class="univga-form-row">
                                    <div class="univga-form-group">
                                        <label for="assignment-mode"><?php _e('Assignment Mode', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <select id="assignment-mode">
                                            <option value="assign"><?php _e('Assign to Team', UNIVGA_TEXT_DOMAIN); ?></option>
                                            <option value="reassign"><?php _e('Reassign Team', UNIVGA_TEXT_DOMAIN); ?></option>
                                            <option value="remove"><?php _e('Remove from Teams', UNIVGA_TEXT_DOMAIN); ?></option>
                                        </select>
                                    </div>
                                    <div class="univga-form-group" id="target-team-group">
                                        <label for="target-team"><?php _e('Target Team', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <select id="target-team">
                                            <option value=""><?php _e('Select a team...', UNIVGA_TEXT_DOMAIN); ?></option>
                                            <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team->id; ?>"><?php echo esc_html($team->name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="univga-form-group">
                                    <label><?php _e('Select Users', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-user-selection-tabs">
                                        <button type="button" class="univga-tab-btn active" data-tab="select-individual"><?php _e('Select Individual', UNIVGA_TEXT_DOMAIN); ?></button>
                                        <button type="button" class="univga-tab-btn" data-tab="select-by-team"><?php _e('Select by Current Team', UNIVGA_TEXT_DOMAIN); ?></button>
                                        <button type="button" class="univga-tab-btn" data-tab="select-all"><?php _e('All Users', UNIVGA_TEXT_DOMAIN); ?></button>
                                    </div>
                                    
                                    <div class="univga-tab-content active" id="select-individual">
                                        <input type="text" id="team-user-search" placeholder="<?php _e('Search users...', UNIVGA_TEXT_DOMAIN); ?>">
                                        <div class="univga-user-list" id="individual-users">
                                            <div class="loading"><?php _e('Loading users...', UNIVGA_TEXT_DOMAIN); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="univga-tab-content" id="select-by-team">
                                        <label for="source-team"><?php _e('Current Team', UNIVGA_TEXT_DOMAIN); ?></label>
                                        <select id="source-team">
                                            <option value=""><?php _e('Select current team...', UNIVGA_TEXT_DOMAIN); ?></option>
                                            <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team->id; ?>"><?php echo esc_html($team->name); ?> (<?php echo $team->member_count ?? 0; ?> <?php _e('members', UNIVGA_TEXT_DOMAIN); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="univga-tab-content" id="select-all">
                                        <div class="univga-all-users-info">
                                            <div class="univga-info-icon">
                                                <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                                </svg>
                                            </div>
                                            <h5><?php _e('All Users Operation', UNIVGA_TEXT_DOMAIN); ?></h5>
                                            <p><?php _e('This will affect all users in the organization.', UNIVGA_TEXT_DOMAIN); ?></p>
                                            <div class="univga-user-stats">
                                                <span class="univga-stat">
                                                    <strong><?php echo $kpis['members']['total']; ?></strong> <?php _e('total users', UNIVGA_TEXT_DOMAIN); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="univga-team-assignment-summary" id="team-assignment-summary" style="display: none;">
                                    <h5><?php _e('Assignment Summary', UNIVGA_TEXT_DOMAIN); ?></h5>
                                    <div class="univga-assignment-stats"></div>
                                </div>
                                
                                <div class="univga-form-actions">
                                    <button type="button" class="univga-btn univga-btn-primary" id="start-team-assignment" disabled>
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                        </svg>
                                        <?php _e('Apply Team Changes', UNIVGA_TEXT_DOMAIN); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Operation Progress -->
                    <div class="univga-operation-progress" id="operation-progress" style="display: none;">
                        <div class="univga-progress-header">
                            <h4 id="progress-title"><?php _e('Processing...', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <button type="button" class="univga-btn univga-btn-secondary" id="cancel-operation">
                                <?php _e('Cancel', UNIVGA_TEXT_DOMAIN); ?>
                            </button>
                        </div>
                        <div class="univga-progress-bar">
                            <div class="univga-progress-fill" id="progress-fill"></div>
                        </div>
                        <div class="univga-progress-stats" id="progress-stats"></div>
                        <div class="univga-progress-log" id="progress-log"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages Tab -->
        <div class="univga-tab-content" id="tab-messages">
            <div class="univga-messages-container">
                <!-- Messages Header -->
                <div class="univga-messages-header">
                    <div class="univga-messages-title">
                        <h3><?php _e('Messagerie Interne', UNIVGA_TEXT_DOMAIN); ?></h3>
                        <div class="univga-messages-nav">
                            <button class="univga-msg-nav-btn active" data-msg-view="conversations">
                                <?php _e('Conversations', UNIVGA_TEXT_DOMAIN); ?>
                            </button>
                            <button class="univga-msg-nav-btn" data-msg-view="archived">
                                <?php _e('Archivées', UNIVGA_TEXT_DOMAIN); ?>
                            </button>
                        </div>
                    </div>
                    <div class="univga-messages-actions">
                        <button type="button" class="univga-btn univga-btn-primary" data-action="new-message">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.5 4.5a.5.5 0 0 0-1 0v3h-3a.5.5 0 0 0 0 1h3v3a.5.5 0 0 0 1 0v-3h3a.5.5 0 0 0 0-1h-3v-3z"/>
                            </svg>
                            <?php _e('Nouveau Message', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>

                <!-- Messages View Container -->
                <div class="univga-messages-view-container">
                    <!-- Conversations List View -->
                    <div class="univga-messages-view active" id="conversations-view">
                        <div class="univga-messages-sidebar">
                            <div class="univga-conversations-list" id="conversations-list">
                                <div class="loading"><?php _e('Chargement des conversations...', UNIVGA_TEXT_DOMAIN); ?></div>
                            </div>
                        </div>
                        
                        <!-- Chat View -->
                        <div class="univga-chat-panel" id="chat-panel">
                            <div class="univga-chat-placeholder">
                                <div class="univga-chat-placeholder-icon">
                                    <svg width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894zm-.493 3.905a21.682 21.682 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a9.68 9.68 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9.06 9.06 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105z"/>
                                    </svg>
                                </div>
                                <h4><?php _e('Sélectionnez une conversation', UNIVGA_TEXT_DOMAIN); ?></h4>
                                <p><?php _e('Choisissez une conversation dans la liste pour commencer à discuter avec vos collègues d\'équipe.', UNIVGA_TEXT_DOMAIN); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Archived Conversations View -->
                    <div class="univga-messages-view" id="archived-view">
                        <div class="univga-archived-conversations" id="archived-conversations">
                            <div class="loading"><?php _e('Chargement des conversations archivées...', UNIVGA_TEXT_DOMAIN); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- White-Label Tab -->
        <div class="univga-tab-content" id="tab-whitelabel">
            <div class="univga-whitelabel-dashboard">
                <!-- Header avec navigation secondaire -->
                <div class="univga-wl-header">
                    <div class="univga-wl-title">
                        <h3><?php _e('Personnalisation White-Label Premium', UNIVGA_TEXT_DOMAIN); ?></h3>
                        <p class="univga-wl-subtitle"><?php _e('Transformez votre plateforme avec votre identité de marque complète', UNIVGA_TEXT_DOMAIN); ?></p>
                    </div>
                    <div class="univga-wl-nav">
                        <button class="univga-wl-nav-btn active" data-wl-section="identity">
                            <span class="wl-nav-icon">🎨</span>
                            <?php _e('Identité', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="domain">
                            <span class="wl-nav-icon">🌐</span>
                            <?php _e('Domaine', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="email">
                            <span class="wl-nav-icon">📧</span>
                            <?php _e('Email', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="templates">
                            <span class="wl-nav-icon">📄</span>
                            <?php _e('Templates', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="advanced">
                            <span class="wl-nav-icon">⚙️</span>
                            <?php _e('Avancé', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>

                <!-- Section Identité & Branding -->
                <div class="univga-wl-section active" id="wl-identity">
                    <div class="univga-wl-grid">
                        <!-- Upload de logos -->
                        <div class="univga-wl-card">
                            <h4>🖼️ <?php _e('Logos et Assets Visuels', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-assets-grid">
                                <div class="univga-asset-item">
                                    <div class="univga-asset-preview" id="logo-preview-wl">
                                        <div class="upload-placeholder">
                                            <svg width="48" height="48" fill="#ccc" viewBox="0 0 16 16">
                                                <path d="M.002 3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-12a2 2 0 0 1-2-2V3zm1 9v1a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12zm5-6.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0z"/>
                                            </svg>
                                            <p><?php _e('Logo Principal', UNIVGA_TEXT_DOMAIN); ?></p>
                                        </div>
                                    </div>
                                    <div class="univga-asset-controls">
                                        <input type="file" id="logo-upload-wl" accept="image/*" style="display: none;">
                                        <button type="button" class="univga-btn univga-btn-primary" onclick="document.getElementById('logo-upload-wl').click()">
                                            <?php _e('Télécharger Logo', UNIVGA_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="univga-asset-item">
                                    <div class="univga-asset-preview small" id="logo-light-preview-wl">
                                        <div class="upload-placeholder">
                                            <svg width="32" height="32" fill="#ccc" viewBox="0 0 16 16">
                                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                            </svg>
                                            <p><?php _e('Logo Clair', UNIVGA_TEXT_DOMAIN); ?></p>
                                        </div>
                                    </div>
                                    <div class="univga-asset-controls">
                                        <input type="file" id="logo-light-upload-wl" accept="image/*" style="display: none;">
                                        <button type="button" class="univga-btn univga-btn-secondary" onclick="document.getElementById('logo-light-upload-wl').click()">
                                            <?php _e('Télécharger', UNIVGA_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="univga-asset-item">
                                    <div class="univga-asset-preview small" id="favicon-preview-wl">
                                        <div class="upload-placeholder">
                                            <svg width="24" height="24" fill="#ccc" viewBox="0 0 16 16">
                                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                            </svg>
                                            <p><?php _e('Favicon', UNIVGA_TEXT_DOMAIN); ?></p>
                                        </div>
                                    </div>
                                    <div class="univga-asset-controls">
                                        <input type="file" id="favicon-upload-wl" accept="image/*" style="display: none;">
                                        <button type="button" class="univga-btn univga-btn-secondary" onclick="document.getElementById('favicon-upload-wl').click()">
                                            <?php _e('Télécharger', UNIVGA_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="univga-asset-item">
                                    <div class="univga-asset-preview" id="cover-preview-wl">
                                        <div class="upload-placeholder">
                                            <svg width="48" height="32" fill="#ccc" viewBox="0 0 16 16">
                                                <path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                                            </svg>
                                            <p><?php _e('Image de Couverture', UNIVGA_TEXT_DOMAIN); ?></p>
                                        </div>
                                    </div>
                                    <div class="univga-asset-controls">
                                        <input type="file" id="cover-upload-wl" accept="image/*" style="display: none;">
                                        <button type="button" class="univga-btn univga-btn-secondary" onclick="document.getElementById('cover-upload-wl').click()">
                                            <?php _e('Télécharger', UNIVGA_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Information d'entreprise -->
                        <div class="univga-wl-card">
                            <h4>🏢 <?php _e('Informations d\'Entreprise', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-grid">
                                <div class="univga-form-group">
                                    <label for="wl-company-name"><?php _e('Nom de l\'Entreprise', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-company-name" placeholder="<?php _e('Votre Entreprise', UNIVGA_TEXT_DOMAIN); ?>">
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-company-slogan"><?php _e('Slogan', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-company-slogan" placeholder="<?php _e('Votre slogan accrocheur', UNIVGA_TEXT_DOMAIN); ?>">
                                </div>
                                <div class="univga-form-group univga-full-width">
                                    <label for="wl-company-description"><?php _e('Description', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <textarea id="wl-company-description" rows="3" placeholder="<?php _e('Description de votre entreprise et de sa mission...', UNIVGA_TEXT_DOMAIN); ?>"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Palette de couleurs -->
                    <div class="univga-wl-card">
                        <h4>🎨 <?php _e('Palette de Couleurs', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-color-grid">
                            <div class="univga-color-item">
                                <label for="wl-primary-color"><?php _e('Couleur Principale', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-primary-color" value="#3b82f6">
                                    <input type="text" class="color-hex" value="#3b82f6">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-secondary-color"><?php _e('Couleur Secondaire', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-secondary-color" value="#10b981">
                                    <input type="text" class="color-hex" value="#10b981">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-accent-color"><?php _e('Couleur d\'Accent', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-accent-color" value="#f59e0b">
                                    <input type="text" class="color-hex" value="#f59e0b">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-background-color"><?php _e('Arrière-plan', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-background-color" value="#ffffff">
                                    <input type="text" class="color-hex" value="#ffffff">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-text-color"><?php _e('Couleur du Texte', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-text-color" value="#1f2937">
                                    <input type="text" class="color-hex" value="#1f2937">
                                </div>
                            </div>
                            <div class="univga-color-item">
                                <label for="wl-link-color"><?php _e('Couleur des Liens', UNIVGA_TEXT_DOMAIN); ?></label>
                                <div class="univga-color-input">
                                    <input type="color" id="wl-link-color" value="#3b82f6">
                                    <input type="text" class="color-hex" value="#3b82f6">
                                </div>
                            </div>
                        </div>

                        <!-- Thèmes prédéfinis -->
                        <div class="univga-preset-themes">
                            <h5><?php _e('Thèmes Prédéfinis', UNIVGA_TEXT_DOMAIN); ?></h5>
                            <div class="univga-themes-grid">
                                <div class="univga-theme-item active" data-theme="blue">
                                    <div class="theme-colors">
                                        <span style="background: #3b82f6"></span>
                                        <span style="background: #10b981"></span>
                                        <span style="background: #f59e0b"></span>
                                    </div>
                                    <span><?php _e('Bleu Pro', UNIVGA_TEXT_DOMAIN); ?></span>
                                </div>
                                <div class="univga-theme-item" data-theme="purple">
                                    <div class="theme-colors">
                                        <span style="background: #8b5cf6"></span>
                                        <span style="background: #06b6d4"></span>
                                        <span style="background: #f59e0b"></span>
                                    </div>
                                    <span><?php _e('Violet', UNIVGA_TEXT_DOMAIN); ?></span>
                                </div>
                                <div class="univga-theme-item" data-theme="green">
                                    <div class="theme-colors">
                                        <span style="background: #10b981"></span>
                                        <span style="background: #059669"></span>
                                        <span style="background: #f59e0b"></span>
                                    </div>
                                    <span><?php _e('Vert Nature', UNIVGA_TEXT_DOMAIN); ?></span>
                                </div>
                                <div class="univga-theme-item" data-theme="red">
                                    <div class="theme-colors">
                                        <span style="background: #ef4444"></span>
                                        <span style="background: #dc2626"></span>
                                        <span style="background: #f59e0b"></span>
                                    </div>
                                    <span><?php _e('Rouge Énergie', UNIVGA_TEXT_DOMAIN); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Typographie -->
                    <div class="univga-wl-card">
                        <h4>✏️ <?php _e('Typographie', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group">
                                <label for="wl-font-family"><?php _e('Police Principale', UNIVGA_TEXT_DOMAIN); ?></label>
                                <select id="wl-font-family">
                                    <option value="Inter"><?php _e('Inter (Recommandé)', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="Roboto">Roboto</option>
                                    <option value="Open Sans">Open Sans</option>
                                    <option value="Lato">Lato</option>
                                    <option value="Montserrat">Montserrat</option>
                                    <option value="Poppins">Poppins</option>
                                </select>
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-heading-font"><?php _e('Police des Titres', UNIVGA_TEXT_DOMAIN); ?></label>
                                <select id="wl-heading-font">
                                    <option value="Inter"><?php _e('Inter (Recommandé)', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="Roboto">Roboto</option>
                                    <option value="Open Sans">Open Sans</option>
                                    <option value="Lato">Lato</option>
                                    <option value="Montserrat">Montserrat</option>
                                    <option value="Poppins">Poppins</option>
                                </select>
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-font-size"><?php _e('Taille de Police de Base', UNIVGA_TEXT_DOMAIN); ?></label>
                                <select id="wl-font-size">
                                    <option value="14px"><?php _e('14px - Petit', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="16px" selected><?php _e('16px - Normal', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="18px"><?php _e('18px - Grand', UNIVGA_TEXT_DOMAIN); ?></option>
                                    <option value="20px"><?php _e('20px - Très Grand', UNIVGA_TEXT_DOMAIN); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Domaine -->
                <div class="univga-wl-section" id="wl-domain">
                    <div class="univga-wl-card">
                        <h4>🌐 <?php _e('Configuration de Domaine Personnalisé', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group">
                                <label for="wl-custom-domain"><?php _e('Domaine Personnalisé', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="text" id="wl-custom-domain" placeholder="<?php _e('votre-entreprise.com', UNIVGA_TEXT_DOMAIN); ?>">
                                <small><?php _e('Configurez votre propre domaine pour une expérience entièrement white-label', UNIVGA_TEXT_DOMAIN); ?></small>
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-subdomain"><?php _e('Sous-domaine', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="text" id="wl-subdomain" placeholder="formation">
                                <small><?php _e('Ex: formation.votre-entreprise.com', UNIVGA_TEXT_DOMAIN); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Email -->
                <div class="univga-wl-section" id="wl-email">
                    <div class="univga-wl-card">
                        <h4>📧 <?php _e('Configuration Email', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group">
                                <label for="wl-from-email"><?php _e('Adresse Email Expéditeur', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="email" id="wl-from-email" placeholder="<?php _e('no-reply@votre-entreprise.com', UNIVGA_TEXT_DOMAIN); ?>">
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-from-name"><?php _e('Nom Expéditeur', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="text" id="wl-from-name" placeholder="<?php _e('Votre Entreprise', UNIVGA_TEXT_DOMAIN); ?>">
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-reply-to"><?php _e('Email de Réponse', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="email" id="wl-reply-to" placeholder="<?php _e('support@votre-entreprise.com', UNIVGA_TEXT_DOMAIN); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Templates -->
                <div class="univga-wl-section" id="wl-templates">
                    <div class="univga-wl-card">
                        <h4>📄 <?php _e('Templates Email', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group univga-full-width">
                                <label for="wl-email-header"><?php _e('En-tête Email', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-email-header" rows="4" placeholder="<?php _e('Personnalisez l\'en-tête de vos emails...', UNIVGA_TEXT_DOMAIN); ?>"></textarea>
                            </div>
                            <div class="univga-form-group univga-full-width">
                                <label for="wl-email-footer"><?php _e('Pied de page Email', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-email-footer" rows="4" placeholder="<?php _e('Personnalisez le pied de page de vos emails...', UNIVGA_TEXT_DOMAIN); ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Avancé -->
                <div class="univga-wl-section" id="wl-advanced">
                    <div class="univga-wl-card">
                        <h4>⚙️ <?php _e('Paramètres Avancés', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-form-grid">
                            <div class="univga-form-group">
                                <label for="wl-css-custom"><?php _e('CSS Personnalisé', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-css-custom" rows="6" placeholder="/* <?php _e('Votre CSS personnalisé ici', UNIVGA_TEXT_DOMAIN); ?> */"></textarea>
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-js-custom"><?php _e('JavaScript Personnalisé', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-js-custom" rows="6" placeholder="// <?php _e('Votre JavaScript personnalisé ici', UNIVGA_TEXT_DOMAIN); ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aperçu en temps réel -->
                <div class="univga-wl-preview" id="wl-preview">
                    <h4><?php _e('Aperçu en Temps Réel', UNIVGA_TEXT_DOMAIN); ?></h4>
                    <div class="preview-container">
                        <div style="padding: 20px; text-align: center;">
                            <h3 style="color: #3b82f6;"><?php _e('Aperçu de votre marque', UNIVGA_TEXT_DOMAIN); ?></h3>
                            <p><?php _e('Ceci est un exemple de texte avec votre palette de couleurs.', UNIVGA_TEXT_DOMAIN); ?></p>
                            <p style="color: #10b981;"><?php _e('Texte secondaire avec accent', UNIVGA_TEXT_DOMAIN); ?></p>
                            <a href="#" style="color: #3b82f6;"><?php _e('Lien d\'exemple', UNIVGA_TEXT_DOMAIN); ?></a>
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="univga-wl-actions">
                    <button type="button" class="univga-btn univga-btn-secondary" id="wl-preview-btn">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                            <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                        </svg>
                        <?php _e('Aperçu', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="univga-btn univga-btn-primary" id="wl-save-btn">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9.5a1 1 0 0 0-1 1v7.293l2.646-2.647a.5.5 0 0 1 .708.708l-3.5 3.5a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L8.5 8.293V1A1 1 0 0 0 7.5 0h-5z"/>
                        </svg>
                        <?php _e('Sauvegarder Configuration', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Administration Tab -->
        <div class="univga-tab-content" id="tab-admin">
            <div class="univga-admin-nav">
                <button class="univga-admin-nav-btn active" data-admin-section="organization">
                    <?php _e('Organisation', UNIVGA_TEXT_DOMAIN); ?>
                </button>
                <button class="univga-admin-nav-btn" data-admin-section="teams">
                    <?php _e('Équipes', UNIVGA_TEXT_DOMAIN); ?>
                </button>
                <button class="univga-admin-nav-btn" data-admin-section="members">
                    <?php _e('Gestion des Membres', UNIVGA_TEXT_DOMAIN); ?>
                </button>
                <button class="univga-admin-nav-btn" data-admin-section="seat-pools">
                    <?php _e('Pools de Sièges', UNIVGA_TEXT_DOMAIN); ?>
                </button>
                <button class="univga-admin-nav-btn" data-admin-section="branding">
                    <?php _e('Marque Blanche', UNIVGA_TEXT_DOMAIN); ?>
                </button>
                <button class="univga-admin-nav-btn" data-admin-section="bulk-operations">
                    <?php _e('Opérations Bulk', UNIVGA_TEXT_DOMAIN); ?>
                </button>
                <button class="univga-admin-nav-btn" data-admin-section="reports">
                    <?php _e('Rapports', UNIVGA_TEXT_DOMAIN); ?>
                </button>
                <button class="univga-admin-nav-btn" data-admin-section="settings">
                    <?php _e('Paramètres', UNIVGA_TEXT_DOMAIN); ?>
                </button>
            </div>

            <!-- Organization Section -->
            <div class="univga-admin-section active" id="admin-organization">
                <div class="univga-admin-header">
                    <h3><?php _e('Détails de l\'Organisation', UNIVGA_TEXT_DOMAIN); ?></h3>
                    <button type="button" class="univga-btn univga-btn-primary" data-action="edit-organization">
                        <?php _e('Modifier l\'Organisation', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
                <div class="univga-org-details">
                    <div class="univga-detail-card">
                        <h4><?php _e('Informations de Base', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="univga-detail-row">
                            <span class="label"><?php _e('Nom:', UNIVGA_TEXT_DOMAIN); ?></span>
                            <span class="value" id="org-name"><?php echo esc_html($org->name); ?></span>
                        </div>
                        <div class="univga-detail-row">
                            <span class="label"><?php _e('ID Légal:', UNIVGA_TEXT_DOMAIN); ?></span>
                            <span class="value" id="org-legal-id"><?php echo esc_html($org->legal_id ?: __('Non défini', UNIVGA_TEXT_DOMAIN)); ?></span>
                        </div>
                        <div class="univga-detail-row">
                            <span class="label"><?php _e('Domaine Email:', UNIVGA_TEXT_DOMAIN); ?></span>
                            <span class="value" id="org-email-domain"><?php echo esc_html($org->email_domain ?: __('Non défini', UNIVGA_TEXT_DOMAIN)); ?></span>
                        </div>
                        <div class="univga-detail-row">
                            <span class="label"><?php _e('Statut:', UNIVGA_TEXT_DOMAIN); ?></span>
                            <span class="value">
                                <span class="univga-status <?php echo $org->status ? 'active' : 'inactive'; ?>">
                                    <?php echo $org->status ? __('Actif', UNIVGA_TEXT_DOMAIN) : __('Inactif', UNIVGA_TEXT_DOMAIN); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teams Section -->
            <div class="univga-admin-section" id="admin-teams">
                <div class="univga-admin-header">
                    <h3><?php _e('Gestion des Équipes', UNIVGA_TEXT_DOMAIN); ?></h3>
                    <button type="button" class="univga-btn univga-btn-primary" data-action="create-team">
                        <?php _e('Créer une Équipe', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
                <div id="admin-teams-content">
                    <div class="loading"><?php _e('Chargement des équipes...', UNIVGA_TEXT_DOMAIN); ?></div>
                </div>
            </div>

            <!-- Member Management Section -->
            <div class="univga-admin-section" id="admin-members">
                <div class="univga-admin-header">
                    <h3><?php _e('Administration des Membres', UNIVGA_TEXT_DOMAIN); ?></h3>
                    <div class="univga-admin-actions">
                        <button type="button" class="univga-btn univga-btn-secondary" data-action="bulk-import">
                            <?php _e('Bulk Import', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" class="univga-btn univga-btn-primary" data-action="invite-member">
                            <?php _e('Inviter un Membre', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>
                <div id="admin-members-content">
                    <div class="loading"><?php _e('Loading member management...', UNIVGA_TEXT_DOMAIN); ?></div>
                </div>
            </div>

            <!-- Seat Pools Section -->
            <div class="univga-admin-section" id="admin-seat-pools">
                <div class="univga-admin-header">
                    <h3><?php _e('Seat Pool Management', UNIVGA_TEXT_DOMAIN); ?></h3>
                    <button type="button" class="univga-btn univga-btn-primary" data-action="create-seat-pool">
                        <?php _e('Create Seat Pool', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
                <div id="admin-seat-pools-content">
                    <div class="loading"><?php _e('Loading seat pools...', UNIVGA_TEXT_DOMAIN); ?></div>
                </div>
            </div>

            <!-- White-Label Section -->
            <div class="univga-admin-section" id="admin-branding">
                <div class="univga-admin-header">
                    <h3><?php _e('Configuration White-Label', UNIVGA_TEXT_DOMAIN); ?></h3>
                    <div class="univga-whitelabel-toggle">
                        <label class="univga-toggle">
                            <input type="checkbox" id="whitelabel-enabled">
                            <span class="univga-toggle-slider"></span>
                        </label>
                        <span><?php _e('Activer White-Label', UNIVGA_TEXT_DOMAIN); ?></span>
                    </div>
                </div>

                <div class="univga-whitelabel-content" id="whitelabel-content">
                    <!-- White-Label Navigation -->
                    <div class="univga-whitelabel-nav">
                        <button class="univga-wl-nav-btn active" data-wl-section="identity">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M6.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/>
                            </svg>
                            <?php _e('Identité Visuelle', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="domain">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm7.5-6.923c-.67.204-1.335.82-1.887 1.855A7.97 7.97 0 0 0 5.145 4H7.5V1.077zM4.09 4a9.267 9.267 0 0 1 .64-1.539 6.7 6.7 0 0 1 .597-.933A7.025 7.025 0 0 0 2.255 4H4.09zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a6.958 6.958 0 0 0-.656 2.5h2.49zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5H4.847zM8.5 5v2.5h2.99a12.5 12.5 0 0 0-.337-2.5H8.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5H4.51zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5H8.5zM5.145 12c.138.386.295.744.468 1.068.552 1.035 1.218 1.65 1.887 1.855V12H5.145zm.182 2.472a6.696 6.696 0 0 1-.597-.933A9.268 9.268 0 0 1 4.09 12H2.255a7.024 7.024 0 0 0 3.072 2.472zM3.82 11a13.652 13.652 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5H3.82zm6.853 3.472A7.024 7.024 0 0 0 13.745 12H11.91a9.27 9.27 0 0 1-.64 1.539 6.688 6.688 0 0 1-.597.933zM8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855.173-.324.33-.682.468-1.068H8.5zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.65 13.65 0 0 1-.312 2.5zm2.802-3.5a6.959 6.959 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5h2.49zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7.024 7.024 0 0 0-3.072-2.472c.218.284.418.598.597.933zM10.855 4a7.966 7.966 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4h2.355z"/>
                            </svg>
                            <?php _e('Domaine & URL', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="email">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 14H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/>
                            </svg>
                            <?php _e('Configuration Email', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="templates">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v10a1 1 0 0 1-1 14H2a1 1 0 0 1-1-1V3Zm12-1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h12Z"/>
                            </svg>
                            <?php _e('Templates', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-wl-nav-btn" data-wl-section="advanced">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872l-.1-.34zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/>
                            </svg>
                            <?php _e('Avancé', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>

                    <!-- Identity Section -->
                    <div class="univga-wl-section active" id="wl-identity">
                        <div class="univga-wl-card">
                            <h4><?php _e('Informations de l\'Entreprise', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-grid">
                                <div class="univga-form-group">
                                    <label for="wl-company-name"><?php _e('Nom de l\'Entreprise', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-company-name" placeholder="<?php _e('Nom de votre entreprise', UNIVGA_TEXT_DOMAIN); ?>">
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-company-slogan"><?php _e('Slogan', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-company-slogan" placeholder="<?php _e('Votre slogan ou tagline', UNIVGA_TEXT_DOMAIN); ?>">
                                </div>
                                <div class="univga-form-group univga-full-width">
                                    <label for="wl-company-description"><?php _e('Description', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <textarea id="wl-company-description" rows="3" placeholder="<?php _e('Description de votre entreprise...', UNIVGA_TEXT_DOMAIN); ?>"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="univga-wl-card">
                            <h4><?php _e('Logos et Images', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-upload-grid">
                                <div class="univga-upload-item">
                                    <label><?php _e('Logo Principal', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-upload-zone" data-upload="logo">
                                        <div class="upload-preview" id="logo-preview-wl">
                                            <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                                                <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/>
                                            </svg>
                                        </div>
                                        <button type="button" class="univga-upload-btn"><?php _e('Choisir l\'image', UNIVGA_TEXT_DOMAIN); ?></button>
                                    </div>
                                </div>
                                <div class="univga-upload-item">
                                    <label><?php _e('Logo Version Claire', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-upload-zone" data-upload="logo-light">
                                        <div class="upload-preview" id="logo-light-preview-wl">
                                            <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                                                <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/>
                                            </svg>
                                        </div>
                                        <button type="button" class="univga-upload-btn"><?php _e('Choisir l\'image', UNIVGA_TEXT_DOMAIN); ?></button>
                                    </div>
                                </div>
                                <div class="univga-upload-item">
                                    <label><?php _e('Favicon', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-upload-zone" data-upload="favicon">
                                        <div class="upload-preview" id="favicon-preview-wl">
                                            <svg width="32" height="32" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                            </svg>
                                        </div>
                                        <button type="button" class="univga-upload-btn"><?php _e('Choisir l\'icône', UNIVGA_TEXT_DOMAIN); ?></button>
                                    </div>
                                </div>
                                <div class="univga-upload-item">
                                    <label><?php _e('Image de Couverture', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-upload-zone" data-upload="cover">
                                        <div class="upload-preview" id="cover-preview-wl">
                                            <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                                                <path d="M1.5 2A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13zm13 1a.5.5 0 0 1 .5.5v6l-3.775-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3.5a.5.5 0 0 1 .5-.5h13z"/>
                                            </svg>
                                        </div>
                                        <button type="button" class="univga-upload-btn"><?php _e('Choisir l\'image', UNIVGA_TEXT_DOMAIN); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="univga-wl-card">
                            <h4><?php _e('Palette de Couleurs', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-color-grid">
                                <div class="univga-color-item">
                                    <label for="wl-primary-color"><?php _e('Couleur Principale', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-color-input">
                                        <input type="color" id="wl-primary-color" value="#3b82f6">
                                        <input type="text" class="color-hex" value="#3b82f6">
                                    </div>
                                </div>
                                <div class="univga-color-item">
                                    <label for="wl-secondary-color"><?php _e('Couleur Secondaire', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-color-input">
                                        <input type="color" id="wl-secondary-color" value="#10b981">
                                        <input type="text" class="color-hex" value="#10b981">
                                    </div>
                                </div>
                                <div class="univga-color-item">
                                    <label for="wl-accent-color"><?php _e('Couleur d\'Accent', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-color-input">
                                        <input type="color" id="wl-accent-color" value="#f59e0b">
                                        <input type="text" class="color-hex" value="#f59e0b">
                                    </div>
                                </div>
                                <div class="univga-color-item">
                                    <label for="wl-background-color"><?php _e('Arrière-plan', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-color-input">
                                        <input type="color" id="wl-background-color" value="#ffffff">
                                        <input type="text" class="color-hex" value="#ffffff">
                                    </div>
                                </div>
                                <div class="univga-color-item">
                                    <label for="wl-text-color"><?php _e('Couleur du Texte', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-color-input">
                                        <input type="color" id="wl-text-color" value="#1f2937">
                                        <input type="text" class="color-hex" value="#1f2937">
                                    </div>
                                </div>
                                <div class="univga-color-item">
                                    <label for="wl-link-color"><?php _e('Couleur des Liens', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <div class="univga-color-input">
                                        <input type="color" id="wl-link-color" value="#3b82f6">
                                        <input type="text" class="color-hex" value="#3b82f6">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="univga-wl-card">
                            <h4><?php _e('Typographie', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-grid">
                                <div class="univga-form-group">
                                    <label for="wl-font-family"><?php _e('Police Principale', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <select id="wl-font-family">
                                        <option value="Inter">Inter (Recommandé)</option>
                                        <option value="Roboto">Roboto</option>
                                        <option value="Open Sans">Open Sans</option>
                                        <option value="Lato">Lato</option>
                                        <option value="Montserrat">Montserrat</option>
                                        <option value="Poppins">Poppins</option>
                                    </select>
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-heading-font"><?php _e('Police des Titres', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <select id="wl-heading-font">
                                        <option value="Inter">Inter (Recommandé)</option>
                                        <option value="Roboto">Roboto</option>
                                        <option value="Open Sans">Open Sans</option>
                                        <option value="Lato">Lato</option>
                                        <option value="Montserrat">Montserrat</option>
                                        <option value="Poppins">Poppins</option>
                                    </select>
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-font-size"><?php _e('Taille de Police de Base', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <select id="wl-font-size">
                                        <option value="14px">14px - Petit</option>
                                        <option value="16px" selected>16px - Normal</option>
                                        <option value="18px">18px - Grand</option>
                                        <option value="20px">20px - Très Grand</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Domain Section -->
                    <div class="univga-wl-section" id="wl-domain">
                        <div class="univga-wl-card">
                            <h4><?php _e('Configuration Domaine', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-grid">
                                <div class="univga-form-group">
                                    <label for="wl-custom-domain"><?php _e('Domaine Personnalisé', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-custom-domain" placeholder="learning.monentreprise.com">
                                    <small class="univga-form-help"><?php _e('Configurez votre propre domaine pour la plateforme d\'apprentissage', UNIVGA_TEXT_DOMAIN); ?></small>
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-subdomain"><?php _e('Sous-domaine Replit', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-subdomain" placeholder="monentreprise">
                                    <small class="univga-form-help"><?php _e('Votre URL sera: monentreprise.univga.com', UNIVGA_TEXT_DOMAIN); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="univga-wl-card">
                            <h4><?php _e('Configuration SSL', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-setting-item">
                                <div class="univga-setting-content">
                                    <h5><?php _e('Certificat SSL Automatique', UNIVGA_TEXT_DOMAIN); ?></h5>
                                    <p><?php _e('Activation automatique du HTTPS pour votre domaine personnalisé', UNIVGA_TEXT_DOMAIN); ?></p>
                                </div>
                                <div class="univga-setting-control">
                                    <label class="univga-toggle">
                                        <input type="checkbox" id="wl-ssl-auto" checked>
                                        <span class="univga-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Section -->
                    <div class="univga-wl-section" id="wl-email">
                        <div class="univga-wl-card">
                            <h4><?php _e('Configuration Email', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-grid">
                                <div class="univga-form-group">
                                    <label for="wl-from-email"><?php _e('Adresse Email d\'Expédition', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="email" id="wl-from-email" placeholder="formation@monentreprise.com">
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-from-name"><?php _e('Nom d\'Expéditeur', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-from-name" placeholder="Mon Entreprise - Formation">
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-reply-to"><?php _e('Adresse de Réponse', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="email" id="wl-reply-to" placeholder="support@monentreprise.com">
                                </div>
                            </div>
                        </div>

                        <div class="univga-wl-card">
                            <h4><?php _e('Signature Email', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-group">
                                <label for="wl-email-header"><?php _e('En-tête Email (HTML)', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-email-header" rows="4" placeholder="<div style='text-align: center;'><img src='logo.png' alt='Logo'></div>"></textarea>
                            </div>
                            <div class="univga-form-group">
                                <label for="wl-email-footer"><?php _e('Pied de Page Email (HTML)', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-email-footer" rows="4" placeholder="<p>Cordialement,<br>L'équipe Formation</p>"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Templates Section -->
                    <div class="univga-wl-section" id="wl-templates">
                        <div class="univga-wl-card">
                            <h4><?php _e('Templates de Pages', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-templates-grid">
                                <div class="univga-template-item">
                                    <div class="univga-template-preview">
                                        <div class="template-mockup">
                                            <div class="template-header"></div>
                                            <div class="template-content">
                                                <div class="template-text"></div>
                                                <div class="template-text"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <h5><?php _e('Page de Connexion', UNIVGA_TEXT_DOMAIN); ?></h5>
                                    <p><?php _e('Personnalisez l\'apparence de la page de connexion', UNIVGA_TEXT_DOMAIN); ?></p>
                                    <button class="univga-btn univga-btn-secondary"><?php _e('Personnaliser', UNIVGA_TEXT_DOMAIN); ?></button>
                                </div>
                                
                                <div class="univga-template-item">
                                    <div class="univga-template-preview">
                                        <div class="template-mockup">
                                            <div class="template-header"></div>
                                            <div class="template-sidebar"></div>
                                            <div class="template-main"></div>
                                        </div>
                                    </div>
                                    <h5><?php _e('Dashboard Utilisateur', UNIVGA_TEXT_DOMAIN); ?></h5>
                                    <p><?php _e('Interface principale des utilisateurs', UNIVGA_TEXT_DOMAIN); ?></p>
                                    <button class="univga-btn univga-btn-secondary"><?php _e('Personnaliser', UNIVGA_TEXT_DOMAIN); ?></button>
                                </div>
                                
                                <div class="univga-template-item">
                                    <div class="univga-template-preview">
                                        <div class="template-mockup">
                                            <div class="template-header"></div>
                                            <div class="template-grid">
                                                <div class="template-card"></div>
                                                <div class="template-card"></div>
                                                <div class="template-card"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <h5><?php _e('Catalogue de Cours', UNIVGA_TEXT_DOMAIN); ?></h5>
                                    <p><?php _e('Liste et présentation des cours disponibles', UNIVGA_TEXT_DOMAIN); ?></p>
                                    <button class="univga-btn univga-btn-secondary"><?php _e('Personnaliser', UNIVGA_TEXT_DOMAIN); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Section -->
                    <div class="univga-wl-section" id="wl-advanced">
                        <div class="univga-wl-card">
                            <h4><?php _e('CSS Personnalisé', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-group">
                                <label for="wl-css-custom"><?php _e('Styles CSS Additionnels', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-css-custom" rows="8" placeholder="/* Ajoutez votre CSS personnalisé ici */
.custom-button {
    background-color: #yourcolor;
    border-radius: 8px;
}"></textarea>
                                <small class="univga-form-help"><?php _e('Ajoutez du CSS pour personnaliser davantage l\'apparence', UNIVGA_TEXT_DOMAIN); ?></small>
                            </div>
                        </div>

                        <div class="univga-wl-card">
                            <h4><?php _e('JavaScript Personnalisé', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-group">
                                <label for="wl-js-custom"><?php _e('Code JavaScript Additionnel', UNIVGA_TEXT_DOMAIN); ?></label>
                                <textarea id="wl-js-custom" rows="8" placeholder="// Ajoutez votre JavaScript personnalisé ici
document.addEventListener('DOMContentLoaded', function() {
    // Votre code ici
});"></textarea>
                                <small class="univga-form-help"><?php _e('Ajoutez du JavaScript pour des fonctionnalités avancées', UNIVGA_TEXT_DOMAIN); ?></small>
                            </div>
                        </div>

                        <div class="univga-wl-card">
                            <h4><?php _e('Intégrations Avancées', UNIVGA_TEXT_DOMAIN); ?></h4>
                            <div class="univga-form-grid">
                                <div class="univga-form-group">
                                    <label for="wl-google-analytics"><?php _e('Google Analytics ID', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-google-analytics" placeholder="GA-XXXXXXXXX-X">
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-facebook-pixel"><?php _e('Facebook Pixel ID', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="wl-facebook-pixel" placeholder="123456789012345">
                                </div>
                                <div class="univga-form-group">
                                    <label for="wl-custom-scripts"><?php _e('Scripts de Tracking', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <textarea id="wl-custom-scripts" rows="4" placeholder="<script>// Scripts tiers</script>"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div class="univga-wl-preview" id="wl-preview">
                        <h4><?php _e('Aperçu en Temps Réel', UNIVGA_TEXT_DOMAIN); ?></h4>
                        <div class="preview-container">
                            <div class="loading">Génération de l'aperçu...</div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="univga-wl-actions">
                        <button type="button" class="univga-btn univga-btn-secondary" id="wl-preview-btn">
                            <?php _e('Aperçu', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button type="button" class="univga-btn univga-btn-primary" id="wl-save-btn">
                            <?php _e('Sauvegarder Configuration', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Settings Section -->
            <div class="univga-admin-section" id="admin-settings">
                <div class="univga-admin-header">
                    <h3><?php _e('Organization Settings', UNIVGA_TEXT_DOMAIN); ?></h3>
                </div>
                <div id="admin-settings-content">
                    <div class="loading"><?php _e('Loading settings...', UNIVGA_TEXT_DOMAIN); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invite Member Modal -->
<div class="univga-modal" id="invite-modal">
    <div class="univga-modal-dialog">
        <div class="univga-modal-header">
            <h3>Inviter un nouveau membre</h3>
            <button type="button" class="univga-modal-close" data-dismiss="modal">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z"/>
                </svg>
            </button>
        </div>
        <div class="univga-modal-body">
            <form id="invite-form">
                <div class="univga-form-group">
                    <label for="invite-email">Adresse e-mail</label>
                    <input type="email" id="invite-email" name="email" required>
                    <?php if ($org->email_domain): ?>
                    <small class="univga-form-help"><?php printf(__('Must be from domain: %s', UNIVGA_TEXT_DOMAIN), $org->email_domain); ?></small>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($teams)): ?>
                <div class="univga-form-group">
                    <label for="invite-team">Équipe (Optionnel)</label>
                    <select id="invite-team" name="team_id">
                        <option value="">Aucune équipe spécifique</option>
                        <?php foreach ($teams as $team): ?>
                        <option value="<?php echo $team->id; ?>"><?php echo esc_html($team->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="univga-form-actions">
                    <button type="submit" class="univga-btn univga-btn-primary">
                        Envoyer l'invitation
                    </button>
                    <button type="button" class="univga-btn univga-btn-secondary" data-dismiss="modal">
                        <?php _e('Cancel', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Organization Edit Modal -->
<div class="univga-modal" id="edit-organization-modal">
    <div class="univga-modal-dialog">
        <div class="univga-modal-header">
            <h3><?php _e('Edit Organization', UNIVGA_TEXT_DOMAIN); ?></h3>
            <button type="button" class="univga-modal-close" data-dismiss="modal">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z"/>
                </svg>
            </button>
        </div>
        <div class="univga-modal-body">
            <form id="edit-organization-form">
                <div class="univga-form-group">
                    <label for="edit-org-name"><?php _e('Organization Name', UNIVGA_TEXT_DOMAIN); ?></label>
                    <input type="text" id="edit-org-name" name="name" required>
                </div>
                <div class="univga-form-group">
                    <label for="edit-org-legal-id"><?php _e('Legal ID', UNIVGA_TEXT_DOMAIN); ?></label>
                    <input type="text" id="edit-org-legal-id" name="legal_id">
                </div>
                <div class="univga-form-group">
                    <label for="edit-org-email-domain"><?php _e('Email Domain', UNIVGA_TEXT_DOMAIN); ?></label>
                    <input type="text" id="edit-org-email-domain" name="email_domain" placeholder="company.com">
                </div>
                <div class="univga-form-group">
                    <label for="edit-org-status"><?php _e('Status', UNIVGA_TEXT_DOMAIN); ?></label>
                    <select id="edit-org-status" name="status">
                        <option value="1"><?php _e('Active', UNIVGA_TEXT_DOMAIN); ?></option>
                        <option value="0"><?php _e('Inactive', UNIVGA_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
                <div class="univga-form-actions">
                    <button type="submit" class="univga-btn univga-btn-primary">
                        <?php _e('Save Changes', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="univga-btn univga-btn-secondary" data-dismiss="modal">
                        <?php _e('Cancel', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div class="univga-modal" id="new-message-modal">
    <div class="univga-modal-dialog univga-modal-lg">
        <div class="univga-modal-header">
            <h3><?php _e('New Message', UNIVGA_TEXT_DOMAIN); ?></h3>
            <button type="button" class="univga-modal-close" data-dismiss="modal">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z"/>
                </svg>
            </button>
        </div>
        <div class="univga-modal-body">
            <form id="new-message-form">
                <div class="univga-form-group">
                    <label for="message-subject"><?php _e('Subject', UNIVGA_TEXT_DOMAIN); ?></label>
                    <input type="text" id="message-subject" name="subject" required>
                </div>
                
                <div class="univga-form-group">
                    <label for="message-recipients"><?php _e('Recipients', UNIVGA_TEXT_DOMAIN); ?></label>
                    <div class="univga-recipients-selector">
                        <div class="univga-recipient-type-tabs">
                            <button type="button" class="univga-tab-btn active" data-recipient-type="members">
                                <?php _e('Individual Members', UNIVGA_TEXT_DOMAIN); ?>
                            </button>
                            <button type="button" class="univga-tab-btn" data-recipient-type="teams">
                                <?php _e('Entire Teams', UNIVGA_TEXT_DOMAIN); ?>
                            </button>
                            <button type="button" class="univga-tab-btn" data-recipient-type="all">
                                <?php _e('All Members', UNIVGA_TEXT_DOMAIN); ?>
                            </button>
                        </div>
                        
                        <div class="univga-recipient-content">
                            <div class="univga-recipient-tab active" id="members-tab">
                                <div class="univga-search-box">
                                    <input type="text" id="member-search-message" placeholder="<?php _e('Search members...', UNIVGA_TEXT_DOMAIN); ?>">
                                </div>
                                <div class="univga-member-list" id="member-list-message">
                                    <div class="loading"><?php _e('Loading members...', UNIVGA_TEXT_DOMAIN); ?></div>
                                </div>
                            </div>
                            
                            <div class="univga-recipient-tab" id="teams-tab">
                                <div class="univga-team-list" id="team-list-message">
                                    <?php if (!empty($teams)): ?>
                                        <?php foreach ($teams as $team): ?>
                                        <div class="univga-recipient-item">
                                            <input type="checkbox" name="team_recipients[]" value="<?php echo $team->id; ?>" id="team-<?php echo $team->id; ?>">
                                            <label for="team-<?php echo $team->id; ?>">
                                                <strong><?php echo esc_html($team->name); ?></strong>
                                                <span class="univga-member-count"><?php printf(__('%d members', UNIVGA_TEXT_DOMAIN), $team->member_count ?? 0); ?></span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="univga-no-data"><?php _e('No teams found', UNIVGA_TEXT_DOMAIN); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="univga-recipient-tab" id="all-tab">
                                <div class="univga-all-members-info">
                                    <div class="univga-info-icon">
                                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                        </svg>
                                    </div>
                                    <h4><?php _e('Broadcast Message', UNIVGA_TEXT_DOMAIN); ?></h4>
                                    <p><?php _e('This message will be sent to all active members in your organization.', UNIVGA_TEXT_DOMAIN); ?></p>
                                    <div class="univga-member-stats">
                                        <span class="univga-stat">
                                            <strong><?php echo $kpis['members']['total']; ?></strong> <?php _e('members', UNIVGA_TEXT_DOMAIN); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="univga-form-group">
                    <label for="message-type"><?php _e('Message Type', UNIVGA_TEXT_DOMAIN); ?></label>
                    <select id="message-type" name="message_type">
                        <option value="text"><?php _e('Regular Message', UNIVGA_TEXT_DOMAIN); ?></option>
                        <option value="announcement"><?php _e('Announcement', UNIVGA_TEXT_DOMAIN); ?></option>
                    </select>
                </div>

                <div class="univga-form-group">
                    <div class="univga-checkbox-wrapper">
                        <input type="checkbox" id="message-priority" name="is_priority">
                        <label for="message-priority">
                            <?php _e('Mark as urgent (sends email notification)', UNIVGA_TEXT_DOMAIN); ?>
                        </label>
                    </div>
                </div>
                
                <div class="univga-form-group">
                    <label for="message-content"><?php _e('Message', UNIVGA_TEXT_DOMAIN); ?></label>
                    <textarea id="message-content" name="message" rows="6" required placeholder="<?php _e('Type your message here...', UNIVGA_TEXT_DOMAIN); ?>"></textarea>
                </div>
                
                <div class="univga-form-actions">
                    <button type="submit" class="univga-btn univga-btn-primary">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M15.854.146a.5.5 0 0 1 .11.54L13.026 8.74a.5.5 0 0 1-.428.26H8.5L7 10.5V8.75a.5.5 0 0 0-.5-.5H4a.5.5 0 0 0-.5.5v2a.5.5 0 0 0 .5.5h2.75l1.5 1.5a.5.5 0 0 0 .854-.354L8.5 10.25h4.098l2.928-7.854a.5.5 0 0 1 .54-.11z"/>
                        </svg>
                        <?php _e('Send Message', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                    <button type="button" class="univga-btn univga-btn-secondary" data-dismiss="modal">
                        <?php _e('Cancel', UNIVGA_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Loading overlay -->
<div class="univga-loading-overlay" id="loading-overlay">
    <div class="univga-loading-spinner"></div>
</div>


<script>
jQuery(document).ready(function($) {
    // Fix courses loading on tab switch
    if (typeof UnivgaDashboard !== 'undefined') {
        const originalLoadCourses = UnivgaDashboard.loadCourses;
        UnivgaDashboard.loadCourses = function() {
            const self = this;
            .html('<div class="loading">Chargement des cours...</div>');
            
            $.ajax({
                url: univga_dashboard.rest_url + 'organizations/' + this.orgId + '/courses',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(courses) {
                self.renderCourses(courses);
            })
            .fail(function(xhr) {
                console.error('Failed to load courses:', xhr);
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Échec du chargement des cours';
                .html('<div class="univga-notice univga-notice-error">' + errorMsg + '</div>');
            });
        };
        
        UnivgaDashboard.renderCourses = function(courses) {
            const $grid = ;
            
            if (!courses || courses.length === 0) {
                $grid.html('<div class="univga-notice univga-notice-info">Aucun cours disponible pour cette organisation.</div>');
                return;
            }
            
            let html = '<div class="univga-courses-grid">';
            courses.forEach(function(course) {
                const thumbnail = course.thumbnail || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjZjNmNGY2Ii8+CjxwYXRoIGQ9Ik0xMjAgODBIMTgwVjEyMEgxMjBWODBaIiBmaWxsPSIjYzlkM2Y5Ii8+Cjwvc3ZnPg==';
                const progressColor = course.avg_progress >= 80 ? 'success' : course.avg_progress >= 50 ? 'warning' : 'danger';
                
                html += '<div class="univga-course-card">';
                html += '<div class="univga-course-header">';
                html += '<img src="' + thumbnail + '" alt="' + course.title + '" class="univga-course-thumbnail" loading="lazy">';
                html += '<div class="univga-course-overlay">';
                html += '<a href="' + course.permalink + '" target="_blank" class="univga-btn univga-btn-primary univga-btn-sm">Voir le cours</a>';
                html += '</div>';
                html += '</div>';
                html += '<div class="univga-course-content">';
                html += '<h4 class="univga-course-title"><a href="' + course.permalink + '" target="_blank">' + course.title + '</a></h4>';
                html += '<p class="univga-course-excerpt">' + (course.excerpt || 'Aucune description disponible') + '</p>';
                html += '<div class="univga-course-stats">';
                html += '<div class="univga-stat"><span class="univga-stat-label">Pool:</span> <span class="univga-stat-value">' + course.pool_name + '</span></div>';
                html += '<div class="univga-stat"><span class="univga-stat-label">Inscrits:</span> <span class="univga-stat-value">' + course.enrolled_count + '</span></div>';
                html += '<div class="univga-stat"><span class="univga-stat-label">Terminés:</span> <span class="univga-stat-value">' + course.completed_count + '</span></div>';
                html += '<div class="univga-stat"><span class="univga-stat-label">Taux:</span> <span class="univga-stat-value">' + course.completion_rate + '%</span></div>';
                html += '</div>';
                html += '<div class="univga-progress-section">';
                html += '<div class="univga-progress-header">';
                html += '<span class="univga-progress-label">Progrès moyen</span>';
                html += '<span class="univga-progress-value">' + course.avg_progress + '%</span>';
                html += '</div>';
                html += '<div class="univga-progress">';
                html += '<div class="univga-progress-bar univga-progress-' + progressColor + '" style="width: ' + course.avg_progress + '%"></div>';
                html += '</div>';
                html += '</div>';
                html += '<div class="univga-course-actions">';
                html += '<div class="univga-seats-info">';
                html += '<span class="univga-seats-available">' + course.seats_available + '</span>';
                html += '<span class="univga-seats-total">/' + course.seats_total + ' sièges</span>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
            
            $grid.html(html);
        };
    }
});
</script>

