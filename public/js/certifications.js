/**
 * Certifications Tab JavaScript for UNIVGA Dashboard
 */
jQuery(document).ready(function($) {
    // Extend UnivgaDashboard with certifications functionality
    if (typeof UnivgaDashboard !== 'undefined') {
        
        // Certifications data storage
        UnivgaDashboard.certificationsData = null;
        UnivgaDashboard.certificationsFilters = {
            type: null,
            search: null,
            team_id: null
        };
        
        /**
         * Load certifications data
         */
        UnivgaDashboard.loadCertifications = function() {
            const self = this;
            
            // Show loading state
            this.showCertificationsLoading();
            
            // Build query parameters
            const params = new URLSearchParams();
            if (this.certificationsFilters.type) {
                params.append('type', this.certificationsFilters.type);
            }
            if (this.certificationsFilters.search) {
                params.append('search', this.certificationsFilters.search);
            }
            if (this.certificationsFilters.team_id) {
                params.append('team_id', this.certificationsFilters.team_id);
            }
            
            const queryString = params.toString();
            const url = univga_dashboard.rest_url + 'organizations/' + this.orgId + '/certifications' + 
                       (queryString ? '?' + queryString : '');
            
            $.ajax({
                url: url,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(data) {
                self.certificationsData = data;
                self.renderCertifications(data);
                self.hideCertificationsLoading();
            })
            .fail(function(xhr) {
                console.error('Failed to load certifications:', xhr);
                self.hideCertificationsLoading();
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Échec du chargement des certifications';
                $('#certifications-list').html('<div class="univga-notice univga-notice-error">' + errorMsg + '</div>');
            });
        };
        
        /**
         * Show certifications loading state
         */
        UnivgaDashboard.showCertificationsLoading = function() {
            $('#certifications-list').html('<div class="loading">Chargement des certifications...</div>');
            $('#compliance-dashboard').html('<div class="loading">Chargement du tableau de conformité...</div>');
            $('.univga-stat-value').text('--');
        };
        
        /**
         * Hide certifications loading state
         */
        UnivgaDashboard.hideCertificationsLoading = function() {
            // Loading handled by render functions
        };
        
        /**
         * Render certifications data
         */
        UnivgaDashboard.renderCertifications = function(data) {
            this.renderCertificationsStats(data.stats);
            this.renderCertificationsList(data.certifications);
            this.renderComplianceDashboard(data.team_compliance, data.users_needing_certs, data.recent_activities);
            this.populateTeamFilterForCerts(data.teams);
        };
        
        /**
         * Render certifications statistics
         */
        UnivgaDashboard.renderCertificationsStats = function(stats) {
            $('#total-certifications').text(stats.total_certifications);
            $('#active-certifications').text(stats.active_certifications);
            $('#expiring-soon').text(stats.expiring_soon);
            $('#compliance-rate').text(stats.compliance_rate + '%');
        };
        
        /**
         * Render certifications list
         */
        UnivgaDashboard.renderCertificationsList = function(certifications) {
            const $container = $('#certifications-list');
            
            if (!certifications || certifications.length === 0) {
                $container.html(`
                    <div class="univga-empty-state">
                        <div class="univga-empty-icon">📜</div>
                        <h3>Aucune certification</h3>
                        <p>Créez votre première certification pour commencer à suivre la conformité de vos équipes.</p>
                        <button type="button" class="univga-btn univga-btn-primary" id="create-first-certification">
                            Créer une Certification
                        </button>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="univga-certifications-grid">';
            
            certifications.forEach(cert => {
                const alertClass = this.getAlertClass(cert.alert_status);
                const typeLabel = cert.is_compliance ? 'Obligatoire' : 'Optionnelle';
                const typeBadgeClass = cert.is_compliance ? 'type-mandatory' : 'type-optional';
                
                html += `
                    <div class="univga-certification-card ${alertClass}" data-certification-id="${cert.id}">
                        <div class="univga-certification-header">
                            <div class="univga-certification-title-section">
                                <h4 class="univga-certification-title">${cert.name}</h4>
                                <div class="univga-certification-badges">
                                    <span class="univga-certification-type ${typeBadgeClass}">${typeLabel}</span>
                                    ${this.getAlertBadge(cert.alert_status)}
                                </div>
                            </div>
                            <div class="univga-certification-actions">
                                <button type="button" class="univga-certification-action" data-action="edit" data-cert-id="${cert.id}" title="Modifier">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L8.5 11.207l-3 1a.5.5 0 0 1-.65-.65l1-3L12.146.146z"/>
                                    </svg>
                                </button>
                                <button type="button" class="univga-certification-action" data-action="award" data-cert-id="${cert.id}" title="Attribuer">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M9.669.864 8 0 6.331.864l-1.858.282-.842 1.68-1.337 1.32L2.6 6l-.306 1.854 1.337 1.32.842 1.68 1.858.282L8 12l1.669-.864 1.858-.282.842-1.68 1.337-1.32L13.4 6l.306-1.854-1.337-1.32-.842-1.68L9.669.864z"/>
                                    </svg>
                                </button>
                                <button type="button" class="univga-certification-action" data-action="delete" data-cert-id="${cert.id}" title="Supprimer">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="univga-certification-description">
                            ${cert.description || 'Aucune description disponible'}
                        </div>
                        
                        <div class="univga-certification-requirements">
                            <strong>Prérequis:</strong> ${cert.requirements_formatted}
                        </div>
                        
                        <div class="univga-certification-stats">
                            <div class="univga-cert-stat">
                                <span class="univga-stat-value">${cert.total_holders}</span>
                                <span class="univga-stat-label">Détenteurs</span>
                            </div>
                            <div class="univga-cert-stat">
                                <span class="univga-stat-value">${cert.active_holders}</span>
                                <span class="univga-stat-label">Actifs</span>
                            </div>
                            <div class="univga-cert-stat">
                                <span class="univga-stat-value">${cert.expiring_soon}</span>
                                <span class="univga-stat-label">Expire Bientôt</span>
                            </div>
                            <div class="univga-cert-stat">
                                <span class="univga-stat-value">${cert.expired}</span>
                                <span class="univga-stat-label">Expirés</span>
                            </div>
                        </div>
                        
                        <div class="univga-certification-progress">
                            <div class="univga-progress-header">
                                <span>Taux de conformité</span>
                                <span>${cert.compliance_percentage}%</span>
                            </div>
                            <div class="univga-progress">
                                <div class="univga-progress-bar" style="width: ${cert.compliance_percentage}%; background-color: ${this.getComplianceColor(cert.compliance_percentage)}"></div>
                            </div>
                        </div>
                        
                        <div class="univga-certification-footer">
                            <div class="univga-certification-meta">
                                <span class="univga-validity">Validité: ${cert.validity_formatted}</span>
                                <span class="univga-created-by">Par ${cert.created_by_name || 'Inconnu'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $container.html(html);
        };
        
        /**
         * Render compliance dashboard
         */
        UnivgaDashboard.renderComplianceDashboard = function(teamCompliance, usersNeedingCerts, recentActivities) {
            const $container = $('#compliance-dashboard');
            
            let html = `
                <div class="univga-compliance-content">
                    <div class="univga-compliance-section">
                        <h5>Conformité par Équipe</h5>
                        <div class="univga-team-compliance-list">
            `;
            
            if (teamCompliance && teamCompliance.length > 0) {
                teamCompliance.forEach(team => {
                    const complianceClass = this.getComplianceClass(team.compliance_percentage);
                    html += `
                        <div class="univga-team-compliance-item ${complianceClass}">
                            <div class="univga-team-info">
                                <div class="univga-team-name">${team.team_name}</div>
                                <div class="univga-team-members">${team.team_members} membres</div>
                            </div>
                            <div class="univga-team-stats">
                                <div class="univga-compliance-percentage">
                                    ${team.compliance_percentage || 0}%
                                </div>
                                <div class="univga-certified-count">
                                    ${team.certified_members}/${team.team_members} certifiés
                                </div>
                            </div>
                            <div class="univga-compliance-bar">
                                <div class="univga-progress-bar" style="width: ${team.compliance_percentage || 0}%"></div>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += '<div class="univga-no-data">Aucune équipe trouvée</div>';
            }
            
            html += `
                        </div>
                    </div>
                    
                    <div class="univga-compliance-section">
                        <h5>Utilisateurs Nécessitant des Certifications</h5>
                        <div class="univga-users-needing-certs">
            `;
            
            if (usersNeedingCerts && usersNeedingCerts.length > 0) {
                usersNeedingCerts.slice(0, 10).forEach(user => {
                    html += `
                        <div class="univga-user-cert-item">
                            <div class="univga-user-avatar">
                                ${this.getUserInitials(user.user_name)}
                            </div>
                            <div class="univga-user-info">
                                <div class="univga-user-name">${user.user_name}</div>
                                <div class="univga-user-team">${user.team_name || 'Aucune équipe'}</div>
                            </div>
                            <div class="univga-cert-status">
                                <div class="univga-missing-certs">${user.missing_certs} manquantes</div>
                                <div class="univga-progress-small">
                                    ${user.earned_certs}/${user.required_certs}
                                </div>
                            </div>
                            <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.assignCertifications('${user.user_id}')">
                                Assigner
                            </button>
                        </div>
                    `;
                });
            } else {
                html += '<div class="univga-no-data">Tous les utilisateurs sont en conformité</div>';
            }
            
            html += `
                        </div>
                    </div>
                    
                    <div class="univga-compliance-section">
                        <h5>Activités Récentes</h5>
                        <div class="univga-recent-activities">
            `;
            
            if (recentActivities && recentActivities.length > 0) {
                recentActivities.slice(0, 8).forEach(activity => {
                    const timeAgo = this.timeAgo(activity.earned_date);
                    const statusClass = activity.status === 'earned' ? 'status-earned' : 'status-expired';
                    
                    html += `
                        <div class="univga-activity-item">
                            <div class="univga-activity-icon ${statusClass}">
                                ${activity.status === 'earned' ? '✓' : '⚠'}
                            </div>
                            <div class="univga-activity-content">
                                <div class="univga-activity-text">
                                    <strong>${activity.user_name}</strong> a ${activity.status === 'earned' ? 'obtenu' : 'perdu'} la certification 
                                    <strong>${activity.certification_name}</strong>
                                </div>
                                <div class="univga-activity-meta">
                                    ${activity.team_name || 'Aucune équipe'} • ${timeAgo}
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += '<div class="univga-no-data">Aucune activité récente</div>';
            }
            
            html += `
                        </div>
                    </div>
                </div>
            `;
            
            $container.html(html);
        };
        
        /**
         * Populate team filter
         */
        UnivgaDashboard.populateTeamFilterForCerts = function(teams) {
            const $select = $('#compliance-team-filter');
            $select.find('option:not(:first)').remove();
            
            if (teams && teams.length > 0) {
                teams.forEach(team => {
                    $select.append(`<option value="${team.id}">${team.name}</option>`);
                });
            }
        };
        
        /**
         * Utility functions
         */
        UnivgaDashboard.getAlertClass = function(status) {
            const classes = {
                'critical': 'alert-critical',
                'warning': 'alert-warning',
                'attention': 'alert-attention',
                'good': 'alert-good'
            };
            return classes[status] || 'alert-good';
        };
        
        UnivgaDashboard.getAlertBadge = function(status) {
            const badges = {
                'critical': '<span class="univga-alert-badge alert-critical">Critique</span>',
                'warning': '<span class="univga-alert-badge alert-warning">Attention</span>',
                'attention': '<span class="univga-alert-badge alert-attention">À surveiller</span>',
                'good': '<span class="univga-alert-badge alert-good">Conforme</span>'
            };
            return badges[status] || '';
        };
        
        UnivgaDashboard.getComplianceColor = function(percentage) {
            if (percentage >= 90) return '#10b981';
            if (percentage >= 70) return '#f59e0b';
            if (percentage >= 50) return '#ef4444';
            return '#dc2626';
        };
        
        UnivgaDashboard.getComplianceClass = function(percentage) {
            if (percentage >= 90) return 'compliance-excellent';
            if (percentage >= 70) return 'compliance-good';
            if (percentage >= 50) return 'compliance-fair';
            return 'compliance-poor';
        };
        
        /**
         * Placeholder functions for actions
         */
        UnivgaDashboard.assignCertifications = function(userId) {
            this.showNotice('info', 'Attribution de certification en développement');
        };
        
        UnivgaDashboard.editCertification = function(certId) {
            this.showNotice('info', 'Éditeur de certification en développement');
        };
        
        UnivgaDashboard.awardCertification = function(certId) {
            this.showNotice('info', 'Attribution manuelle de certification en développement');
        };
        
        // Event handlers
        $(document).on('change', '#certification-type-filter, #compliance-team-filter', function() {
            if (UnivgaDashboard && UnivgaDashboard.applyCertificationFilters) {
                UnivgaDashboard.applyCertificationFilters();
            }
        });
        
        $(document).on('input', '#certification-search', function() {
            if (UnivgaDashboard && UnivgaDashboard.applyCertificationFilters) {
                clearTimeout(UnivgaDashboard.certSearchTimeout);
                UnivgaDashboard.certSearchTimeout = setTimeout(function() {
                    UnivgaDashboard.applyCertificationFilters();
                }, 500);
            }
        });
        
        $(document).on('click', '#create-certification, #create-first-certification', function() {
            if (UnivgaDashboard && UnivgaDashboard.showNotice) {
                UnivgaDashboard.showNotice('info', 'Créateur de certification en développement');
            }
        });
        
        $(document).on('click', '#compliance-report', function() {
            if (UnivgaDashboard && UnivgaDashboard.showNotice) {
                UnivgaDashboard.showNotice('info', 'Rapport de conformité en développement');
            }
        });
        
        $(document).on('click', '.univga-certification-action', function() {
            const action = $(this).data('action');
            const certId = $(this).data('cert-id');
            
            switch (action) {
                case 'edit':
                    UnivgaDashboard.editCertification(certId);
                    break;
                case 'award':
                    UnivgaDashboard.awardCertification(certId);
                    break;
                case 'delete':
                    if (confirm('Êtes-vous sûr de vouloir supprimer cette certification ?')) {
                        UnivgaDashboard.showNotice('info', 'Suppression de certification en développement');
                    }
                    break;
            }
        });
        
        /**
         * Apply certification filters
         */
        UnivgaDashboard.applyCertificationFilters = function() {
            this.certificationsFilters = {
                type: $('#certification-type-filter').val(),
                search: $('#certification-search').val(),
                team_id: $('#compliance-team-filter').val() || null
            };
            this.loadCertifications();
        };
        
        // Load certifications when tab is clicked
        $(document).on('click', '[data-tab="certifications"]', function() {
            setTimeout(function() {
                if (UnivgaDashboard && UnivgaDashboard.loadCertifications) {
                    UnivgaDashboard.loadCertifications();
                }
            }, 100);
        });
    }
});