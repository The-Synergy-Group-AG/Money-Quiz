/**
 * Money Quiz Service Worker
 * 
 * Handles push notifications and offline functionality
 */

// Service Worker version
const CACHE_VERSION = 'v1.0.0';
const CACHE_NAME = `money-quiz-${CACHE_VERSION}`;

// Assets to cache for offline use
const STATIC_ASSETS = [
    '/wp-content/plugins/money-quiz/assets/css/quiz.css',
    '/wp-content/plugins/money-quiz/assets/js/quiz.js',
    '/wp-content/plugins/money-quiz/assets/images/logo.png',
    '/wp-content/plugins/money-quiz/assets/images/notifications/default-icon.png'
];

// Install event
self.addEventListener('install', (event) => {
    console.log('Service Worker installing...');
    
    // Cache static assets
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate event
self.addEventListener('activate', (event) => {
    console.log('Service Worker activating...');
    
    // Clean up old caches
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(cacheName => cacheName.startsWith('money-quiz-') && cacheName !== CACHE_NAME)
                        .map(cacheName => caches.delete(cacheName))
                );
            })
            .then(() => self.clients.claim())
    );
});

// Fetch event
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Network first, falling back to cache
    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Cache successful responses
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME)
                        .then(cache => cache.put(event.request, responseClone));
                }
                return response;
            })
            .catch(() => {
                // Try cache on network failure
                return caches.match(event.request);
            })
    );
});

// Push event
self.addEventListener('push', (event) => {
    console.log('Push notification received');
    
    let notificationData = {
        title: 'Money Quiz',
        body: 'You have a new notification',
        icon: '/wp-content/plugins/money-quiz/assets/images/notifications/default-icon.png',
        badge: '/wp-content/plugins/money-quiz/assets/images/badge.png',
        tag: 'money-quiz-notification',
        data: {}
    };
    
    // Parse push data
    if (event.data) {
        try {
            const data = event.data.json();
            notificationData = Object.assign(notificationData, data);
        } catch (e) {
            console.error('Error parsing push data:', e);
        }
    }
    
    // Show notification
    event.waitUntil(
        self.registration.showNotification(notificationData.title, {
            body: notificationData.body,
            icon: notificationData.icon,
            badge: notificationData.badge,
            tag: notificationData.tag,
            data: notificationData.data,
            requireInteraction: notificationData.requireInteraction || false,
            actions: notificationData.actions || [],
            vibrate: [200, 100, 200],
            timestamp: Date.now()
        })
    );
    
    // Track notification shown
    trackEvent('notification_shown', {
        type: notificationData.data.type || 'unknown',
        id: notificationData.data.id
    });
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
    console.log('Notification clicked:', event.notification.tag);
    
    event.notification.close();
    
    const data = event.notification.data || {};
    let url = '/wp-admin/admin.php?page=money-quiz';
    
    // Handle action clicks
    if (event.action) {
        const action = (data.actions || []).find(a => a.action === event.action);
        if (action && action.url) {
            url = action.url;
        }
    } else if (data.url) {
        // Default click action
        url = data.url;
    }
    
    // Open or focus the target URL
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                // Try to focus an existing window
                for (const client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                
                // Open new window if no match
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
    
    // Track click
    trackEvent('notification_clicked', {
        type: data.type || 'unknown',
        id: data.id,
        action: event.action || 'default'
    });
});

// Notification close event
self.addEventListener('notificationclose', (event) => {
    console.log('Notification closed:', event.notification.tag);
    
    const data = event.notification.data || {};
    
    // Track dismissal
    trackEvent('notification_dismissed', {
        type: data.type || 'unknown',
        id: data.id
    });
});

// Message event (from client)
self.addEventListener('message', (event) => {
    console.log('Service Worker received message:', event.data);
    
    if (event.data.type === 'showNotification') {
        // Show notification on behalf of client
        self.registration.showNotification(event.data.title, event.data.options);
    } else if (event.data.type === 'skipWaiting') {
        self.skipWaiting();
    }
});

// Background sync event
self.addEventListener('sync', (event) => {
    console.log('Background sync:', event.tag);
    
    if (event.tag === 'money-quiz-sync') {
        event.waitUntil(syncData());
    }
});

// Periodic background sync
self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'money-quiz-check') {
        event.waitUntil(checkForUpdates());
    }
});

/**
 * Sync offline data
 */
async function syncData() {
    try {
        // Get pending data from IndexedDB
        const pendingData = await getPendingData();
        
        if (pendingData.length === 0) {
            return;
        }
        
        // Send to server
        const response = await fetch('/wp-json/money-quiz/v1/sync', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ data: pendingData })
        });
        
        if (response.ok) {
            // Clear synced data
            await clearPendingData();
            
            // Notify clients
            await notifyClients('sync-complete', {
                count: pendingData.length
            });
        }
    } catch (error) {
        console.error('Sync failed:', error);
    }
}

/**
 * Check for updates
 */
async function checkForUpdates() {
    try {
        const response = await fetch('/wp-json/money-quiz/v1/notifications/check');
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.hasUpdates) {
                // Show notification about updates
                await self.registration.showNotification('Money Quiz Updates', {
                    body: data.message || 'New updates available',
                    icon: '/wp-content/plugins/money-quiz/assets/images/notifications/update-icon.png',
                    tag: 'money-quiz-update',
                    data: { type: 'update' }
                });
            }
        }
    } catch (error) {
        console.error('Update check failed:', error);
    }
}

/**
 * Track events
 */
function trackEvent(eventName, data = {}) {
    // Send to analytics in background
    fetch('/wp-json/money-quiz/v1/analytics/track', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            event: eventName,
            data: data,
            timestamp: Date.now()
        })
    }).catch(error => {
        console.error('Analytics tracking failed:', error);
    });
}

/**
 * Notify all clients
 */
async function notifyClients(type, data) {
    const clients = await self.clients.matchAll();
    
    clients.forEach(client => {
        client.postMessage({
            type: type,
            data: data
        });
    });
}

/**
 * Get pending data from IndexedDB
 */
async function getPendingData() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('MoneyQuizDB', 1);
        
        request.onsuccess = (event) => {
            const db = event.target.result;
            const transaction = db.transaction(['pending'], 'readonly');
            const store = transaction.objectStore('pending');
            const getAllRequest = store.getAll();
            
            getAllRequest.onsuccess = () => {
                resolve(getAllRequest.result || []);
            };
            
            getAllRequest.onerror = () => {
                reject(getAllRequest.error);
            };
        };
        
        request.onerror = () => {
            reject(request.error);
        };
    });
}

/**
 * Clear pending data from IndexedDB
 */
async function clearPendingData() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('MoneyQuizDB', 1);
        
        request.onsuccess = (event) => {
            const db = event.target.result;
            const transaction = db.transaction(['pending'], 'readwrite');
            const store = transaction.objectStore('pending');
            const clearRequest = store.clear();
            
            clearRequest.onsuccess = () => {
                resolve();
            };
            
            clearRequest.onerror = () => {
                reject(clearRequest.error);
            };
        };
        
        request.onerror = () => {
            reject(request.error);
        };
    });
}

// Log Service Worker lifecycle
console.log('Money Quiz Service Worker loaded');