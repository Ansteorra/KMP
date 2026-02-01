/**
 * OfflineQueueService - IndexedDB-backed service for queuing offline actions
 * 
 * Provides persistent storage for actions that need to be synced when online.
 * Uses IndexedDB for reliable cross-session persistence.
 * 
 * Features:
 * - Queue actions for later sync
 * - Persist across browser sessions
 * - Automatic sync when coming online
 * - Progress callbacks during sync
 * - Error tracking for failed actions
 * - CSRF token refresh before sync
 */

const DB_NAME = 'kmp-offline-queue';
const DB_VERSION = 1;
const STORE_NAME = 'pending-actions';

class OfflineQueueService {
    constructor() {
        this.db = null;
        this.isInitialized = false;
        this.syncInProgress = false;
        
        // Bind methods
        this._handleOnline = this._handleOnline.bind(this);
    }

    /**
     * Initialize the IndexedDB database
     * @returns {Promise<void>}
     */
    async init() {
        if (this.isInitialized) return;

        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => {
                console.error('Failed to open offline queue database:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                this.db = request.result;
                this.isInitialized = true;
                
                // Set up online listener for auto-sync
                window.addEventListener('online', this._handleOnline);
                
                console.log('Offline queue service initialized');
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create the pending-actions store
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, {
                        keyPath: 'id',
                        autoIncrement: true
                    });
                    
                    // Create indexes for querying
                    store.createIndex('createdAt', 'createdAt', { unique: false });
                    store.createIndex('type', 'type', { unique: false });
                    store.createIndex('status', 'status', { unique: false });
                }
            };
        });
    }

    /**
     * Handle coming online - trigger sync
     */
    async _handleOnline() {
        console.log('Network online - syncing pending actions');
        try {
            await this.syncPendingActions();
        } catch (error) {
            console.error('Auto-sync failed:', error);
        }
    }

    /**
     * Queue an action for later sync
     * 
     * @param {string} type - Action type identifier (e.g., 'rsvp', 'attendance')
     * @param {string} url - API endpoint URL
     * @param {string} method - HTTP method (POST, PUT, DELETE, PATCH)
     * @param {Object} data - Request body data
     * @param {Object} meta - Additional metadata (e.g., display info)
     * @returns {Promise<number>} ID of queued action
     */
    async queueAction(type, url, method, data, meta = {}) {
        await this.ensureInitialized();

        const action = {
            type,
            url,
            method,
            data,
            meta,
            status: 'pending',
            createdAt: new Date().toISOString(),
            attempts: 0,
            lastError: null
        };

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.add(action);

            request.onsuccess = () => {
                console.log('Action queued:', request.result, type);
                this.dispatchQueueEvent('action-queued', { id: request.result, action });
                resolve(request.result);
            };

            request.onerror = () => {
                console.error('Failed to queue action:', request.error);
                reject(request.error);
            };
        });
    }

    /**
     * Get all pending actions
     * @returns {Promise<Array>} Array of pending actions
     */
    async getPendingActions() {
        await this.ensureInitialized();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const index = store.index('status');
            const request = index.getAll('pending');

            request.onsuccess = () => {
                resolve(request.result || []);
            };

            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Get all actions (including failed)
     * @returns {Promise<Array>} Array of all actions
     */
    async getAllActions() {
        await this.ensureInitialized();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.getAll();

            request.onsuccess = () => {
                resolve(request.result || []);
            };

            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Remove an action from the queue
     * @param {number} id - Action ID
     * @returns {Promise<void>}
     */
    async removeAction(id) {
        await this.ensureInitialized();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.delete(id);

            request.onsuccess = () => {
                this.dispatchQueueEvent('action-removed', { id });
                resolve();
            };

            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Update an action with error information
     * @param {number} id - Action ID
     * @param {string} error - Error message
     * @returns {Promise<void>}
     */
    async updateActionError(id, error) {
        await this.ensureInitialized();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const getRequest = store.get(id);

            getRequest.onsuccess = () => {
                const action = getRequest.result;
                if (action) {
                    action.attempts += 1;
                    action.lastError = error;
                    action.lastAttempt = new Date().toISOString();
                    
                    // Mark as failed after 3 attempts
                    if (action.attempts >= 3) {
                        action.status = 'failed';
                    }

                    const updateRequest = store.put(action);
                    updateRequest.onsuccess = () => {
                        this.dispatchQueueEvent('action-error', { id, error, action });
                        resolve();
                    };
                    updateRequest.onerror = () => reject(updateRequest.error);
                } else {
                    resolve(); // Action not found, nothing to update
                }
            };

            getRequest.onerror = () => reject(getRequest.error);
        });
    }

    /**
     * Get count of pending actions
     * @returns {Promise<number>} Count of pending actions
     */
    async getPendingCount() {
        await this.ensureInitialized();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const index = store.index('status');
            const request = index.count('pending');

            request.onsuccess = () => {
                resolve(request.result);
            };

            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Sync all pending actions to the server
     * @param {Function} onProgress - Progress callback (current, total, action)
     * @returns {Promise<Object>} Sync result { success: number, failed: number }
     */
    async syncPendingActions(onProgress = null) {
        if (this.syncInProgress) {
            console.log('Sync already in progress');
            return { success: 0, failed: 0, skipped: true };
        }

        if (!navigator.onLine) {
            console.log('Cannot sync - offline');
            return { success: 0, failed: 0, offline: true };
        }

        this.syncInProgress = true;
        this.dispatchQueueEvent('sync-started');

        try {
            // Get fresh CSRF token
            const csrfToken = await this.refreshCsrfToken();
            
            const pending = await this.getPendingActions();
            let success = 0;
            let failed = 0;

            for (let i = 0; i < pending.length; i++) {
                const action = pending[i];
                
                if (onProgress) {
                    onProgress(i + 1, pending.length, action);
                }

                try {
                    const response = await fetch(action.url, {
                        method: action.method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify(action.data)
                    });

                    if (response.ok) {
                        await this.removeAction(action.id);
                        success++;
                    } else {
                        const errorText = await response.text();
                        await this.updateActionError(action.id, `HTTP ${response.status}: ${errorText}`);
                        failed++;
                    }
                } catch (error) {
                    await this.updateActionError(action.id, error.message);
                    failed++;
                }
            }

            this.dispatchQueueEvent('sync-complete', { success, failed, total: pending.length });
            return { success, failed };

        } finally {
            this.syncInProgress = false;
        }
    }

    /**
     * Get fresh CSRF token from the server
     * @returns {Promise<string>} CSRF token
     */
    async refreshCsrfToken() {
        // Try to get token from meta tag first
        const metaToken = document.querySelector('meta[name="csrfToken"]');
        if (metaToken) {
            return metaToken.getAttribute('content');
        }

        // Otherwise fetch from a known endpoint that returns the token
        try {
            const response = await fetch('/api/csrf-token', {
                headers: { 'Accept': 'application/json' }
            });
            if (response.ok) {
                const data = await response.json();
                return data.token;
            }
        } catch (error) {
            console.warn('Could not refresh CSRF token:', error);
        }

        return '';
    }

    /**
     * Clear all actions from the queue
     * @returns {Promise<void>}
     */
    async clearAll() {
        await this.ensureInitialized();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.clear();

            request.onsuccess = () => {
                this.dispatchQueueEvent('queue-cleared');
                resolve();
            };

            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Ensure the database is initialized
     */
    async ensureInitialized() {
        if (!this.isInitialized) {
            await this.init();
        }
    }

    /**
     * Dispatch a custom event for queue state changes
     * @param {string} eventName - Event name
     * @param {Object} detail - Event detail
     */
    dispatchQueueEvent(eventName, detail = {}) {
        const event = new CustomEvent(`offline-queue:${eventName}`, {
            bubbles: true,
            detail
        });
        window.dispatchEvent(event);
    }

    /**
     * Destroy the service and clean up
     */
    destroy() {
        window.removeEventListener('online', this._handleOnline);
        if (this.db) {
            this.db.close();
            this.db = null;
        }
        this.isInitialized = false;
    }
}

// Create singleton instance
const offlineQueueService = new OfflineQueueService();

// Export for use in other modules
export default offlineQueueService;

// Also expose globally for non-module scripts
window.OfflineQueueService = offlineQueueService;
