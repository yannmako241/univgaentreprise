<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="univga-join-page">
    <div class="univga-join-container">
        <div class="univga-join-header">
            <h1><?php _e('Join Organization', UNIVGA_TEXT_DOMAIN); ?></h1>
            <div class="univga-org-info">
                <h2><?php echo esc_html($org->name); ?></h2>
                <?php if ($team): ?>
                <p><?php printf(__('Team: %s', UNIVGA_TEXT_DOMAIN), esc_html($team->name)); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_logged_in): ?>
            <?php if ($email_match): ?>
                <!-- User is logged in with matching email -->
                <div class="univga-join-content">
                    <div class="univga-notice univga-notice-info">
                        <p><?php printf(__('Welcome %s! Click below to join the organization.', UNIVGA_TEXT_DOMAIN), $current_user->display_name); ?></p>
                    </div>
                    
                    <form id="join-form" method="post">
                        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                        <input type="hidden" name="action_type" value="existing_user">
                        <?php wp_nonce_field('univga_join_nonce', 'nonce'); ?>
                        
                        <div class="univga-form-actions">
                            <button type="submit" class="univga-btn univga-btn-primary univga-btn-large">
                                <?php _e('Join Organization', UNIVGA_TEXT_DOMAIN); ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- User is logged in but email doesn't match -->
                <div class="univga-join-content">
                    <div class="univga-notice univga-notice-error">
                        <p><?php _e('The email address for your current account does not match the invitation.', UNIVGA_TEXT_DOMAIN); ?></p>
                        <p><?php printf(__('Invitation sent to: %s', UNIVGA_TEXT_DOMAIN), esc_html($token_data['email'])); ?></p>
                        <p><?php printf(__('Your account email: %s', UNIVGA_TEXT_DOMAIN), esc_html($current_user->user_email)); ?></p>
                    </div>
                    
                    <div class="univga-form-actions">
                        <a href="<?php echo wp_logout_url(add_query_arg(array('univga_action' => 'join', 'token' => $token), home_url())); ?>" class="univga-btn univga-btn-secondary">
                            <?php _e('Logout and Try Again', UNIVGA_TEXT_DOMAIN); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- User is not logged in - show login/register options -->
            <div class="univga-join-content">
                <div class="univga-join-tabs">
                    <div class="univga-tab-nav">
                        <button class="univga-tab-btn active" data-tab="register">
                            <?php _e('Create Account', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                        <button class="univga-tab-btn" data-tab="login">
                            <?php _e('Sign In', UNIVGA_TEXT_DOMAIN); ?>
                        </button>
                    </div>

                    <!-- Register Tab -->
                    <div class="univga-tab-content active" id="tab-register">
                        <form id="register-form" method="post">
                            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                            <input type="hidden" name="action_type" value="register">
                            <?php wp_nonce_field('univga_join_nonce', 'nonce'); ?>
                            
                            <div class="univga-form-group">
                                <label for="reg-email"><?php _e('Email Address', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="email" id="reg-email" name="email" value="<?php echo esc_attr($token_data['email']); ?>" readonly>
                            </div>
                            
                            <div class="univga-form-row">
                                <div class="univga-form-group">
                                    <label for="reg-first-name"><?php _e('First Name', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="reg-first-name" name="first_name" required>
                                </div>
                                <div class="univga-form-group">
                                    <label for="reg-last-name"><?php _e('Last Name', UNIVGA_TEXT_DOMAIN); ?></label>
                                    <input type="text" id="reg-last-name" name="last_name" required>
                                </div>
                            </div>
                            
                            <div class="univga-form-group">
                                <label for="reg-username"><?php _e('Username', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="text" id="reg-username" name="username" required>
                            </div>
                            
                            <div class="univga-form-group">
                                <label for="reg-password"><?php _e('Password', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="password" id="reg-password" name="password" required minlength="8">
                                <small class="univga-form-help"><?php _e('Minimum 8 characters', UNIVGA_TEXT_DOMAIN); ?></small>
                            </div>
                            
                            <div class="univga-form-actions">
                                <button type="submit" class="univga-btn univga-btn-primary univga-btn-large">
                                    <?php _e('Create Account & Join', UNIVGA_TEXT_DOMAIN); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Login Tab -->
                    <div class="univga-tab-content" id="tab-login">
                        <form id="login-form" method="post">
                            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                            <input type="hidden" name="action_type" value="login">
                            <?php wp_nonce_field('univga_join_nonce', 'nonce'); ?>
                            
                            <div class="univga-notice univga-notice-info">
                                <p><?php printf(__('Sign in with your existing account. Your email must be: %s', UNIVGA_TEXT_DOMAIN), esc_html($token_data['email'])); ?></p>
                            </div>
                            
                            <div class="univga-form-group">
                                <label for="login-username"><?php _e('Username or Email', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="text" id="login-username" name="login_username" required>
                            </div>
                            
                            <div class="univga-form-group">
                                <label for="login-password"><?php _e('Password', UNIVGA_TEXT_DOMAIN); ?></label>
                                <input type="password" id="login-password" name="login_password" required>
                            </div>
                            
                            <div class="univga-form-actions">
                                <button type="submit" class="univga-btn univga-btn-primary univga-btn-large">
                                    <?php _e('Sign In & Join', UNIVGA_TEXT_DOMAIN); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.univga-join-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.univga-join-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    max-width: 500px;
    width: 100%;
    overflow: hidden;
}

.univga-join-header {
    background: #f8f9fa;
    padding: 30px;
    text-align: center;
    border-bottom: 1px solid #dee2e6;
}

.univga-join-header h1 {
    margin: 0 0 15px;
    color: #495057;
    font-size: 24px;
}

.univga-org-info h2 {
    margin: 0 0 5px;
    color: #007cba;
    font-size: 20px;
}

.univga-org-info p {
    margin: 0;
    color: #6c757d;
}

.univga-join-content {
    padding: 30px;
}

.univga-join-tabs .univga-tab-nav {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
}

.univga-join-tabs .univga-tab-btn {
    flex: 1;
    background: none;
    border: none;
    padding: 12px;
    cursor: pointer;
    color: #6c757d;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
}

.univga-join-tabs .univga-tab-btn.active {
    color: #007cba;
    border-bottom-color: #007cba;
}

.univga-join-tabs .univga-tab-content {
    display: none;
}

.univga-join-tabs .univga-tab-content.active {
    display: block;
}

.univga-form-group {
    margin-bottom: 20px;
}

.univga-form-row {
    display: flex;
    gap: 15px;
}

.univga-form-row .univga-form-group {
    flex: 1;
}

.univga-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.univga-form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ced4da;
    border-radius: 5px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.univga-form-group input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0,124,186,0.25);
}

.univga-form-help {
    color: #6c757d;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.univga-form-actions {
    margin-top: 30px;
    text-align: center;
}

.univga-btn {
    display: inline-block;
    padding: 12px 24px;
    border: none;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.univga-btn-large {
    padding: 15px 30px;
    font-size: 16px;
    width: 100%;
}

.univga-btn-primary {
    background: #007cba;
    color: white;
}

.univga-btn-primary:hover {
    background: #005a87;
}

.univga-btn-secondary {
    background: #6c757d;
    color: white;
}

.univga-btn-secondary:hover {
    background: #545b62;
}

.univga-notice {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.univga-notice-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.univga-notice-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.univga-notice p {
    margin: 0 0 5px;
}

.univga-notice p:last-child {
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .univga-join-page {
        padding: 10px;
    }
    
    .univga-join-header, .univga-join-content {
        padding: 20px;
    }
    
    .univga-form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.univga-tab-btn').click(function() {
        var tab = $(this).data('tab');
        
        $('.univga-tab-btn').removeClass('active');
        $('.univga-tab-content').removeClass('active');
        
        $(this).addClass('active');
        $('#tab-' + tab).addClass('active');
    });
    
    // Form submission
    $('form').submit(function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('<?php _e('Processing...', UNIVGA_TEXT_DOMAIN); ?>');
        
        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'univga_process_join',
            ...Object.fromEntries(new FormData(this))
        }).done(function(response) {
            if (response.success) {
                window.location.href = response.data.redirect_url;
            } else {
                alert(response.data || '<?php _e('An error occurred. Please try again.', UNIVGA_TEXT_DOMAIN); ?>');
                $button.prop('disabled', false).text(originalText);
            }
        }).fail(function() {
            alert('<?php _e('Connection error. Please try again.', UNIVGA_TEXT_DOMAIN); ?>');
            $button.prop('disabled', false).text(originalText);
        });
    });
});
</script>

<?php
get_footer();
?>

