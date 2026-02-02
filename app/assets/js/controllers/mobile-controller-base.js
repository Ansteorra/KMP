import { Controller } from "@hotwired/stimulus";

/**
 * MobileControllerBase - Shared base class for all mobile Stimulus controllers
 * 
 * Provides centralized online/offline state management, connection event handling,
 * and utility methods for network requests with retry logic.
 * 
 * Features:
 * - Single source of truth for online/offline state (static isOnline property)
 * - Automatic connection listener registration/cleanup
 * - fetchWithRetry() for network requests with exponential backoff
 * - Connection state change notifications via onConnectionStateChanged()
 * - Proper event handler cleanup on disconnect
 * 
 * Usage:
 * class MyMobileController extends MobileControllerBase {
 *     onConnectionStateChanged(isOnline) {
 *         // React to connection changes
 *     }
 * }
 */
class MobileControllerBase extends Controller {
    // Static properties - shared across all instances
    static isOnline = navigator.onLine;
    static connectionListeners = new Set();
    static initialized = false;

    /**
     * Initialize static connection listeners once
     * Sets up window online/offline event handlers
     */
    static initializeConnectionListeners() {
        if (MobileControllerBase.initialized) return;
        
        window.addEventListener('online', () => {
            MobileControllerBase.isOnline = true;
            MobileControllerBase.notifyListeners(true);
        });
        
        window.addEventListener('offline', () => {
            MobileControllerBase.isOnline = false;
            MobileControllerBase.notifyListeners(false);
        });
        
        MobileControllerBase.initialized = true;
    }
    
    /**
     * Sync static isOnline with current navigator.onLine state
     * Called on each controller connect to handle page loads while offline
     */
    static syncOnlineState() {
        const currentState = navigator.onLine;
        if (MobileControllerBase.isOnline !== currentState) {
            MobileControllerBase.isOnline = currentState;
            // Don't notify - let onConnect handle the initial display
        }
    }

    /**
     * Notify all registered controllers of connection state change
     * @param {boolean} isOnline - Current connection state
     */
    static notifyListeners(isOnline) {
        MobileControllerBase.connectionListeners.forEach(controller => {
            if (typeof controller.onConnectionStateChanged === 'function') {
                try {
                    controller.onConnectionStateChanged(isOnline);
                } catch (error) {
                    console.error('Error in onConnectionStateChanged:', error);
                }
            }
        });
    }

    /**
     * Initialize instance
     * Sets up bound handler tracking map
     */
    initialize() {
        // Ensure static listeners are set up
        MobileControllerBase.initializeConnectionListeners();
        
        // Sync online state with current navigator.onLine (handles page loads while offline)
        MobileControllerBase.syncOnlineState();
        
        // Map for tracking bound event handlers for cleanup
        this._boundHandlers = new Map();
    }

    /**
     * Connect controller to DOM
     * Registers controller for connection state notifications
     */
    connect() {
        // Sync online state again on connect (handles Turbo navigation while offline)
        MobileControllerBase.syncOnlineState();
        
        // Register this controller for connection notifications
        MobileControllerBase.connectionListeners.add(this);
        
        // Call subclass connect if defined
        if (typeof this.onConnect === 'function') {
            this.onConnect();
        }
    }

    /**
     * Disconnect controller from DOM
     * Unregisters controller and cleans up event handlers
     */
    disconnect() {
        // Unregister from connection notifications
        MobileControllerBase.connectionListeners.delete(this);
        
        // Clear bound handlers map
        this._boundHandlers.clear();
        
        // Call subclass disconnect if defined
        if (typeof this.onDisconnect === 'function') {
            this.onDisconnect();
        }
    }

    /**
     * Override in subclass to handle connection state changes
     * @param {boolean} isOnline - Current connection state
     */
    onConnectionStateChanged(isOnline) {
        // Override in subclass
    }

    /**
     * Get current online status
     * @returns {boolean} Current connection state
     */
    get online() {
        return MobileControllerBase.isOnline;
    }

    /**
     * Bind and track an event handler for later cleanup
     * @param {string} name - Identifier for the handler
     * @param {Function} handler - Function to bind
     * @returns {Function} Bound handler
     */
    bindHandler(name, handler) {
        const bound = handler.bind(this);
        this._boundHandlers.set(name, bound);
        return bound;
    }

    /**
     * Get a previously bound handler
     * @param {string} name - Handler identifier
     * @returns {Function|undefined} Bound handler or undefined
     */
    getHandler(name) {
        return this._boundHandlers.get(name);
    }

    /**
     * Fetch with retry logic and exponential backoff
     * 
     * @param {string} url - URL to fetch
     * @param {Object} options - Fetch options
     * @param {number} retries - Number of retry attempts (default: 3)
     * @param {number} timeout - Request timeout in ms (default: 10000)
     * @returns {Promise<Response>} Fetch response
     */
    async fetchWithRetry(url, options = {}, retries = 3, timeout = 10000) {
        let lastError;
        
        for (let attempt = 0; attempt <= retries; attempt++) {
            try {
                // Create abort controller for timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), timeout);
                
                const response = await fetch(url, {
                    ...options,
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        ...options.headers
                    }
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return response;
            } catch (error) {
                lastError = error;
                
                // Don't retry on abort or if offline
                if (error.name === 'AbortError' || !MobileControllerBase.isOnline) {
                    throw error;
                }
                
                // Exponential backoff: 1s, 2s, 4s...
                if (attempt < retries) {
                    const delay = Math.pow(2, attempt) * 1000;
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            }
        }
        
        throw lastError;
    }

    /**
     * Dispatch a custom event with connection status
     * @param {string} eventName - Event name to dispatch
     * @param {Object} detail - Additional event detail
     */
    dispatchConnectionEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, {
            bubbles: true,
            detail: {
                isOnline: MobileControllerBase.isOnline,
                ...detail
            }
        });
        this.element.dispatchEvent(event);
    }
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["mobile-controller-base"] = MobileControllerBase;

export default MobileControllerBase;
