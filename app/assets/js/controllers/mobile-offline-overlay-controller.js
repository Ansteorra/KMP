import MobileControllerBase from "./mobile-controller-base.js";

/**
 * MobileOfflineOverlay Stimulus Controller
 * 
 * Manages offline state overlay for mobile pages that require internet connection.
 * Extends MobileControllerBase for centralized connection handling.
 * 
 * Features:
 * - Shows blocking overlay when offline
 * - Provides "Return to Auth Card" button
 * - Automatically hides when back online
 */
class MobileOfflineOverlayController extends MobileControllerBase {
    static values = {
        authCardUrl: String
    }

    initialize() {
        super.initialize();
        this.overlay = null;
    }

    /**
     * Called after base class connect
     */
    onConnect() {
        console.log("MobileOfflineOverlayController connected");
        
        // Listen for connection status events for auth card URL
        this._handleConnectionStatus = this.bindHandler('connectionStatus', this.handleConnectionStatusEvent);
        document.addEventListener('connection-status-changed', this._handleConnectionStatus);
        
        // Check initial state
        if (!this.online) {
            this.showOverlay();
        }
    }

    /**
     * Called when connection state changes (from base class)
     */
    onConnectionStateChanged(isOnline) {
        if (!isOnline) {
            this.showOverlay();
        } else {
            this.hideOverlay();
        }
    }

    /**
     * Handle connection status event from PWA controller
     */
    handleConnectionStatusEvent(event) {
        if (event.detail.authCardUrl) {
            this.authCardUrlValue = event.detail.authCardUrl;
        }
    }

    /**
     * Called after base class disconnect
     */
    onDisconnect() {
        document.removeEventListener('connection-status-changed', this._handleConnectionStatus);
        this.hideOverlay();
        console.log("MobileOfflineOverlayController disconnected");
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
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["mobile-offline-overlay"] = MobileOfflineOverlayController;

export default MobileOfflineOverlayController;
