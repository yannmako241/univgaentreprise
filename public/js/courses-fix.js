/**
 * Course loading fix for UNIVGA Dashboard
 */
jQuery(document).ready(function($) {
    // Override courses loading functionality
    if (typeof UnivgaDashboard !== 'undefined') {
        // Store original method if it exists
        const originalLoadCourses = UnivgaDashboard.loadCourses;
        
        // Override loadCourses method
        UnivgaDashboard.loadCourses = function() {
            const self = this;
            $('#courses-grid').html('<div class="loading">Chargement des cours...</div>');
            
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
                $('#courses-grid').html('<div class="univga-notice univga-notice-error">' + errorMsg + '</div>');
            });
        };
        
        // Add renderCourses method
        UnivgaDashboard.renderCourses = function(courses) {
            const $grid = $('#courses-grid');
            
            if (!courses || courses.length === 0) {
                $grid.html('<div class="univga-notice univga-notice-info">Aucun cours disponible pour cette organisation.</div>');
                return;
            }
            
            let html = '<div class="univga-courses-grid">';
            courses.forEach(function(course) {
                const thumbnail = course.thumbnail || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDMwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjZjNmNGY2Ii8+CjxwYXRoIGQ9Ik0xMjAgODBIMTgwVjEyMEgxMjBWODBaIiBmaWxsPSIjYzlkM2Y5Ii8+Cjwvc3ZnPg==';
                const progressColor = course.avg_progress >= 80 ? 'success' : course.avg_progress >= 50 ? 'warning' : 'danger';
                
                html += '<div class="univga-course-card">';
                
                // Course header with thumbnail and overlay
                html += '<div class="univga-course-header">';
                html += '<img src="' + thumbnail + '" alt="' + course.title + '" class="univga-course-thumbnail" loading="lazy">';
                html += '<div class="univga-course-overlay">';
                html += '<a href="' + course.permalink + '" target="_blank" class="univga-btn univga-btn-primary univga-btn-sm">Voir le cours</a>';
                html += '</div>';
                html += '</div>';
                
                // Course content
                html += '<div class="univga-course-content">';
                html += '<h4 class="univga-course-title"><a href="' + course.permalink + '" target="_blank">' + course.title + '</a></h4>';
                html += '<p class="univga-course-excerpt">' + (course.excerpt || 'Aucune description disponible') + '</p>';
                
                // Course stats
                html += '<div class="univga-course-stats">';
                html += '<div class="univga-stat">';
                html += '<span class="univga-stat-label">Pool:</span>';
                html += '<span class="univga-stat-value">' + course.pool_name + '</span>';
                html += '</div>';
                html += '<div class="univga-stat">';
                html += '<span class="univga-stat-label">Inscrits:</span>';
                html += '<span class="univga-stat-value">' + course.enrolled_count + '</span>';
                html += '</div>';
                html += '<div class="univga-stat">';
                html += '<span class="univga-stat-label">Terminés:</span>';
                html += '<span class="univga-stat-value">' + course.completed_count + '</span>';
                html += '</div>';
                html += '<div class="univga-stat">';
                html += '<span class="univga-stat-label">Taux:</span>';
                html += '<span class="univga-stat-value">' + course.completion_rate + '%</span>';
                html += '</div>';
                html += '</div>';
                
                // Progress section
                html += '<div class="univga-progress-section">';
                html += '<div class="univga-progress-header">';
                html += '<span class="univga-progress-label">Progrès moyen</span>';
                html += '<span class="univga-progress-value">' + course.avg_progress + '%</span>';
                html += '</div>';
                html += '<div class="univga-progress">';
                html += '<div class="univga-progress-bar univga-progress-' + progressColor + '" style="width: ' + course.avg_progress + '%"></div>';
                html += '</div>';
                html += '</div>';
                
                // Course actions
                html += '<div class="univga-course-actions">';
                html += '<div class="univga-seats-info">';
                html += '<span class="univga-seats-available">' + course.seats_available + '</span>';
                html += '<span class="univga-seats-total">/' + course.seats_total + ' sièges</span>';
                html += '</div>';
                html += '</div>';
                
                html += '</div>'; // Close course-content
                html += '</div>'; // Close course-card
            });
            html += '</div>'; // Close courses-grid
            
            $grid.html(html);
        };
        
        // Also bind a click event to refresh courses when clicking on Courses tab
        $(document).on('click', '[data-tab="courses"]', function() {
            setTimeout(function() {
                if (UnivgaDashboard && UnivgaDashboard.loadCourses) {
                    UnivgaDashboard.loadCourses();
                }
            }, 100);
        });
        
        // Bind refresh courses button
        $(document).on('click', '#refresh-courses', function() {
            if (UnivgaDashboard && UnivgaDashboard.loadCourses) {
                UnivgaDashboard.loadCourses();
            }
        });
    }
});