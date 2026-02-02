/**
 * RsvpCacheService - IndexedDB-backed service for caching user RSVPs
 * 
 * Provides local storage for user's RSVPs to enable offline viewing
 * and offline RSVP creation. Syncs with server when online.
 * 
 * Features:
 * - Cache user's RSVPs for offline access
 * - Store pending RSVPs when offline
 * - Sync pending RSVPs when coming online
 * - Update cache when user changes RSVPs
 */

const DB_NAME = 'kmp-rsvp-cache';
const DB_VERSION = 1;
const RSVPS_STORE = 'user-rsvps';
const PENDING_STORE = 'pending-rsvps';

class RsvpCacheService {
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
                console.error('[RsvpCache] Failed to open database:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                this.db = request.result;
                this.isInitialized = true;
                
                // Set up online listener for auto-sync
                window.addEventListener('online', this._handleOnline);
                
                console.log('[RsvpCache] Service initialized');
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create store for cached RSVPs
                if (!db.objectStoreNames.contains(RSVPS_STORE)) {
                    const rsvpStore = db.createObjectStore(RSVPS_STORE, {
                        keyPath: 'gathering_id'
                    });
                    rsvpStore.createIndex('updatedAt', 'updatedAt', { unique: false });
                }
                
                // Create store for pending (offline) RSVPs
                if (!db.objectStoreNames.contains(PENDING_STORE)) {
                    const pendingStore = db.createObjectStore(PENDING_STORE, {
                        keyPath: 'id',
                        autoIncrement: true
                    });
                    pendingStore.createIndex('gathering_id', 'gathering_id', { unique: false });
                    pendingStore.createIndex('createdAt', 'createdAt', { unique: false });
                    pendingStore.createIndex('status', 'status', { unique: false });
                }
            };
        });
    }

    /**
     * Handle coming online - trigger sync
     */
    async _handleOnline() {
        console.log('[RsvpCache] Network online - syncing pending RSVPs');
        try {
            await this.syncPendingRsvps();
        } catch (error) {
            console.error('[RsvpCache] Auto-sync failed:', error);
        }
    }

    /**
     * Ensure the database is initialized
     */
    async ensureInitialized() {
        if (!this.isInitialized) {
            await this.init();
        }
    }

    // ==================== RSVP Cache Methods ====================

    /**
     * Cache user's RSVPs from API response
     * @param {Array} events - Events array from mobileCalendarData API
     */
    async cacheUserRsvps(events) {
        await this.ensureInitialized();
        
        const rsvps = events.filter(e => e.user_attending).map(e => ({
            gathering_id: e.id,
            public_id: e.public_id,
            name: e.name,
            start_date: e.start_date,
            start_time: e.start_time,
            end_date: e.end_date,
            location: e.location,
            branch: e.branch,
            attendance_id: e.attendance_id,
            share_with_kingdom: e.share_with_kingdom,
            share_with_hosting_group: e.share_with_hosting_group,
            share_with_crown: e.share_with_crown,
            public_note: e.public_note,
            updatedAt: new Date().toISOString()
        }));
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([RSVPS_STORE], 'readwrite');
            const store = transaction.objectStore(RSVPS_STORE);
            
            let completed = 0;
            let errors = 0;
            
            if (rsvps.length === 0) {
                resolve({ saved: 0, errors: 0 });
                return;
            }
            
            rsvps.forEach(rsvp => {
                const request = store.put(rsvp);
                request.onsuccess = () => {
                    completed++;
                    if (completed + errors === rsvps.length) {
                        console.log(`[RsvpCache] Cached ${completed} RSVPs`);
                        resolve({ saved: completed, errors });
                    }
                };
                request.onerror = () => {
                    errors++;
                    if (completed + errors === rsvps.length) {
                        resolve({ saved: completed, errors });
                    }
                };
            });
        });
    }

    /**
     * Get cached RSVP for a specific gathering
     * @param {number} gatheringId - Gathering ID
     * @returns {Promise<Object|null>} Cached RSVP or null
     */
    async getCachedRsvp(gatheringId) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([RSVPS_STORE], 'readonly');
            const store = transaction.objectStore(RSVPS_STORE);
            const request = store.get(gatheringId);
            
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all cached RSVPs
     * @returns {Promise<Array>} Array of cached RSVPs
     */
    async getAllCachedRsvps() {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([RSVPS_STORE], 'readonly');
            const store = transaction.objectStore(RSVPS_STORE);
            const request = store.getAll();
            
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Check if user has RSVP'd to a gathering (from cache)
     * @param {number} gatheringId - Gathering ID
     * @returns {Promise<boolean>}
     */
    async hasRsvp(gatheringId) {
        const rsvp = await this.getCachedRsvp(gatheringId);
        return rsvp !== null;
    }

    /**
     * Update cached RSVP after successful online save
     * @param {number} gatheringId - Gathering ID
     * @param {Object} data - Updated RSVP data
     */
    async updateCachedRsvp(gatheringId, data) {
        await this.ensureInitialized();
        
        const existing = await this.getCachedRsvp(gatheringId);
        const rsvp = {
            ...existing,
            ...data,
            gathering_id: gatheringId,
            updatedAt: new Date().toISOString()
        };
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([RSVPS_STORE], 'readwrite');
            const store = transaction.objectStore(RSVPS_STORE);
            const request = store.put(rsvp);
            
            request.onsuccess = () => {
                console.log(`[RsvpCache] Updated RSVP for gathering ${gatheringId}`);
                resolve();
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Remove cached RSVP (after user cancels)
     * @param {number} gatheringId - Gathering ID
     */
    async removeCachedRsvp(gatheringId) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([RSVPS_STORE], 'readwrite');
            const store = transaction.objectStore(RSVPS_STORE);
            const request = store.delete(gatheringId);
            
            request.onsuccess = () => {
                console.log(`[RsvpCache] Removed RSVP for gathering ${gatheringId}`);
                resolve();
            };
            request.onerror = () => reject(request.error);
        });
    }

    // ==================== Pending RSVP Methods ====================

    /**
     * Queue an RSVP for later sync (when offline)
     * @param {Object} rsvpData - RSVP data
     * @returns {Promise<number>} ID of queued RSVP
     */
    async queueOfflineRsvp(rsvpData) {
        await this.ensureInitialized();
        
        const pending = {
            gathering_id: rsvpData.gathering_id,
            gathering_name: rsvpData.gathering_name,
            share_with_kingdom: rsvpData.share_with_kingdom || false,
            share_with_hosting_group: rsvpData.share_with_hosting_group || false,
            share_with_crown: rsvpData.share_with_crown || false,
            public_note: rsvpData.public_note || '',
            status: 'pending',
            createdAt: new Date().toISOString(),
            attempts: 0
        };
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([PENDING_STORE], 'readwrite');
            const store = transaction.objectStore(PENDING_STORE);
            const request = store.add(pending);
            
            request.onsuccess = () => {
                console.log(`[RsvpCache] Queued offline RSVP: ${pending.gathering_name}`);
                this.dispatchEvent('rsvp-queued', { id: request.result, data: pending });
                resolve(request.result);
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all pending RSVPs
     * @returns {Promise<Array>} Array of pending RSVPs
     */
    async getPendingRsvps() {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([PENDING_STORE], 'readonly');
            const store = transaction.objectStore(PENDING_STORE);
            const index = store.index('status');
            const request = index.getAll('pending');
            
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get count of pending RSVPs
     * @returns {Promise<number>}
     */
    async getPendingCount() {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([PENDING_STORE], 'readonly');
            const store = transaction.objectStore(PENDING_STORE);
            const index = store.index('status');
            const request = index.count('pending');
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Remove a pending RSVP
     * @param {number} id - Pending RSVP ID
     */
    async removePendingRsvp(id) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([PENDING_STORE], 'readwrite');
            const store = transaction.objectStore(PENDING_STORE);
            const request = store.delete(id);
            
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Sync all pending RSVPs to the server
     * @returns {Promise<Object>} Sync result
     */
    async syncPendingRsvps() {
        if (this.syncInProgress) {
            return { success: 0, failed: 0, skipped: true };
        }
        
        if (!navigator.onLine) {
            return { success: 0, failed: 0, offline: true };
        }
        
        this.syncInProgress = true;
        this.dispatchEvent('sync-started');
        
        try {
            const csrfToken = this.getCsrfToken();
            const pending = await this.getPendingRsvps();
            let success = 0;
            let failed = 0;
            
            for (const rsvp of pending) {
                try {
                    const response = await fetch('/gathering-attendances/mobile-rsvp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            gathering_id: rsvp.gathering_id,
                            share_with_kingdom: rsvp.share_with_kingdom,
                            share_with_hosting_group: rsvp.share_with_hosting_group,
                            share_with_crown: rsvp.share_with_crown,
                            public_note: rsvp.public_note
                        })
                    });
                    
                    if (response.ok) {
                        await this.removePendingRsvp(rsvp.id);
                        // Update the RSVP cache
                        await this.updateCachedRsvp(rsvp.gathering_id, rsvp);
                        success++;
                    } else {
                        await this.updatePendingError(rsvp.id, `HTTP ${response.status}`);
                        failed++;
                    }
                } catch (error) {
                    await this.updatePendingError(rsvp.id, error.message);
                    failed++;
                }
            }
            
            this.dispatchEvent('sync-complete', { success, failed, total: pending.length });
            return { success, failed };
            
        } finally {
            this.syncInProgress = false;
        }
    }

    /**
     * Update pending RSVP with error
     * @param {number} id - Pending RSVP ID
     * @param {string} error - Error message
     */
    async updatePendingError(id, error) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([PENDING_STORE], 'readwrite');
            const store = transaction.objectStore(PENDING_STORE);
            const getRequest = store.get(id);
            
            getRequest.onsuccess = () => {
                const rsvp = getRequest.result;
                if (rsvp) {
                    rsvp.attempts = (rsvp.attempts || 0) + 1;
                    rsvp.lastError = error;
                    rsvp.lastAttempt = new Date().toISOString();
                    
                    if (rsvp.attempts >= 3) {
                        rsvp.status = 'failed';
                    }
                    
                    const updateRequest = store.put(rsvp);
                    updateRequest.onsuccess = () => resolve();
                    updateRequest.onerror = () => reject(updateRequest.error);
                } else {
                    resolve();
                }
            };
            getRequest.onerror = () => reject(getRequest.error);
        });
    }

    // ==================== Utilities ====================

    /**
     * Get CSRF token from meta tag
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]') 
            || document.querySelector('meta[name="csrfToken"]');
        return meta?.getAttribute('content') || '';
    }

    /**
     * Dispatch a custom event
     */
    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`rsvp-cache:${eventName}`, {
            bubbles: true,
            detail
        });
        window.dispatchEvent(event);
    }

    /**
     * Clear all cached data
     */
    async clearAll() {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([RSVPS_STORE, PENDING_STORE], 'readwrite');
            
            transaction.objectStore(RSVPS_STORE).clear();
            transaction.objectStore(PENDING_STORE).clear();
            
            transaction.oncomplete = () => {
                console.log('[RsvpCache] Cleared all cached data');
                resolve();
            };
            transaction.onerror = () => reject(transaction.error);
        });
    }

    /**
     * Destroy the service
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
const rsvpCacheService = new RsvpCacheService();

export default rsvpCacheService;

// Expose globally for non-module scripts
window.RsvpCacheService = rsvpCacheService;
