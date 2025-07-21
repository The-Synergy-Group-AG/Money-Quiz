/**
 * Money Quiz Notification Handler
 * 
 * Manages browser notifications, real-time updates, and in-app notifications
 */
(function($) {
    'use strict';
    
    const MoneyQuizNotifications = {
        
        // Configuration
        config: {
            apiUrl: moneyQuizAjax.restUrl + 'money-quiz/v1',
            nonce: moneyQuizAjax.nonce,
            vapidPublicKey: moneyQuizNotifications.vapidKey,
            checkInterval: 30000, // 30 seconds
            sseReconnectDelay: 5000,
            maxReconnectAttempts: 5
        },
        
        // State
        state: {
            pushSubscription: null,
            eventSource: null,
            unreadCount: 0,
            notifications: [],
            reconnectAttempts: 0,
            isSubscribed: false
        },
        
        /**
         * Initialize notification system
         */
        init() {
            // Check browser support
            if (!this.checkSupport()) {
                console.warn('Notifications not supported in this browser');
                return;
            }
            
            // Initialize components
            this.initServiceWorker();
            this.initRealTimeConnection();
            this.bindEvents();
            this.loadNotifications();
            
            // Check permission status
            this.checkNotificationPermission();
        },
        
        /**
         * Check browser support
         */
        checkSupport() {
            return 'Notification' in window && 
                   'serviceWorker' in navigator && 
                   'PushManager' in window;
        },
        
        /**
         * Initialize service worker
         */
        async initServiceWorker() {
            try {
                // Register service worker
                const registration = await navigator.serviceWorker.register(
                    moneyQuizNotifications.serviceWorkerUrl,
                    { scope: '/' }
                );
                
                console.log('Service Worker registered:', registration);
                
                // Check existing subscription
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    this.state.pushSubscription = subscription;
                    this.state.isSubscribed = true;
                    this.updateUI();
                }
                
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        },
        
        /**
         * Initialize real-time connection
         */
        initRealTimeConnection() {
            // Prefer WebSocket if available
            if (window.WebSocket && moneyQuizNotifications.websocketUrl) {
                this.connectWebSocket();
            } else {
                // Fall back to Server-Sent Events
                this.connectSSE();
            }
        },
        
        /**
         * Connect via WebSocket
         */
        connectWebSocket() {
            try {
                const ws = new WebSocket(moneyQuizNotifications.websocketUrl);
                
                ws.onopen = () => {
                    console.log('WebSocket connected');
                    this.state.reconnectAttempts = 0;
                    
                    // Authenticate
                    ws.send(JSON.stringify({
                        type: 'auth',
                        token: this.config.nonce
                    }));
                };
                
                ws.onmessage = (event) => {
                    const data = JSON.parse(event.data);
                    this.handleRealtimeMessage(data);
                };
                
                ws.onclose = () => {
                    console.log('WebSocket disconnected');
                    this.reconnect();
                };
                
                ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                };
                
                this.state.websocket = ws;
                
            } catch (error) {
                console.error('WebSocket connection failed:', error);
                this.connectSSE();
            }
        },
        
        /**
         * Connect via Server-Sent Events
         */
        connectSSE() {
            if (this.state.eventSource) {
                this.state.eventSource.close();
            }
            
            const url = `${this.config.apiUrl}/notifications/stream`;
            
            try {
                const eventSource = new EventSource(url);
                
                eventSource.onopen = () => {
                    console.log('SSE connected');
                    this.state.reconnectAttempts = 0;
                };
                
                eventSource.onmessage = (event) => {
                    const data = JSON.parse(event.data);
                    this.handleRealtimeMessage(data);
                };
                
                eventSource.addEventListener('notification', (event) => {
                    const notification = JSON.parse(event.data);
                    this.handleNewNotification(notification);
                });
                
                eventSource.addEventListener('heartbeat', (event) => {
                    // Keep connection alive
                });
                
                eventSource.onerror = () => {
                    console.error('SSE connection error');
                    eventSource.close();
                    this.reconnect();
                };
                
                this.state.eventSource = eventSource;
                
            } catch (error) {
                console.error('SSE connection failed:', error);
            }
        },
        
        /**
         * Reconnect to real-time service
         */
        reconnect() {
            if (this.state.reconnectAttempts >= this.config.maxReconnectAttempts) {
                console.error('Max reconnection attempts reached');
                return;
            }
            
            this.state.reconnectAttempts++;
            
            setTimeout(() => {
                console.log(`Reconnecting... (attempt ${this.state.reconnectAttempts})`);
                this.initRealTimeConnection();
            }, this.config.sseReconnectDelay * this.state.reconnectAttempts);
        },
        
        /**
         * Handle real-time message
         */
        handleRealtimeMessage(data) {
            switch (data.event || data.type) {
                case 'notification':
                    this.handleNewNotification(data.data || data);
                    break;
                    
                case 'update':
                    this.updateNotificationCount(data.unreadCount);
                    break;
                    
                case 'connected':
                    console.log('Real-time connection established');
                    break;
                    
                default:
                    console.log('Unknown message type:', data);
            }
        },
        
        /**
         * Handle new notification
         */
        handleNewNotification(notification) {
            // Add to notifications list
            this.state.notifications.unshift(notification);
            
            // Update UI
            this.addNotificationToUI(notification);
            this.updateNotificationCount(this.state.unreadCount + 1);
            
            // Show browser notification if permitted
            if (this.hasNotificationPermission()) {
                this.showBrowserNotification(notification);
            }
            
            // Show in-app notification
            this.showInAppNotification(notification);
            
            // Trigger custom event
            $(document).trigger('moneyQuiz:notification', [notification]);
        },
        
        /**
         * Show browser notification
         */
        showBrowserNotification(notification) {
            const options = {
                body: notification.message,
                icon: notification.icon || moneyQuizNotifications.defaultIcon,
                badge: moneyQuizNotifications.badge,
                tag: `money-quiz-${notification.id}`,
                data: notification.data,
                requireInteraction: notification.priority === 'high',
                actions: this.formatNotificationActions(notification.actions)
            };
            
            // Use service worker to show notification
            if (this.state.pushSubscription && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({
                    type: 'showNotification',
                    title: notification.title,
                    options: options
                });
            } else {
                // Fallback to Notification API
                new Notification(notification.title, options);
            }
        },
        
        /**
         * Show in-app notification
         */
        showInAppNotification(notification) {
            const $notification = $(`
                <div class="money-quiz-notification ${notification.type}" data-id="${notification.id}">
                    <div class="notification-icon">
                        <span class="dashicons dashicons-${this.getIconClass(notification.type)}"></span>
                    </div>
                    <div class="notification-content">
                        <h4>${notification.title}</h4>
                        <p>${notification.message}</p>
                        ${this.renderNotificationActions(notification.actions)}
                    </div>
                    <button class="notification-close" aria-label="Close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
            `);
            
            // Add to container
            let $container = $('#money-quiz-notifications-container');
            if (!$container.length) {
                $container = $('<div id="money-quiz-notifications-container"></div>');
                $('body').append($container);
            }
            
            $container.append($notification);
            
            // Animate in
            setTimeout(() => {
                $notification.addClass('show');
            }, 10);
            
            // Auto-hide after delay (unless high priority)
            if (notification.priority !== 'high') {
                setTimeout(() => {
                    this.hideInAppNotification($notification);
                }, 8000);
            }
        },
        
        /**
         * Hide in-app notification
         */
        hideInAppNotification($notification) {
            $notification.removeClass('show');
            setTimeout(() => {
                $notification.remove();
            }, 300);
        },
        
        /**
         * Bind events
         */
        bindEvents() {
            // Enable notifications button
            $(document).on('click', '.enable-notifications', (e) => {
                e.preventDefault();
                this.requestNotificationPermission();
            });
            
            // Close notification
            $(document).on('click', '.notification-close', (e) => {
                const $notification = $(e.currentTarget).closest('.money-quiz-notification');
                this.hideInAppNotification($notification);
                
                // Mark as read
                const notificationId = $notification.data('id');
                if (notificationId) {
                    this.markAsRead(notificationId);
                }
            });
            
            // Notification actions
            $(document).on('click', '.notification-action', (e) => {
                e.preventDefault();
                const action = $(e.currentTarget).data('action');
                const notificationId = $(e.currentTarget).closest('.money-quiz-notification').data('id');
                
                this.handleNotificationAction(notificationId, action);
            });
            
            // Admin bar notifications
            $(document).on('click', '#wp-admin-bar-money-quiz-notifications', (e) => {
                e.preventDefault();
                this.toggleNotificationDropdown();
            });
            
            // Mark all as read
            $(document).on('click', '.mark-all-read', (e) => {
                e.preventDefault();
                this.markAllAsRead();
            });
            
            // Page visibility change
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.loadNotifications();
                }
            });
        },
        
        /**
         * Request notification permission
         */
        async requestNotificationPermission() {
            try {
                const permission = await Notification.requestPermission();
                
                if (permission === 'granted') {
                    // Subscribe to push notifications
                    await this.subscribeToPush();
                    
                    // Show success message
                    this.showInAppNotification({
                        type: 'success',
                        title: 'Notifications Enabled',
                        message: 'You will now receive real-time updates!',
                        priority: 'normal'
                    });
                } else {
                    // Show info about enabling notifications
                    alert('Please enable notifications in your browser settings to receive updates.');
                }
                
            } catch (error) {
                console.error('Error requesting notification permission:', error);
            }
        },
        
        /**
         * Subscribe to push notifications
         */
        async subscribeToPush() {
            try {
                const registration = await navigator.serviceWorker.ready;
                
                // Subscribe
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.config.vapidPublicKey)
                });
                
                this.state.pushSubscription = subscription;
                this.state.isSubscribed = true;
                
                // Send subscription to server
                const response = await fetch(`${this.config.apiUrl}/notifications/subscribe`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce
                    },
                    body: JSON.stringify({
                        subscription: subscription.toJSON()
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Failed to save subscription');
                }
                
                this.updateUI();
                
            } catch (error) {
                console.error('Failed to subscribe to push:', error);
            }
        },
        
        /**
         * Load notifications
         */
        async loadNotifications() {
            try {
                const response = await fetch(`${this.config.apiUrl}/notifications?status=unread`, {
                    headers: {
                        'X-WP-Nonce': this.config.nonce
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Failed to load notifications');
                }
                
                const data = await response.json();
                
                this.state.notifications = data.notifications;
                this.updateNotificationCount(data.unread_count);
                this.renderNotificationsList();
                
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        },
        
        /**
         * Mark notification as read
         */
        async markAsRead(notificationId) {
            try {
                const response = await fetch(`${this.config.apiUrl}/notifications/${notificationId}/read`, {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': this.config.nonce
                    }
                });
                
                if (response.ok) {
                    // Update local state
                    const notification = this.state.notifications.find(n => n.id === notificationId);
                    if (notification) {
                        notification.read = true;
                    }
                    
                    this.updateNotificationCount(Math.max(0, this.state.unreadCount - 1));
                }
                
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        },
        
        /**
         * Update notification count
         */
        updateNotificationCount(count) {
            this.state.unreadCount = count;
            
            // Update UI elements
            $('.money-quiz-notification-count').text(count || '');
            $('.money-quiz-notification-count').toggle(count > 0);
            
            // Update document title
            if (count > 0) {
                document.title = `(${count}) ${document.title.replace(/^\(\d+\)\s*/, '')}`;
            } else {
                document.title = document.title.replace(/^\(\d+\)\s*/, '');
            }
        },
        
        /**
         * Get icon class for notification type
         */
        getIconClass(type) {
            const icons = {
                success: 'yes-alt',
                info: 'info',
                warning: 'warning',
                error: 'dismiss',
                quiz: 'forms',
                achievement: 'awards'
            };
            
            return icons[type] || 'bell';
        },
        
        /**
         * Render notification actions
         */
        renderNotificationActions(actions) {
            if (!actions || !actions.length) {
                return '';
            }
            
            return `
                <div class="notification-actions">
                    ${actions.map(action => `
                        <button class="notification-action button button-small" 
                                data-action="${action.action}">
                            ${action.title}
                        </button>
                    `).join('')}
                </div>
            `;
        },
        
        /**
         * Handle notification action
         */
        handleNotificationAction(notificationId, action) {
            const notification = this.state.notifications.find(n => n.id === notificationId);
            
            if (!notification) {
                return;
            }
            
            // Find action details
            const actionDetails = notification.actions.find(a => a.action === action);
            
            if (actionDetails && actionDetails.url) {
                window.location.href = actionDetails.url;
            }
            
            // Mark as read
            this.markAsRead(notificationId);
        },
        
        /**
         * Check notification permission
         */
        checkNotificationPermission() {
            if (!('Notification' in window)) {
                return;
            }
            
            if (Notification.permission === 'default') {
                // Show prompt to enable notifications
                this.showNotificationPrompt();
            } else if (Notification.permission === 'granted') {
                // Check if subscribed to push
                if (!this.state.isSubscribed) {
                    this.subscribeToPush();
                }
            }
        },
        
        /**
         * Show notification prompt
         */
        showNotificationPrompt() {
            // Only show on admin pages for now
            if (!$('body').hasClass('wp-admin')) {
                return;
            }
            
            const $prompt = $(`
                <div class="money-quiz-notification-prompt">
                    <div class="prompt-content">
                        <h4>Enable Notifications</h4>
                        <p>Get real-time updates about quiz completions and important events.</p>
                        <div class="prompt-actions">
                            <button class="button button-primary enable-notifications">
                                Enable Notifications
                            </button>
                            <button class="button dismiss-prompt">
                                Not Now
                            </button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append($prompt);
            
            // Dismiss handler
            $prompt.on('click', '.dismiss-prompt', () => {
                $prompt.remove();
                localStorage.setItem('moneyQuizNotificationPromptDismissed', Date.now());
            });
        },
        
        /**
         * Update UI based on subscription state
         */
        updateUI() {
            if (this.state.isSubscribed) {
                $('.enable-notifications').text('Notifications Enabled').prop('disabled', true);
                $('.notification-status').addClass('enabled');
            } else {
                $('.enable-notifications').text('Enable Notifications').prop('disabled', false);
                $('.notification-status').removeClass('enabled');
            }
        },
        
        /**
         * Convert VAPID key
         */
        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');
            
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            
            return outputArray;
        }
    };
    
    // Initialize when ready
    $(document).ready(() => {
        if (typeof moneyQuizNotifications !== 'undefined') {
            MoneyQuizNotifications.init();
        }
    });
    
    // Export for external use
    window.MoneyQuizNotifications = MoneyQuizNotifications;
    
})(jQuery);