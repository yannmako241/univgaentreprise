(function($) {
    'use strict';
    
    // Dashboard object
    const UnivgaDashboard = {
        orgId: null,
        currentPage: 1,
        currentFilters: {},
        
        init: function() {
            this.orgId = $('.univga-dashboard').data('org-id');
            this.bindEvents();
            this.loadMembers();
            this.initBulkOperations();
        },
        
        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.univga-tab-btn', this.switchTab.bind(this));
            
            // Invite member modal
            $(document).on('click', '[data-action="invite-member"]', this.openInviteModal.bind(this));
            $(document).on('click', '[data-dismiss="modal"]', this.closeModal.bind(this));
            $(document).on('submit', '#invite-form', this.sendInvitation.bind(this));
            
            // Member actions
            $(document).on('click', '[data-action="remove-member"]', this.removeMember.bind(this));
            $(document).on('click', '[data-action="export-members"]', this.exportMembers.bind(this));
            
            // Filters and search
            $(document).on('change', '#team-filter', this.filterMembers.bind(this));
            $(document).on('input', '#member-search', this.debounce(this.filterMembers.bind(this), 300));
            
            // Pagination
            $(document).on('click', '.univga-pagination button', this.handlePagination.bind(this));
            
            // Export reports
            $(document).on('click', '[data-export]', this.exportReport.bind(this));
            
            // Admin section navigation
            $(document).on('click', '.univga-admin-nav-btn', this.switchAdminSection.bind(this));
            
            // Admin actions
            $(document).on('click', '[data-action="edit-organization"]', this.openEditOrganizationModal.bind(this));
            $(document).on('submit', '#edit-organization-form', this.saveOrganization.bind(this));
            $(document).on('submit', '#branding-form', this.saveBranding.bind(this));
            $(document).on('change', '#logo-upload', this.handleLogoUpload.bind(this));
            $(document).on('change', '#cover-upload', this.handleCoverUpload.bind(this));
            $(document).on('click', '#preview-branding', this.previewBranding.bind(this));
            
            // Messaging system events
            $(document).on('click', '.univga-msg-nav-btn', this.switchMessagesView.bind(this));
            $(document).on('click', '[data-action="new-message"]', this.openNewMessageModal.bind(this));
            $(document).on('click', '.univga-recipient-type-tabs .univga-tab-btn', this.switchRecipientType.bind(this));
            $(document).on('submit', '#new-message-form', this.sendNewMessage.bind(this));
            $(document).on('click', '.univga-conversation-item', this.openConversation.bind(this));
            $(document).on('submit', '.univga-chat-input-form', this.sendChatMessage.bind(this));
            $(document).on('input', '#member-search-message', this.debounce(this.searchMembersForMessage.bind(this), 300));
            
            // Modal overlay click
            $(document).on('click', '.univga-modal', function(e) {
                if (e.target === this) {
                    $(this).removeClass('show');
                }
            });
            
            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) {
                    $('.univga-modal').removeClass('show');
                }
            });
            
            // Bulk Operations events
            $(document).on('click', '.univga-bulk-nav-btn', this.switchBulkSection.bind(this));
            $(document).on('change', '#csv-file', this.handleCSVUpload.bind(this));
            $(document).on('click', '#start-import', this.startCSVImport.bind(this));
            $(document).on('click', '#download-template', this.downloadCSVTemplate.bind(this));
            $(document).on('change', '#enrollment-mode', this.toggleScheduleDate.bind(this));
            $(document).on('click', '.univga-user-selection-tabs .univga-tab-btn', this.switchUserSelectionTab.bind(this));
            $(document).on('click', '#start-enrollment', this.startBulkEnrollment.bind(this));
            $(document).on('click', '#start-team-assignment', this.startTeamAssignment.bind(this));
            $(document).on('click', '#cancel-operation', this.cancelOperation.bind(this));
        },
        
        switchTab: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const tab = $btn.data('tab');
            
            // Update active tab
            $('.univga-tab-btn').removeClass('active');
            $('.univga-tab-content').removeClass('active');
            
            $btn.addClass('active');
            $('#tab-' + tab).addClass('active');
            
            // Load tab content
            switch(tab) {
                case 'members':
                    this.loadMembers();
                    break;
                case 'courses':
                    this.loadCourses();
                    break;
                case 'analytics':
                    this.initAnalytics();
                    break;
                case 'learning-paths':
                    this.initLearningPaths();
                    break;
                case 'gamification':
                    this.initGamification();
                    break;
                case 'certifications':
                    this.initCertifications();
                    break;
                case 'branding':
                    this.initBranding();
                    break;
                case 'reports':
                    // Reports tab is static
                    break;
                case 'messages':
                    this.loadMessagesTab();
                    break;
                case 'admin':
                    this.loadAdminSection();
                    break;
            }
        },
        
        loadMembers: function() {
            const self = this;
            
            $('#members-tbody').html('<tr class="loading"><td colspan="6">' + univga_dashboard.strings.loading + '</td></tr>');
            
            const params = {
                page: this.currentPage,
                per_page: 20,
                ...this.currentFilters
            };
            
            $.ajax({
                url: univga_dashboard.rest_url + 'organizations/' + this.orgId + '/members',
                method: 'GET',
                data: params,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
                .done(function(data, textStatus, xhr) {
                    self.renderMembers(data);
                    self.renderPagination(xhr.getResponseHeader('X-Total-Pages'), self.currentPage);
                })
                .fail(function() {
                    console.error('Failed to load members:', xhr);
                    const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Échec du chargement des membres';
                    $('#members-tbody').html('<tr class="no-data"><td colspan="6">' + errorMsg + '</td></tr>');
                });
        },
        
        renderMembers: function(members) {
            const tbody = $('#members-tbody');
            
            if (!members || members.length === 0) {
                tbody.html('<tr class="no-data"><td colspan="6">Aucun membre trouvé</td></tr>');
                return;
            }
            
            let html = '';
            members.forEach(function(member) {
                const initials = member.display_name.split(' ').map(n => n[0]).join('').toUpperCase();
                const progressColor = member.avg_progress >= 80 ? 'success' : member.avg_progress >= 50 ? 'warning' : 'danger';
                const lastActivity = member.last_activity ? new Date(member.last_activity).toLocaleDateString() : 'Jamais';
                
                html += `
                    <tr>
                        <td>
                            <div class="univga-member-card">
                                <div class="univga-member-avatar">${initials}</div>
                                <div class="univga-member-info">
                                    <h4>${member.display_name}</h4>
                                    <p>${member.user_email}</p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="univga-badge univga-badge-secondary">${member.team_name || 'Aucune équipe'}</span>
                        </td>
                        <td>
                            ${member.enrolled_courses}/${member.enrolled_courses + (member.courses ? Object.keys(member.courses).length - member.enrolled_courses : 0)}
                        </td>
                        <td>
                            <div class="univga-progress">
                                <div class="univga-progress-bar" style="width: ${member.avg_progress}%"></div>
                                <span class="univga-progress-text">${Math.round(member.avg_progress)}%</span>
                            </div>
                        </td>
                        <td>${lastActivity}</td>
                        <td>
                            <button type="button" class="univga-btn univga-btn-sm univga-btn-danger" 
                                    data-action="remove-member" data-member-id="${member.id}">
                                Remove
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.html(html);
        },
        
        renderPagination: function(totalPages, currentPage) {
            totalPages = parseInt(totalPages) || 1;
            currentPage = parseInt(currentPage) || 1;
            
            if (totalPages <= 1) {
                $('#members-pagination').empty();
                return;
            }
            
            let html = '';
            
            // Previous button
            html += `<button ${currentPage <= 1 ? 'disabled' : ''} data-page="${currentPage - 1}">Previous</button>`;
            
            // Page numbers
            for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                html += `<button class="${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }
            
            // Next button
            html += `<button ${currentPage >= totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">Next</button>`;
            
            $('#members-pagination').html(html);
        },
        
        handlePagination: function(e) {
            const page = parseInt($(e.currentTarget).data('page'));
            if (page && page !== this.currentPage) {
                this.currentPage = page;
                this.loadMembers();
            }
        },
        
        filterMembers: function() {
            this.currentFilters = {
                team_id: $('#team-filter').val(),
                search: $('#member-search').val()
            };
            this.currentPage = 1;
            this.loadMembers();
        },
        
        loadCourses: function() {
            const self = this;
            
            $('#courses-grid').html('<div class="loading">' + univga_dashboard.strings.loading + '</div>');
            
            // This would typically call a REST endpoint for course stats
            // For now, we'll show a placeholder
            setTimeout(function() {
                self.loadCoursesFromAPI();
            }, 500);
        },
        
        openInviteModal: function() {
            $('#invite-modal').addClass('show');
            $('#invite-email').focus();
        },
        
        closeModal: function() {
            $('.univga-modal').removeClass('show');
            this.resetForms();
        },
        
        resetForms: function() {
            $('form')[0].reset();
            $('.univga-notice').remove();
        },
        
        sendInvitation: function(e) {
            e.preventDefault();
            
            const self = this;
            const $form = $(e.currentTarget);
            const $button = $form.find('button[type="submit"]');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Sending...');
            
            const data = {
                org_id: this.orgId,
                email: $('#invite-email').val(),
                team_id: $('#invite-team').val() || null
            };
            
            $.ajax({
                url: univga_dashboard.rest_url + 'organizations/' + this.orgId + '/invite',
                method: 'POST',
                data: data,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wp.api.versionString);
                }
            })
            .done(function(response) {
                self.showNotice('success', 'Invitation sent successfully!');
                self.closeModal();
                // Optionally refresh members list
            })
            .fail(function(xhr) {
                const error = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to send invitation';
                self.showNotice('error', error, $form);
            })
            .always(function() {
                $button.prop('disabled', false).text(originalText);
            });
        },
        
        removeMember: function(e) {
            if (!confirm(univga_dashboard.strings.confirm_remove)) {
                return;
            }
            
            const self = this;
            const memberId = $(e.currentTarget).data('member-id');
            
            this.showLoading();
            
            $.ajax({
                url: univga_dashboard.rest_url + 'organizations/' + this.orgId + '/members/' + memberId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wp.api.versionString);
                }
            })
            .done(function() {
                self.showNotice('success', 'Member removed successfully');
                self.loadMembers();
            })
            .fail(function(xhr) {
                const error = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to remove member';
                self.showNotice('error', error);
            })
            .always(function() {
                self.hideLoading();
            });
        },
        
        exportMembers: function() {
            window.location.href = univga_dashboard.ajaxurl + 
                '?action=univga_export_members&org_id=' + this.orgId + 
                '&nonce=' + univga_dashboard.nonce +
                '&' + $.param(this.currentFilters);
        },
        
        exportReport: function(e) {
            const type = $(e.currentTarget).data('export');
            
            window.location.href = univga_dashboard.ajaxurl + 
                '?action=univga_export_' + type + '&org_id=' + this.orgId + 
                '&nonce=' + univga_dashboard.nonce;
        },
        
        showNotice: function(type, message, container) {
            const $notice = $('<div class="univga-notice univga-notice-' + type + '">' + message + '</div>');
            
            if (container) {
                container.prepend($notice);
            } else {
                $('.univga-header').after($notice);
                $('html, body').animate({ scrollTop: 0 }, 300);
            }
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        showLoading: function() {
            $('#loading-overlay').addClass('show');
        },
        
        hideLoading: function() {
            $('#loading-overlay').removeClass('show');
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Admin functionality
        loadAdminSection: function() {
            console.log('Loading admin section');
            this.loadAdminOrganization();
        },

        switchAdminSection: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const section = $btn.data('admin-section');
            
            $('.univga-admin-nav-btn').removeClass('active');
            $('.univga-admin-section').removeClass('active');
            
            $btn.addClass('active');
            $('#admin-' + section).addClass('active');
            
            switch(section) {
                case 'teams':
                    this.loadAdminTeams();
                    break;
                case 'members':
                    this.loadAdminMembers();
                    break;
                case 'seat-pools':
                    this.loadAdminSeatPools();
                    break;
                case 'settings':
                    this.loadAdminSettings();
                    break;
            }
        },

        loadAdminOrganization: function() {
            console.log('Admin organization section ready');
        },

        openEditOrganizationModal: function(e) {
            e.preventDefault();
            
            $('#edit-org-name').val($('#org-name').text());
            $('#edit-org-legal-id').val($('#org-legal-id').text() === 'Not set' ? '' : $('#org-legal-id').text());
            $('#edit-org-email-domain').val($('#org-email-domain').text() === 'Not set' ? '' : $('#org-email-domain').text());
            $('#edit-org-status').val($('.univga-status').hasClass('active') ? '1' : '0');
            
            $('#edit-organization-modal').addClass('show');
        },

        saveOrganization: function(e) {
            e.preventDefault();
            const self = this;
            
            const formData = {
                action: 'univga_update_organization_frontend',
                org_id: this.orgId,
                name: $('#edit-org-name').val(),
                legal_id: $('#edit-org-legal-id').val(),
                email_domain: $('#edit-org-email-domain').val(),
                status: $('#edit-org-status').val(),
                nonce: univga_dashboard.nonce
            };
            
            this.showLoading();
            
            $.ajax({
                url: univga_dashboard.ajaxurl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $('#org-name').text(formData.name);
                        $('#org-legal-id').text(formData.legal_id || 'Not set');
                        $('#org-email-domain').text(formData.email_domain || 'Not set');
                        
                        const $status = $('.univga-status');
                        $status.removeClass('active inactive').addClass(formData.status === '1' ? 'active' : 'inactive');
                        $status.text(formData.status === '1' ? 'Active' : 'Inactive');
                        
                        $('#edit-organization-modal').removeClass('show');
                        self.showNotice('success', 'Organization updated successfully!');
                    } else {
                        self.showNotice('error', response.data || 'Failed to update organization');
                    }
                },
                error: function() {
                    self.showNotice('error', 'Network error occurred');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        saveBranding: function(e) {
            e.preventDefault();
            const self = this;
            
            const formData = new FormData();
            formData.append('action', 'univga_save_branding_frontend');
            formData.append('org_id', this.orgId);
            formData.append('nonce', univga_dashboard.nonce);
            
            // Text fields
            formData.append('company_name', $('#company-name').val());
            formData.append('slogan', $('#company-slogan').val());
            formData.append('primary_color', $('#primary-color').val());
            formData.append('secondary_color', $('#secondary-color').val());
            
            // File uploads
            const logoFile = $('#logo-upload')[0].files[0];
            const coverFile = $('#cover-upload')[0].files[0];
            
            if (logoFile) {
                formData.append('logo', logoFile);
            }
            if (coverFile) {
                formData.append('cover', coverFile);
            }
            
            this.showLoading();
            
            $.ajax({
                url: univga_dashboard.ajaxurl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showNotice('success', 'Branding updated successfully! Please refresh to see changes.');
                        
                        if (response.data.logo_url) {
                            $('.univga-org-logo').attr('src', response.data.logo_url);
                        }
                        
                        if (response.data.cover_url) {
                            $('.univga-header-cover').css('background-image', `url(${response.data.cover_url})`);
                        }
                    } else {
                        self.showNotice('error', response.data || 'Failed to save branding');
                    }
                },
                error: function() {
                    self.showNotice('error', 'Network error occurred');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        handleLogoUpload: function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#logo-preview').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);
            }
        },

        handleCoverUpload: function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('.cover-preview').css('background-image', `url(${e.target.result})`);
                };
                reader.readAsDataURL(file);
            }
        },

        previewBranding: function(e) {
            e.preventDefault();
            const self = this;
            
            const brandingData = {
                company_name: $('#company-name').val(),
                slogan: $('#company-slogan').val(),
                primary_color: $('#primary-color').val(),
                secondary_color: $('#secondary-color').val(),
                logo_url: $('#logo-preview').attr('src')
            };
            
            $.ajax({
                url: univga_dashboard.ajaxurl,
                method: 'POST',
                data: {
                    action: 'univga_preview_branding',
                    org_id: this.orgId,
                    branding: brandingData,
                    nonce: univga_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#branding-preview').html(response.data.preview_html);
                    } else {
                        self.showNotice('error', response.data || 'Failed to generate preview');
                    }
                },
                error: function() {
                    self.showNotice('error', 'Network error occurred');
                }
            });
        },

        loadAdminTeams: function() {
            $('#admin-teams-content').html('<div class="loading">Loading teams...</div>');
            $.ajax({
                url: univga_dashboard.ajaxurl,
                method: 'POST',
                data: {
                    action: 'univga_get_admin_teams',
                    org_id: this.orgId,
                    nonce: univga_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#admin-teams-content').html(response.data.html);
                    } else {
                        $('#admin-teams-content').html('<div class="error">Failed to load teams</div>');
                    }
                },
                error: function() {
                    $('#admin-teams-content').html('<div class="error">Network error occurred</div>');
                }
            });
        },

        loadAdminMembers: function() {
            $('#admin-members-content').html('<div class="loading">Loading member management...</div>');
            $.ajax({
                url: univga_dashboard.ajaxurl,
                method: 'POST',
                data: {
                    action: 'univga_get_admin_members',
                    org_id: this.orgId,
                    nonce: univga_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#admin-members-content').html(response.data.html);
                    } else {
                        $('#admin-members-content').html('<div class="error">Failed to load member management</div>');
                    }
                },
                error: function() {
                    $('#admin-members-content').html('<div class="error">Network error occurred</div>');
                }
            });
        },

        loadAdminSeatPools: function() {
            $('#admin-seat-pools-content').html('<div class="loading">Loading seat pools...</div>');
            $.ajax({
                url: univga_dashboard.ajaxurl,
                method: 'POST',
                data: {
                    action: 'univga_get_admin_seat_pools',
                    org_id: this.orgId,
                    nonce: univga_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#admin-seat-pools-content').html(response.data.html);
                    } else {
                        $('#admin-seat-pools-content').html('<div class="error">Failed to load seat pools</div>');
                    }
                },
                error: function() {
                    $('#admin-seat-pools-content').html('<div class="error">Network error occurred</div>');
                }
            });
        },

        loadAdminSettings: function() {
            $('#admin-settings-content').html('<div class="loading">Loading settings...</div>');
            $.ajax({
                url: univga_dashboard.ajaxurl,
                method: 'POST',
                data: {
                    action: 'univga_get_admin_settings',
                    org_id: this.orgId,
                    nonce: univga_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#admin-settings-content').html(response.data.html);
                    } else {
                        $('#admin-settings-content').html('<div class="error">Failed to load settings</div>');
                    }
                },
                error: function() {
                    $('#admin-settings-content').html('<div class="error">Network error occurred</div>');
                }
            });
        },
        
        // ========== MESSAGING SYSTEM ==========
        
        // Current messaging state
        currentConversation: null,
        messagesPollingInterval: null,
        
        loadMessagesTab: function() {
            this.loadConversations();
        },
        
        switchMessagesView: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const view = $btn.data('msg-view');
            
            // Update active nav
            $('.univga-msg-nav-btn').removeClass('active');
            $('.univga-messages-view').removeClass('active');
            
            $btn.addClass('active');
            $('#' + view + '-view').addClass('active');
            
            // Load content based on view
            if (view === 'conversations') {
                this.loadConversations();
            } else if (view === 'archived') {
                this.loadArchivedConversations();
            }
        },
        
        loadConversations: function() {
            const self = this;
            
            $('#conversations-list').html('<div class="loading">Loading conversations...</div>');
            
            $.post(univga_dashboard.ajax_url, {
                action: 'univga_get_conversations',
                nonce: univga_dashboard.nonce,
                org_id: this.orgId,
                archived: false
            })
            .done(function(response) {
                if (response.success) {
                    self.renderConversations(response.data);
                } else {
                    $('#conversations-list').html('<div class="univga-no-data">Failed to load conversations</div>');
                }
            })
            .fail(function() {
                $('#conversations-list').html('<div class="univga-no-data">Failed to load conversations</div>');
            });
        },
        
        loadArchivedConversations: function() {
            const self = this;
            
            $('#archived-conversations').html('<div class="loading">Loading archived conversations...</div>');
            
            $.post(univga_dashboard.ajax_url, {
                action: 'univga_get_conversations',
                nonce: univga_dashboard.nonce,
                org_id: this.orgId,
                archived: true
            })
            .done(function(response) {
                if (response.success) {
                    self.renderArchivedConversations(response.data);
                } else {
                    $('#archived-conversations').html('<div class="univga-no-data">No archived conversations</div>');
                }
            })
            .fail(function() {
                $('#archived-conversations').html('<div class="univga-no-data">Failed to load archived conversations</div>');
            });
        },
        
        renderConversations: function(conversations) {
            const $list = $('#conversations-list');
            
            if (!conversations || conversations.length === 0) {
                $list.html('<div class="univga-no-data">No conversations yet. Create your first message!</div>');
                return;
            }
            
            let html = '';
            conversations.forEach(function(conversation) {
                const time = new Date(conversation.last_activity).toLocaleDateString();
                const unreadClass = conversation.unread_count > 0 ? 'unread' : '';
                
                html += `
                    <div class="univga-conversation-item ${unreadClass}" data-conversation-id="${conversation.id}">
                        <div class="univga-conversation-header">
                            <h4 class="univga-conversation-subject">${conversation.subject}</h4>
                            <span class="univga-conversation-time">${time}</span>
                        </div>
                        <p class="univga-conversation-preview">${conversation.preview || 'No messages yet'}</p>
                        <div class="univga-conversation-meta">
                            <span class="univga-conversation-participants">${conversation.participant_count} participants</span>
                            ${conversation.unread_count > 0 ? `<span class="univga-unread-badge">${conversation.unread_count}</span>` : ''}
                        </div>
                    </div>
                `;
            });
            
            $list.html(html);
        },
        
        renderArchivedConversations: function(conversations) {
            const $container = $('#archived-conversations');
            
            if (!conversations || conversations.length === 0) {
                $container.html('<div class="univga-no-data">No archived conversations</div>');
                return;
            }
            
            let html = '';
            conversations.forEach(function(conversation) {
                const time = new Date(conversation.last_activity).toLocaleDateString();
                
                html += `
                    <div class="univga-archived-conversation" data-conversation-id="${conversation.id}">
                        <div class="univga-archived-subject">${conversation.subject}</div>
                        <div class="univga-archived-meta">
                            <span>${conversation.participant_count} participants</span>
                            <span>Last activity: ${time}</span>
                        </div>
                    </div>
                `;
            });
            
            $container.html(html);
        },
        
        openConversation: function(e) {
            e.preventDefault();
            
            const $item = $(e.currentTarget);
            const conversationId = $item.data('conversation-id');
            
            // Update active conversation
            $('.univga-conversation-item').removeClass('active');
            $item.addClass('active').removeClass('unread');
            
            this.currentConversation = conversationId;
            this.loadConversationMessages(conversationId);
            this.markConversationAsRead(conversationId);
        },
        
        loadConversationMessages: function(conversationId) {
            const self = this;
            
            // Clear any existing polling
            if (this.messagesPollingInterval) {
                clearInterval(this.messagesPollingInterval);
            }
            
            // Show loading state
            this.showChatLoading();
            
            // Load conversation details first
            $.post(univga_dashboard.ajax_url, {
                action: 'univga_get_conversation_details',
                nonce: univga_dashboard.nonce,
                conversation_id: conversationId
            })
            .done(function(response) {
                if (response.success) {
                    self.renderChatHeader(response.data);
                }
            });
            
            // Load messages
            this.refreshMessages(conversationId);
            
            // Start periodic refresh for new messages (every 10 seconds)
            this.messagesPollingInterval = setInterval(function() {
                self.refreshMessages(conversationId);
            }, 10000);
        },
        
        showChatLoading: function() {
            $('#chat-panel').html(`
                <div class="univga-chat-header">
                    <div>
                        <h4 class="univga-chat-title">Loading...</h4>
                        <p class="univga-chat-participants">Loading participants...</p>
                    </div>
                    <div class="univga-chat-actions">
                        <button class="univga-chat-action-btn" data-action="archive-conversation" disabled>Archive</button>
                    </div>
                </div>
                <div class="univga-chat-messages" id="chat-messages">
                    <div class="loading">Loading messages...</div>
                </div>
                <div class="univga-chat-input">
                    <form class="univga-chat-input-form" id="chat-input-form">
                        <textarea class="univga-chat-input-field" placeholder="Loading..." rows="1" disabled></textarea>
                        <button type="submit" class="univga-chat-send-btn" disabled>
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M15.854.146a.5.5 0 0 1 .11.54L13.026 8.74a.5.5 0 0 1-.428.26H8.5L7 10.5V8.75a.5.5 0 0 0-.5-.5H4a.5.5 0 0 0-.5.5v2a.5.5 0 0 0 .5.5h2.75l1.5 1.5a.5.5 0 0 0 .854-.354L8.5 10.25h4.098l2.928-7.854a.5.5 0 0 1 .54-.11z"/>
                            </svg>
                        </button>
                    </form>
                </div>
            `);
        },
        
        renderChatHeader: function(conversation) {
            $('.univga-chat-title').text(conversation.subject);
            $('.univga-chat-participants').text(`${conversation.participant_count} participants`);
            $('.univga-chat-input-field').prop('disabled', false).attr('placeholder', 'Type your message...');
            $('.univga-chat-send-btn').prop('disabled', false);
            $('.univga-chat-action-btn').prop('disabled', false);
        },
        
        refreshMessages: function(conversationId) {
            const self = this;
            
            $.post(univga_dashboard.ajax_url, {
                action: 'univga_get_conversation_messages',
                nonce: univga_dashboard.nonce,
                conversation_id: conversationId
            })
            .done(function(response) {
                if (response.success) {
                    self.renderChatMessages(response.data);
                } else {
                    $('#chat-messages').html('<div class="univga-no-data">Failed to load messages</div>');
                }
            })
            .fail(function() {
                $('#chat-messages').html('<div class="univga-no-data">Failed to load messages</div>');
            });
        },
        
        renderChatMessages: function(messages) {
            const $container = $('#chat-messages');
            
            if (!messages || messages.length === 0) {
                $container.html('<div class="univga-no-data">No messages in this conversation yet.</div>');
                return;
            }
            
            let html = '';
            const currentUserId = parseInt(univga_dashboard.current_user_id);
            
            messages.forEach(function(message) {
                const isOwn = parseInt(message.sender_id) === currentUserId;
                const ownClass = isOwn ? 'own' : '';
                const messageClass = message.message_type === 'announcement' ? 'announcement' : '';
                const priorityClass = parseInt(message.is_priority) ? 'priority' : '';
                const avatar = message.sender_name ? message.sender_name.charAt(0).toUpperCase() : 'U';
                const time = new Date(message.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                
                html += `
                    <div class="univga-message ${ownClass} ${messageClass} ${priorityClass}">
                        <div class="univga-message-avatar">${avatar}</div>
                        <div class="univga-message-content">
                            <div class="univga-message-header">
                                <span class="univga-message-sender">${message.sender_name}</span>
                                <span class="univga-message-time">${time}</span>
                            </div>
                            <div class="univga-message-bubble">${message.message}</div>
                        </div>
                    </div>
                `;
            });
            
            $container.html(html);
            
            // Scroll to bottom
            $container.scrollTop($container[0].scrollHeight);
        },
        
        sendChatMessage: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $input = $form.find('.univga-chat-input-field');
            const $button = $form.find('.univga-chat-send-btn');
            const message = $input.val().trim();
            
            if (!message || !this.currentConversation) {
                return;
            }
            
            // Show sending state
            const originalPlaceholder = $input.attr('placeholder');
            $input.prop('disabled', true).attr('placeholder', 'Sending...');
            $button.prop('disabled', true);
            
            const self = this;
            
            $.post(univga_dashboard.ajax_url, {
                action: 'univga_send_message',
                nonce: univga_dashboard.nonce,
                conversation_id: this.currentConversation,
                message: message
            })
            .done(function(response) {
                if (response.success) {
                    $input.val('');
                    // Only refresh messages instead of reloading entire conversation
                    self.refreshMessages(self.currentConversation);
                    // Show success feedback
                    self.showNotification('Message sent successfully', 'success');
                } else {
                    self.showNotification('Failed to send message: ' + (response.data || 'Unknown error'), 'error');
                }
            })
            .fail(function() {
                self.showNotification('Failed to send message. Please try again.', 'error');
            })
            .always(function() {
                // Restore input state
                $input.prop('disabled', false).attr('placeholder', originalPlaceholder);
                $button.prop('disabled', false);
                $input.focus();
            });
        },
        
        showNotification: function(message, type) {
            // Create notification toast
            const $notification = $(`
                <div class="univga-notification ${type}">
                    ${message}
                </div>
            `);
            
            // Add to page
            if (!$('.univga-notifications-container').length) {
                $('body').append('<div class="univga-notifications-container"></div>');
            }
            
            $('.univga-notifications-container').append($notification);
            
            // Animate in
            setTimeout(() => $notification.addClass('show'), 100);
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                $notification.removeClass('show');
                setTimeout(() => $notification.remove(), 300);
            }, 4000);
        },
        
        markConversationAsRead: function(conversationId) {
            $.post(univga_dashboard.ajax_url, {
                action: 'univga_mark_conversation_read',
                nonce: univga_dashboard.nonce,
                conversation_id: conversationId
            });
        },
        
        openNewMessageModal: function(e) {
            e.preventDefault();
            
            $('#new-message-modal').addClass('show');
            this.loadMembersForMessage();
        },
        
        switchRecipientType: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const type = $btn.data('recipient-type');
            
            // Update active tab
            $('.univga-recipient-type-tabs .univga-tab-btn').removeClass('active');
            $('.univga-recipient-tab').removeClass('active');
            
            $btn.addClass('active');
            $('#' + type + '-tab').addClass('active');
            
            if (type === 'members') {
                this.loadMembersForMessage();
            }
        },
        
        loadMembersForMessage: function() {
            const self = this;
            
            $('#member-list-message').html('<div class="loading">Loading members...</div>');
            
            $.get(univga_dashboard.rest_url + 'organizations/' + this.orgId + '/members')
                .done(function(members) {
                    self.renderMembersForMessage(members);
                })
                .fail(function() {
                    $('#member-list-message').html('<div class="univga-no-data">Failed to load members</div>');
                });
        },
        
        renderMembersForMessage: function(members) {
            const $list = $('#member-list-message');
            
            if (!members || members.length === 0) {
                $list.html('<div class="univga-no-data">No members found</div>');
                return;
            }
            
            let html = '';
            members.forEach(function(member) {
                html += `
                    <div class="univga-recipient-item">
                        <input type="checkbox" name="member_recipients[]" value="${member.ID}" id="member-${member.ID}">
                        <label for="member-${member.ID}">
                            <strong>${member.display_name}</strong>
                            <span class="univga-member-count">${member.user_email}</span>
                        </label>
                    </div>
                `;
            });
            
            $list.html(html);
        },
        
        searchMembersForMessage: function() {
            const query = $('#member-search-message').val().toLowerCase();
            
            $('.univga-recipient-item').each(function() {
                const $item = $(this);
                const text = $item.text().toLowerCase();
                $item.toggle(text.includes(query));
            });
        },
        
        sendNewMessage: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const subject = $('#message-subject').val();
            const message = $('#message-content').val();
            
            if (!subject || !message) {
                alert('Please fill in all required fields.');
                return;
            }
            
            // Get selected recipients
            const memberRecipients = [];
            const teamRecipients = [];
            let allMembers = false;
            
            // Check which tab is active
            const activeTab = $('.univga-recipient-tab.active').attr('id');
            
            if (activeTab === 'members-tab') {
                $('input[name="member_recipients[]"]:checked').each(function() {
                    memberRecipients.push($(this).val());
                });
            } else if (activeTab === 'teams-tab') {
                $('input[name="team_recipients[]"]:checked').each(function() {
                    teamRecipients.push($(this).val());
                });
            } else if (activeTab === 'all-tab') {
                allMembers = true;
            }
            
            if (!allMembers && memberRecipients.length === 0 && teamRecipients.length === 0) {
                alert('Please select at least one recipient.');
                return;
            }
            
            const self = this;
            
            $.post(univga_dashboard.ajax_url, {
                action: 'univga_create_conversation',
                nonce: univga_dashboard.nonce,
                org_id: this.orgId,
                subject: subject,
                initial_message: message,
                message_type: $('#message-type').val(),
                is_priority: $('#message-priority').is(':checked'),
                participants: allMembers ? 'all' : memberRecipients.concat(teamRecipients)
            })
            .done(function(response) {
                if (response.success) {
                    $('#new-message-modal').removeClass('show');
                    $form[0].reset();
                    self.loadConversations();
                    alert('Message sent successfully!');
                } else {
                    alert('Failed to send message: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Failed to send message. Please try again.');
            });
        },
        
        // ===================== BULK OPERATIONS =====================
        
        initBulkOperations: function() {
            this.initFileUpload();
        },
        
        switchBulkSection: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const section = $btn.data('bulk-section');
            
            $('.univga-bulk-nav-btn').removeClass('active');
            $btn.addClass('active');
            
            $('.univga-bulk-section').removeClass('active');
            $('#bulk-' + section).addClass('active');
        },
        
        initFileUpload: function() {
            const $uploadArea = $('#csv-upload-area');
            const $fileInput = $('#csv-file');
            
            $uploadArea.on('click', function() {
                $fileInput.click();
            });
            
            $uploadArea.on('dragover dragenter', function(e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            });
            
            $uploadArea.on('dragleave dragend', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
            });
            
            $uploadArea.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $fileInput[0].files = files;
                    $fileInput.trigger('change');
                }
            });
        },
        
        handleCSVUpload: function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('bulk_file', file);
            formData.append('action', 'univga_upload_bulk_file');
            formData.append('nonce', univga_dashboard.nonce);
            
            $.ajax({
                url: univga_dashboard.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        UnivgaDashboard.displayCSVPreview(response.data);
                        $('#start-import').prop('disabled', false);
                    } else {
                        UnivgaDashboard.showNotice('error', response.data);
                    }
                },
                error: function() {
                    UnivgaDashboard.showNotice('error', 'Failed to upload file');
                }
            });
        },
        
        displayCSVPreview: function(data) {
            const $preview = $('#csv-preview');
            const $stats = $preview.find('.univga-csv-stats');
            const $header = $preview.find('#csv-preview-header');
            const $body = $preview.find('#csv-preview-body');
            
            $stats.html(`
                <div class="univga-csv-stat">
                    <div class="univga-csv-stat-value">${data.preview.total_rows}</div>
                    <div class="univga-csv-stat-label">Total Rows</div>
                </div>
                <div class="univga-csv-stat">
                    <div class="univga-csv-stat-value">${data.preview.columns.length}</div>
                    <div class="univga-csv-stat-label">Columns</div>
                </div>
                <div class="univga-csv-stat">
                    <div class="univga-csv-stat-value">${data.preview.valid_emails || 0}</div>
                    <div class="univga-csv-stat-label">Valid Emails</div>
                </div>
            `);
            
            let headerHtml = '';
            data.preview.columns.forEach(col => {
                headerHtml += `<th>${col}</th>`;
            });
            $header.html(headerHtml);
            
            let bodyHtml = '';
            data.preview.sample_rows.forEach(row => {
                bodyHtml += '<tr>';
                row.forEach(cell => {
                    bodyHtml += `<td>${cell || '-'}</td>`;
                });
                bodyHtml += '</tr>';
            });
            $body.html(bodyHtml);
            
            $preview.show();
        },
        
        startCSVImport: function(e) {
            e.preventDefault();
            
            const data = {
                action: 'univga_bulk_import_users',
                org_id: this.orgId,
                filename: $('#csv-file')[0].files[0].name,
                default_team: $('#default-team').val(),
                send_invitations: $('#send-invitations').val(),
                nonce: univga_dashboard.nonce
            };
            
            this.startOperation('CSV Import', data);
        },
        
        downloadCSVTemplate: function(e) {
            e.preventDefault();
            
            const csvContent = "data:text/csv;charset=utf-8,email,display_name,team\n" +
                              "user1@example.com,John Doe,Sales Team\n" +
                              "user2@example.com,Jane Smith,Marketing Team\n" +
                              "user3@example.com,Bob Johnson,";
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "bulk_import_template.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        
        toggleScheduleDate: function(e) {
            const mode = $(e.target).val();
            const $scheduleGroup = $('#schedule-date-group');
            
            if (mode === 'scheduled') {
                $scheduleGroup.show();
                $('#start-date').prop('required', true);
            } else {
                $scheduleGroup.hide();
                $('#start-date').prop('required', false);
            }
        },
        
        switchUserSelectionTab: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const tab = $btn.data('tab');
            
            $('.univga-user-selection-tabs .univga-tab-btn').removeClass('active');
            $btn.addClass('active');
            
            $('.univga-tab-content').removeClass('active');
            $('#' + tab).addClass('active');
            
            if (tab === 'select-individual') {
                this.loadUsersForSelection();
            }
        },
        
        loadUsersForSelection: function() {
            const $container = $('#individual-users');
            $container.html('<div class="loading">Loading users...</div>');
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_get_org_members',
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done(function(response) {
                if (response.success) {
                    let html = '';
                    response.data.members.forEach(member => {
                        const initials = member.display_name.split(' ').map(n => n[0]).join('').toUpperCase();
                        html += `
                            <div class="univga-user-item">
                                <input type="checkbox" id="user-${member.id}" value="${member.id}">
                                <div class="univga-user-avatar">${initials}</div>
                                <div class="univga-user-info">
                                    <h5>${member.display_name}</h5>
                                    <span>${member.email}</span>
                                </div>
                            </div>
                        `;
                    });
                    $container.html(html);
                } else {
                    $container.html('<div class="univga-no-data">No users found</div>');
                }
            })
            .fail(function() {
                $container.html('<div class="univga-error">Failed to load users</div>');
            });
        },
        
        startBulkEnrollment: function(e) {
            e.preventDefault();
            this.showNotice('info', 'Bulk enrollment functionality coming soon');
        },
        
        startTeamAssignment: function(e) {
            e.preventDefault();
            this.showNotice('info', 'Team assignment functionality coming soon');
        },
        
        startOperation: function(operationName, data) {
            const $progress = $('#operation-progress');
            $('#progress-title').text(operationName);
            $('#progress-fill').css('width', '0%');
            $('#progress-stats').html('<span>Starting...</span>');
            $('#progress-log').empty();
            $progress.show();
            
            this.currentOperation = $.post(univga_dashboard.ajaxurl, data)
                .done(function(response) {
                    if (response.success) {
                        UnivgaDashboard.showOperationComplete();
                    } else {
                        UnivgaDashboard.showNotice('error', response.data);
                        $progress.hide();
                    }
                })
                .fail(function() {
                    UnivgaDashboard.showNotice('error', 'Operation failed');
                    $progress.hide();
                });
        },
        
        showOperationComplete: function() {
            $('#progress-fill').css('width', '100%');
            $('#progress-stats').html('<span>Operation completed successfully</span>');
            
            setTimeout(() => {
                $('#operation-progress').hide();
                this.loadMembers();
            }, 2000);
        },
        
        cancelOperation: function(e) {
            e.preventDefault();
            
            if (this.currentOperation) {
                this.currentOperation.abort();
            }
            
            $('#operation-progress').hide();
            this.showNotice('info', 'Operation cancelled');
        },
        
        // ===================== ANALYTICS =====================
        
        initAnalytics: function() {
            this.loadAnalyticsData();
            this.initAnalyticsEvents();
        },
        
        initAnalyticsEvents: function() {
            $('#analytics-timeframe').on('change', (e) => {
                this.loadAnalyticsData();
            });
            
            $('#refresh-analytics').on('click', (e) => {
                e.preventDefault();
                this.loadAnalyticsData(true);
            });
            
            $('.univga-chart-btn').on('click', (e) => {
                e.preventDefault();
                const $btn = $(e.currentTarget);
                const view = $btn.data('chart-view');
                
                $btn.siblings('.univga-chart-btn').removeClass('active');
                $btn.addClass('active');
                
                this.updateEngagementChart(view);
            });
            
            $('#export-analytics').on('click', (e) => {
                e.preventDefault();
                this.exportAnalytics();
            });
            
            $('#schedule-report').on('click', (e) => {
                e.preventDefault();
                this.showNotice('info', 'Report scheduling functionality coming soon');
            });
        },
        
        loadAnalyticsData: function(forceRefresh = false) {
            const timeframe = $('#analytics-timeframe').val();
            
            if (!forceRefresh) {
                $('.univga-chart-loading').show();
                $('.univga-metric-card').addClass('loading');
            }
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_analytics_data',
                org_id: this.orgId,
                timeframe: timeframe,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayAnalyticsData(response.data);
                } else {
                    this.showNotice('error', response.data || 'Failed to load analytics data');
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to load analytics data');
            })
            .always(() => {
                $('.univga-chart-loading').hide();
                $('.univga-metric-card').removeClass('loading');
            });
        },
        
        displayAnalyticsData: function(data) {
            // Update metric cards
            this.updateMetricCards(data);
            
            // Update charts
            this.updateCompletionChart(data.completion_rates);
            this.updateEngagementChart('daily', data.engagement_metrics);
            this.updateTeamPerformanceChart(data.progress_tracking);
            
            // Update insights
            this.updateInsights(data);
        },
        
        updateMetricCards: function(data) {
            const metrics = $('#analytics-metrics .univga-metric-card');
            
            // Completion Rate
            const completionRate = this.calculateAverageCompletion(data.completion_rates);
            $(metrics[0]).find('.univga-metric-value').text(completionRate + '%');
            $(metrics[0]).find('.univga-metric-change')
                .removeClass('positive negative')
                .addClass(completionRate > 75 ? 'positive' : 'negative')
                .text(completionRate > 75 ? '+5.2%' : '-2.1%');
            
            // Active Learners
            const activeLearners = data.engagement_metrics ? Object.keys(data.engagement_metrics).length : 0;
            $(metrics[1]).find('.univga-metric-value').text(activeLearners);
            $(metrics[1]).find('.univga-metric-change')
                .removeClass('positive negative')
                .addClass('positive')
                .text('+12%');
            
            // Average Study Time
            $(metrics[2]).find('.univga-metric-value').text('3.5h');
            $(metrics[2]).find('.univga-metric-change')
                .removeClass('positive negative')
                .addClass('positive')
                .text('+8%');
            
            // Skill Gaps
            const skillGaps = data.skill_gaps ? data.skill_gaps.length : 0;
            $(metrics[3]).find('.univga-metric-value').text(skillGaps);
            $(metrics[3]).find('.univga-metric-change')
                .removeClass('positive negative')
                .addClass(skillGaps < 3 ? 'positive' : 'negative')
                .text(skillGaps < 3 ? '-15%' : '+5%');
        },
        
        calculateAverageCompletion: function(completionRates) {
            if (!completionRates || completionRates.length === 0) return 0;
            
            const total = completionRates.reduce((sum, course) => {
                return sum + parseFloat(course.completion_rate || 0);
            }, 0);
            
            return Math.round(total / completionRates.length);
        },
        
        updateCompletionChart: function(completionData) {
            // Simple chart visualization using CSS
            const $legend = $('#completion-legend');
            let legendHtml = '';
            
            if (completionData && completionData.length > 0) {
                completionData.slice(0, 5).forEach((course, index) => {
                    const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
                    legendHtml += `
                        <div class="univga-legend-item">
                            <div class="univga-legend-color" style="background: ${colors[index]}"></div>
                            <span>${course.course_name}: ${course.completion_rate}%</span>
                        </div>
                    `;
                });
            } else {
                legendHtml = '<div class="univga-legend-item">No data available</div>';
            }
            
            $legend.html(legendHtml);
            
            // For now, hide canvas and show legend only
            $('#completion-rates-chart').hide();
        },
        
        updateEngagementChart: function(view, engagementData = null) {
            const $canvas = $('#engagement-timeline-chart');
            
            // For now, show placeholder
            $canvas.hide();
            $canvas.parent().append('<div class="univga-chart-placeholder" style="height: 200px; display: flex; align-items: center; justify-content: center; color: #6b7280;">Chart visualization coming soon</div>');
        },
        
        updateTeamPerformanceChart: function(progressData) {
            const $canvas = $('#team-performance-chart');
            
            // For now, show placeholder
            $canvas.hide();
            $canvas.parent().append('<div class="univga-chart-placeholder" style="height: 200px; display: flex; align-items: center; justify-content: center; color: #6b7280;">Team performance visualization coming soon</div>');
        },
        
        updateInsights: function(data) {
            this.updateAtRiskLearners(data.at_risk_learners);
            this.updateSkillGaps(data.skill_gaps);
            this.updateTrendingCourses(data.completion_rates);
        },
        
        updateAtRiskLearners: function(atRiskData) {
            const $container = $('#at-risk-learners');
            let html = '';
            
            if (atRiskData && atRiskData.length > 0) {
                atRiskData.slice(0, 3).forEach(learner => {
                    const initials = learner.name ? learner.name.split(' ').map(n => n[0]).join('').toUpperCase() : '?';
                    html += `
                        <div class="univga-at-risk-item">
                            <div class="univga-insight-icon">${initials}</div>
                            <div class="univga-insight-content">
                                <div class="univga-insight-title">${learner.name || 'Unknown User'}</div>
                                <div class="univga-insight-description">
                                    ${learner.reason || 'Low engagement score'} • 
                                    <span class="univga-insight-metric">${learner.score || 25}% progress</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = '<div class="univga-no-data">All learners are on track! 🎉</div>';
            }
            
            $container.html(html);
        },
        
        updateSkillGaps: function(skillGapsData) {
            const $container = $('#skill-gaps');
            let html = '';
            
            if (skillGapsData && skillGapsData.length > 0) {
                skillGapsData.slice(0, 3).forEach((gap, index) => {
                    html += `
                        <div class="univga-skill-gap-item">
                            <div class="univga-insight-icon">${index + 1}</div>
                            <div class="univga-insight-content">
                                <div class="univga-insight-title">${gap.skill || 'General Skills'}</div>
                                <div class="univga-insight-description">
                                    Identified in ${gap.affected_users || 12} team members • 
                                    <span class="univga-insight-metric">${gap.gap_score || 'High'} priority</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = '<div class="univga-no-data">No significant skill gaps detected</div>';
            }
            
            $container.html(html);
        },
        
        updateTrendingCourses: function(completionData) {
            const $container = $('#trending-courses');
            let html = '';
            
            if (completionData && completionData.length > 0) {
                // Sort by highest completion rate
                const trending = completionData
                    .sort((a, b) => parseFloat(b.completion_rate || 0) - parseFloat(a.completion_rate || 0))
                    .slice(0, 3);
                
                trending.forEach((course, index) => {
                    html += `
                        <div class="univga-trending-item">
                            <div class="univga-insight-icon">${index + 1}</div>
                            <div class="univga-insight-content">
                                <div class="univga-insight-title">${course.course_name}</div>
                                <div class="univga-insight-description">
                                    ${course.started || 0} enrolled • 
                                    <span class="univga-insight-metric">${course.completion_rate}% completed</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = '<div class="univga-no-data">No course data available</div>';
            }
            
            $container.html(html);
        },
        
        exportAnalytics: function() {
            const timeframe = $('#analytics-timeframe').val();
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_export_analytics',
                org_id: this.orgId,
                timeframe: timeframe,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    // Create download link
                    const blob = new Blob([response.data.csv_data], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.setAttribute('href', url);
                    link.setAttribute('download', `analytics_report_${new Date().toISOString().split('T')[0]}.csv`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                    
                    this.showNotice('success', 'Analytics report downloaded successfully');
                } else {
                    this.showNotice('error', response.data || 'Failed to export analytics');
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to export analytics report');
            });
        },
        
        // ===================== LEARNING PATHS =====================
        
        initLearningPaths: function() {
            this.loadLearningPathsStats();
            this.loadLearningPaths();
            this.initLearningPathsEvents();
        },
        
        initLearningPathsEvents: function() {
            // Filter events
            $('#path-status-filter, #path-difficulty-filter, #path-role-filter').on('change', () => {
                this.filterLearningPaths();
            });
            
            $('#path-search').on('input', this.debounce(() => {
                this.filterLearningPaths();
            }, 300));
            
            $('#reset-filters').on('click', (e) => {
                e.preventDefault();
                this.resetLearningPathsFilters();
            });
            
            // Action events
            $('#create-learning-path').on('click', (e) => {
                e.preventDefault();
                this.showCreateLearningPathModal();
            });
            
            $('#import-path').on('click', (e) => {
                e.preventDefault();
                this.showNotice('info', 'Import template functionality coming soon');
            });
            
            // Path card events
            $(document).on('click', '.path-edit', (e) => {
                e.preventDefault();
                const pathId = $(e.currentTarget).data('path-id');
                this.editLearningPath(pathId);
            });
            
            $(document).on('click', '.path-assign', (e) => {
                e.preventDefault();
                const pathId = $(e.currentTarget).data('path-id');
                this.showAssignPathModal(pathId);
            });
            
            $(document).on('click', '.path-duplicate', (e) => {
                e.preventDefault();
                const pathId = $(e.currentTarget).data('path-id');
                this.duplicateLearningPath(pathId);
            });
            
            $(document).on('click', '.path-delete', (e) => {
                e.preventDefault();
                const pathId = $(e.currentTarget).data('path-id');
                this.deleteLearningPath(pathId);
            });
        },
        
        loadLearningPathsStats: function() {
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_learning_paths_stats',
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayLearningPathsStats(response.data);
                }
            })
            .fail(() => {
                // Silent fail for stats
            });
        },
        
        displayLearningPathsStats: function(stats) {
            $('#total-paths').text(stats.total_paths || 0);
            $('#active-learners').text(stats.active_learners || 0);
            $('#completion-rate').text((stats.avg_completion || 0) + '%');
            $('#avg-duration').text(stats.avg_duration || '0h');
        },
        
        loadLearningPaths: function() {
            const $grid = $('#learning-paths-grid');
            $grid.html('<div class="loading">Loading learning paths...</div>');
            
            const filters = this.getLearningPathsFilters();
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_get_learning_paths',
                org_id: this.orgId,
                ...filters,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayLearningPaths(response.data);
                } else {
                    $grid.html('<div class="univga-error">Failed to load learning paths</div>');
                }
            })
            .fail(() => {
                $grid.html('<div class="univga-error">Failed to load learning paths</div>');
            });
        },
        
        getLearningPathsFilters: function() {
            return {
                status: $('#path-status-filter').val(),
                difficulty: $('#path-difficulty-filter').val(),
                role: $('#path-role-filter').val(),
                search: $('#path-search').val()
            };
        },
        
        displayLearningPaths: function(data) {
            const $grid = $('#learning-paths-grid');
            
            if (!data.paths || data.paths.length === 0) {
                $grid.html(`
                    <div class="univga-paths-empty">
                        <svg fill="currentColor" viewBox="0 0 16 16">
                            <path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1.002 1.002 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4.018 4.018 0 0 1-.128-1.287z"/>
                            <path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243L6.586 4.672z"/>
                        </svg>
                        <h4>No Learning Paths Found</h4>
                        <p>Create your first learning path to structure training for your teams</p>
                        <button type="button" class="univga-btn univga-btn-primary" id="create-first-path">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                            </svg>
                            Create Learning Path
                        </button>
                    </div>
                `);
                return;
            }
            
            let html = '';
            data.paths.forEach(path => {
                html += this.renderLearningPathCard(path);
            });
            
            $grid.html(html);
            
            // Update pagination if needed
            if (data.pagination) {
                this.updatePagination('#paths-pagination', data.pagination);
            }
        },
        
        renderLearningPathCard: function(path) {
            const progress = path.progress || 0;
            const enrolledCount = path.enrolled_count || 0;
            const courseCount = path.course_count || 0;
            const duration = path.estimated_duration ? `${path.estimated_duration}h` : 'N/A';
            const difficultyClass = (path.difficulty_level || 'beginner').toLowerCase();
            
            return `
                <div class="univga-path-card">
                    <div class="univga-path-difficulty ${difficultyClass}">
                        ${path.difficulty_level || 'Beginner'}
                    </div>
                    
                    <div class="univga-path-header">
                        <div>
                            <h4 class="univga-path-title">${path.name}</h4>
                            ${path.job_role ? `<span class="univga-path-role">${path.job_role}</span>` : ''}
                        </div>
                    </div>
                    
                    <p class="univga-path-description">${path.description || 'No description available'}</p>
                    
                    <div class="univga-path-progress">
                        <div class="univga-progress-bar">
                            <div class="univga-progress-fill" style="width: ${progress}%"></div>
                        </div>
                        <div class="univga-progress-text">${progress}% completion rate</div>
                    </div>
                    
                    <div class="univga-path-meta">
                        <div class="univga-path-stats">
                            <div class="univga-path-stat">
                                <svg fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                </svg>
                                ${enrolledCount}
                            </div>
                            <div class="univga-path-stat">
                                <svg fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8.5 2.687c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                                </svg>
                                ${courseCount}
                            </div>
                            <div class="univga-path-stat">
                                <svg fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                                </svg>
                                ${duration}
                            </div>
                        </div>
                        
                        <div class="univga-path-actions">
                            <button class="univga-path-btn path-edit" data-path-id="${path.id}">Edit</button>
                            <button class="univga-path-btn path-assign" data-path-id="${path.id}">Assign</button>
                            <button class="univga-path-btn path-duplicate" data-path-id="${path.id}">Copy</button>
                        </div>
                    </div>
                </div>
            `;
        },
        
        filterLearningPaths: function() {
            this.loadLearningPaths();
        },
        
        resetLearningPathsFilters: function() {
            $('#path-status-filter, #path-difficulty-filter, #path-role-filter').val('');
            $('#path-search').val('');
            this.loadLearningPaths();
        },
        
        showCreateLearningPathModal: function() {
            this.showNotice('info', 'Learning path creation modal coming soon');
        },
        
        editLearningPath: function(pathId) {
            this.showNotice('info', `Edit learning path functionality coming soon for path ${pathId}`);
        },
        
        showAssignPathModal: function(pathId) {
            this.showNotice('info', `Assign path functionality coming soon for path ${pathId}`);
        },
        
        duplicateLearningPath: function(pathId) {
            if (!confirm('Are you sure you want to duplicate this learning path?')) {
                return;
            }
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_duplicate_learning_path',
                path_id: pathId,
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', 'Learning path duplicated successfully');
                    this.loadLearningPaths();
                } else {
                    this.showNotice('error', response.data || 'Failed to duplicate learning path');
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to duplicate learning path');
            });
        },
        
        deleteLearningPath: function(pathId) {
            if (!confirm('Are you sure you want to delete this learning path? This action cannot be undone.')) {
                return;
            }
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_delete_learning_path',
                path_id: pathId,
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', 'Learning path deleted successfully');
                    this.loadLearningPaths();
                    this.loadLearningPathsStats();
                } else {
                    this.showNotice('error', response.data || 'Failed to delete learning path');
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to delete learning path');
            });
        },
        
        // ===================== GAMIFICATION =====================
        
        initGamification: function() {
            this.loadGamificationStats();
            this.loadLeaderboard();
            this.loadBadges();
            this.loadActivities();
            this.initGamificationEvents();
        },
        
        initGamificationEvents: function() {
            // Leaderboard controls
            $('#leaderboard-period, #leaderboard-team').on('change', () => {
                this.loadLeaderboard();
            });
            
            // Badge controls
            $('#badge-category').on('change', () => {
                this.filterBadges();
            });
            
            $('#badge-search').on('input', this.debounce(() => {
                this.filterBadges();
            }, 300));
            
            // Action buttons
            $('#create-badge').on('click', (e) => {
                e.preventDefault();
                this.showCreateBadgeModal();
            });
            
            $('#manage-rewards').on('click', (e) => {
                e.preventDefault();
                this.showNotice('info', 'Rewards management coming soon');
            });
            
            $('#refresh-activities').on('click', (e) => {
                e.preventDefault();
                this.loadActivities();
            });
            
            // Badge interactions
            $(document).on('click', '.univga-badge-item', (e) => {
                const badgeId = $(e.currentTarget).data('badge-id');
                this.showBadgeDetails(badgeId);
            });
        },
        
        loadGamificationStats: function() {
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_gamification_stats',
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayGamificationStats(response.data);
                }
            })
            .fail(() => {
                // Silent fail for stats
            });
        },
        
        displayGamificationStats: function(stats) {
            $('#total-points').text((stats.total_points || 0).toLocaleString());
            $('#total-badges').text(stats.total_badges || 0);
            $('#active-participants').text(stats.active_participants || 0);
            $('#avg-engagement').text((stats.avg_engagement || 0) + '%');
        },
        
        loadLeaderboard: function() {
            const $container = $('#leaderboard-container');
            $container.html('<div class="loading">Loading leaderboard...</div>');
            
            const period = $('#leaderboard-period').val();
            const teamId = $('#leaderboard-team').val();
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_get_leaderboard',
                org_id: this.orgId,
                period: period,
                team_id: teamId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayLeaderboard(response.data);
                } else {
                    $container.html('<div class="univga-leaderboard-empty">No leaderboard data available</div>');
                }
            })
            .fail(() => {
                $container.html('<div class="univga-leaderboard-empty">Failed to load leaderboard</div>');
            });
        },
        
        displayLeaderboard: function(data) {
            const $container = $('#leaderboard-container');
            
            if (!data.leaderboard || data.leaderboard.length === 0) {
                $container.html(`
                    <div class="univga-leaderboard-empty">
                        <svg fill="currentColor" viewBox="0 0 16 16">
                            <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>
                        </svg>
                        <h4>No Rankings Yet</h4>
                        <p>Start engaging with learning content to see rankings appear here</p>
                    </div>
                `);
                return;
            }
            
            let html = '<ul class="univga-leaderboard">';
            data.leaderboard.forEach((user, index) => {
                const rank = index + 1;
                const rankClass = rank <= 3 ? `rank-${rank}` : 'rank-other';
                const initials = user.display_name.split(' ').map(n => n[0]).join('').substr(0, 2).toUpperCase();
                
                html += `
                    <li class="univga-leaderboard-item">
                        <div class="univga-leaderboard-rank ${rankClass}">${rank}</div>
                        <div class="univga-leaderboard-user">
                            <div class="univga-leaderboard-avatar">${initials}</div>
                            <div class="univga-leaderboard-info">
                                <div class="univga-leaderboard-name">${user.display_name}</div>
                                <div class="univga-leaderboard-team">${user.team_name || 'No Team'}</div>
                            </div>
                        </div>
                        <div class="univga-leaderboard-points">
                            <div class="univga-leaderboard-score">${(user.points || 0).toLocaleString()}</div>
                            <div class="univga-leaderboard-level">Level ${user.level || 1}</div>
                        </div>
                    </li>
                `;
            });
            html += '</ul>';
            
            $container.html(html);
        },
        
        loadBadges: function() {
            const $grid = $('#badges-grid');
            $grid.html('<div class="loading">Loading badges...</div>');
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_get_badges',
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.badges = response.data.badges || [];
                    this.displayBadges(this.badges);
                } else {
                    $grid.html('<div class="univga-badges-empty">Failed to load badges</div>');
                }
            })
            .fail(() => {
                $grid.html('<div class="univga-badges-empty">Failed to load badges</div>');
            });
        },
        
        filterBadges: function() {
            const category = $('#badge-category').val();
            const search = $('#badge-search').val().toLowerCase();
            
            let filteredBadges = this.badges || [];
            
            if (category) {
                filteredBadges = filteredBadges.filter(badge => badge.category === category);
            }
            
            if (search) {
                filteredBadges = filteredBadges.filter(badge => 
                    badge.name.toLowerCase().includes(search) || 
                    badge.description.toLowerCase().includes(search)
                );
            }
            
            this.displayBadges(filteredBadges);
        },
        
        displayBadges: function(badges) {
            const $grid = $('#badges-grid');
            
            if (!badges || badges.length === 0) {
                $grid.html(`
                    <div class="univga-badges-empty">
                        <svg fill="currentColor" viewBox="0 0 16 16">
                            <path d="M9.669.864 8 0 6.331.864l-1.858.282-.842 1.68-1.337 1.32L2.6 6l-.306 1.854 1.337 1.32.842 1.68 1.858.282L8 12l1.669-.864 1.858-.282.842-1.68 1.337-1.32L13.4 6l.306-1.854-1.337-1.32-.842-1.68L9.669.864z"/>
                        </svg>
                        <h4>No Badges Found</h4>
                        <p>Create badges to recognize achievements and motivate your teams</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            badges.forEach(badge => {
                const awarded = badge.awarded_count > 0;
                const iconClass = awarded ? 'awarded' : 'not-awarded';
                const countClass = awarded ? 'earned' : '';
                
                html += `
                    <div class="univga-badge-item" data-badge-id="${badge.id}">
                        <div class="univga-badge-icon ${iconClass}">
                            ${badge.icon || '🏆'}
                        </div>
                        <div class="univga-badge-name">${badge.name}</div>
                        <div class="univga-badge-count ${countClass}">
                            ${badge.awarded_count || 0} earned
                        </div>
                    </div>
                `;
            });
            
            $grid.html(html);
        },
        
        loadActivities: function() {
            const $feed = $('#activities-feed');
            $feed.html('<div class="loading">Loading activities...</div>');
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_get_point_activities',
                org_id: this.orgId,
                limit: 20,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayActivities(response.data);
                } else {
                    $feed.html('<div class="univga-activities-empty">No activities found</div>');
                }
            })
            .fail(() => {
                $feed.html('<div class="univga-activities-empty">Failed to load activities</div>');
            });
        },
        
        displayActivities: function(data) {
            const $feed = $('#activities-feed');
            
            if (!data.activities || data.activities.length === 0) {
                $feed.html(`
                    <div class="univga-activities-empty">
                        <svg fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                        </svg>
                        <h4>No Activities Yet</h4>
                        <p>Point activities will appear here as your team engages with learning content</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            data.activities.forEach(activity => {
                const iconType = this.getActivityIconType(activity.point_type);
                const timeAgo = this.timeAgo(activity.created_at);
                
                html += `
                    <div class="univga-activity-item">
                        <div class="univga-activity-icon ${iconType}">
                            ${this.getActivityIcon(activity.point_type)}
                        </div>
                        <div class="univga-activity-content">
                            <div class="univga-activity-text">
                                <strong>${activity.user_name}</strong> ${this.getActivityText(activity.point_type)}
                            </div>
                            <div class="univga-activity-meta">
                                <span class="univga-activity-time">${timeAgo}</span>
                                <span class="univga-activity-points">+${activity.points} points</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $feed.html(html);
        },
        
        getActivityIconType: function(pointType) {
            if (pointType.includes('badge') || pointType.includes('level')) return 'badge';
            if (pointType.includes('streak') || pointType.includes('perfect')) return 'level';
            return 'points';
        },
        
        getActivityIcon: function(pointType) {
            const icons = {
                'course_completed': '📚',
                'certification_earned': '🏅',
                'learning_path_completed': '🛤️',
                'first_login': '👋',
                'streak_7_days': '🔥',
                'streak_30_days': '💪',
                'quiz_perfect_score': '💯',
                'mentor_session_completed': '🤝',
                'forum_participation': '💬'
            };
            return icons[pointType] || '⭐';
        },
        
        getActivityText: function(pointType) {
            const texts = {
                'course_completed': 'completed a course',
                'certification_earned': 'earned a certification',
                'learning_path_completed': 'finished a learning path',
                'first_login': 'joined the platform',
                'streak_7_days': 'maintained a 7-day streak',
                'streak_30_days': 'achieved a 30-day streak',
                'quiz_perfect_score': 'scored perfectly on a quiz',
                'mentor_session_completed': 'completed a mentor session',
                'forum_participation': 'participated in the forum'
            };
            return texts[pointType] || 'earned points';
        },
        
        showCreateBadgeModal: function() {
            this.showNotice('info', 'Badge creation modal coming soon');
        },
        
        showBadgeDetails: function(badgeId) {
            this.showNotice('info', `Badge details modal coming soon for badge ${badgeId}`);
        },
        
        timeAgo: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            const intervals = {
                year: 31536000,
                month: 2592000,
                week: 604800,
                day: 86400,
                hour: 3600,
                minute: 60
            };
            
            for (let unit in intervals) {
                const interval = Math.floor(seconds / intervals[unit]);
                if (interval >= 1) {
                    return `${interval} ${unit}${interval !== 1 ? 's' : ''} ago`;
                }
            }
            
            return 'just now';
        },
        
        // ===================== CERTIFICATIONS =====================
        
        initCertifications: function() {
            this.loadCertificationsStats();
            this.loadCertifications();
            this.loadComplianceData();
            this.loadExpiringAlerts();
            this.initCertificationsEvents();
        },
        
        initCertificationsEvents: function() {
            // Filter events
            $('#certification-type-filter').on('change', () => {
                this.filterCertifications();
            });
            
            $('#certification-search').on('input', this.debounce(() => {
                this.filterCertifications();
            }, 300));
            
            $('#compliance-team-filter, #compliance-status-filter').on('change', () => {
                this.filterComplianceData();
            });
            
            // Action buttons
            $('#create-certification').on('click', (e) => {
                e.preventDefault();
                this.showCreateCertificationModal();
            });
            
            $('#compliance-report').on('click', (e) => {
                e.preventDefault();
                this.generateComplianceReport();
            });
            
            $('#send-renewal-reminders').on('click', (e) => {
                e.preventDefault();
                this.sendRenewalReminders();
            });
            
            // Certification item events
            $(document).on('click', '.cert-edit', (e) => {
                e.preventDefault();
                const certId = $(e.currentTarget).data('cert-id');
                this.editCertification(certId);
            });
            
            $(document).on('click', '.cert-assign', (e) => {
                e.preventDefault();
                const certId = $(e.currentTarget).data('cert-id');
                this.showAssignCertificationModal(certId);
            });
            
            $(document).on('click', '.cert-view', (e) => {
                e.preventDefault();
                const certId = $(e.currentTarget).data('cert-id');
                this.viewCertificationDetails(certId);
            });
        },
        
        loadCertificationsStats: function() {
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_certifications_stats',
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayCertificationsStats(response.data);
                }
            })
            .fail(() => {
                // Silent fail for stats
            });
        },
        
        displayCertificationsStats: function(stats) {
            $('#total-certifications').text(stats.total_certifications || 0);
            $('#active-certifications').text(stats.active_certifications || 0);
            $('#expiring-soon').text(stats.expiring_soon || 0);
            $('#compliance-rate').text((stats.compliance_rate || 0) + '%');
        },
        
        loadCertifications: function() {
            const $list = $('#certifications-list');
            $list.html('<div class="loading">Loading certifications...</div>');
            
            const filters = this.getCertificationsFilters();
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_get_certifications',
                org_id: this.orgId,
                ...filters,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayCertifications(response.data);
                } else {
                    $list.html('<div class="univga-certifications-empty">Failed to load certifications</div>');
                }
            })
            .fail(() => {
                $list.html('<div class="univga-certifications-empty">Failed to load certifications</div>');
            });
        },
        
        getCertificationsFilters: function() {
            return {
                type: $('#certification-type-filter').val(),
                search: $('#certification-search').val()
            };
        },
        
        displayCertifications: function(certifications) {
            const $list = $('#certifications-list');
            
            if (!certifications || certifications.length === 0) {
                $list.html(`
                    <div class="univga-certifications-empty">
                        <svg fill="currentColor" viewBox="0 0 16 16">
                            <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002A.274.274 0 0 1 15 13H7.022Z"/>
                        </svg>
                        <h4>No Certifications Found</h4>
                        <p>Create certifications to track team compliance and professional development</p>
                        <button type="button" class="univga-btn univga-btn-primary" id="create-first-certification">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                            </svg>
                            Create Certification
                        </button>
                    </div>
                `);
                return;
            }
            
            let html = '';
            certifications.forEach(cert => {
                html += this.renderCertificationItem(cert);
            });
            
            $list.html(html);
        },
        
        renderCertificationItem: function(cert) {
            const typeClass = cert.is_compliance ? 'compliance' : (cert.is_mandatory ? 'mandatory' : 'optional');
            const typeName = cert.is_compliance ? 'Compliance' : (cert.is_mandatory ? 'Mandatory' : 'Optional');
            
            return `
                <div class="univga-certification-item">
                    <div class="univga-certification-header">
                        <div>
                            <h5 class="univga-certification-name">${cert.name}</h5>
                        </div>
                        <span class="univga-certification-type ${typeClass}">${typeName}</span>
                    </div>
                    
                    <p class="univga-certification-description">${cert.description || 'No description available'}</p>
                    
                    <div class="univga-certification-stats">
                        <div class="univga-certification-stat">
                            <svg fill="currentColor" viewBox="0 0 16 16">
                                <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                            </svg>
                            ${cert.total_earned || 0} earned
                        </div>
                        <div class="univga-certification-stat">
                            <svg fill="currentColor" viewBox="0 0 16 16">
                                <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                            </svg>
                            ${cert.active_count || 0} active
                        </div>
                        <div class="univga-certification-stat">
                            <svg fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566z"/>
                            </svg>
                            ${cert.expired_count || 0} expired
                        </div>
                        <div class="univga-certification-stat">
                            <svg fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                            </svg>
                            ${cert.validity_period || 'N/A'} days
                        </div>
                    </div>
                    
                    <div class="univga-certification-actions">
                        <button class="univga-cert-btn cert-view" data-cert-id="${cert.id}">View</button>
                        <button class="univga-cert-btn cert-edit" data-cert-id="${cert.id}">Edit</button>
                        <button class="univga-cert-btn cert-assign" data-cert-id="${cert.id}">Assign</button>
                    </div>
                </div>
            `;
        },
        
        filterCertifications: function() {
            this.loadCertifications();
        },
        
        loadComplianceData: function() {
            const $grid = $('#compliance-grid');
            $grid.html('<div class="loading">Loading compliance data...</div>');
            
            const filters = this.getComplianceFilters();
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_get_compliance_status',
                org_id: this.orgId,
                ...filters,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayComplianceData(response.data);
                } else {
                    $grid.html('<div class="univga-compliance-empty">Failed to load compliance data</div>');
                }
            })
            .fail(() => {
                $grid.html('<div class="univga-compliance-empty">Failed to load compliance data</div>');
            });
        },
        
        getComplianceFilters: function() {
            return {
                team_id: $('#compliance-team-filter').val(),
                status: $('#compliance-status-filter').val()
            };
        },
        
        displayComplianceData: function(data) {
            const $grid = $('#compliance-grid');
            
            if (!data.compliance || data.compliance.length === 0) {
                $grid.html(`
                    <div class="univga-compliance-empty">
                        <svg fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                        </svg>
                        <h4>No Compliance Data</h4>
                        <p>Compliance status will appear here as certifications are assigned and earned</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            data.compliance.forEach(user => {
                const initials = user.display_name.split(' ').map(n => n[0]).join('').substr(0, 2).toUpperCase();
                const complianceRate = user.compliance_rate || 0;
                const statusClass = complianceRate >= 90 ? 'compliant' : (complianceRate >= 50 ? 'expiring' : 'non-compliant');
                const statusText = complianceRate >= 90 ? 'Compliant' : (complianceRate >= 50 ? 'Partial' : 'Non-Compliant');
                
                html += `
                    <div class="univga-compliance-item">
                        <div class="univga-compliance-avatar">${initials}</div>
                        <div class="univga-compliance-info">
                            <div class="univga-compliance-name">${user.display_name}</div>
                            <div class="univga-compliance-team">${user.team_name || 'No Team'}</div>
                        </div>
                        <div class="univga-compliance-status">
                            <span class="univga-compliance-badge ${statusClass}">${statusText}</span>
                            <div class="univga-compliance-progress">
                                <div class="univga-compliance-fill ${statusClass}" style="width: ${complianceRate}%"></div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $grid.html(html);
        },
        
        filterComplianceData: function() {
            this.loadComplianceData();
        },
        
        loadExpiringAlerts: function() {
            const $alerts = $('#expiring-alerts');
            $alerts.html('<div class="loading">Loading expiring certifications...</div>');
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_get_expiring_certifications',
                org_id: this.orgId,
                days_ahead: 30,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.displayExpiringAlerts(response.data);
                } else {
                    $alerts.html('<div class="univga-expiring-empty">No expiring certifications</div>');
                }
            })
            .fail(() => {
                $alerts.html('<div class="univga-expiring-empty">Failed to load expiring certifications</div>');
            });
        },
        
        displayExpiringAlerts: function(data) {
            const $alerts = $('#expiring-alerts');
            
            if (!data.expiring || data.expiring.length === 0) {
                $alerts.html(`
                    <div class="univga-expiring-empty">
                        <svg fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                        </svg>
                        <h4>All Certifications Up to Date</h4>
                        <p>No certifications are expiring in the next 30 days</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            data.expiring.forEach(item => {
                const daysLeft = Math.ceil((new Date(item.expires_date) - new Date()) / (1000 * 60 * 60 * 24));
                const urgency = daysLeft <= 7 ? 'critical' : (daysLeft <= 14 ? 'warning' : 'normal');
                
                html += `
                    <div class="univga-expiring-item">
                        <div class="univga-expiring-info">
                            <h5>${item.user_name} - ${item.certification_name}</h5>
                            <p>${item.team_name || 'No Team'}</p>
                        </div>
                        <div class="univga-expiring-meta">
                            <div class="univga-expiring-date">${this.formatDate(item.expires_date)}</div>
                            <span class="univga-expiring-badge">${daysLeft} days</span>
                        </div>
                    </div>
                `;
            });
            
            $alerts.html(html);
        },
        
        showCreateCertificationModal: function() {
            this.showNotice('info', 'Certification creation modal coming soon');
        },
        
        editCertification: function(certId) {
            this.showNotice('info', `Edit certification functionality coming soon for certification ${certId}`);
        },
        
        showAssignCertificationModal: function(certId) {
            this.showNotice('info', `Assign certification functionality coming soon for certification ${certId}`);
        },
        
        viewCertificationDetails: function(certId) {
            this.showNotice('info', `Certification details view coming soon for certification ${certId}`);
        },
        
        generateComplianceReport: function() {
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_export_compliance_report',
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', 'Compliance report generated and downloaded');
                    // Trigger download
                    window.location.href = response.data.download_url;
                } else {
                    this.showNotice('error', response.data || 'Failed to generate compliance report');
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to generate compliance report');
            });
        },
        
        sendRenewalReminders: function() {
            if (!confirm('Send renewal reminders to all users with expiring certifications?')) {
                return;
            }
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_send_renewal_reminders',
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', `Renewal reminders sent to ${response.data.count} users`);
                } else {
                    this.showNotice('error', response.data || 'Failed to send renewal reminders');
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to send renewal reminders');
            });
        },
        
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        },
        
        // ===================== BRANDING / WHITE-LABEL =====================
        
        initBranding: function() {
            this.loadBrandingSettings();
            this.initBrandingEvents();
            this.initColorPickers();
            this.initThemePresets();
        },
        
        initBrandingEvents: function() {
            // File upload events
            $('#logo-upload').on('change', (e) => {
                this.handleLogoUpload(e, 'logo');
            });
            
            $('#favicon-upload').on('change', (e) => {
                this.handleLogoUpload(e, 'favicon');
            });
            
            $('#remove-logo').on('click', () => {
                this.removeLogo('logo');
            });
            
            $('#remove-favicon').on('click', () => {
                this.removeLogo('favicon');
            });
            
            // Color picker events
            $('.univga-color-picker input[type="color"]').on('change', (e) => {
                this.handleColorChange(e);
            });
            
            // Theme preset events
            $('.univga-theme-preset').on('click', (e) => {
                this.applyThemePreset($(e.currentTarget).data('theme'));
            });
            
            // CSS editor events
            $('#validate-css').on('click', () => {
                this.validateCustomCSS();
            });
            
            $('#reset-css').on('click', () => {
                this.resetCustomCSS();
            });
            
            // Main action buttons
            $('#preview-changes').on('click', () => {
                this.previewBrandingChanges();
            });
            
            $('#save-branding').on('click', () => {
                this.saveBrandingSettings();
            });
            
            // Domain settings
            $('#custom-domain').on('change', () => {
                this.validateCustomDomain();
            });
        },
        
        initColorPickers: function() {
            // Update hex display when color changes
            $('.univga-color-picker input[type="color"]').each(function() {
                const $input = $(this);
                const $hex = $input.siblings('.univga-color-hex');
                $hex.text($input.val());
                
                $input.on('input', function() {
                    $hex.text($(this).val());
                    this.updatePreview();
                }.bind(this));
            }.bind(this));
        },
        
        initThemePresets: function() {
            this.themePresets = {
                blue: {
                    primary: '#3b82f6',
                    secondary: '#10b981',
                    accent: '#f59e0b',
                    text: '#374151'
                },
                purple: {
                    primary: '#8b5cf6',
                    secondary: '#06b6d4',
                    accent: '#f59e0b',
                    text: '#374151'
                },
                green: {
                    primary: '#10b981',
                    secondary: '#059669',
                    accent: '#f59e0b',
                    text: '#374151'
                },
                red: {
                    primary: '#ef4444',
                    secondary: '#dc2626',
                    accent: '#f59e0b',
                    text: '#374151'
                }
            };
        },
        
        loadBrandingSettings: function() {
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_get_branding_settings',
                org_id: this.orgId,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.applyBrandingSettings(response.data);
                }
            })
            .fail(() => {
                // Silent fail, use defaults
            });
        },
        
        applyBrandingSettings: function(settings) {
            // Apply logo
            if (settings.logo) {
                this.displayUploadedImage('logo', settings.logo);
            }
            
            if (settings.favicon) {
                this.displayUploadedImage('favicon', settings.favicon);
            }
            
            // Apply colors
            if (settings.colors) {
                $('#primary-color').val(settings.colors.primary || '#3b82f6');
                $('#secondary-color').val(settings.colors.secondary || '#10b981');
                $('#accent-color').val(settings.colors.accent || '#f59e0b');
                $('#text-color').val(settings.colors.text || '#374151');
                
                // Update hex displays
                $('.univga-color-picker input[type="color"]').trigger('input');
            }
            
            // Apply custom domain
            if (settings.custom_domain) {
                $('#custom-domain').val(settings.custom_domain);
            }
            
            // Apply custom CSS
            if (settings.custom_css) {
                $('#custom-css').val(settings.custom_css);
            }
            
            // Update preview
            this.updatePreview();
        },
        
        handleLogoUpload: function(event, type) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Validate file
            if (!file.type.startsWith('image/')) {
                this.showNotice('error', 'Please select a valid image file');
                return;
            }
            
            if (file.size > 2 * 1024 * 1024) { // 2MB limit
                this.showNotice('error', 'Image file must be smaller than 2MB');
                return;
            }
            
            // Create preview
            const reader = new FileReader();
            reader.onload = (e) => {
                this.displayUploadedImage(type, e.target.result);
                this.updatePreview();
            };
            reader.readAsDataURL(file);
            
            // Upload file
            this.uploadBrandingAsset(file, type);
        },
        
        displayUploadedImage: function(type, imageSrc) {
            const $preview = $(`#${type}-preview`);
            const $removeBtn = $(`#remove-${type}`);
            
            $preview.addClass('has-image')
                   .html(`<img src="${imageSrc}" alt="${type}">`);
            
            $removeBtn.show();
        },
        
        removeLogo: function(type) {
            const $preview = $(`#${type}-preview`);
            const $removeBtn = $(`#remove-${type}`);
            
            // Reset preview to default state
            if (type === 'logo') {
                $preview.removeClass('has-image').html(`
                    <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M.002 3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-12a2 2 0 0 1-2-2V3zm1 9v1a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12zm5-6.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0z"/>
                    </svg>
                    <p>Main Logo</p>
                `);
            } else {
                $preview.removeClass('has-image').html(`
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                    </svg>
                    <p>Favicon</p>
                `);
            }
            
            $removeBtn.hide();
            this.updatePreview();
        },
        
        handleColorChange: function(event) {
            const $input = $(event.target);
            const $hex = $input.siblings('.univga-color-hex');
            $hex.text($input.val());
            
            this.updatePreview();
        },
        
        applyThemePreset: function(theme) {
            const preset = this.themePresets[theme];
            if (!preset) return;
            
            // Update color inputs
            $('#primary-color').val(preset.primary);
            $('#secondary-color').val(preset.secondary);
            $('#accent-color').val(preset.accent);
            $('#text-color').val(preset.text);
            
            // Update hex displays
            $('.univga-color-picker input[type="color"]').trigger('input');
            
            // Update active preset
            $('.univga-theme-preset').removeClass('active');
            $(`.univga-theme-preset[data-theme="${theme}"]`).addClass('active');
            
            this.updatePreview();
        },
        
        updatePreview: function() {
            const colors = this.getCurrentColors();
            
            // Update CSS variables in preview
            const $mockup = $('.univga-mockup-header, .univga-mockup-btn');
            $mockup.css('background', colors.primary);
            
            // Update logo in preview if available
            const $logoPreview = $('#logo-preview');
            if ($logoPreview.hasClass('has-image')) {
                const logoSrc = $logoPreview.find('img').attr('src');
                $('.univga-mockup-logo').html(`<img src="${logoSrc}" style="height: 24px;">`);
            } else {
                $('.univga-mockup-logo').text('Your Logo');
            }
        },
        
        getCurrentColors: function() {
            return {
                primary: $('#primary-color').val(),
                secondary: $('#secondary-color').val(),
                accent: $('#accent-color').val(),
                text: $('#text-color').val()
            };
        },
        
        validateCustomCSS: function() {
            const css = $('#custom-css').val();
            
            // Basic CSS validation
            try {
                const styleEl = document.createElement('style');
                styleEl.textContent = css;
                document.head.appendChild(styleEl);
                document.head.removeChild(styleEl);
                
                this.showNotice('success', 'CSS is valid');
            } catch (error) {
                this.showNotice('error', 'CSS contains syntax errors');
            }
        },
        
        resetCustomCSS: function() {
            if (confirm('Reset custom CSS? This will remove all custom styling.')) {
                $('#custom-css').val('');
            }
        },
        
        validateCustomDomain: function() {
            const domain = $('#custom-domain').val();
            
            if (!domain) return;
            
            // Basic domain validation
            const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$/;
            if (!domainRegex.test(domain)) {
                this.showNotice('error', 'Please enter a valid domain name');
                return;
            }
            
            // Check DNS configuration (mock for now)
            $('.univga-domain-status').html(`
                <div class="univga-status-item">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                        <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                    </svg>
                    <span>Checking DNS configuration...</span>
                </div>
            `);
        },
        
        previewBrandingChanges: function() {
            this.showNotice('info', 'Opening preview in new window...');
            
            // Get current settings
            const settings = {
                colors: this.getCurrentColors(),
                css: $('#custom-css').val(),
                domain: $('#custom-domain').val()
            };
            
            // Open preview window (mock for now)
            this.showNotice('info', 'Preview functionality coming soon');
        },
        
        saveBrandingSettings: function() {
            const settings = {
                colors: this.getCurrentColors(),
                custom_css: $('#custom-css').val(),
                custom_domain: $('#custom-domain').val()
            };
            
            $.post(univga_dashboard.ajaxurl, {
                action: 'univga_save_branding_settings',
                org_id: this.orgId,
                settings: settings,
                nonce: univga_dashboard.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', 'Branding settings saved successfully');
                } else {
                    this.showNotice('error', response.data || 'Failed to save branding settings');
                }
            })
            .fail(() => {
                this.showNotice('error', 'Failed to save branding settings');
            });
        },
        
        uploadBrandingAsset: function(file, type) {
            const formData = new FormData();
            formData.append('action', 'univga_upload_branding_asset');
            formData.append('org_id', this.orgId);
            formData.append('asset_type', type);
            formData.append('file', file);
            formData.append('nonce', univga_dashboard.nonce);
            
            $.ajax({
                url: univga_dashboard.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', `${type} uploaded successfully`);
                } else {
                    this.showNotice('error', response.data || `Failed to upload ${type}`);
                }
            })
            .fail(() => {
                this.showNotice('error', `Failed to upload ${type}`);
            });
        }
    };
    
    // Initialize dashboard when DOM is ready
    $(document).ready(function() {
        if ($('.univga-dashboard').length) {
            UnivgaDashboard.init();
        }
    });
    
    // AJAX handlers for WordPress admin-ajax.php
    $(document).on('submit', '#invite-form', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Sending...');
        
        $.post(univga_dashboard.ajaxurl, {
            action: 'univga_send_invitation',
            org_id: $('.univga-dashboard').data('org-id'),
            email: $('#invite-email').val(),
            team_id: $('#invite-team').val() || null,
            nonce: univga_dashboard.nonce
        })
        .done(function(response) {
            if (response.success) {
                UnivgaDashboard.showNotice('success', response.data);
                UnivgaDashboard.closeModal();
            } else {
                UnivgaDashboard.showNotice('error', response.data, $form);
            }
        })
        .fail(function() {
            UnivgaDashboard.showNotice('error', univga_dashboard.strings.error, $form);
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
})(jQuery);

// Add WordPress REST API nonce
jQuery(document).ready(function($) {
    if (typeof wp !== 'undefined' && wp.api) {
        wp.api.init({
            versionString: $('#_wpnonce').val() || univga_dashboard.nonce
        });
    }
});

