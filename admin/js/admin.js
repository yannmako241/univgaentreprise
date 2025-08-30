/**
 * UNIVGA Modern Admin Interface JavaScript
 */
(function($) {
    'use strict';

    // Admin Dashboard Object
    var UnivgaAdmin = {
        
        init: function() {
            this.initDashboard();
            this.initMetrics();
            this.initDataTables();
            this.initModals();
            this.initPermissions();
            this.bindEvents();
        },
        
        initDashboard: function() {
            // Load dashboard widgets
            this.loadDashboardMetrics();
            this.initRealTimeUpdates();
        },
        
        initMetrics: function() {
            // Animate metric cards on load
            $('.univga-metric-card').each(function(index) {
                $(this).delay(index * 100).queue(function() {
                    $(this).addClass('univga-fade-in').dequeue();
                });
            });
            
            // Load metrics data
            this.refreshMetrics();
        },
        
        loadDashboardMetrics: function() {
            $.post(ajaxurl, {
                action: 'univga_load_dashboard_metrics',
                nonce: univga_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnivgaAdmin.updateMetrics(response.data);
                }
            });
        },
        
        updateMetrics: function(data) {
            // Update organization metrics
            $('#total-organizations').text(data.organizations.total || 0);
            $('#active-organizations').text(data.organizations.active || 0);
            $('#pending-organizations').text(data.organizations.pending || 0);
            
            // Update member metrics  
            $('#total-members').text(data.members.total || 0);
            $('#active-members').text(data.members.active || 0);
            $('#new-members-month').text(data.members.new_this_month || 0);
            
            // Update seat metrics
            $('#total-seats').text(data.seats.total || 0);
            $('#used-seats').text(data.seats.used || 0);
            $('#utilization-rate').text((data.seats.utilization_rate || 0) + '%');
            
            // Update financial metrics (for accountant profile)
            if (data.financial) {
                $('#monthly-revenue').text('$' + (data.financial.monthly_revenue || 0).toLocaleString());
                $('#total-revenue').text('$' + (data.financial.total_revenue || 0).toLocaleString());
                $('#pending-payments').text('$' + (data.financial.pending_payments || 0).toLocaleString());
            }
        },
        
        refreshMetrics: function() {
            $('.univga-metric-card').addClass('loading');
            
            // Simulate metric refresh
            setTimeout(function() {
                $('.univga-metric-card').removeClass('loading');
                UnivgaAdmin.loadDashboardMetrics();
            }, 1000);
        },
        
        initDataTables: function() {
            // Enhanced table interactions
            $('.univga-data-table tbody tr').on('click', function(e) {
                if (!$(e.target).closest('.row-actions').length) {
                    $(this).toggleClass('selected');
                }
            });
            
            // Bulk actions
            $('#bulk-action-select').on('change', function() {
                var action = $(this).val();
                var $btn = $('#bulk-action-apply');
                
                if (action) {
                    $btn.prop('disabled', false).removeClass('univga-btn-secondary').addClass('univga-btn-primary');
                } else {
                    $btn.prop('disabled', true).removeClass('univga-btn-primary').addClass('univga-btn-secondary');
                }
            });
            
            // Table search
            $('.univga-table-search').on('input', this.debounce(function() {
                UnivgaAdmin.filterTable($(this).val());
            }, 300));
        },
        
        filterTable: function(search) {
            var $table = $('.univga-data-table tbody');
            var $rows = $table.find('tr');
            
            if (!search) {
                $rows.show();
                return;
            }
            
            $rows.each(function() {
                var text = $(this).text().toLowerCase();
                if (text.indexOf(search.toLowerCase()) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },
        
        initModals: function() {
            // Modern modal system
            $(document).on('click', '[data-modal]', function(e) {
                e.preventDefault();
                var modalId = $(this).data('modal');
                UnivgaAdmin.showModal(modalId);
            });
            
            $(document).on('click', '.modal-close', function() {
                UnivgaAdmin.hideModal();
            });
            
            $(document).on('click', '.modal-backdrop', function(e) {
                if (e.target === this) {
                    UnivgaAdmin.hideModal();
                }
            });
        },
        
        showModal: function(modalId) {
            var $modal = $('#' + modalId);
            if ($modal.length) {
                $('body').addClass('modal-open');
                $modal.addClass('show').focus();
                
                // Trap focus within modal
                this.trapFocus($modal);
            }
        },
        
        hideModal: function() {
            $('.modal.show').removeClass('show');
            $('body').removeClass('modal-open');
        },
        
        trapFocus: function($modal) {
            var focusableElements = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            var firstElement = focusableElements.first();
            var lastElement = focusableElements.last();
            
            $modal.on('keydown', function(e) {
                if (e.key === 'Tab') {
                    if (e.shiftKey && document.activeElement === firstElement[0]) {
                        e.preventDefault();
                        lastElement.focus();
                    } else if (!e.shiftKey && document.activeElement === lastElement[0]) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
                
                if (e.key === 'Escape') {
                    UnivgaAdmin.hideModal();
                }
            });
        },
        
        initPermissions: function() {
            // Permission profile management
            $('.permission-profile-select').on('change', function() {
                var profile = $(this).val();
                var userId = $(this).data('user-id');
                UnivgaAdmin.updateUserProfile(userId, profile);
            });
            
            // Load current user profile capabilities
            this.loadUserProfileCapabilities();
        },
        
        updateUserProfile: function(userId, profile) {
            $.post(ajaxurl, {
                action: 'univga_update_user_profile',
                user_id: userId,
                profile: profile,
                nonce: univga_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnivgaAdmin.showNotice('User profile updated successfully', 'success');
                    // Refresh capabilities display
                    UnivgaAdmin.refreshUserCapabilities(userId);
                } else {
                    UnivgaAdmin.showNotice(response.data || 'Failed to update user profile', 'error');
                }
            })
            .fail(function() {
                UnivgaAdmin.showNotice('Failed to update user profile', 'error');
            });
        },
        
        loadUserProfileCapabilities: function() {
            $('.user-capabilities-list').each(function() {
                var userId = $(this).data('user-id');
                UnivgaAdmin.refreshUserCapabilities(userId);
            });
        },
        
        refreshUserCapabilities: function(userId) {
            var $container = $('[data-user-id="' + userId + '"]');
            
            $.post(ajaxurl, {
                action: 'univga_get_user_capabilities',
                user_id: userId,
                nonce: univga_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnivgaAdmin.displayUserCapabilities($container, response.data);
                }
            });
        },
        
        displayUserCapabilities: function($container, capabilities) {
            var html = '<div class="capabilities-grid">';
            
            // Group capabilities by category
            var categories = {
                'Organizations': capabilities.organization || [],
                'Teams': capabilities.teams || [],
                'Members': capabilities.members || [],
                'Analytics': capabilities.analytics || [],
                'Financial': capabilities.financial || []
            };
            
            for (var category in categories) {
                if (categories[category].length > 0) {
                    html += '<div class="capability-category">';
                    html += '<h4>' + category + '</h4>';
                    html += '<ul>';
                    
                    categories[category].forEach(function(cap) {
                        html += '<li><i class="dashicons dashicons-yes"></i> ' + cap.label + '</li>';
                    });
                    
                    html += '</ul></div>';
                }
            }
            
            html += '</div>';
            $container.html(html);
        },
        
        initRealTimeUpdates: function() {
            // Set up periodic updates for dashboard metrics
            if ($('.univga-admin-wrap[data-page="dashboard"]').length) {
                setInterval(function() {
                    UnivgaAdmin.loadDashboardMetrics();
                }, 60000); // Update every minute
            }
        },
        
        bindEvents: function() {
            // Refresh button
            $(document).on('click', '.refresh-metrics', function(e) {
                e.preventDefault();
                UnivgaAdmin.refreshMetrics();
            });
            
            // Export actions
            $(document).on('click', '.export-data', function(e) {
                e.preventDefault();
                var exportType = $(this).data('export');
                UnivgaAdmin.exportData(exportType);
            });
            
            // Quick actions
            $(document).on('click', '.quick-action', function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                var itemId = $(this).data('item-id');
                UnivgaAdmin.performQuickAction(action, itemId);
            });
            
            // Bulk actions
            $(document).on('click', '#bulk-action-apply', function(e) {
                e.preventDefault();
                var action = $('#bulk-action-select').val();
                var selectedIds = [];
                
                $('.univga-data-table tbody tr.selected').each(function() {
                    selectedIds.push($(this).data('id'));
                });
                
                if (selectedIds.length === 0) {
                    UnivgaAdmin.showNotice('Please select items to perform bulk action', 'warning');
                    return;
                }
                
                UnivgaAdmin.performBulkAction(action, selectedIds);
            });
            
            // Search functionality
            $('.search-box input').on('keypress', function(e) {
                if (e.which === 13) {
                    $(this).closest('form').submit();
                }
            });
        },
        
        exportData: function(type) {
            this.showNotice('Preparing export...', 'info');
            
            $.post(ajaxurl, {
                action: 'univga_export_data',
                export_type: type,
                nonce: univga_admin.nonce
            })
            .done(function(response) {
                if (response.success && response.data.download_url) {
                    // Trigger download
                    window.location.href = response.data.download_url;
                    UnivgaAdmin.showNotice('Export completed', 'success');
                } else {
                    UnivgaAdmin.showNotice(response.data || 'Export failed', 'error');
                }
            })
            .fail(function() {
                UnivgaAdmin.showNotice('Export failed', 'error');
            });
        },
        
        performQuickAction: function(action, itemId) {
            var confirmMessage = this.getActionConfirmMessage(action);
            
            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }
            
            $.post(ajaxurl, {
                action: 'univga_quick_action',
                quick_action: action,
                item_id: itemId,
                nonce: univga_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnivgaAdmin.showNotice(response.data.message || 'Action completed', 'success');
                    // Refresh the relevant section
                    location.reload();
                } else {
                    UnivgaAdmin.showNotice(response.data || 'Action failed', 'error');
                }
            })
            .fail(function() {
                UnivgaAdmin.showNotice('Action failed', 'error');
            });
        },
        
        performBulkAction: function(action, selectedIds) {
            var confirmMessage = this.getBulkActionConfirmMessage(action, selectedIds.length);
            
            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }
            
            $.post(ajaxurl, {
                action: 'univga_bulk_action',
                bulk_action: action,
                selected_ids: selectedIds,
                nonce: univga_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    UnivgaAdmin.showNotice(response.data.message || 'Bulk action completed', 'success');
                    location.reload();
                } else {
                    UnivgaAdmin.showNotice(response.data || 'Bulk action failed', 'error');
                }
            })
            .fail(function() {
                UnivgaAdmin.showNotice('Bulk action failed', 'error');
            });
        },
        
        getActionConfirmMessage: function(action) {
            var messages = {
                'delete': 'Are you sure you want to delete this item?',
                'deactivate': 'Are you sure you want to deactivate this item?',
                'resync': 'This will resync data. Continue?'
            };
            
            return messages[action] || null;
        },
        
        getBulkActionConfirmMessage: function(action, count) {
            var messages = {
                'delete': `Are you sure you want to delete ${count} items?`,
                'deactivate': `Are you sure you want to deactivate ${count} items?`,
                'activate': `Are you sure you want to activate ${count} items?`
            };
            
            return messages[action] || null;
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.univga-admin-wrap').prepend($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        UnivgaAdmin.init();
    });
    
    // Make UnivgaAdmin globally accessible
    window.UnivgaAdmin = UnivgaAdmin;
    
})(jQuery);