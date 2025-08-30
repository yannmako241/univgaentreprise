/**
 * Analytics Tab JavaScript for UNIVGA Dashboard
 */
jQuery(document).ready(function($) {
    // Extend UnivgaDashboard with analytics functionality
    if (typeof UnivgaDashboard !== 'undefined') {
        
        // Analytics data storage
        UnivgaDashboard.analyticsData = null;
        
        /**
         * Load analytics data
         */
        UnivgaDashboard.loadAnalytics = function() {
            const self = this;
            const timeframe = $('#analytics-timeframe').val() || '30';
            
            // Show loading state
            this.showAnalyticsLoading();
            
            $.ajax({
                url: univga_dashboard.rest_url + 'organizations/' + this.orgId + '/analytics',
                method: 'GET',
                data: { timeframe: timeframe },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(data) {
                self.analyticsData = data;
                self.renderAnalytics(data);
                self.hideAnalyticsLoading();
            })
            .fail(function(xhr) {
                console.error('Failed to load analytics:', xhr);
                self.hideAnalyticsLoading();
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Échec du chargement des analytics';
                $('.univga-analytics-metrics').html('<div class="univga-notice univga-notice-error">' + errorMsg + '</div>');
            });
        };
        
        /**
         * Show analytics loading state
         */
        UnivgaDashboard.showAnalyticsLoading = function() {
            $('.univga-metric-card').addClass('loading');
            $('.univga-metric-value').text('--');
            $('.univga-chart-loading').show();
            $('.univga-metric-change').text('');
        };
        
        /**
         * Hide analytics loading state
         */
        UnivgaDashboard.hideAnalyticsLoading = function() {
            $('.univga-metric-card').removeClass('loading');
            $('.univga-chart-loading').hide();
        };
        
        /**
         * Render analytics data
         */
        UnivgaDashboard.renderAnalytics = function(data) {
            this.renderMetricCards(data.metrics);
            this.renderCompletionRatesChart(data.completion_rates);
            this.renderEngagementChart(data.engagement_timeline);
            this.renderTeamPerformanceChart(data.team_performance);
            this.renderTrendingCourses(data.trending_courses);
            this.renderSkillGapsInsights(data.skill_gaps);
            this.renderLearnerInsights(data.at_risk_learners);
        };
        
        /**
         * Render metric cards
         */
        UnivgaDashboard.renderMetricCards = function(metrics) {
            const cards = $('.univga-metric-card');
            
            // Completion Rate
            $(cards[0]).find('.univga-metric-value').text(metrics.completion_rate + '%');
            $(cards[0]).find('.univga-metric-change').html(this.generateChangeIndicator(metrics.completion_rate, 75));
            
            // Active Learners
            $(cards[1]).find('.univga-metric-value').text(metrics.active_learners);
            $(cards[1]).find('.univga-metric-change').html(this.generateChangeIndicator(metrics.active_learners, 0, 'count'));
            
            // Average Study Time
            const hours = Math.floor(metrics.avg_study_time / 60);
            const minutes = metrics.avg_study_time % 60;
            const timeText = hours > 0 ? hours + 'h ' + minutes + 'm' : minutes + 'm';
            $(cards[2]).find('.univga-metric-value').text(timeText);
            $(cards[2]).find('.univga-metric-change').html(this.generateChangeIndicator(metrics.avg_study_time, 120, 'time'));
            
            // Skill Gaps
            $(cards[3]).find('.univga-metric-value').text(metrics.skill_gaps);
            $(cards[3]).find('.univga-metric-change').html(this.generateChangeIndicator(metrics.skill_gaps, 0, 'gaps'));
        };
        
        /**
         * Generate change indicator
         */
        UnivgaDashboard.generateChangeIndicator = function(current, baseline, type) {
            let change = 0;
            let icon = '';
            let className = '';
            let text = '';
            
            if (type === 'count') {
                change = current - baseline;
                text = Math.abs(change) + ' ce mois';
            } else if (type === 'time') {
                change = current - baseline;
                text = Math.abs(change) + 'min de plus';
            } else if (type === 'gaps') {
                change = baseline - current; // Inverse pour les gaps (moins c'est mieux)
                text = current === 0 ? 'Aucun gap identifié' : current + ' lacunes détectées';
            } else {
                change = current - baseline;
                text = Math.abs(change) + '% ce mois';
            }
            
            if (change > 0) {
                icon = '▲';
                className = type === 'gaps' ? 'negative' : 'positive';
            } else if (change < 0) {
                icon = '▼';
                className = type === 'gaps' ? 'positive' : 'negative';
            } else {
                icon = '●';
                className = 'neutral';
                text = 'Stable';
            }
            
            return '<span class="univga-change-indicator ' + className + '">' + icon + ' ' + text + '</span>';
        };
        
        /**
         * Render completion rates chart
         */
        UnivgaDashboard.renderCompletionRatesChart = function(completionData) {
            if (!completionData || completionData.length === 0) {
                $('#completion-rates-chart').parent().html('<div class="univga-no-data">Aucune donnée de cours disponible</div>');
                return;
            }
            
            // Create simple bar chart representation
            let html = '<div class="univga-chart-bars">';
            const maxRate = Math.max(...completionData.map(item => parseFloat(item.completion_rate || 0)));
            
            completionData.slice(0, 5).forEach((course, index) => {
                const rate = parseFloat(course.completion_rate || 0);
                const height = maxRate > 0 ? (rate / maxRate) * 100 : 0;
                const color = this.getCompletionColor(rate);
                
                html += `
                    <div class="univga-chart-bar">
                        <div class="univga-bar-container">
                            <div class="univga-bar" style="height: ${height}%; background-color: ${color}"></div>
                        </div>
                        <div class="univga-bar-label">
                            <div class="univga-bar-title">${course.course_name || 'Cours ' + (index + 1)}</div>
                            <div class="univga-bar-value">${rate}%</div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            $('#completion-rates-chart').parent().html(html);
        };
        
        /**
         * Render engagement timeline chart
         */
        UnivgaDashboard.renderEngagementChart = function(engagementData) {
            if (!engagementData || engagementData.length === 0) {
                $('#engagement-timeline-chart').parent().html('<div class="univga-no-data">Aucune donnée d\'engagement disponible</div>');
                return;
            }
            
            // Create simple line chart representation
            let html = '<div class="univga-chart-timeline">';
            const maxValue = Math.max(...engagementData.map(item => parseInt(item.activity_count || 0)));
            
            engagementData.slice(-7).forEach((day, index) => {
                const count = parseInt(day.activity_count || 0);
                const height = maxValue > 0 ? (count / maxValue) * 100 : 0;
                const date = new Date(day.date || Date.now() - (6-index) * 24 * 60 * 60 * 1000);
                const dayName = date.toLocaleDateString('fr-FR', { weekday: 'short' });
                
                html += `
                    <div class="univga-timeline-point">
                        <div class="univga-point-container">
                            <div class="univga-point" style="height: ${height}%; bottom: 0;"></div>
                        </div>
                        <div class="univga-point-label">
                            <div class="univga-point-day">${dayName}</div>
                            <div class="univga-point-value">${count}</div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            $('#engagement-timeline-chart').parent().html(html);
        };
        
        /**
         * Render team performance chart
         */
        UnivgaDashboard.renderTeamPerformanceChart = function(teamData) {
            const $container = $('#team-performance-chart').parent();
            
            if (!teamData || teamData.length === 0) {
                $container.html('<div class="univga-no-data">Aucune donnée d\'équipe disponible</div>');
                return;
            }
            
            let html = '<div class="univga-team-performance">';
            
            teamData.forEach((team, index) => {
                const color = this.getTeamColor(index);
                html += `
                    <div class="univga-team-card">
                        <div class="univga-team-header">
                            <div class="univga-team-name">${team.name}</div>
                            <div class="univga-team-members">${team.members} membres</div>
                        </div>
                        <div class="univga-team-stats">
                            <div class="univga-team-stat">
                                <span class="univga-stat-label">Inscriptions:</span>
                                <span class="univga-stat-value">${team.enrollments}</span>
                            </div>
                            <div class="univga-team-stat">
                                <span class="univga-stat-label">Achèvements:</span>
                                <span class="univga-stat-value">${team.completions}</span>
                            </div>
                        </div>
                        <div class="univga-team-progress">
                            <div class="univga-progress-header">
                                <span>Taux d'achèvement</span>
                                <span>${team.completion_rate}%</span>
                            </div>
                            <div class="univga-progress">
                                <div class="univga-progress-bar" style="width: ${team.completion_rate}%; background-color: ${color}"></div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $container.html(html);
        };
        
        /**
         * Render trending courses
         */
        UnivgaDashboard.renderTrendingCourses = function(coursesData) {
            const $container = $('#trending-courses');
            
            if (!coursesData || coursesData.length === 0) {
                $container.html('<div class="univga-no-data">Aucune donnée de cours disponible</div>');
                return;
            }
            
            let html = '';
            coursesData.slice(0, 5).forEach((course, index) => {
                const trendIcon = index < 2 ? '🔥' : index < 4 ? '📈' : '📊';
                html += `
                    <div class="univga-trending-item">
                        <div class="univga-trending-icon">${trendIcon}</div>
                        <div class="univga-trending-content">
                            <div class="univga-trending-title">${course.course_name || 'Cours'}</div>
                            <div class="univga-trending-stats">
                                ${course.started || 0} inscrits • 
                                <span class="univga-trending-metric">${course.completion_rate || 0}% terminé</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $container.html(html);
        };
        
        /**
         * Render skill gaps insights
         */
        UnivgaDashboard.renderSkillGapsInsights = function(skillGaps) {
            const $container = $('#learner-insights');
            
            if (!skillGaps || skillGaps.length === 0) {
                $container.html('<div class="univga-insight-positive">✅ Aucune lacune de compétences majeure détectée dans votre organisation.</div>');
                return;
            }
            
            let html = '<div class="univga-skills-analysis">';
            skillGaps.slice(0, 3).forEach((gap, index) => {
                html += `
                    <div class="univga-skill-gap">
                        <div class="univga-gap-severity ${gap.severity || 'medium'}"></div>
                        <div class="univga-gap-content">
                            <div class="univga-gap-title">${gap.skill_name || 'Compétence'}</div>
                            <div class="univga-gap-description">${gap.description || 'Amélioration recommandée'}</div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            $container.html(html);
        };
        
        /**
         * Render learner insights
         */
        UnivgaDashboard.renderLearnerInsights = function(atRiskLearners) {
            const $container = $('#skill-gaps-analysis');
            
            if (!atRiskLearners || atRiskLearners.length === 0) {
                $container.html('<div class="univga-insight-positive">✅ Tous les apprenants progressent normalement.</div>');
                return;
            }
            
            let html = '<div class="univga-at-risk-analysis">';
            html += '<div class="univga-risk-summary">⚠️ ' + atRiskLearners.length + ' apprenants nécessitent une attention particulière :</div>';
            
            atRiskLearners.slice(0, 5).forEach((learner, index) => {
                html += `
                    <div class="univga-risk-learner">
                        <div class="univga-learner-name">${learner.name || 'Apprenant'}</div>
                        <div class="univga-learner-issue">${learner.issue || 'Progrès lent détecté'}</div>
                    </div>
                `;
            });
            
            html += '</div>';
            $container.html(html);
        };
        
        /**
         * Utility functions
         */
        UnivgaDashboard.getCompletionColor = function(rate) {
            if (rate >= 80) return '#10b981';
            if (rate >= 60) return '#f59e0b';
            if (rate >= 40) return '#ef4444';
            return '#6b7280';
        };
        
        UnivgaDashboard.getTeamColor = function(index) {
            const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
            return colors[index % colors.length];
        };
        
        // Event handlers
        $(document).on('change', '#analytics-timeframe', function() {
            if (UnivgaDashboard && UnivgaDashboard.loadAnalytics) {
                UnivgaDashboard.loadAnalytics();
            }
        });
        
        $(document).on('click', '#refresh-analytics', function() {
            if (UnivgaDashboard && UnivgaDashboard.loadAnalytics) {
                UnivgaDashboard.loadAnalytics();
            }
        });
        
        // Load analytics when analytics tab is clicked
        $(document).on('click', '[data-tab="analytics"]', function() {
            setTimeout(function() {
                if (UnivgaDashboard && UnivgaDashboard.loadAnalytics) {
                    UnivgaDashboard.loadAnalytics();
                }
            }, 100);
        });
    }
});