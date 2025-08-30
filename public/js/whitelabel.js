/**
 * White-Label JavaScript for UNIVGA Dashboard
 */
jQuery(document).ready(function($) {
    // Extend UnivgaDashboard with white-label functionality
    if (typeof UnivgaDashboard !== 'undefined') {
        
        // White-label data storage
        UnivgaDashboard.whitelabelData = null;
        UnivgaDashboard.currentWLSection = 'identity';
        UnivgaDashboard.previewMode = false;
        
        /**
         * Load white-label configuration
         */
        UnivgaDashboard.loadWhiteLabel = function() {
            const self = this;
            
            // Show loading state
            this.showWhiteLabelLoading();
            
            const url = univga_dashboard.rest_url + 'organizations/' + this.orgId + '/whitelabel';
            
            $.ajax({
                url: url,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(data) {
                self.whitelabelData = data;
                self.renderWhiteLabel(data);
                self.hideWhiteLabelLoading();
                self.initWhiteLabelEvents();
            })
            .fail(function(xhr) {
                console.error('Failed to load white-label config:', xhr);
                self.hideWhiteLabelLoading();
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? 
                    xhr.responseJSON.message : 'Échec du chargement de la configuration white-label';
                self.showNotice('error', errorMsg);
            });
        };
        
        /**
         * Initialize white-label events
         */
        UnivgaDashboard.initWhiteLabelEvents = function() {
            const self = this;
            
            // Navigation buttons
            $(document).on('click', '.univga-wl-nav-btn', function() {
                $('.univga-wl-nav-btn').removeClass('active');
                $(this).addClass('active');
                
                const section = $(this).data('wl-section');
                self.showWhiteLabelSection(section);
            });
            
            // Theme preset selection
            $(document).on('click', '.univga-theme-item', function() {
                $('.univga-theme-item').removeClass('active');
                $(this).addClass('active');
                
                const theme = $(this).data('theme');
                self.applyThemePreset(theme);
            });
            
            // Color picker changes
            $(document).on('change', '.univga-color-input input[type="color"]', function() {
                const hex = $(this).val();
                $(this).siblings('.color-hex').val(hex);
                self.updatePreview();
            });
            
            // Color hex input changes
            $(document).on('input', '.color-hex', function() {
                const hex = $(this).val();
                if (/^#[0-9A-F]{6}$/i.test(hex)) {
                    $(this).siblings('input[type="color"]').val(hex);
                    self.updatePreview();
                }
            });
            
            // File uploads
            $(document).on('change', 'input[type="file"]', function() {
                const file = this.files[0];
                const previewId = $(this).attr('id').replace('-upload', '-preview');
                self.handleFileUpload(file, previewId);
            });
            
            // Save button
            $(document).on('click', '#wl-save-btn', function() {
                self.saveWhiteLabelConfig();
            });
            
            // Preview button
            $(document).on('click', '#wl-preview-btn', function() {
                self.togglePreviewMode();
            });
        };
        
        /**
         * Show specific white-label section
         */
        UnivgaDashboard.showWhiteLabelSection = function(section) {
            $('.univga-wl-section').removeClass('active');
            $('#wl-' + section).addClass('active');
            this.currentWLSection = section;
            
            // Load section content if needed
            this.loadWhiteLabelSection(section);
        };
        
        /**
         * Apply theme preset
         */
        UnivgaDashboard.applyThemePreset = function(theme) {
            const themes = {
                blue: {
                    primary: '#3b82f6',
                    secondary: '#10b981',
                    accent: '#f59e0b',
                    background: '#ffffff',
                    text: '#1f2937',
                    link: '#3b82f6'
                },
                purple: {
                    primary: '#8b5cf6',
                    secondary: '#06b6d4',
                    accent: '#f59e0b',
                    background: '#ffffff',
                    text: '#1f2937',
                    link: '#8b5cf6'
                },
                green: {
                    primary: '#10b981',
                    secondary: '#059669',
                    accent: '#f59e0b',
                    background: '#ffffff',
                    text: '#1f2937',
                    link: '#10b981'
                },
                red: {
                    primary: '#ef4444',
                    secondary: '#dc2626',
                    accent: '#f59e0b',
                    background: '#ffffff',
                    text: '#1f2937',
                    link: '#ef4444'
                }
            };
            
            const selectedTheme = themes[theme];
            if (selectedTheme) {
                $('#wl-primary-color').val(selectedTheme.primary).siblings('.color-hex').val(selectedTheme.primary);
                $('#wl-secondary-color').val(selectedTheme.secondary).siblings('.color-hex').val(selectedTheme.secondary);
                $('#wl-accent-color').val(selectedTheme.accent).siblings('.color-hex').val(selectedTheme.accent);
                $('#wl-background-color').val(selectedTheme.background).siblings('.color-hex').val(selectedTheme.background);
                $('#wl-text-color').val(selectedTheme.text).siblings('.color-hex').val(selectedTheme.text);
                $('#wl-link-color').val(selectedTheme.link).siblings('.color-hex').val(selectedTheme.link);
                
                this.updatePreview();
            }
        };
        
        /**
         * Handle file upload
         */
        UnivgaDashboard.handleFileUpload = function(file, previewId) {
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = $('#' + previewId);
                preview.html('<img src="' + e.target.result + '" alt="Preview">');
                preview.addClass('has-image');
            };
            reader.readAsDataURL(file);
        };
        
        /**
         * Update preview
         */
        UnivgaDashboard.updatePreview = function() {
            const colors = {
                primary: $('#wl-primary-color').val(),
                secondary: $('#wl-secondary-color').val(),
                accent: $('#wl-accent-color').val(),
                background: $('#wl-background-color').val(),
                text: $('#wl-text-color').val(),
                link: $('#wl-link-color').val()
            };
            
            // Apply live preview styles
            const previewStyle = `
                .preview-container {
                    background: ${colors.background};
                    color: ${colors.text};
                }
                .preview-container .primary {
                    color: ${colors.primary};
                }
                .preview-container .secondary {
                    color: ${colors.secondary};
                }
                .preview-container .accent {
                    color: ${colors.accent};
                }
                .preview-container a {
                    color: ${colors.link};
                }
            `;
            
            // Update preview container
            $('#wl-preview .preview-container').html(`
                <div style="padding: 20px; text-align: center;">
                    <h3 class="primary">Aperçu de votre marque</h3>
                    <p>Ceci est un exemple de texte avec votre palette de couleurs.</p>
                    <p class="secondary">Texte secondaire avec accent</p>
                    <a href="#" class="accent">Lien d'exemple</a>
                </div>
            `);
            
            // Add dynamic styles
            if (!$('#wl-preview-styles').length) {
                $('<style id="wl-preview-styles"></style>').appendTo('head');
            }
            $('#wl-preview-styles').text(previewStyle);
        };
        
        /**
         * Show white-label loading state
         */
        UnivgaDashboard.showWhiteLabelLoading = function() {
            $('#whitelabel-content').html('<div class="loading">Chargement de la configuration white-label...</div>');
        };
        
        /**
         * Hide white-label loading state
         */
        UnivgaDashboard.hideWhiteLabelLoading = function() {
            // Loading handled by render functions
        };
        
        /**
         * Render white-label configuration
         */
        UnivgaDashboard.renderWhiteLabel = function(data) {
            const settings = data.settings;
            
            // Update toggle state
            $('#whitelabel-enabled').prop('checked', settings.enabled);
            $('#whitelabel-content').toggle(settings.enabled);
            
            // Populate identity section
            this.populateIdentitySection(settings);
            
            // Render domain section
            this.renderDomainSection(data);
            
            // Render email section
            this.renderEmailSection(data);
            
            // Render templates section
            this.renderTemplatesSection(data);
            
            // Render advanced section
            this.renderAdvancedSection(data);
            
            // Initialize color pickers
            this.initializeColorPickers();
            
            // Initialize file uploads
            this.initializeFileUploads();
            
            // Update preview
            this.updateLivePreview();
        };
        
        /**
         * Populate identity section with current settings
         */
        UnivgaDashboard.populateIdentitySection = function(settings) {
            $('#wl-company-name').val(settings.company_name || '');
            $('#wl-company-slogan').val(settings.company_slogan || '');
            $('#wl-company-description').val(settings.company_description || '');
            
            // Set colors
            $('#wl-primary-color').val(settings.primary_color || '#3b82f6');
            $('#wl-secondary-color').val(settings.secondary_color || '#10b981');
            $('#wl-accent-color').val(settings.accent_color || '#f59e0b');
            $('#wl-background-color').val(settings.background_color || '#ffffff');
            $('#wl-text-color').val(settings.text_color || '#1f2937');
            $('#wl-link-color').val(settings.link_color || '#3b82f6');
            
            // Set fonts
            $('#wl-font-family').val(settings.font_family || 'Inter');
            $('#wl-heading-font').val(settings.heading_font || 'Inter');
            $('#wl-font-size').val(settings.font_size_base || '16px');
            
            // Update color hex inputs
            this.updateColorHexInputs();
            
            // Set uploaded images
            if (settings.logo_url) {
                $('#logo-preview-wl').html(`<img src="${settings.logo_url}" alt="Logo">`);
            }
            if (settings.logo_light_url) {
                $('#logo-light-preview-wl').html(`<img src="${settings.logo_light_url}" alt="Logo clair">`);
            }
            if (settings.favicon_url) {
                $('#favicon-preview-wl').html(`<img src="${settings.favicon_url}" alt="Favicon">`);
            }
            if (settings.cover_image_url) {
                $('#cover-preview-wl').html(`<img src="${settings.cover_image_url}" alt="Couverture">`);
            }
        };
        
        /**
         * Render domain configuration section
         */
        UnivgaDashboard.renderDomainSection = function(data) {
            const settings = data.settings;
            const validation = data.domain_validation;
            
            let html = `
                <div class="univga-wl-card">
                    <h4>Configuration de Domaine Personnalisé</h4>
                    <div class="univga-domain-options">
                        <div class="univga-domain-option">
                            <div class="univga-option-header">
                                <input type="radio" id="domain-option-subdomain" name="domain-option" value="subdomain" ${settings.subdomain ? 'checked' : ''}>
                                <label for="domain-option-subdomain">
                                    <strong>Sous-domaine gratuit</strong>
                                    <span class="option-description">Utilisez un sous-domaine gratuit (ex: votre-entreprise.univga.app)</span>
                                </label>
                            </div>
                            <div class="univga-option-content" id="subdomain-config">
                                <div class="univga-subdomain-input">
                                    <input type="text" id="wl-subdomain" placeholder="votre-entreprise" value="${settings.subdomain || ''}">
                                    <span class="subdomain-suffix">.univga.app</span>
                                </div>
                                <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.checkSubdomainAvailability()">
                                    Vérifier Disponibilité
                                </button>
                                <div class="availability-result" id="subdomain-availability"></div>
                            </div>
                        </div>
                        
                        <div class="univga-domain-option">
                            <div class="univga-option-header">
                                <input type="radio" id="domain-option-custom" name="domain-option" value="custom" ${settings.custom_domain ? 'checked' : ''}>
                                <label for="domain-option-custom">
                                    <strong>Domaine personnalisé</strong>
                                    <span class="option-description">Utilisez votre propre domaine (ex: formation.votre-entreprise.com)</span>
                                </label>
                            </div>
                            <div class="univga-option-content" id="custom-domain-config">
                                <div class="univga-form-group">
                                    <label for="wl-custom-domain">Nom de Domaine</label>
                                    <input type="text" id="wl-custom-domain" placeholder="formation.votre-entreprise.com" value="${settings.custom_domain || ''}">
                                </div>
                                <div class="univga-form-group">
                                    <label>
                                        <input type="checkbox" id="wl-ssl-enabled" ${settings.ssl_enabled ? 'checked' : ''}>
                                        Activer SSL (HTTPS) - Recommandé
                                    </label>
                                </div>
                                <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.checkCustomDomainStatus()">
                                    Vérifier Configuration DNS
                                </button>
                                <div class="domain-status" id="custom-domain-status">
                                    ${this.renderDomainStatus(validation)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="univga-wl-card">
                    <h4>Instructions de Configuration DNS</h4>
                    <div class="univga-dns-instructions" id="dns-instructions">
                        <p class="instruction-note">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                            </svg>
                            Choisissez une option ci-dessus pour voir les instructions de configuration.
                        </p>
                    </div>
                </div>
            `;
            
            $('#wl-domain').html(html);
        };
        
        /**
         * Render email configuration section
         */
        UnivgaDashboard.renderEmailSection = function(data) {
            const settings = data.settings;
            
            let html = `
                <div class="univga-wl-card">
                    <h4>Configuration Email Personnalisée</h4>
                    <div class="univga-form-grid">
                        <div class="univga-form-group">
                            <label for="wl-email-domain">Domaine Email</label>
                            <input type="text" id="wl-email-domain" placeholder="votre-entreprise.com" value="${settings.custom_email_domain || ''}">
                            <small>Ex: votre-entreprise.com (pour envoyer depuis noreply@votre-entreprise.com)</small>
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-email-from-name">Nom d'Expéditeur</label>
                            <input type="text" id="wl-email-from-name" placeholder="Formation Entreprise" value="${settings.email_from_name || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-email-from-address">Adresse d'Expéditeur</label>
                            <input type="email" id="wl-email-from-address" placeholder="noreply@votre-entreprise.com" value="${settings.email_from_address || ''}">
                        </div>
                    </div>
                </div>
                
                <div class="univga-wl-card">
                    <h4>Configuration SMTP (Optionnel)</h4>
                    <p class="setting-description">Configurez votre propre serveur SMTP pour un contrôle total sur l'envoi d'emails.</p>
                    <div class="univga-form-grid">
                        <div class="univga-form-group">
                            <label for="wl-smtp-host">Serveur SMTP</label>
                            <input type="text" id="wl-smtp-host" placeholder="smtp.gmail.com" value="${settings.smtp_host || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-smtp-port">Port</label>
                            <select id="wl-smtp-port">
                                <option value="587" ${settings.smtp_port === '587' ? 'selected' : ''}>587 (TLS)</option>
                                <option value="465" ${settings.smtp_port === '465' ? 'selected' : ''}>465 (SSL)</option>
                                <option value="25" ${settings.smtp_port === '25' ? 'selected' : ''}>25 (Non sécurisé)</option>
                            </select>
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-smtp-username">Nom d'Utilisateur</label>
                            <input type="text" id="wl-smtp-username" placeholder="votre-email@gmail.com" value="${settings.smtp_username || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-smtp-password">Mot de Passe</label>
                            <input type="password" id="wl-smtp-password" placeholder="••••••••" value="${settings.smtp_password || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-smtp-encryption">Chiffrement</label>
                            <select id="wl-smtp-encryption">
                                <option value="tls" ${settings.smtp_encryption === 'tls' ? 'selected' : ''}>TLS</option>
                                <option value="ssl" ${settings.smtp_encryption === 'ssl' ? 'selected' : ''}>SSL</option>
                                <option value="none" ${settings.smtp_encryption === 'none' ? 'selected' : ''}>Aucun</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="univga-btn univga-btn-secondary" onclick="UnivgaDashboard.testEmailConfiguration()">
                        Tester Configuration Email
                    </button>
                </div>
            `;
            
            $('#wl-email').html(html);
        };
        
        /**
         * Render templates section
         */
        UnivgaDashboard.renderTemplatesSection = function(data) {
            const settings = data.settings;
            const templates = data.available_templates;
            
            let html = `
                <div class="univga-wl-card">
                    <h4>Templates de Page</h4>
                    <div class="univga-template-grid">
                        <div class="univga-template-item">
                            <label for="wl-login-template">Page de Connexion</label>
                            <select id="wl-login-template">
            `;
            
            Object.entries(templates).forEach(([value, label]) => {
                const selected = settings.login_template === value ? 'selected' : '';
                html += `<option value="${value}" ${selected}>${label}</option>`;
            });
            
            html += `
                            </select>
                        </div>
                        <div class="univga-template-item">
                            <label for="wl-dashboard-template">Tableau de Bord</label>
                            <select id="wl-dashboard-template">
            `;
            
            Object.entries(templates).forEach(([value, label]) => {
                const selected = settings.dashboard_template === value ? 'selected' : '';
                html += `<option value="${value}" ${selected}>${label}</option>`;
            });
            
            html += `
                            </select>
                        </div>
                        <div class="univga-template-item">
                            <label for="wl-course-template">Pages de Cours</label>
                            <select id="wl-course-template">
            `;
            
            Object.entries(templates).forEach(([value, label]) => {
                const selected = settings.course_template === value ? 'selected' : '';
                html += `<option value="${value}" ${selected}>${label}</option>`;
            });
            
            html += `
                            </select>
                        </div>
                        <div class="univga-template-item">
                            <label for="wl-certificate-template">Certificats</label>
                            <select id="wl-certificate-template">
            `;
            
            Object.entries(templates).forEach(([value, label]) => {
                const selected = settings.certificate_template === value ? 'selected' : '';
                html += `<option value="${value}" ${selected}>${label}</option>`;
            });
            
            html += `
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="univga-wl-card">
                    <h4>Réseaux Sociaux</h4>
                    <div class="univga-form-grid">
                        <div class="univga-form-group">
                            <label for="wl-social-facebook">Facebook</label>
                            <input type="url" id="wl-social-facebook" placeholder="https://facebook.com/votre-page" value="${settings.social_facebook || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-social-twitter">Twitter/X</label>
                            <input type="url" id="wl-social-twitter" placeholder="https://twitter.com/votre-compte" value="${settings.social_twitter || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-social-linkedin">LinkedIn</label>
                            <input type="url" id="wl-social-linkedin" placeholder="https://linkedin.com/company/votre-entreprise" value="${settings.social_linkedin || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-social-instagram">Instagram</label>
                            <input type="url" id="wl-social-instagram" placeholder="https://instagram.com/votre-compte" value="${settings.social_instagram || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-social-youtube">YouTube</label>
                            <input type="url" id="wl-social-youtube" placeholder="https://youtube.com/c/votre-chaine" value="${settings.social_youtube || ''}">
                        </div>
                    </div>
                </div>
                
                <div class="univga-wl-card">
                    <h4>Informations Légales</h4>
                    <div class="univga-form-grid">
                        <div class="univga-form-group">
                            <label for="wl-contact-email">Email de Contact</label>
                            <input type="email" id="wl-contact-email" placeholder="contact@votre-entreprise.com" value="${settings.contact_email || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-contact-phone">Téléphone</label>
                            <input type="tel" id="wl-contact-phone" placeholder="+33 1 23 45 67 89" value="${settings.contact_phone || ''}">
                        </div>
                        <div class="univga-form-group univga-full-width">
                            <label for="wl-contact-address">Adresse</label>
                            <textarea id="wl-contact-address" rows="3" placeholder="Votre adresse complète...">${settings.contact_address || ''}</textarea>
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-privacy-policy">Politique de Confidentialité</label>
                            <input type="url" id="wl-privacy-policy" placeholder="https://votre-site.com/privacy" value="${settings.privacy_policy_url || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-terms-service">Conditions d'Utilisation</label>
                            <input type="url" id="wl-terms-service" placeholder="https://votre-site.com/terms" value="${settings.terms_of_service_url || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-cookie-policy">Politique de Cookies</label>
                            <input type="url" id="wl-cookie-policy" placeholder="https://votre-site.com/cookies" value="${settings.cookie_policy_url || ''}">
                        </div>
                    </div>
                </div>
            `;
            
            $('#wl-templates').html(html);
        };
        
        /**
         * Render advanced settings section
         */
        UnivgaDashboard.renderAdvancedSection = function(data) {
            const settings = data.settings;
            
            let html = `
                <div class="univga-wl-card">
                    <h4>CSS et JavaScript Personnalisés</h4>
                    <div class="univga-code-editors">
                        <div class="univga-code-editor">
                            <label for="wl-custom-css">CSS Personnalisé</label>
                            <textarea id="wl-custom-css" rows="10" placeholder="/* Votre CSS personnalisé ici... */">${settings.custom_css || ''}</textarea>
                        </div>
                        <div class="univga-code-editor">
                            <label for="wl-custom-js">JavaScript Personnalisé</label>
                            <textarea id="wl-custom-js" rows="10" placeholder="// Votre JavaScript personnalisé ici...">${settings.custom_js || ''}</textarea>
                        </div>
                    </div>
                </div>
                
                <div class="univga-wl-card">
                    <h4>Analytics et Tracking</h4>
                    <div class="univga-form-grid">
                        <div class="univga-form-group">
                            <label for="wl-google-analytics">Google Analytics ID</label>
                            <input type="text" id="wl-google-analytics" placeholder="G-XXXXXXXXXX" value="${settings.google_analytics || ''}">
                        </div>
                        <div class="univga-form-group">
                            <label for="wl-facebook-pixel">Facebook Pixel ID</label>
                            <input type="text" id="wl-facebook-pixel" placeholder="1234567890123456" value="${settings.facebook_pixel || ''}">
                        </div>
                    </div>
                </div>
                
                <div class="univga-wl-card">
                    <h4>Code Personnalisé</h4>
                    <div class="univga-code-sections">
                        <div class="univga-code-section">
                            <label for="wl-custom-head">Code dans &lt;head&gt;</label>
                            <textarea id="wl-custom-head" rows="6" placeholder="<!-- Meta tags, CSS, ou autre code pour le <head> -->">${settings.custom_head_code || ''}</textarea>
                        </div>
                        <div class="univga-code-section">
                            <label for="wl-custom-footer">Code avant &lt;/body&gt;</label>
                            <textarea id="wl-custom-footer" rows="6" placeholder="<!-- Scripts, tracking, ou autre code avant </body> -->">${settings.custom_footer_code || ''}</textarea>
                        </div>
                    </div>
                </div>
                
                <div class="univga-wl-card">
                    <h4>Options Avancées</h4>
                    <div class="univga-advanced-options">
                        <div class="univga-option-item">
                            <label>
                                <input type="checkbox" id="wl-hide-branding" ${settings.hide_univga_branding ? 'checked' : ''}>
                                Masquer complètement la marque UNIVGA
                            </label>
                            <small>Retire toute référence à UNIVGA de votre plateforme</small>
                        </div>
                        <div class="univga-option-item">
                            <label for="wl-custom-footer-text">Texte de pied de page personnalisé</label>
                            <input type="text" id="wl-custom-footer-text" placeholder="© 2024 Votre Entreprise. Tous droits réservés." value="${settings.custom_footer_text || ''}">
                        </div>
                        <div class="univga-option-item">
                            <label>
                                <input type="checkbox" id="wl-maintenance-mode" ${settings.maintenance_mode ? 'checked' : ''}>
                                Mode Maintenance
                            </label>
                            <small>Active une page de maintenance pour votre plateforme</small>
                        </div>
                        <div class="univga-option-item">
                            <label for="wl-maintenance-message">Message de maintenance</label>
                            <textarea id="wl-maintenance-message" rows="3" placeholder="Notre plateforme est temporairement en maintenance...">${settings.maintenance_message || ''}</textarea>
                        </div>
                    </div>
                </div>
            `;
            
            $('#wl-advanced').html(html);
        };
        
        /**
         * Initialize color pickers with hex input sync
         */
        UnivgaDashboard.initializeColorPickers = function() {
            $('.univga-color-input input[type="color"]').on('input', function() {
                const color = $(this).val();
                $(this).siblings('.color-hex').val(color);
                this.updateLivePreview();
            }.bind(this));
            
            $('.univga-color-input .color-hex').on('input', function() {
                const color = $(this).val();
                if (/^#[0-9A-F]{6}$/i.test(color)) {
                    $(this).siblings('input[type="color"]').val(color);
                    this.updateLivePreview();
                }
            }.bind(this));
        };
        
        /**
         * Initialize file upload handlers
         */
        UnivgaDashboard.initializeFileUploads = function() {
            $('.univga-upload-zone').on('click', function() {
                const uploadType = $(this).data('upload');
                this.triggerFileUpload(uploadType);
            }.bind(this));
        };
        
        /**
         * Update color hex inputs
         */
        UnivgaDashboard.updateColorHexInputs = function() {
            $('.univga-color-input input[type="color"]').each(function() {
                const color = $(this).val();
                $(this).siblings('.color-hex').val(color);
            });
        };
        
        /**
         * Update live preview
         */
        UnivgaDashboard.updateLivePreview = function() {
            if (!this.previewMode) return;
            
            const settings = this.collectWhiteLabelSettings();
            
            // Generate preview HTML
            const previewHtml = this.generatePreviewHtml(settings);
            $('#wl-preview .preview-container').html(previewHtml);
        };
        
        /**
         * Collect current white-label settings from form
         */
        UnivgaDashboard.collectWhiteLabelSettings = function() {
            return {
                enabled: $('#whitelabel-enabled').is(':checked'),
                company_name: $('#wl-company-name').val(),
                company_slogan: $('#wl-company-slogan').val(),
                company_description: $('#wl-company-description').val(),
                primary_color: $('#wl-primary-color').val(),
                secondary_color: $('#wl-secondary-color').val(),
                accent_color: $('#wl-accent-color').val(),
                background_color: $('#wl-background-color').val(),
                text_color: $('#wl-text-color').val(),
                link_color: $('#wl-link-color').val(),
                font_family: $('#wl-font-family').val(),
                heading_font: $('#wl-heading-font').val(),
                font_size_base: $('#wl-font-size').val(),
                custom_domain: $('#wl-custom-domain').val(),
                subdomain: $('#wl-subdomain').val(),
                ssl_enabled: $('#wl-ssl-enabled').is(':checked'),
                // ... autres champs
            };
        };
        
        /**
         * Generate preview HTML
         */
        UnivgaDashboard.generatePreviewHtml = function(settings) {
            return `
                <div class="preview-mockup" style="
                    background: ${settings.background_color};
                    color: ${settings.text_color};
                    font-family: ${settings.font_family}, sans-serif;
                    font-size: ${settings.font_size_base};
                ">
                    <div class="preview-header" style="background: ${settings.primary_color}; color: white; padding: 16px;">
                        <h1 style="margin: 0; font-family: ${settings.heading_font}, sans-serif;">${settings.company_name || 'Votre Entreprise'}</h1>
                        ${settings.company_slogan ? `<p style="margin: 4px 0 0 0; opacity: 0.9;">${settings.company_slogan}</p>` : ''}
                    </div>
                    <div class="preview-content" style="padding: 20px;">
                        <h2 style="color: ${settings.secondary_color};">Tableau de Bord</h2>
                        <p>Voici un aperçu de votre plateforme avec votre marque personnalisée.</p>
                        <button style="
                            background: ${settings.accent_color};
                            color: white;
                            border: none;
                            padding: 8px 16px;
                            border-radius: 4px;
                            cursor: pointer;
                        ">Bouton d'Action</button>
                        <p><a href="#" style="color: ${settings.link_color};">Exemple de lien</a></p>
                    </div>
                </div>
            `;
        };
        
        /**
         * Save white-label configuration
         */
        UnivgaDashboard.saveWhiteLabelConfig = function() {
            const self = this;
            const settings = this.collectWhiteLabelSettings();
            
            const url = univga_dashboard.rest_url + 'organizations/' + this.orgId + '/whitelabel';
            
            $('#wl-save-btn').prop('disabled', true).text('Sauvegarde...');
            
            $.ajax({
                url: url,
                method: 'POST',
                data: JSON.stringify(settings),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(response) {
                self.showNotice('success', response.message || 'Configuration white-label sauvegardée avec succès');
                self.whitelabelData.settings = { ...self.whitelabelData.settings, ...settings };
            })
            .fail(function(xhr) {
                console.error('Failed to save white-label config:', xhr);
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? 
                    xhr.responseJSON.message : 'Échec de la sauvegarde';
                self.showNotice('error', errorMsg);
            })
            .always(function() {
                $('#wl-save-btn').prop('disabled', false).text('Sauvegarder Configuration');
            });
        };
        
        /**
         * Domain and subdomain management functions
         */
        UnivgaDashboard.checkSubdomainAvailability = function() {
            const subdomain = $('#wl-subdomain').val().trim();
            if (!subdomain) return;
            
            const url = univga_dashboard.rest_url + 'organizations/' + this.orgId + '/domain-check';
            
            $.ajax({
                url: url,
                method: 'POST',
                data: JSON.stringify({
                    domain: subdomain,
                    type: 'subdomain'
                }),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(response) {
                const status = response.available ? 'available' : 'taken';
                $('#subdomain-availability').html(`
                    <div class="availability-${status}">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            ${response.available ? 
                                '<path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>' :
                                '<path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>'
                            }
                        </svg>
                        ${response.message}
                    </div>
                `);
            });
        };
        
        UnivgaDashboard.checkCustomDomainStatus = function() {
            const domain = $('#wl-custom-domain').val().trim();
            if (!domain) return;
            
            const url = univga_dashboard.rest_url + 'organizations/' + this.orgId + '/domain-check';
            
            $.ajax({
                url: url,
                method: 'POST',
                data: JSON.stringify({
                    domain: domain,
                    type: 'domain'
                }),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(response) {
                $('#custom-domain-status').html(this.renderDomainStatus({
                    custom_domain: response
                }));
            }.bind(this));
        };
        
        UnivgaDashboard.renderDomainStatus = function(validation) {
            if (!validation || !validation.custom_domain) {
                return '<p>Aucune vérification de domaine effectuée.</p>';
            }
            
            const status = validation.custom_domain;
            const statusClass = status.points_to_us ? 'status-success' : 'status-warning';
            
            return `
                <div class="domain-status-item ${statusClass}">
                    <div class="status-header">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            ${status.points_to_us ? 
                                '<path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>' :
                                '<path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>'
                            }
                        </svg>
                        ${status.message}
                    </div>
                    <div class="status-details">
                        <p><strong>IP Actuelle:</strong> ${status.current_ip}</p>
                        <p><strong>IP Requise:</strong> ${status.required_ip}</p>
                        ${!status.points_to_us ? `
                            <div class="dns-instructions">
                                <p><strong>Action Requise:</strong></p>
                                <ol>
                                    <li>Connectez-vous à votre gestionnaire de domaine</li>
                                    <li>Créez un enregistrement A pointant vers ${status.required_ip}</li>
                                    <li>Patientez 24-48h pour la propagation DNS</li>
                                </ol>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        };
        
        UnivgaDashboard.testEmailConfiguration = function() {
            this.showNotice('info', 'Test d\'email en développement');
        };
        
        UnivgaDashboard.triggerFileUpload = function(type) {
            this.showNotice('info', 'Upload de fichier en développement pour: ' + type);
        };
        
        // Event handlers
        $(document).on('change', '#whitelabel-enabled', function() {
            const enabled = $(this).is(':checked');
            $('#whitelabel-content').toggle(enabled);
            
            if (enabled && !UnivgaDashboard.whitelabelData) {
                UnivgaDashboard.loadWhiteLabel();
            }
        });
        
        $(document).on('click', '.univga-wl-nav-btn', function() {
            const section = $(this).data('wl-section');
            $('.univga-wl-nav-btn').removeClass('active');
            $(this).addClass('active');
            
            $('.univga-wl-section').removeClass('active');
            $('#wl-' + section).addClass('active');
            
            UnivgaDashboard.currentWLSection = section;
        });
        
        $(document).on('click', '#wl-preview-btn', function() {
            UnivgaDashboard.previewMode = !UnivgaDashboard.previewMode;
            $(this).text(UnivgaDashboard.previewMode ? 'Masquer Aperçu' : 'Aperçu');
            $('#wl-preview').toggle(UnivgaDashboard.previewMode);
            
            if (UnivgaDashboard.previewMode) {
                UnivgaDashboard.updateLivePreview();
            }
        });
        
        $(document).on('click', '#wl-save-btn', function() {
            UnivgaDashboard.saveWhiteLabelConfig();
        });
        
        $(document).on('change', 'input[name="domain-option"]', function() {
            const option = $(this).val();
            $('.univga-option-content').hide();
            $('#' + option + '-config').show();
        });
        
        // Real-time preview updates
        $(document).on('input', '#wl-company-name, #wl-company-slogan', function() {
            if (UnivgaDashboard.previewMode) {
                UnivgaDashboard.updateLivePreview();
            }
        });
        
        // Load white-label when branding tab is clicked
        $(document).on('click', '[data-admin-section="branding"]', function() {
            setTimeout(function() {
                if (UnivgaDashboard && !UnivgaDashboard.whitelabelData) {
                    UnivgaDashboard.loadWhiteLabel();
                }
            }, 100);
        });
    }
});