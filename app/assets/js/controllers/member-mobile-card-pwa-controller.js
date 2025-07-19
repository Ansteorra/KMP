import { Controller } from "@hotwired/stimulus"

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
    static values = {
        swUrl: String
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
        const refreshButton = this.refreshBtnTarget;

        if (navigator.onLine) {
            statusDiv.textContent = 'Online';
            statusDiv.classList.remove('bg-danger');
            statusDiv.classList.add('bg-success');
            refreshButton.hidden = false;
            if (this.sw) {
                this.sw.active.postMessage({
                    type: 'ONLINE'
                });
            }
            refreshButton.click();
        } else {
            statusDiv.textContent = 'Offline';
            statusDiv.classList.remove('bg-success');
            statusDiv.classList.add('bg-danger');
            refreshButton.hidden = true;
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
        window.addEventListener('online', this.updateOnlineStatus.bind(this));
        window.addEventListener('offline', this.updateOnlineStatus.bind(this));
        navigator.serviceWorker.register(this.swUrlValue)
            .then(registration => {
                this.sw = registration;
                new Promise(r => setTimeout(r, 100)).then(() => {
                    console.log('Service Worker registered with scope:', registration.scope);
                    console.log('Service Worker active:', registration.active);
                    registration.active.postMessage({
                        type: 'CACHE_URLS',
                        payload: this.urlCacheValue
                    });
                    this.element.attributes['data-member-mobile-card-profile-pwa-ready-value'].value = true;
                });
            }, error => {
                console.log('Service Worker registration failed:', error);
            });
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
     */
    connect() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', this.manageOnlineStatus.bind(this));
        }
        setInterval(this.refreshPageIfOnline, 300000);
    }

    /**
     * Disconnect controller from DOM
     * Cleans up event listeners to prevent memory leaks
     */
    disconnect() {
        window.addEventListener('load', this.manageOnlineStatus.bind(this));
        window.removeEventListener('online', this.updateOnlineStatus.bind(this));
        window.removeEventListener('offline', this.updateOnlineStatus.bind(this));
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-mobile-card-pwa"] = MemberMobileCardPWA;