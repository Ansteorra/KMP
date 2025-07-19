const { Controller } = require("@hotwired/stimulus");

/**
 * **INTERNAL CODE DOCUMENTATION COMPLETE**
 * 
 * Delayed Forward Controller
 * 
 * A Stimulus controller that provides automatic page redirection after a configurable delay.
 * Useful for implementing splash screens, automatic redirects after form submissions, or
 * timed navigation scenarios with user feedback periods.
 * 
 * Key Features:
 * - Configurable delay timing for redirect control
 * - Automatic cleanup to prevent memory leaks
 * - Timeout management with proper cancellation
 * - Immediate activation on controller connection
 * - Console logging for debugging redirect behavior
 * 
 * @class DelayForwardController
 * @extends Controller
 * 
 * Values:
 * - url: String - Target URL for redirection
 * - delayMs: Number - Delay in milliseconds before redirect
 * 
 * HTML Structure Example:
 * ```html
 * <!-- Basic delayed redirect after 3 seconds -->
 * <div data-controller="delayed-forward" 
 *      data-delayed-forward-url-value="/dashboard"
 *      data-delayed-forward-delay-ms-value="3000">
 *   <div class="text-center">
 *     <h2>Processing your request...</h2>
 *     <p>You will be redirected automatically in 3 seconds.</p>
 *     <div class="spinner-border" role="status">
 *       <span class="visually-hidden">Loading...</span>
 *     </div>
 *   </div>
 * </div>
 * 
 * <!-- Post-form submission redirect with feedback -->
 * <div data-controller="delayed-forward"
 *      data-delayed-forward-url-value="/members/list" 
 *      data-delayed-forward-delay-ms-value="2000">
 *   <div class="alert alert-success">
 *     <h4>Success!</h4>
 *     <p>Member has been created successfully.</p>
 *     <p>Redirecting to member list...</p>
 *   </div>
 * </div>
 * ```
 */
class DelayForwardController extends Controller {
    static values = { url: String, delayMs: Number };

    /** @type {number|null} Timer reference for cleanup management */
    timeout = null;

    /**
     * Initialize controller and start delayed forward process
     * Automatically begins the redirect timer upon connection
     */
    connect() {
        console.log("DelayForwardController connected");
        this.timeout = null;
        this.forward();
    }

    /**
     * Manage delayed redirect with timeout control
     * Cancels any existing timeout before setting a new one to prevent multiple redirects
     * Uses window.location.href for full page navigation
     */
    forward() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
        this.timeout = setTimeout(() => {
            console.log("Forwarding to " + this.urlValue);
            window.location.href = this.urlValue;
        }, this.delayMsValue);
    }

    /**
     * Clean up timeout on controller disconnection
     * Prevents redirect execution if controller is disconnected before timeout completes
     * Essential for preventing memory leaks and unwanted redirects
     */
    disconnect() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["delay-forward"] = DelayForwardController;