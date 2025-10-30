import { Controller } from "@hotwired/stimulus";

/**
 * MemberMobileCardPWA Stimulus Controller
 * 
 * Manages Progressive Web App (PWA) functionality for mobile member cards with
 * service worker management, offline capability, and connection monitoring.
 * Provides seamless online/offline experience with automatic cache management.
 * 
 * Features:
 * - Service worker registration and management
 * - Online/offline status detection and display
 * - URL caching for offline access
 * - Automatic page refresh when online
 * - Visual status indicators with Bootstrap styling
 * - PWA readiness state management
 * - Connection monitoring with periodic refresh
 * 
 * Values:
 * - swUrl: String - Service worker script URL for registration
 * 
 * Targets:
 * - urlCache: Hidden element containing URLs to cache (JSON format)
 * - status: Status display element for online/offline indication
 * - refreshBtn: Button for manual page refresh
 * 
 * Usage:
 * <div data-controller="member-mobile-card-pwa" 
 *      data-member-mobile-card-pwa-sw-url-value="/sw.js">
 *   <script data-member-mobile-card-pwa-target="urlCache" type="application/json">
 *     ["/api/member/123", "/css/app.css"]
 *   </script>
 *   <div data-member-mobile-card-pwa-target="status" class="badge">Status</div>
 *   <button data-member-mobile-card-pwa-target="refreshBtn">Refresh</button>
 * </div>
 */
class MemberMobileCardPWA extends Controller {
    static targets = ["urlCache", "status", "refreshBtn"]
    
    // Make refreshBtn optional
    static optionalTargets = ["refreshBtn"]
    
    static values = {
        swUrl: String
    }

    initialize() {
        this.boundUpdateOnlineStatus = this.updateOnlineStatus.bind(this);
        this.boundManageOnlineStatus = this.manageOnlineStatus.bind(this);
    }

    /**
     * Handle URL cache target connection
     * Parses JSON cache configuration from DOM element
     */
    urlCacheTargetConnected() {
        this.urlCacheValue = JSON.parse(this.urlCacheTarget.textContent);
    }

    /**
     * Update online/offline status display and service worker communication
     * Changes visual indicators and manages service worker state
     */
    updateOnlineStatus() {
        const statusDiv = this.statusTarget;
        const refreshButton = this.hasRefreshBtnTarget ? this.refreshBtnTarget : null;

        if (navigator.onLine) {
            statusDiv.textContent = 'Online';
            statusDiv.classList.remove('bg-danger');
            statusDiv.classList.add('bg-success');
            if (refreshButton) {
                refreshButton.hidden = false;
            }
            if (this.sw) {
                this.sw.active.postMessage({
                    type: 'ONLINE'
                });
            }
            if (refreshButton) {
                refreshButton.click();
            }
        } else {
            statusDiv.textContent = 'Offline';
            statusDiv.classList.remove('bg-success');
            statusDiv.classList.add('bg-danger');
            if (refreshButton) {
                refreshButton.hidden = true;
            }
            if (this.sw) {
                this.sw.active.postMessage({
                    type: 'OFFLINE'
                });
            }
        }
    }

    /**
     * Initialize online status monitoring and service worker registration
     * Sets up event listeners and registers service worker with cache URLs
     */
    manageOnlineStatus() {
        this.updateOnlineStatus();
        window.addEventListener('online', this.boundUpdateOnlineStatus);
        window.addEventListener('offline', this.boundUpdateOnlineStatus);
        
        // Register service worker with a slight delay to ensure all controllers are connected
        setTimeout(() => {
            navigator.serviceWorker.register(this.swUrlValue)
                .then(registration => {
                    this.sw = registration;
                    
                    // Wait for service worker to be active
                    const waitForActive = () => {
                        if (registration.active) {
                            registration.active.postMessage({
                                type: 'CACHE_URLS',
                                payload: this.urlCacheValue
                            });
                            
                            // Dispatch custom event to notify profile controller PWA is ready
                            const event = new CustomEvent('pwa-ready', { bubbles: true });
                            this.element.dispatchEvent(event);
                        } else if (registration.installing) {
                            registration.installing.addEventListener('statechange', (e) => {
                                if (e.target.state === 'activated') {
                                    waitForActive();
                                }
                            });
                        } else if (registration.waiting) {
                            waitForActive();
                        } else {
                            setTimeout(waitForActive, 100);
                        }
                    };
                    
                    waitForActive();
                }, error => {
                    console.error('Service Worker registration failed:', error);
                });
        }, 100);
    }

    /**
     * Refresh page if online connection is available
     * Used for periodic updates to ensure fresh content
     */
    refreshPageIfOnline() {
        if (navigator.onLine) {
            window.location.reload();
        }
    }

    /**
     * Connect controller to DOM
     * Initializes service worker support and sets up periodic refresh
     * Falls back to basic online detection if Service Workers not available
     */
    connect() {
        if ('serviceWorker' in navigator) {
            // Start PWA initialization immediately or on DOMContentLoaded/load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', this.boundManageOnlineStatus, { once: true });
            } else {
                // readyState is 'interactive' or 'complete', safe to initialize
                this.manageOnlineStatus();
            }
        } else {
            // Service Workers not available (likely HTTP on IP address)
            // Fall back to basic online/offline detection without PWA features
            console.warn('Service Workers not available - PWA features disabled. Access via localhost or HTTPS for full functionality.');
            this.updateOnlineStatus();
            window.addEventListener('online', this.boundUpdateOnlineStatus);
            window.addEventListener('offline', this.boundUpdateOnlineStatus);
            
            // Dispatch PWA ready event even without Service Workers
            // This allows the profile controller to load data
            setTimeout(() => {
                const event = new CustomEvent('pwa-ready', { bubbles: true });
                this.element.dispatchEvent(event);
            }, 100);
        }
    this.refreshIntervalId = setInterval(() => this.refreshPageIfOnline(), 300000);
    }

    /**
     * Disconnect controller from DOM
     * Cleans up event listeners and intervals to prevent memory leaks
     */
    disconnect() {
        // Clear refresh interval
        if (this.refreshIntervalId) {
            clearInterval(this.refreshIntervalId);
            this.refreshIntervalId = null;
        }
        
        // Remove online/offline listeners
        window.removeEventListener('online', this.boundUpdateOnlineStatus);
        window.removeEventListener('offline', this.boundUpdateOnlineStatus);
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-mobile-card-pwa"] = MemberMobileCardPWA;