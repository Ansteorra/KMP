const { Controller } = require("@hotwired/stimulus");

/**
 * SessionExtender Stimulus Controller
 * 
 * Manages automatic session extension to prevent timeout during active user sessions.
 * Provides proactive session management with user notification and automatic extension
 * to maintain workflow continuity.
 * 
 * Features:
 * - Automatic session timeout warning after 25 minutes
 * - User notification with extension confirmation
 * - AJAX-based session extension requests
 * - Timer management and cleanup
 * - Configurable session extension endpoint
 * 
 * Values:
 * - url: String - Session extension API endpoint
 * 
 * Usage:
 * <div data-controller="session-extender" 
 *      data-session-extender-url-value="/api/extend-session">
 * </div>
 * 
 * Note: Timer automatically starts when URL value is set or changed
 */
class SessionExtender extends Controller {
    static values = { url: String }

    /**
     * Handle URL value changes and setup session timer
     * Resets existing timer and starts new countdown for session warning
     */
    urlValueChanged() {
        if (this.timer) {
            clearTimeout(this.timer)
        }
        var me = this;
        this.timer = setTimeout(function () {
            alert('Session Expiring! Click ok to extend session.');
            fetch(me.urlValue)
                .then(res => {
                    return res.json()
                })
                .then(data => {
                    me.urlValueChanged();
                })
            //minutes * 60000 miliseconds per minute
        }, 25 * 60000)
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["session-extender"] = SessionExtender;
