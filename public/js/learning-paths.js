/**
 * Learning Paths Tab JavaScript for UNIVGA Dashboard
 */
jQuery(document).ready(function($) {
    // Extend UnivgaDashboard with learning paths functionality
    if (typeof UnivgaDashboard !== 'undefined') {
        
        // Learning paths data storage
        UnivgaDashboard.learningPathsData = null;
        UnivgaDashboard.learningPathsFilters = {};
        
        /**
         * Load learning paths data
         */
        UnivgaDashboard.loadLearningPaths = function() {
            const self = this;
            
            // Show loading state
            this.showLearningPathsLoading();
            
            // Build query parameters
            const params = new URLSearchParams();
            if (this.learningPathsFilters.status) {
                params.append('status', this.learningPathsFilters.status);
            }
            if (this.learningPathsFilters.role) {
                params.append('role', this.learningPathsFilters.role);
            }
            if (this.learningPathsFilters.search) {
                params.append('search', this.learningPathsFilters.search);
            }
            
            const queryString = params.toString();
            const url = univga_dashboard.rest_url + 'organizations/' + this.orgId + '/learning-paths' + 
                       (queryString ? '?' + queryString : '');
            
            $.ajax({
                url: url,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(data) {
                self.learningPathsData = data;
                self.renderLearningPaths(data);
                self.hideLearningPathsLoading();
            })
            .fail(function(xhr) {
                console.error('Failed to load learning paths:', xhr);
                self.hideLearningPathsLoading();
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Échec du chargement des parcours d\'apprentissage';
                $('#learning-paths-grid').html('<div class="univga-notice univga-notice-error">' + errorMsg + '</div>');
            });
        };
        
        /**
         * Show learning paths loading state
         */
        UnivgaDashboard.showLearningPathsLoading = function() {
            $('#learning-paths-grid').html('<div class="loading">Chargement des parcours d\'apprentissage...</div>');
            $('.univga-stat-value').text('--');
        };
        
        /**
         * Hide learning paths loading state
         */
        UnivgaDashboard.hideLearningPathsLoading = function() {
            // Loading handled by render function
        };
        
        /**
         * Render learning paths data
         */
        UnivgaDashboard.renderLearningPaths = function(data) {
            this.renderLearningPathsStats(data.stats);
            this.renderLearningPathsGrid(data.paths);
        };
        
        /**
         * Render learning paths statistics
         */
        UnivgaDashboard.renderLearningPathsStats = function(stats) {
            $('#total-paths').text(stats.total_paths || 0);
            $('#active-learners').text(stats.total_learners || 0);
            $('#completion-rate').text((stats.avg_completion || 0).toFixed(1) + '%');
            $('#avg-duration').text((stats.avg_duration || 0) + 'h');
        };
        
        /**
         * Render learning paths grid
         */
        UnivgaDashboard.renderLearningPathsGrid = function(paths) {
            const $container = $('#learning-paths-grid');
            
            if (!paths || paths.length === 0) {
                $container.html(`
                    <div class="univga-empty-state">
                        <div class="univga-empty-icon">📚</div>
                        <h3>Aucun parcours d'apprentissage</h3>
                        <p>Créez votre premier parcours d'apprentissage pour organiser la formation de vos équipes.</p>
                        <button type="button" class="univga-btn univga-btn-primary" id="create-first-path">
                            Créer un Parcours
                        </button>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="univga-paths-grid">';
            
            paths.forEach(path => {
                const statusClass = this.getPathStatusClass(path.status);
                const difficultyColor = this.getDifficultyColor(path.difficulty_level);
                
                html += `
                    <div class="univga-path-card" data-path-id="${path.id}">
                        <div class="univga-path-header">
                            <div class="univga-path-title-section">
                                <h4 class="univga-path-title">${path.name}</h4>
                                <div class="univga-path-badges">
                                    <span class="univga-path-status ${statusClass}">${this.translateStatus(path.status)}</span>
                                    <span class="univga-path-difficulty" style="background-color: ${difficultyColor}">${this.translateDifficulty(path.difficulty_level)}</span>
                                </div>
                            </div>
                            <div class="univga-path-actions">
                                <button type="button" class="univga-path-action" data-action="edit" data-path-id="${path.id}" title="Modifier">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L8.5 11.207l-3 1a.5.5 0 0 1-.65-.65l1-3L12.146.146z"/>
                                    </svg>
                                </button>
                                <button type="button" class="univga-path-action" data-action="assign" data-path-id="${path.id}" title="Assigner">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                    </svg>
                                </button>
                                <button type="button" class="univga-path-action" data-action="delete" data-path-id="${path.id}" title="Supprimer">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="univga-path-description">
                            ${path.description || 'Aucune description disponible'}
                        </div>
                        
                        <div class="univga-path-courses">
                            <div class="univga-courses-header">
                                <span class="univga-courses-count">${path.course_count || 0} cours</span>
                                <span class="univga-courses-duration">${path.estimated_duration_formatted}</span>
                            </div>
                            <div class="univga-courses-sequence">
                                ${this.renderCourseSequence(path.courses)}
                            </div>
                        </div>
                        
                        <div class="univga-path-stats">
                            <div class="univga-path-stat">
                                <span class="univga-stat-label">Assignés</span>
                                <span class="univga-stat-value">${path.completion_stats?.total_assigned || 0}</span>
                            </div>
                            <div class="univga-path-stat">
                                <span class="univga-stat-label">En cours</span>
                                <span class="univga-stat-value">${path.completion_stats?.in_progress_count || 0}</span>
                            </div>
                            <div class="univga-path-stat">
                                <span class="univga-stat-label">Terminés</span>
                                <span class="univga-stat-value">${path.completion_stats?.completed_count || 0}</span>
                            </div>
                        </div>
                        
                        <div class="univga-path-progress">
                            <div class="univga-progress-header">
                                <span>Taux d'achèvement</span>
                                <span>${path.completion_rate || 0}%</span>
                            </div>
                            <div class="univga-progress">
                                <div class="univga-progress-bar" style="width: ${path.completion_rate || 0}%"></div>
                            </div>
                        </div>
                        
                        <div class="univga-path-footer">
                            <div class="univga-path-meta">
                                <span class="univga-path-role">${this.translateRole(path.job_role)}</span>
                                <span class="univga-path-created">Créé par ${path.created_by_name || 'Inconnu'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $container.html(html);
        };
        
        /**
         * Render course sequence
         */
        UnivgaDashboard.renderCourseSequence = function(courses) {
            if (!courses || courses.length === 0) {
                return '<span class="univga-no-courses">Aucun cours configuré</span>';
            }
            
            let html = '<div class="univga-sequence-list">';
            courses.slice(0, 3).forEach((course, index) => {
                html += `
                    <div class="univga-sequence-item">
                        <span class="univga-sequence-number">${index + 1}</span>
                        <span class="univga-sequence-title">${course.course_title || 'Cours'}</span>
                    </div>
                `;
            });
            
            if (courses.length > 3) {
                html += `<div class="univga-sequence-more">+${courses.length - 3} autres</div>`;
            }
            
            html += '</div>';
            return html;
        };
        
        /**
         * Translation helpers
         */
        UnivgaDashboard.translateStatus = function(status) {
            const translations = {
                'active': 'Actif',
                'draft': 'Brouillon',
                'archived': 'Archivé',
                'paused': 'En pause'
            };
            return translations[status] || status;
        };
        
        UnivgaDashboard.translateDifficulty = function(difficulty) {
            const translations = {
                'beginner': 'Débutant',
                'intermediate': 'Intermédiaire',
                'advanced': 'Avancé',
                'expert': 'Expert'
            };
            return translations[difficulty] || difficulty;
        };
        
        UnivgaDashboard.translateRole = function(role) {
            const translations = {
                'developer': 'Développeur',
                'designer': 'Designer',
                'manager': 'Manager',
                'sales': 'Commercial',
                'general': 'Général'
            };
            return translations[role] || role;
        };
        
        /**
         * Utility functions
         */
        UnivgaDashboard.getPathStatusClass = function(status) {
            const classes = {
                'active': 'status-active',
                'draft': 'status-draft',
                'archived': 'status-archived',
                'paused': 'status-paused'
            };
            return classes[status] || 'status-default';
        };
        
        UnivgaDashboard.getDifficultyColor = function(difficulty) {
            const colors = {
                'beginner': '#10b981',
                'intermediate': '#f59e0b',
                'advanced': '#ef4444',
                'expert': '#8b5cf6'
            };
            return colors[difficulty] || '#6b7280';
        };
        
        /**
         * Create learning path modal
         */
        UnivgaDashboard.openCreatePathModal = function() {
            const modalHtml = `
                <div id="create-path-modal" class="univga-modal">
                    <div class="univga-modal-content">
                        <div class="univga-modal-header">
                            <h3>Créer un Nouveau Parcours d'Apprentissage</h3>
                            <button type="button" class="univga-modal-close" aria-label="Fermer">&times;</button>
                        </div>
                        <form id="create-path-form">
                            <div class="univga-form-group">
                                <label for="path-name">Nom du parcours *</label>
                                <input type="text" id="path-name" name="name" required>
                            </div>
                            
                            <div class="univga-form-group">
                                <label for="path-description">Description *</label>
                                <textarea id="path-description" name="description" rows="4" required></textarea>
                            </div>
                            
                            <div class="univga-form-row">
                                <div class="univga-form-group">
                                    <label for="path-role">Rôle cible</label>
                                    <select id="path-role" name="job_role">
                                        <option value="general">Général</option>
                                        <option value="developer">Développeur</option>
                                        <option value="designer">Designer</option>
                                        <option value="manager">Manager</option>
                                        <option value="sales">Commercial</option>
                                    </select>
                                </div>
                                
                                <div class="univga-form-group">
                                    <label for="path-difficulty">Niveau</label>
                                    <select id="path-difficulty" name="difficulty_level">
                                        <option value="beginner">Débutant</option>
                                        <option value="intermediate">Intermédiaire</option>
                                        <option value="advanced">Avancé</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="univga-form-row">
                                <div class="univga-form-group">
                                    <label for="path-duration">Durée estimée (heures)</label>
                                    <input type="number" id="path-duration" name="estimated_duration" min="1" max="200">
                                </div>
                                
                                <div class="univga-form-group">
                                    <div class="univga-checkbox-group">
                                        <input type="checkbox" id="path-mandatory" name="is_mandatory">
                                        <label for="path-mandatory">Parcours obligatoire</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="univga-modal-actions">
                                <button type="button" class="univga-btn univga-btn-secondary" id="cancel-create-path">Annuler</button>
                                <button type="submit" class="univga-btn univga-btn-primary">Créer le Parcours</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            $('#create-path-modal').addClass('show');
        };
        
        /**
         * Handle form submission
         */
        UnivgaDashboard.createLearningPath = function(formData) {
            const self = this;
            
            // Add organization ID
            formData.org_id = this.orgId;
            
            $.ajax({
                url: univga_dashboard.rest_url + 'learning-paths',
                method: 'POST',
                data: formData,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(response) {
                self.showNotice('success', 'Parcours d\'apprentissage créé avec succès !');
                $('#create-path-modal').removeClass('show').remove();
                self.loadLearningPaths(); // Reload the list
            })
            .fail(function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Erreur lors de la création du parcours';
                self.showNotice('error', errorMsg);
            });
        };
        
        // Event handlers
        $(document).on('click', '#create-learning-path, #create-first-path', function() {
            if (UnivgaDashboard && UnivgaDashboard.openCreatePathModal) {
                UnivgaDashboard.openCreatePathModal();
            }
        });
        
        $(document).on('click', '.univga-modal-close, #cancel-create-path', function() {
            $('#create-path-modal').removeClass('show').remove();
        });
        
        $(document).on('submit', '#create-path-form', function(e) {
            e.preventDefault();
            const formData = {};
            $(this).serializeArray().forEach(function(item) {
                formData[item.name] = item.value;
            });
            formData.is_mandatory = $('#path-mandatory').is(':checked');
            
            if (UnivgaDashboard && UnivgaDashboard.createLearningPath) {
                UnivgaDashboard.createLearningPath(formData);
            }
        });
        
        // Filter handlers
        $(document).on('change', '#path-status-filter, #path-difficulty-filter, #path-role-filter', function() {
            if (UnivgaDashboard && UnivgaDashboard.applyFilters) {
                UnivgaDashboard.applyFilters();
            }
        });
        
        $(document).on('input', '#path-search', function() {
            if (UnivgaDashboard && UnivgaDashboard.applySearchFilter) {
                clearTimeout(UnivgaDashboard.searchTimeout);
                UnivgaDashboard.searchTimeout = setTimeout(function() {
                    UnivgaDashboard.applyFilters();
                }, 500);
            }
        });
        
        $(document).on('click', '#reset-filters', function() {
            if (UnivgaDashboard && UnivgaDashboard.resetFilters) {
                UnivgaDashboard.resetFilters();
            }
        });
        
        /**
         * Apply filters
         */
        UnivgaDashboard.applyFilters = function() {
            this.learningPathsFilters = {
                status: $('#path-status-filter').val(),
                role: $('#path-role-filter').val(),
                search: $('#path-search').val()
            };
            this.loadLearningPaths();
        };
        
        /**
         * Reset filters
         */
        UnivgaDashboard.resetFilters = function() {
            $('#path-status-filter, #path-difficulty-filter, #path-role-filter').val('');
            $('#path-search').val('');
            this.learningPathsFilters = {};
            this.loadLearningPaths();
        };
        
        // Load learning paths when tab is clicked
        $(document).on('click', '[data-tab="learning-paths"]', function() {
            setTimeout(function() {
                if (UnivgaDashboard && UnivgaDashboard.loadLearningPaths) {
                    UnivgaDashboard.loadLearningPaths();
                }
            }, 100);
        });
    }
});