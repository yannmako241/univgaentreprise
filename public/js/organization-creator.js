/**
 * Organization Creator - Handles new organization creation from frontend dashboard
 */
(function($) {
    'use strict';

    // Add new organization functionality to existing dashboard
    $(document).ready(function() {
        
        // Handle new organization button click
        $(document).on('click', '[data-action="new-organization"]', function(e) {
            e.preventDefault();
            console.log('New organization button clicked');
            openNewOrganizationModal();
        });

        // Handle new organization form submission
        $(document).on('submit', '#new-organization-form', function(e) {
            e.preventDefault();
            console.log('New organization form submitted');
            createNewOrganization();
        });
        
        // Handle modal close
        $(document).on('click', '[data-dismiss="modal"]', function() {
            $('.univga-modal').removeClass('show');
        });
        
        // Close modal on overlay click
        $(document).on('click', '.univga-modal', function(e) {
            if (e.target === this) {
                $(this).removeClass('show');
            }
        });
    });

    function openNewOrganizationModal() {
        console.log('Opening new organization modal');
        
        // Create modal if it doesn't exist
        if ($('#new-organization-modal').length === 0) {
            createNewOrganizationModal();
        }
        
        // Show modal
        $('#new-organization-modal').addClass('show');
    }

    function createNewOrganizationModal() {
        const modalHtml = `
        <div class="univga-modal" id="new-organization-modal">
            <div class="univga-modal-dialog">
                <div class="univga-modal-header">
                    <h3>Create New Organization</h3>
                    <button type="button" class="univga-modal-close" data-dismiss="modal">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z"/>
                        </svg>
                    </button>
                </div>
                <div class="univga-modal-body">
                    <form id="new-organization-form">
                        <div class="univga-form-group">
                            <label for="new-org-name">Organization Name *</label>
                            <input type="text" id="new-org-name" name="name" required placeholder="Enter organization name">
                        </div>
                        
                        <div class="univga-form-group">
                            <label for="new-org-legal-id">Legal ID/SIRET</label>
                            <input type="text" id="new-org-legal-id" name="legal_id" placeholder="Enter legal identifier">
                        </div>
                        
                        <div class="univga-form-group">
                            <label for="new-org-email-domain">Email Domain</label>
                            <input type="text" id="new-org-email-domain" name="email_domain" placeholder="e.g., company.com (optional)">
                            <small class="univga-form-help">If set, only users with this domain can join automatically</small>
                        </div>
                        
                        <div class="univga-form-group">
                            <label for="new-org-contact-user">Contact Person *</label>
                            <select id="new-org-contact-user" name="contact_user_id" required>
                                <option value="">Select contact person</option>
                                <option value="1" selected>Current User (Me)</option>
                            </select>
                        </div>
                        
                        <div class="univga-form-group">
                            <label for="new-org-status">Status</label>
                            <select id="new-org-status" name="status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="univga-form-actions">
                            <button type="submit" class="univga-btn univga-btn-primary">
                                Create Organization
                            </button>
                            <button type="button" class="univga-btn univga-btn-secondary" data-dismiss="modal">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>`;
        
        $('body').append(modalHtml);
        console.log('New organization modal created');
    }

    function createNewOrganization() {
        const formData = {
            action: 'create_organization',
            name: $('#new-org-name').val(),
            legal_id: $('#new-org-legal-id').val(),
            email_domain: $('#new-org-email-domain').val(),
            contact_user_id: $('#new-org-contact-user').val(),
            status: $('#new-org-status').val(),
            nonce: $('#_wpnonce').val() || 'temp_nonce' // Fallback for testing
        };

        console.log('Creating organization with data:', formData);

        // Show loading state
        const submitBtn = $('#new-organization-form button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.text('Creating...').prop('disabled', true);

        // Make AJAX request
        $.ajax({
            url: '/wp-admin/admin-ajax.php', // WordPress AJAX endpoint
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Organization creation response:', response);
                
                if (response.success) {
                    // Success
                    showSuccessMessage('Organization created successfully!');
                    $('#new-organization-modal').removeClass('show');
                    $('#new-organization-form')[0].reset();
                    
                    // Optionally reload page or update UI
                    setTimeout(function() {
                        location.reload(); // Reload to show new organization
                    }, 1500);
                } else {
                    // Error
                    showErrorMessage(response.data || 'Failed to create organization');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showErrorMessage('Network error. Please check your connection and try again.');
            },
            complete: function() {
                // Reset button
                submitBtn.text(originalText).prop('disabled', false);
            }
        });
    }

    function showSuccessMessage(message) {
        showNotification(message, 'success');
    }

    function showErrorMessage(message) {
        showNotification(message, 'error');
    }

    function showNotification(message, type) {
        // Remove existing notifications
        $('.univga-notification').remove();
        
        // Create notification
        const notification = $(`
            <div class="univga-notification univga-notification-${type}">
                <div class="univga-notification-content">
                    <span>${message}</span>
                    <button class="univga-notification-close">&times;</button>
                </div>
            </div>
        `);
        
        // Add to page
        $('body').append(notification);
        
        // Show with animation
        setTimeout(() => notification.addClass('show'), 100);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notification.removeClass('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Handle close button
        notification.find('.univga-notification-close').on('click', function() {
            notification.removeClass('show');
            setTimeout(() => notification.remove(), 300);
        });
    }

})(jQuery);