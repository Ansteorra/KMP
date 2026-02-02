import MobileControllerBase from "./mobile-controller-base.js";

/**
 * MemberMobileCardPWA Stimulus Controller
 * 
 * Manages Progressive Web App (PWA) functionality for mobile member cards.
 * Extends MobileControllerBase for centralized connection handling.
 * 
 * Features:
 * - Service worker registration with version management
 * - SW update detection and user notification
 * - Online/offline status detection and display
 * - URL caching for offline access
 * - Visual status indicators with Bootstrap styling
 */
class MemberMobileCardPWA extends MobileControllerBase {
    static targets = ["urlCache", "status", "refreshBtn", "updateToast"]
    
    static values = {
        swUrl: String,
        authCardUrl: String,
        isAuthCard: Boolean
    }

    initialize() {
        super.initialize();
        this.sw = null;
        this.swVersion = null;
        this.updateDismissed = false;
        this.refreshIntervalId = null;
    }

    /**
     * Handle URL cache target connection
     */
    urlCacheTargetConnected() {
        this.urlCacheValue = JSON.parse(this.urlCacheTarget.textContent);
    }
    
    /**
     * Handle status target connection - ensures indicator is displayed correctly
     * This fires when the target element is connected to the DOM, which handles
     * Turbo page restoration where targets might connect after onConnect runs
     */
    statusTargetConnected() {
        // Always update display when status target connects
        this.updateStatusDisplay(navigator.onLine);
    }

    /**
     * Called when connection state changes (from base class)
     */
    onConnectionStateChanged(isOnline) {
        this.updateStatusDisplay(isOnline);
        this.notifyServiceWorker(isOnline);
        this.dispatchStatusEvent(isOnline ? 'online' : 'offline');
        
        // Auto-refresh when coming back online
        if (isOnline && this.hasRefreshBtnTarget) {
            this.refreshBtnTarget.click();
        }
    }

    /**
     * Update the status display indicator (simple circle)
     */
    updateStatusDisplay(isOnline) {
        if (!this.hasStatusTarget) return;
        
        const statusEl = this.statusTarget;
        
        if (isOnline) {
            statusEl.title = 'Online';
            statusEl.classList.remove('bg-danger');
            statusEl.classList.add('bg-success');
            if (this.hasRefreshBtnTarget) {
                this.refreshBtnTarget.hidden = false;
            }
        } else {
            statusEl.title = 'Offline';
            statusEl.classList.remove('bg-success');
            statusEl.classList.add('bg-danger');
            if (this.hasRefreshBtnTarget) {
                this.refreshBtnTarget.hidden = true;
            }
        }
    }

    /**
     * Notify service worker of connection state
     */
    notifyServiceWorker(isOnline) {
        if (this.sw && this.sw.active) {
            this.sw.active.postMessage({
                type: isOnline ? 'ONLINE' : 'OFFLINE'
            });
        }
    }

    /**
     * Dispatch connection status event for other controllers
     */
    dispatchStatusEvent(status) {
        const event = new CustomEvent('connection-status-changed', {
            bubbles: true,
            detail: {
                status: status,
                isOnline: status === 'online',
                isAuthCard: this.hasIsAuthCardValue && this.isAuthCardValue,
                authCardUrl: this.hasAuthCardUrlValue ? this.authCardUrlValue : null
            }
        });
        this.element.dispatchEvent(event);
    }

    /**
     * Handle service worker messages (e.g., SW_UPDATED)
     */
    handleSwMessage(event) {
        if (!event.data) return;
        
        switch (event.data.type) {
            case 'SW_UPDATED':
                console.log('[PWA] Service worker updated to version:', event.data.version);
                this.swVersion = event.data.version;
                if (!this.updateDismissed) {
                    this.showUpdateToast();
                }
                break;
        }
    }

    /**
     * Show update available toast
     */
    showUpdateToast() {
        // Create toast if target doesn't exist
        if (!this.hasUpdateToastTarget) {
            this.createUpdateToast();
        }
        
        if (this.hasUpdateToastTarget) {
            this.updateToastTarget.hidden = false;
            
            // Auto-dismiss after 10 seconds
            setTimeout(() => {
                if (this.hasUpdateToastTarget) {
                    this.updateToastTarget.hidden = true;
                    this.updateDismissed = true;
                }
            }, 10000);
        }
    }

    /**
     * Create update toast element
     */
    createUpdateToast() {
        const toast = document.createElement('div');
        toast.className = 'mobile-update-toast alert alert-info alert-dismissible fade show position-fixed bottom-0 start-50 translate-middle-x mb-3';
        toast.style.zIndex = '9999';
        toast.setAttribute('data-member-mobile-card-pwa-target', 'updateToast');
        toast.innerHTML = `
            <i class="bi bi-arrow-repeat me-2"></i>
            Update available - tap to refresh
            <button type="button" class="btn-close" data-action="click->member-mobile-card-pwa#dismissUpdate"></button>
        `;
        toast.addEventListener('click', (e) => {
            if (!e.target.classList.contains('btn-close')) {
                this.applyUpdate();
            }
        });
        this.element.appendChild(toast);
    }

    /**
     * Apply the service worker update
     */
    applyUpdate() {
        window.location.reload();
    }

    /**
     * Dismiss update toast
     */
    dismissUpdate(event) {
        event.stopPropagation();
        if (this.hasUpdateToastTarget) {
            this.updateToastTarget.hidden = true;
        }
        this.updateDismissed = true;
    }

    /**
     * Register service worker and set up message handling
     */
    async registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register(this.swUrlValue);
            this.sw = registration;
            
            // Listen for messages from service worker
            navigator.serviceWorker.addEventListener('message', this.bindHandler('swMessage', this.handleSwMessage));
            
            // Wait for service worker to be active
            await this.waitForActive(registration);
            
            // Send URLs to cache
            if (this.urlCacheValue && registration.active) {
                registration.active.postMessage({
                    type: 'CACHE_URLS',
                    payload: this.urlCacheValue
                });
            }
            
            // Dispatch PWA ready event
            const event = new CustomEvent('pwa-ready', { bubbles: true });
            this.element.dispatchEvent(event);
            
            console.log('[PWA] Service worker registered successfully');
        } catch (error) {
            console.error('[PWA] Service worker registration failed:', error);
            // Still dispatch ready event so app works without SW
            const event = new CustomEvent('pwa-ready', { bubbles: true });
            this.element.dispatchEvent(event);
        }
    }

    /**
     * Wait for service worker to become active
     */
    waitForActive(registration) {
        return new Promise((resolve) => {
            if (registration.active) {
                resolve();
                return;
            }
            
            const worker = registration.installing || registration.waiting;
            if (worker) {
                worker.addEventListener('statechange', function handler(e) {
                    if (e.target.state === 'activated') {
                        worker.removeEventListener('statechange', handler);
                        resolve();
                    }
                });
            } else {
                // Fallback - resolve after short delay
                setTimeout(resolve, 100);
            }
        });
    }

    /**
     * Refresh page if online
     */
    refreshPageIfOnline() {
        if (this.online) {
            window.location.reload();
        }
    }

    /**
     * Connect controller - called after base class connect
     */
    onConnect() {
        // Initialize status display
        this.updateStatusDisplay(this.online);
        this.dispatchStatusEvent(this.online ? 'online' : 'offline');
        
        if ('serviceWorker' in navigator) {
            // Register service worker
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.registerServiceWorker(), { once: true });
            } else {
                this.registerServiceWorker();
            }
        } else {
            console.warn('[PWA] Service Workers not available');
            // Dispatch ready event even without SW
            setTimeout(() => {
                const event = new CustomEvent('pwa-ready', { bubbles: true });
                this.element.dispatchEvent(event);
            }, 100);
        }
        
        // Set up periodic refresh (5 minutes)
        this.refreshIntervalId = setInterval(() => this.refreshPageIfOnline(), 300000);
    }

    /**
     * Disconnect controller - called after base class disconnect
     */
    onDisconnect() {
        if (this.refreshIntervalId) {
            clearInterval(this.refreshIntervalId);
            this.refreshIntervalId = null;
        }
        
        // Remove SW message listener
        const swMessageHandler = this.getHandler('swMessage');
        if (swMessageHandler) {
            navigator.serviceWorker?.removeEventListener('message', swMessageHandler);
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["member-mobile-card-pwa"] = MemberMobileCardPWA;