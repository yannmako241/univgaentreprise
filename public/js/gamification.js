/**
 * Gamification Tab JavaScript for UNIVGA Dashboard
 */
jQuery(document).ready(function($) {
    // Extend UnivgaDashboard with gamification functionality
    if (typeof UnivgaDashboard !== 'undefined') {
        
        // Gamification data storage
        UnivgaDashboard.gamificationData = null;
        UnivgaDashboard.gamificationFilters = {
            period: 'month',
            team_id: null
        };
        
        /**
         * Load gamification data
         */
        UnivgaDashboard.loadGamification = function() {
            const self = this;
            
            // Show loading state
            this.showGamificationLoading();
            
            // Build query parameters
            const params = new URLSearchParams();
            params.append('period', this.gamificationFilters.period);
            if (this.gamificationFilters.team_id) {
                params.append('team_id', this.gamificationFilters.team_id);
            }
            
            const queryString = params.toString();
            const url = univga_dashboard.rest_url + 'organizations/' + this.orgId + '/gamification' + 
                       (queryString ? '?' + queryString : '');
            
            $.ajax({
                url: url,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(data) {
                self.gamificationData = data;
                self.renderGamification(data);
                self.hideGamificationLoading();
            })
            .fail(function(xhr) {
                console.error('Failed to load gamification:', xhr);
                self.hideGamificationLoading();
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Échec du chargement de la gamification';
                $('#leaderboard-container').html('<div class="univga-notice univga-notice-error">' + errorMsg + '</div>');
            });
        };
        
        /**
         * Show gamification loading state
         */
        UnivgaDashboard.showGamificationLoading = function() {
            $('#leaderboard-container').html('<div class="loading">Chargement du classement...</div>');
            $('#badges-grid').html('<div class="loading">Chargement des badges...</div>');
            $('.univga-stat-value').text('--');
        };
        
        /**
         * Hide gamification loading state
         */
        UnivgaDashboard.hideGamificationLoading = function() {
            // Loading handled by render functions
        };
        
        /**
         * Render gamification data
         */
        UnivgaDashboard.renderGamification = function(data) {
            this.renderGamificationStats(data.stats);
            this.renderLeaderboard(data.leaderboard, data.team_leaderboard);
            this.renderBadges(data.badges, data.recent_badges);
            this.populateTeamFilter(data.teams);
        };
        
        /**
         * Render gamification statistics
         */
        UnivgaDashboard.renderGamificationStats = function(stats) {
            $('#total-points').text(this.formatNumber(stats.total_points));
            $('#total-badges').text(stats.total_badges);
            $('#active-participants').text(stats.active_participants);
            $('#avg-engagement').text(stats.engagement_score + '%');
        };
        
        /**
         * Render leaderboard
         */
        UnivgaDashboard.renderLeaderboard = function(userLeaderboard, teamLeaderboard) {
            const $container = $('#leaderboard-container');
            
            if (!userLeaderboard || userLeaderboard.length === 0) {
                $container.html(`
                    <div class="univga-empty-state">
                        <div class="univga-empty-icon">🏆</div>
                        <h3>Aucun participant</h3>
                        <p>Les classements apparaîtront dès que vos membres commenceront à gagner des points.</p>
                    </div>
                `);
                return;
            }
            
            let html = `
                <div class="univga-leaderboard-tabs">
                    <button type="button" class="univga-tab-btn active" data-leaderboard-type="users">Classement Individuel</button>
                    <button type="button" class="univga-tab-btn" data-leaderboard-type="teams">Classement Équipes</button>
                </div>
                
                <div class="univga-leaderboard-content">
                    <div class="univga-leaderboard-tab active" id="users-leaderboard">
                        <div class="univga-leaderboard-list">
            `;
            
            // Individual leaderboard
            userLeaderboard.forEach((user, index) => {
                const rankClass = index < 3 ? `rank-${index + 1}` : '';
                const medalIcon = this.getRankMedal(index + 1);
                const levelBadge = this.getLevelBadge(user.level);
                
                html += `
                    <div class="univga-leaderboard-item ${rankClass}">
                        <div class="univga-rank-section">
                            <div class="univga-rank-number">${medalIcon || user.rank}</div>
                        </div>
                        
                        <div class="univga-user-section">
                            <div class="univga-user-avatar">
                                ${this.getUserInitials(user.display_name)}
                            </div>
                            <div class="univga-user-info">
                                <div class="univga-user-name">${user.display_name}</div>
                                <div class="univga-user-meta">
                                    ${levelBadge}
                                    ${user.team_name ? '<span class="univga-team-badge">' + user.team_name + '</span>' : ''}
                                </div>
                            </div>
                        </div>
                        
                        <div class="univga-stats-section">
                            <div class="univga-stat">
                                <span class="univga-stat-value">${this.formatNumber(user.total_points)}</span>
                                <span class="univga-stat-label">Points</span>
                            </div>
                            <div class="univga-stat">
                                <span class="univga-stat-value">${user.badge_count}</span>
                                <span class="univga-stat-label">Badges</span>
                            </div>
                            <div class="univga-stat">
                                <span class="univga-stat-value">${user.current_streak}</span>
                                <span class="univga-stat-label">Série</span>
                            </div>
                        </div>
                        
                        <div class="univga-activity-section">
                            <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.showUserActivity('${user.user_id}')">
                                Activité
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += `
                        </div>
                    </div>
                    
                    <div class="univga-leaderboard-tab" id="teams-leaderboard">
                        <div class="univga-team-leaderboard">
            `;
            
            // Team leaderboard
            if (teamLeaderboard && teamLeaderboard.length > 0) {
                teamLeaderboard.forEach((team, index) => {
                    const rankClass = index < 3 ? `rank-${index + 1}` : '';
                    const medalIcon = this.getRankMedal(index + 1);
                    
                    html += `
                        <div class="univga-team-leaderboard-item ${rankClass}">
                            <div class="univga-team-rank">
                                <div class="univga-rank-number">${medalIcon || (index + 1)}</div>
                            </div>
                            
                            <div class="univga-team-info">
                                <h4 class="univga-team-name">${team.name}</h4>
                                <div class="univga-team-members">${team.member_count} membres</div>
                            </div>
                            
                            <div class="univga-team-stats">
                                <div class="univga-team-stat">
                                    <span class="univga-stat-value">${this.formatNumber(team.total_points)}</span>
                                    <span class="univga-stat-label">Points Totaux</span>
                                </div>
                                <div class="univga-team-stat">
                                    <span class="univga-stat-value">${Math.round(team.avg_points)}</span>
                                    <span class="univga-stat-label">Moy. par Membre</span>
                                </div>
                                <div class="univga-team-stat">
                                    <span class="univga-stat-value">${team.badge_count}</span>
                                    <span class="univga-stat-label">Badges</span>
                                </div>
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
                </div>
            `;
            
            $container.html(html);
        };
        
        /**
         * Render badges
         */
        UnivgaDashboard.renderBadges = function(badges, recentBadges) {
            const $container = $('#badges-grid');
            
            if (!badges || badges.length === 0) {
                $container.html(`
                    <div class="univga-empty-state">
                        <div class="univga-empty-icon">🏅</div>
                        <h3>Aucun badge créé</h3>
                        <p>Créez des badges pour récompenser les accomplissements de vos équipes.</p>
                        <button type="button" class="univga-btn univga-btn-primary" id="create-first-badge">
                            Créer un Badge
                        </button>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="univga-badges-container">';
            
            // Recent badges section
            if (recentBadges && recentBadges.length > 0) {
                html += `
                    <div class="univga-recent-badges">
                        <h5>Badges Récemment Obtenus</h5>
                        <div class="univga-recent-badges-list">
                `;
                
                recentBadges.slice(0, 5).forEach(badge => {
                    const timeAgo = this.timeAgo(badge.earned_at);
                    html += `
                        <div class="univga-recent-badge-item">
                            <div class="univga-badge-icon" style="background-color: ${badge.color || '#3b82f6'}">
                                ${this.getBadgeIcon(badge.icon_url)}
                            </div>
                            <div class="univga-recent-badge-info">
                                <div class="univga-badge-name">${badge.badge_name}</div>
                                <div class="univga-badge-earner">${badge.user_name} • ${timeAgo}</div>
                            </div>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            // All badges grid
            html += `
                <div class="univga-all-badges">
                    <h5>Tous les Badges (${badges.length})</h5>
                    <div class="univga-badges-grid-list">
            `;
            
            badges.forEach(badge => {
                const rarity = this.getBadgeRarity(badge.awarded_count);
                
                html += `
                    <div class="univga-badge-card" data-badge-id="${badge.id}">
                        <div class="univga-badge-header">
                            <div class="univga-badge-icon-large" style="background-color: ${badge.color || '#3b82f6'}">
                                ${this.getBadgeIcon(badge.icon_url)}
                            </div>
                            <div class="univga-badge-rarity ${rarity.class}">${rarity.label}</div>
                        </div>
                        
                        <div class="univga-badge-content">
                            <h4 class="univga-badge-title">${badge.name}</h4>
                            <p class="univga-badge-description">${badge.description || 'Aucune description'}</p>
                            
                            <div class="univga-badge-stats">
                                <div class="univga-badge-stat">
                                    <span class="univga-stat-value">${badge.awarded_count}</span>
                                    <span class="univga-stat-label">Obtenus</span>
                                </div>
                                <div class="univga-badge-stat">
                                    <span class="univga-stat-value">${badge.points_value || 0}</span>
                                    <span class="univga-stat-label">Points</span>
                                </div>
                            </div>
                            
                            <div class="univga-badge-criteria">
                                <small>Critères: ${this.formatBadgeCriteria(badge.criteria)}</small>
                            </div>
                        </div>
                        
                        <div class="univga-badge-actions">
                            <button type="button" class="univga-btn univga-btn-small univga-btn-secondary" onclick="UnivgaDashboard.editBadge('${badge.id}')">
                                Modifier
                            </button>
                            <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.awardBadge('${badge.id}')">
                                Attribuer
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            </div>`;
            
            $container.html(html);
        };
        
        /**
         * Populate team filter
         */
        UnivgaDashboard.populateTeamFilter = function(teams) {
            const $select = $('#leaderboard-team');
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
        UnivgaDashboard.getRankMedal = function(rank) {
            const medals = {
                1: '🥇',
                2: '🥈', 
                3: '🥉'
            };
            return medals[rank] || null;
        };
        
        UnivgaDashboard.getLevelBadge = function(level) {
            const levelNames = {
                1: 'Novice', 2: 'Apprenti', 3: 'Pratiquant', 4: 'Compétent', 5: 'Expert',
                6: 'Maître', 7: 'Sage', 8: 'Légende', 9: 'Héros', 10: 'Champion'
            };
            const levelName = levelNames[level] || 'Niveau ' + level;
            return `<span class="univga-level-badge level-${Math.min(level, 10)}">${levelName} ${level}</span>`;
        };
        
        UnivgaDashboard.getUserInitials = function(name) {
            return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        };
        
        UnivgaDashboard.getBadgeIcon = function(iconUrl) {
            if (iconUrl && iconUrl.startsWith('http')) {
                return `<img src="${iconUrl}" alt="Badge" width="24" height="24">`;
            }
            return '🏆'; // Default emoji
        };
        
        UnivgaDashboard.getBadgeRarity = function(awardedCount) {
            if (awardedCount === 0) return { class: 'rarity-legendary', label: 'Légendaire' };
            if (awardedCount <= 5) return { class: 'rarity-epic', label: 'Épique' };
            if (awardedCount <= 15) return { class: 'rarity-rare', label: 'Rare' };
            return { class: 'rarity-common', label: 'Commun' };
        };
        
        UnivgaDashboard.formatBadgeCriteria = function(criteria) {
            if (!criteria) return 'Non défini';
            try {
                const parsed = JSON.parse(criteria);
                return parsed.description || 'Critères personnalisés';
            } catch (e) {
                return criteria;
            }
        };
        
        UnivgaDashboard.timeAgo = function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Il y a quelques secondes';
            if (diffInSeconds < 3600) return `Il y a ${Math.floor(diffInSeconds / 60)} min`;
            if (diffInSeconds < 86400) return `Il y a ${Math.floor(diffInSeconds / 3600)}h`;
            return `Il y a ${Math.floor(diffInSeconds / 86400)} jours`;
        };
        
        UnivgaDashboard.formatNumber = function(num) {
            if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
            if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
            return num.toString();
        };
        
        /**
         * Show user activity modal (placeholder)
         */
        UnivgaDashboard.showUserActivity = function(userId) {
            this.showNotice('info', 'Fonctionnalité d\'activité utilisateur en développement');
        };
        
        /**
         * Edit badge modal (placeholder)
         */
        UnivgaDashboard.editBadge = function(badgeId) {
            this.showNotice('info', 'Éditeur de badge en développement');
        };
        
        /**
         * Award badge modal (placeholder)
         */
        UnivgaDashboard.awardBadge = function(badgeId) {
            this.showNotice('info', 'Attribution de badge en développement');
        };
        
        // Event handlers
        $(document).on('change', '#leaderboard-period, #leaderboard-team', function() {
            if (UnivgaDashboard && UnivgaDashboard.updateLeaderboardFilters) {
                UnivgaDashboard.updateLeaderboardFilters();
            }
        });
        
        $(document).on('click', '[data-leaderboard-type]', function() {
            const type = $(this).data('leaderboard-type');
            $('.univga-tab-btn').removeClass('active');
            $('.univga-leaderboard-tab').removeClass('active');
            $(this).addClass('active');
            $('#' + type + '-leaderboard').addClass('active');
        });
        
        $(document).on('click', '#create-badge, #create-first-badge', function() {
            if (UnivgaDashboard && UnivgaDashboard.showNotice) {
                UnivgaDashboard.showNotice('info', 'Créateur de badge en développement');
            }
        });
        
        $(document).on('click', '#manage-rewards', function() {
            if (UnivgaDashboard && UnivgaDashboard.showNotice) {
                UnivgaDashboard.showNotice('info', 'Gestionnaire de récompenses en développement');
            }
        });
        
        /**
         * Update leaderboard filters
         */
        UnivgaDashboard.updateLeaderboardFilters = function() {
            this.gamificationFilters.period = $('#leaderboard-period').val();
            this.gamificationFilters.team_id = $('#leaderboard-team').val() || null;
            this.loadGamification();
        };
        
        // Load gamification when tab is clicked
        $(document).on('click', '[data-tab="gamification"]', function() {
            setTimeout(function() {
                if (UnivgaDashboard && UnivgaDashboard.loadGamification) {
                    UnivgaDashboard.loadGamification();
                }
            }, 100);
        });
    }
});