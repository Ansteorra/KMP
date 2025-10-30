import { Controller } from "@hotwired/stimulus";

/**
 * MobileOfflineOverlay Stimulus Controller
 * 
 * Manages offline state overlay for mobile pages that require internet connection.
 * Shows a blocking overlay when offline with option to return to the auth card.
 * The auth card page itself doesn't use this controller as it works offline.
 * 
 * Features:
 * - Detects offline state from PWA controller
 * - Shows blocking overlay with message
 * - Provides "Return to Auth Card" button
 * - Automatically hides when back online
 * - Only active on non-auth-card pages
 * 
 * Values:
 * - authCardUrl: String - URL to navigate to auth card
 * 
 * Usage:
 * <div data-controller="mobile-offline-overlay"
 *      data-mobile-offline-overlay-auth-card-url-value="/members/view-mobile-card/token">
 * </div>
 */
class MobileOfflineOverlayController extends Controller {
    static values = {
        authCardUrl: String
    }

    /**
     * Initialize controller
     */
    initialize() {
        this.isOnline = navigator.onLine;
        this.overlay = null;
        this._handleConnectionStatusChanged = this.handleConnectionStatusChanged.bind(this);
    }

    /**
     * Connect controller to DOM
     */
    connect() {
        console.log("MobileOfflineOverlayController connected");
        
        // Listen for connection status changes
        document.addEventListener('connection-status-changed', this._handleConnectionStatusChanged);
        
        // Check initial state
        if (!this.isOnline) {
            this.showOverlay();
        }
    }

    /**
     * Handle connection status changes from PWA controller
     * 
     * @param {CustomEvent} event Connection status event
     */
    handleConnectionStatusChanged(event) {
        const wasOnline = this.isOnline;
        this.isOnline = event.detail.isOnline;
        
        // Update auth card URL if provided
        if (event.detail.authCardUrl) {
            this.authCardUrlValue = event.detail.authCardUrl;
        }
        
        // Show overlay when going offline, hide when coming online
        if (!this.isOnline && wasOnline) {
            this.showOverlay();
        } else if (this.isOnline && !wasOnline) {
            this.hideOverlay();
        }
    }

    /**
     * Show offline overlay
     */
    showOverlay() {
        if (this.overlay) return; // Already showing

        // Create overlay element
        this.overlay = document.createElement('div');
        this.overlay.className = 'mobile-offline-overlay';
        this.overlay.setAttribute('role', 'dialog');
        this.overlay.setAttribute('aria-modal', 'true');
        this.overlay.setAttribute('aria-labelledby', 'offline-title');
        
        // Build content
        const content = `
            <div class="mobile-offline-content">
                <div class="mobile-offline-icon">
                    <i class="bi bi-wifi-off" aria-hidden="true"></i>
                </div>
                <h2 id="offline-title" class="mobile-offline-title">You're Offline</h2>
                <p class="mobile-offline-message">
                    This page requires an internet connection. 
                    You can return to your Auth Card which works offline.
                </p>
                <div class="mobile-offline-buttons">
                    <a href="${this.authCardUrlValue}" 
                       class="btn btn-primary btn-lg">
                        <i class="bi bi-person-vcard me-2"></i>
                        Return to Auth Card
                    </a>
                </div>
            </div>
        `;
        
        this.overlay.innerHTML = content;
        document.body.appendChild(this.overlay);
        
        // Disable page interaction
        document.body.style.overflow = 'hidden';
    }

    /**
     * Hide offline overlay
     */
    hideOverlay() {
        if (!this.overlay) return;

        this.overlay.remove();
        this.overlay = null;
        
        // Re-enable page interaction
        document.body.style.overflow = '';
    }

    /**
     * Disconnect controller from DOM
     */
    disconnect() {
        // Remove event listener
        if (this._handleConnectionStatusChanged) {
            document.removeEventListener('connection-status-changed', this._handleConnectionStatusChanged);
        }
        
        // Remove overlay if present
        this.hideOverlay();
        
        console.log("MobileOfflineOverlayController disconnected");
    }
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["mobile-offline-overlay"] = MobileOfflineOverlayController;

export default MobileOfflineOverlayController;
