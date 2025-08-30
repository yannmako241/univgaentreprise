/**
 * Administration Tab JavaScript for UNIVGA Dashboard
 */
jQuery(document).ready(function($) {
    // Extend UnivgaDashboard with administration functionality
    if (typeof UnivgaDashboard !== 'undefined') {
        
        // Administration data storage
        UnivgaDashboard.administrationData = null;
        UnivgaDashboard.currentAdminSection = 'organization';
        
        /**
         * Load administration data
         */
        UnivgaDashboard.loadAdministration = function(section) {
            const self = this;
            section = section || this.currentAdminSection;
            
            // Show loading state
            this.showAdministrationLoading(section);
            
            // Build query parameters
            const params = new URLSearchParams();
            params.append('section', section);
            
            const queryString = params.toString();
            const url = univga_dashboard.rest_url + 'organizations/' + this.orgId + '/administration' + 
                       (queryString ? '?' + queryString : '');
            
            $.ajax({
                url: url,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(data) {
                self.administrationData = data;
                self.currentAdminSection = section;
                self.renderAdministration(data);
                self.hideAdministrationLoading();
            })
            .fail(function(xhr) {
                console.error('Failed to load administration:', xhr);
                self.hideAdministrationLoading();
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Échec du chargement de l\\'administration';
                self.showNotice('error', errorMsg);
            });
        };
        
        /**
         * Show administration loading state
         */
        UnivgaDashboard.showAdministrationLoading = function(section) {
            const $section = $('#admin-' + section);
            if ($section.length) {
                $section.html('<div class="loading">Chargement des données d\\'administration...</div>');
            }
        };
        
        /**
         * Hide administration loading state
         */
        UnivgaDashboard.hideAdministrationLoading = function() {
            // Loading handled by render functions
        };
        
        /**
         * Render administration data
         */
        UnivgaDashboard.renderAdministration = function(data) {
            this.renderOrganizationDetails(data.organization, data.stats, data.performance_metrics);
            this.renderTeamsManagement(data.teams);
            this.renderMembersManagement(data.members);
            this.renderSeatPoolsManagement(data.seat_pools);
            this.renderRecentActivities(data.recent_activities);
            this.renderSettings(data.settings);
        };
        
        /**
         * Render organization details
         */
        UnivgaDashboard.renderOrganizationDetails = function(org, stats, metrics) {
            const $container = $('#admin-organization');
            
            let html = `
                <div class="univga-admin-header">
                    <h3>Détails de l'Organisation</h3>
                    <button type="button" class="univga-btn univga-btn-primary" onclick="UnivgaDashboard.editOrganization()">
                        Modifier l'Organisation
                    </button>
                </div>
                
                <div class="univga-admin-content">
                    <!-- Organization Info -->
                    <div class="univga-admin-section">
                        <h4>Informations de Base</h4>
                        <div class="univga-info-grid">
                            <div class="univga-info-item">
                                <label>Nom de l'Organisation</label>
                                <span>${org.name}</span>
                            </div>
                            <div class="univga-info-item">
                                <label>ID Légal</label>
                                <span>${org.legal_id || 'Non spécifié'}</span>
                            </div>
                            <div class="univga-info-item">
                                <label>Contact Principal</label>
                                <span>${org.contact_name || 'Non assigné'}</span>
                            </div>
                            <div class="univga-info-item">
                                <label>Email de Contact</label>
                                <span>${org.contact_email || 'Non spécifié'}</span>
                            </div>
                            <div class="univga-info-item">
                                <label>Domaine Email</label>
                                <span>${org.email_domain || 'Non configuré'}</span>
                            </div>
                            <div class="univga-info-item">
                                <label>Statut</label>
                                <span class="univga-status-badge ${org.status ? 'active' : 'inactive'}">
                                    ${org.status ? 'Actif' : 'Inactif'}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics Overview -->
                    <div class="univga-admin-section">
                        <h4>Aperçu des Statistiques</h4>
                        <div class="univga-stats-overview">
                            <div class="univga-stat-card">
                                <div class="univga-stat-icon members">
                                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002A.274.274 0 0 1 15 13H7.022Z"/>
                                    </svg>
                                </div>
                                <div class="univga-stat-info">
                                    <div class="univga-stat-value">${stats.active_members}</div>
                                    <div class="univga-stat-label">Membres Actifs</div>
                                    <div class="univga-stat-sublabel">sur ${stats.total_members} total</div>
                                </div>
                            </div>
                            
                            <div class="univga-stat-card">
                                <div class="univga-stat-icon teams">
                                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                    </svg>
                                </div>
                                <div class="univga-stat-info">
                                    <div class="univga-stat-value">${stats.total_teams}</div>
                                    <div class="univga-stat-label">Équipes</div>
                                </div>
                            </div>
                            
                            <div class="univga-stat-card">
                                <div class="univga-stat-icon courses">
                                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/>
                                    </svg>
                                </div>
                                <div class="univga-stat-info">
                                    <div class="univga-stat-value">${stats.total_courses}</div>
                                    <div class="univga-stat-label">Cours</div>
                                </div>
                            </div>
                            
                            <div class="univga-stat-card">
                                <div class="univga-stat-icon certifications">
                                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M9.669.864 8 0 6.331.864l-1.858.282-.842 1.68-1.337 1.32L2.6 6l-.306 1.854 1.337 1.32.842 1.68 1.858.282L8 12l1.669-.864 1.858-.282.842-1.68 1.337-1.32L13.4 6l.306-1.854-1.337-1.32-.842-1.68L9.669.864z"/>
                                    </svg>
                                </div>
                                <div class="univga-stat-info">
                                    <div class="univga-stat-value">${stats.total_certifications}</div>
                                    <div class="univga-stat-label">Certifications</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Performance Metrics -->
                    <div class="univga-admin-section">
                        <h4>Métriques de Performance (30 derniers jours)</h4>
                        <div class="univga-metrics-grid">
                            <div class="univga-metric-item">
                                <div class="univga-metric-value">${metrics.new_members_30d}</div>
                                <div class="univga-metric-label">Nouveaux Membres</div>
                            </div>
                            <div class="univga-metric-item">
                                <div class="univga-metric-value">${metrics.course_completions_30d}</div>
                                <div class="univga-metric-label">Cours Terminés</div>
                            </div>
                            <div class="univga-metric-item">
                                <div class="univga-metric-value">${metrics.certifications_earned_30d}</div>
                                <div class="univga-metric-label">Certifications Obtenues</div>
                            </div>
                            <div class="univga-metric-item">
                                <div class="univga-metric-value">${metrics.avg_course_completion_rate}%</div>
                                <div class="univga-metric-label">Taux Moyen de Complétion</div>
                            </div>
                            <div class="univga-metric-item">
                                <div class="univga-metric-value">${metrics.active_users_7d}</div>
                                <div class="univga-metric-label">Utilisateurs Actifs (7j)</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $container.html(html);
        };
        
        /**
         * Render teams management
         */
        UnivgaDashboard.renderTeamsManagement = function(teams) {
            const $container = $('#admin-teams');
            
            let html = `
                <div class="univga-admin-header">
                    <h3>Gestion des Équipes</h3>
                    <button type="button" class="univga-btn univga-btn-primary" onclick="UnivgaDashboard.createTeam()">
                        Créer une Équipe
                    </button>
                </div>
                
                <div class="univga-admin-content">
                    <div class="univga-teams-list">
            `;
            
            if (teams && teams.length > 0) {
                teams.forEach(team => {
                    html += `
                        <div class="univga-team-admin-card">
                            <div class="univga-team-header">
                                <h4>${team.name}</h4>
                                <div class="univga-team-actions">
                                    <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.editTeam('${team.id}')">
                                        Modifier
                                    </button>
                                    <button type="button" class="univga-btn univga-btn-small univga-btn-outline" onclick="UnivgaDashboard.manageTeamMembers('${team.id}')">
                                        Membres
                                    </button>
                                </div>
                            </div>
                            <div class="univga-team-info">
                                <div class="univga-team-stat">
                                    <label>Manager</label>
                                    <span>${team.manager_name || 'Non assigné'}</span>
                                </div>
                                <div class="univga-team-stat">
                                    <label>Membres</label>
                                    <span>${team.member_count} personnes</span>
                                </div>
                                <div class="univga-team-stat">
                                    <label>Taux de Complétion</label>
                                    <span>${team.completion_percentage}%</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += `
                    <div class="univga-empty-state">
                        <h3>Aucune équipe</h3>
                        <p>Créez votre première équipe pour organiser vos membres.</p>
                        <button type="button" class="univga-btn univga-btn-primary" onclick="UnivgaDashboard.createTeam()">
                            Créer une Équipe
                        </button>
                    </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
            
            $container.html(html);
        };
        
        /**
         * Render members management
         */
        UnivgaDashboard.renderMembersManagement = function(members) {
            const $container = $('#admin-members');
            
            let html = `
                <div class="univga-admin-header">
                    <h3>Gestion des Membres</h3>
                    <div class="univga-admin-actions">
                        <button type="button" class="univga-btn univga-btn-secondary" onclick="UnivgaDashboard.exportMembers()">
                            Exporter
                        </button>
                        <button type="button" class="univga-btn univga-btn-primary" onclick="UnivgaDashboard.inviteMember()">
                            Inviter un Membre
                        </button>
                    </div>
                </div>
                
                <div class="univga-admin-content">
                    <div class="univga-members-table-container">
                        <table class="univga-members-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Équipe</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Progression</th>
                                    <th>Membre Depuis</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (members && members.length > 0) {
                members.forEach(member => {
                    const statusClass = member.status === 'active' ? 'status-active' : 'status-inactive';
                    
                    html += `
                        <tr>
                            <td>
                                <div class="univga-member-name">
                                    <div class="univga-member-avatar">
                                        ${this.getUserInitials(member.display_name)}
                                    </div>
                                    ${member.display_name}
                                </div>
                            </td>
                            <td>${member.user_email}</td>
                            <td>${member.team_name || 'Aucune équipe'}</td>
                            <td>
                                <span class="univga-role-badge">${member.role_label}</span>
                            </td>
                            <td>
                                <span class="univga-status-badge ${statusClass}">${member.status_label}</span>
                            </td>
                            <td>
                                <div class="univga-progress-info">
                                    <span>${member.courses_completed}/${member.courses_enrolled} cours</span>
                                    <div class="univga-progress-bar">
                                        <div class="univga-progress-fill" style="width: ${member.completion_percentage}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>${member.member_since}</td>
                            <td>
                                <div class="univga-member-actions">
                                    <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.editMember('${member.ID}')">
                                        Modifier
                                    </button>
                                    <button type="button" class="univga-btn univga-btn-small univga-btn-outline" onclick="UnivgaDashboard.viewMemberDetails('${member.ID}')">
                                        Détails
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html += `
                    <tr>
                        <td colspan="8" class="univga-no-data">Aucun membre trouvé</td>
                    </tr>
                `;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            $container.html(html);
        };
        
        /**
         * Render seat pools management
         */
        UnivgaDashboard.renderSeatPoolsManagement = function(seatPools) {
            const $container = $('#admin-seat-pools');
            
            let html = `
                <div class="univga-admin-header">
                    <h3>Gestion des Pools de Sièges</h3>
                    <button type="button" class="univga-btn univga-btn-primary" onclick="UnivgaDashboard.createSeatPool()">
                        Créer un Pool
                    </button>
                </div>
                
                <div class="univga-admin-content">
                    <div class="univga-seat-pools-grid">
            `;
            
            if (seatPools && seatPools.length > 0) {
                seatPools.forEach(pool => {
                    html += `
                        <div class="univga-seat-pool-card ${pool.status_class}">
                            <div class="univga-pool-header">
                                <h4>${pool.course_name || 'Pool Général'}</h4>
                                <span class="univga-pool-utilization">${pool.utilization_rate}%</span>
                            </div>
                            
                            <div class="univga-pool-stats">
                                <div class="univga-pool-stat">
                                    <label>Sièges Utilisés</label>
                                    <span>${pool.used_seats}/${pool.total_seats}</span>
                                </div>
                                <div class="univga-pool-stat">
                                    <label>Disponibles</label>
                                    <span>${pool.available_seats}</span>
                                </div>
                                <div class="univga-pool-stat">
                                    <label>Expire</label>
                                    <span>${pool.expires_in}</span>
                                </div>
                            </div>
                            
                            <div class="univga-pool-progress">
                                <div class="univga-progress-bar">
                                    <div class="univga-progress-fill" style="width: ${pool.utilization_rate}%"></div>
                                </div>
                            </div>
                            
                            <div class="univga-pool-actions">
                                <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.manageSeatPool('${pool.id}')">
                                    Gérer
                                </button>
                                <button type="button" class="univga-btn univga-btn-small univga-btn-outline" onclick="UnivgaDashboard.extendSeatPool('${pool.id}')">
                                    Étendre
                                </button>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += `
                    <div class="univga-empty-state">
                        <h3>Aucun pool de sièges</h3>
                        <p>Créez des pools de sièges pour gérer l'accès aux cours.</p>
                        <button type="button" class="univga-btn univga-btn-primary" onclick="UnivgaDashboard.createSeatPool()">
                            Créer un Pool
                        </button>
                    </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
            
            $container.html(html);
        };
        
        /**
         * Render recent activities
         */
        UnivgaDashboard.renderRecentActivities = function(activities) {
            // This would be shown in a sidebar or dedicated section
            console.log('Recent activities:', activities);
        };
        
        /**
         * Render settings
         */
        UnivgaDashboard.renderSettings = function(settings) {
            const $container = $('#admin-settings');
            
            let html = `
                <div class="univga-admin-header">
                    <h3>Paramètres de l'Organisation</h3>
                    <button type="button" class="univga-btn univga-btn-primary" onclick="UnivgaDashboard.saveSettings()">
                        Sauvegarder
                    </button>
                </div>
                
                <div class="univga-admin-content">
                    <div class="univga-settings-sections">
                        <div class="univga-settings-section">
                            <h4>Apprentissage</h4>
                            <div class="univga-setting-item">
                                <label>
                                    <input type="checkbox" ${settings.learning_path_auto_assignment ? 'checked' : ''}>
                                    Attribution automatique des parcours d'apprentissage
                                </label>
                            </div>
                            <div class="univga-setting-item">
                                <label>
                                    <input type="checkbox" ${settings.course_sharing ? 'checked' : ''}>
                                    Partage de cours entre équipes
                                </label>
                            </div>
                        </div>
                        
                        <div class="univga-settings-section">
                            <h4>Notifications</h4>
                            <div class="univga-setting-item">
                                <label>
                                    <input type="checkbox" ${settings.certification_notifications ? 'checked' : ''}>
                                    Notifications de certification
                                </label>
                            </div>
                            <div class="univga-setting-item">
                                <label>
                                    <input type="checkbox" ${settings.seat_pool_alerts ? 'checked' : ''}>
                                    Alertes de pools de sièges
                                </label>
                            </div>
                        </div>
                        
                        <div class="univga-settings-section">
                            <h4>Sécurité</h4>
                            <div class="univga-setting-item">
                                <label>
                                    <input type="checkbox" ${settings.member_registration_approval ? 'checked' : ''}>
                                    Approbation d'inscription des membres
                                </label>
                            </div>
                            <div class="univga-setting-item">
                                <label for="team-visibility">Visibilité des équipes</label>
                                <select id="team-visibility">
                                    <option value="public" ${settings.team_visibility === 'public' ? 'selected' : ''}>Publique</option>
                                    <option value="restricted" ${settings.team_visibility === 'restricted' ? 'selected' : ''}>Restreinte</option>
                                    <option value="private" ${settings.team_visibility === 'private' ? 'selected' : ''}>Privée</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $container.html(html);
        };
        
        /**
         * Action functions - placeholders for development
         */
        UnivgaDashboard.editOrganization = function() {
            this.showNotice('info', 'Éditeur d\\'organisation en développement');
        };
        
        UnivgaDashboard.createTeam = function() {
            this.showNotice('info', 'Créateur d\\'équipe en développement');
        };
        
        UnivgaDashboard.editTeam = function(teamId) {
            this.showNotice('info', 'Éditeur d\\'équipe en développement');
        };
        
        UnivgaDashboard.manageTeamMembers = function(teamId) {
            this.showNotice('info', 'Gestionnaire de membres d\\'équipe en développement');
        };
        
        UnivgaDashboard.inviteMember = function() {
            this.showNotice('info', 'Inviteur de membre en développement');
        };
        
        UnivgaDashboard.editMember = function(memberId) {
            this.showNotice('info', 'Éditeur de membre en développement');
        };
        
        UnivgaDashboard.viewMemberDetails = function(memberId) {
            this.showNotice('info', 'Visualiseur de détails de membre en développement');
        };
        
        UnivgaDashboard.exportMembers = function() {
            this.showNotice('info', 'Exportateur de membres en développement');
        };
        
        UnivgaDashboard.createSeatPool = function() {
            this.showNotice('info', 'Créateur de pool de sièges en développement');
        };
        
        UnivgaDashboard.manageSeatPool = function(poolId) {
            this.showNotice('info', 'Gestionnaire de pool de sièges en développement');
        };
        
        UnivgaDashboard.extendSeatPool = function(poolId) {
            this.showNotice('info', 'Extension de pool de sièges en développement');
        };
        
        UnivgaDashboard.saveSettings = function() {
            this.showNotice('info', 'Sauvegarde des paramètres en développement');
        };
        
        // Event handlers
        $(document).on('click', '.univga-admin-nav-btn', function() {
            const section = $(this).data('admin-section');
            $('.univga-admin-nav-btn').removeClass('active');
            $(this).addClass('active');
            
            $('.univga-admin-section').removeClass('active');
            $('#admin-' + section).addClass('active');
            
            if (UnivgaDashboard && UnivgaDashboard.loadAdministration) {
                UnivgaDashboard.loadAdministration(section);
            }
        });
        
        // Load administration when tab is clicked
        $(document).on('click', '[data-tab="admin"]', function() {
            setTimeout(function() {
                if (UnivgaDashboard && UnivgaDashboard.loadAdministration) {
                    UnivgaDashboard.loadAdministration();
                }
            }, 100);
        });
    }
});