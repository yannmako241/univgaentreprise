/**
 * Messages Tab JavaScript for UNIVGA Dashboard
 */
jQuery(document).ready(function($) {
    // Extend UnivgaDashboard with messages functionality
    if (typeof UnivgaDashboard !== 'undefined') {
        
        // Messages data storage
        UnivgaDashboard.messagesData = null;
        UnivgaDashboard.currentView = 'conversations';
        UnivgaDashboard.currentConversationId = null;
        UnivgaDashboard.messageFilters = {
            search: null
        };
        
        /**
         * Load messages data
         */
        UnivgaDashboard.loadMessages = function(view, conversationId) {
            const self = this;
            view = view || this.currentView;
            
            // Show loading state
            this.showMessagesLoading(view);
            
            // Build query parameters
            const params = new URLSearchParams();
            params.append('view', view);
            
            if (conversationId) {
                params.append('conversation_id', conversationId);
            }
            
            if (this.messageFilters.search) {
                params.append('search', this.messageFilters.search);
            }
            
            const queryString = params.toString();
            const url = univga_dashboard.rest_url + 'organizations/' + this.orgId + '/messages' + 
                       (queryString ? '?' + queryString : '');
            
            $.ajax({
                url: url,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', univga_dashboard.nonce);
                }
            })
            .done(function(data) {
                self.messagesData = data;
                self.currentView = view;
                self.currentConversationId = conversationId;
                self.renderMessages(data);
                self.hideMessagesLoading();
            })
            .fail(function(xhr) {
                console.error('Failed to load messages:', xhr);
                self.hideMessagesLoading();
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Échec du chargement des messages';
                $('#conversations-list').html('<div class="univga-notice univga-notice-error">' + errorMsg + '</div>');
            });
        };
        
        /**
         * Show messages loading state
         */
        UnivgaDashboard.showMessagesLoading = function(view) {
            if (view === 'archived') {
                $('#archived-conversations').html('<div class="loading">Chargement des conversations archivées...</div>');
            } else {
                $('#conversations-list').html('<div class="loading">Chargement des conversations...</div>');
            }
        };
        
        /**
         * Hide messages loading state
         */
        UnivgaDashboard.hideMessagesLoading = function() {
            // Loading handled by render functions
        };
        
        /**
         * Render messages data
         */
        UnivgaDashboard.renderMessages = function(data) {
            if (data.current_view === 'archived') {
                this.renderArchivedConversations(data.archived_conversations);
            } else {
                this.renderConversations(data.conversations);
            }
            
            if (data.current_conversation_id && data.conversation_messages) {
                this.renderConversationMessages(data.conversation_messages, data.current_conversation_id);
            }
            
            this.updateMessagesBadges(data.stats);
        };
        
        /**
         * Render conversations list
         */
        UnivgaDashboard.renderConversations = function(conversations) {
            const $container = $('#conversations-list');
            
            if (!conversations || conversations.length === 0) {
                $container.html(`
                    <div class="univga-empty-state">
                        <div class="univga-empty-icon">💬</div>
                        <h3>Aucune conversation</h3>
                        <p>Commencez une nouvelle conversation avec vos collègues d'équipe.</p>
                        <button type="button" class="univga-btn univga-btn-primary" onclick="UnivgaDashboard.showNewMessageModal()">
                            Nouveau Message
                        </button>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="univga-conversations-grid">';
            
            conversations.forEach(conv => {
                const unreadClass = conv.is_unread ? 'unread' : '';
                const unreadBadge = conv.unread_count > 0 ? `<span class="univga-unread-badge">${conv.unread_count}</span>` : '';
                
                html += `
                    <div class="univga-conversation-item ${unreadClass}" data-conversation-id="${conv.id}">
                        <div class="univga-conversation-header">
                            <div class="univga-conversation-info">
                                <h4 class="univga-conversation-title">${conv.subject || 'Conversation'}</h4>
                                <div class="univga-conversation-meta">
                                    <span class="univga-participants">${conv.participant_count} participants</span>
                                    <span class="univga-time">${conv.time_ago}</span>
                                    ${unreadBadge}
                                </div>
                            </div>
                            <div class="univga-conversation-actions">
                                <button type="button" class="univga-conversation-action" data-action="archive" data-conv-id="${conv.id}" title="Archiver">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M0 2a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1v7.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 1 12.5V5a1 1 0 0 1-1-1V2zm2 3v7.5A1.5 1.5 0 0 0 3.5 14h9a1.5 1.5 0 0 0 1.5-1.5V5H2zm13-3H1v2h14V2zM5 7.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="univga-conversation-preview">
                            <div class="univga-last-message">
                                <strong>${conv.last_sender_name || conv.created_by_name}:</strong> 
                                ${conv.last_message_preview || 'Aucun message'}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $container.html(html);
        };
        
        /**
         * Render archived conversations
         */
        UnivgaDashboard.renderArchivedConversations = function(conversations) {
            const $container = $('#archived-conversations');
            
            if (!conversations || conversations.length === 0) {
                $container.html(`
                    <div class="univga-empty-state">
                        <div class="univga-empty-icon">📥</div>
                        <h3>Aucune conversation archivée</h3>
                        <p>Les conversations archivées apparaîtront ici.</p>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="univga-archived-grid">';
            
            conversations.forEach(conv => {
                html += `
                    <div class="univga-archived-item" data-conversation-id="${conv.id}">
                        <div class="univga-archived-header">
                            <h4 class="univga-archived-title">${conv.subject || 'Conversation'}</h4>
                            <div class="univga-archived-meta">
                                <span class="univga-participants">${conv.participant_count} participants</span>
                                <span class="univga-archived-date">${conv.time_ago}</span>
                            </div>
                        </div>
                        
                        <div class="univga-archived-preview">
                            ${conv.last_message_preview || 'Aucun message'}
                        </div>
                        
                        <div class="univga-archived-actions">
                            <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.unarchiveConversation('${conv.id}')">
                                Désarchiver
                            </button>
                            <button type="button" class="univga-btn univga-btn-small univga-btn-outline" onclick="UnivgaDashboard.viewArchivedConversation('${conv.id}')">
                                Voir
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $container.html(html);
        };
        
        /**
         * Render conversation messages
         */
        UnivgaDashboard.renderConversationMessages = function(messages, conversationId) {
            const $chatPanel = $('#chat-panel');
            
            if (!messages || messages.length === 0) {
                $chatPanel.html(`
                    <div class="univga-chat-empty">
                        <div class="univga-chat-header">
                            <h4>Conversation</h4>
                            <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.closeConversation()">
                                Fermer
                            </button>
                        </div>
                        <div class="univga-empty-chat">
                            <p>Aucun message dans cette conversation. Commencez la discussion !</p>
                        </div>
                        ${this.renderMessageInput(conversationId)}
                    </div>
                `);
                return;
            }
            
            let html = `
                <div class="univga-chat-container">
                    <div class="univga-chat-header">
                        <h4>Conversation</h4>
                        <div class="univga-chat-actions">
                            <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.archiveConversation('${conversationId}')">
                                Archiver
                            </button>
                            <button type="button" class="univga-btn univga-btn-small" onclick="UnivgaDashboard.closeConversation()">
                                Fermer
                            </button>
                        </div>
                    </div>
                    
                    <div class="univga-messages-list" id="messages-list">
            `;
            
            messages.forEach(msg => {
                const messageClass = msg.is_own_message ? 'own-message' : 'other-message';
                
                html += `
                    <div class="univga-message-item ${messageClass}">
                        <div class="univga-message-avatar">
                            ${msg.sender_initials}
                        </div>
                        <div class="univga-message-content">
                            <div class="univga-message-header">
                                <span class="univga-sender-name">${msg.sender_name}</span>
                                <span class="univga-message-time">${msg.time_ago}</span>
                            </div>
                            <div class="univga-message-text">
                                ${msg.message}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                    ${this.renderMessageInput(conversationId)}
                </div>
            `;
            
            $chatPanel.html(html);
            
            // Scroll to bottom
            const messagesList = document.getElementById('messages-list');
            if (messagesList) {
                messagesList.scrollTop = messagesList.scrollHeight;
            }
        };
        
        /**
         * Render message input
         */
        UnivgaDashboard.renderMessageInput = function(conversationId) {
            return `
                <div class="univga-message-input-container">
                    <div class="univga-message-input">
                        <textarea id="message-input" placeholder="Tapez votre message..." rows="3"></textarea>
                        <button type="button" class="univga-send-btn" onclick="UnivgaDashboard.sendMessage('${conversationId}')">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M15.854.146a.5.5 0 0 1 .11.54L13.026 14.74a.5.5 0 0 1-.954.1L9.8 11.1l-3.6 3.6a.5.5 0 0 1-.854-.353V11.7L1.5 10.25a.5.5 0 0 1 .1-.954L15.406.256a.5.5 0 0 1 .448-.11z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        };
        
        /**
         * Update messages badges and counters
         */
        UnivgaDashboard.updateMessagesBadges = function(stats) {
            // Update tab badge if there are unread messages
            const totalUnread = stats.unread_messages + stats.unread_notifications;
            const $tab = $('[data-tab="messages"]');
            
            if (totalUnread > 0) {
                $tab.addClass('has-notifications');
                let badge = $tab.find('.univga-tab-badge');
                if (badge.length === 0) {
                    badge = $('<span class="univga-tab-badge"></span>');
                    $tab.append(badge);
                }
                badge.text(totalUnread);
            } else {
                $tab.removeClass('has-notifications');
                $tab.find('.univga-tab-badge').remove();
            }
        };
        
        /**
         * Action functions
         */
        UnivgaDashboard.showNewMessageModal = function() {
            this.showNotice('info', 'Nouvelle conversation en développement');
        };
        
        UnivgaDashboard.archiveConversation = function(conversationId) {
            if (confirm('Êtes-vous sûr de vouloir archiver cette conversation ?')) {
                this.showNotice('info', 'Archivage de conversation en développement');
            }
        };
        
        UnivgaDashboard.unarchiveConversation = function(conversationId) {
            this.showNotice('info', 'Désarchivage de conversation en développement');
        };
        
        UnivgaDashboard.viewArchivedConversation = function(conversationId) {
            this.loadMessages('conversations', conversationId);
        };
        
        UnivgaDashboard.closeConversation = function() {
            $('#chat-panel').html(`
                <div class="univga-chat-placeholder">
                    <div class="univga-chat-placeholder-icon">
                        <svg width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894zm-.493 3.905a21.682 21.682 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a9.68 9.68 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9.06 9.06 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105z"/>
                        </svg>
                    </div>
                    <h4>Sélectionner une conversation</h4>
                    <p>Choisissez une conversation dans la liste pour commencer à discuter avec vos collègues d'équipe.</p>
                </div>
            `);
            this.currentConversationId = null;
        };
        
        UnivgaDashboard.sendMessage = function(conversationId) {
            const messageText = $('#message-input').val().trim();
            if (!messageText) return;
            
            this.showNotice('info', 'Envoi de message en développement');
            $('#message-input').val('');
        };
        
        // Event handlers
        $(document).on('click', '.univga-msg-nav-btn', function() {
            const view = $(this).data('msg-view');
            $('.univga-msg-nav-btn').removeClass('active');
            $(this).addClass('active');
            
            $('.univga-messages-view').removeClass('active');
            $('#' + view + '-view').addClass('active');
            
            if (UnivgaDashboard && UnivgaDashboard.loadMessages) {
                UnivgaDashboard.loadMessages(view);
            }
        });
        
        $(document).on('click', '.univga-conversation-item', function() {
            const conversationId = $(this).data('conversation-id');
            $('.univga-conversation-item').removeClass('active');
            $(this).addClass('active');
            
            if (UnivgaDashboard && UnivgaDashboard.loadMessages) {
                UnivgaDashboard.loadMessages('conversations', conversationId);
            }
        });
        
        $(document).on('click', '[data-action="new-message"]', function() {
            if (UnivgaDashboard && UnivgaDashboard.showNewMessageModal) {
                UnivgaDashboard.showNewMessageModal();
            }
        });
        
        $(document).on('click', '.univga-conversation-action', function(e) {
            e.stopPropagation();
            const action = $(this).data('action');
            const convId = $(this).data('conv-id');
            
            if (action === 'archive' && UnivgaDashboard && UnivgaDashboard.archiveConversation) {
                UnivgaDashboard.archiveConversation(convId);
            }
        });
        
        $(document).on('keypress', '#message-input', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                const conversationId = UnivgaDashboard.currentConversationId;
                if (conversationId && UnivgaDashboard && UnivgaDashboard.sendMessage) {
                    UnivgaDashboard.sendMessage(conversationId);
                }
            }
        });
        
        // Load messages when tab is clicked
        $(document).on('click', '[data-tab="messages"]', function() {
            setTimeout(function() {
                if (UnivgaDashboard && UnivgaDashboard.loadMessages) {
                    UnivgaDashboard.loadMessages();
                }
            }, 100);
        });
    }
});