import { Controller } from "@hotwired/stimulus"

/**
 * MemberUniqueEmail Stimulus Controller
 * 
 * Provides real-time email uniqueness validation with AJAX checking and 
 * Bootstrap validation feedback. Ensures email addresses are unique across
 * the system while providing immediate user feedback.
 * 
 * Features:
 * - Real-time email uniqueness validation
 * - AJAX-based server-side checking
 * - Bootstrap validation class integration
 * - Original email comparison for updates
 * - Custom HTML5 validation messages
 * - Visual feedback with is-valid/is-invalid classes
 * - Automatic event listener management
 * 
 * Values:
 * - url: String - API endpoint for email uniqueness checking
 * 
 * Required HTML attributes:
 * - data-original-value: Original email value for comparison during updates
 * 
 * Usage:
 * <input type="email" 
 *        data-controller="member-unique-email"
 *        data-member-unique-email-url-value="/api/check-email"
 *        data-original-value="existing@example.com"
 *        name="email" required>
 */
class MemberUniqueEmail extends Controller {
    static values = { url: String }

    /**
     * Connect controller to DOM
     * Sets up event listeners and removes conflicting attributes
     */
    connect() {
        this.element.removeAttribute('oninput');
        this.element.removeAttribute('oninvalid');
        this.element.addEventListener('change', this.checkEmail.bind(this));
    }

    /**
     * Disconnect controller from DOM
     * Cleans up event listeners
     */
    disconnect(event) {
        this.element.removeEventListener('change', this.checkEmail.bind(this));
    }

    /**
     * Configure fetch options for AJAX requests
     * Sets up headers for JSON API communication
     * 
     * @returns {Object} Fetch options object
     */
    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    /**
     * Check email uniqueness via AJAX
     * Validates email against server and updates UI with Bootstrap classes
     * 
     * @param {Event} event - Change event from email input
     */
    checkEmail(event) {
        var email = this.element.value;
        if (email == '') {
            this.element.classList.remove('is-invalid');
            this.element.classList.remove('is-valid');
            this.element.setCustomValidity('');
            return;
        }
        var originalEmail = this.element.dataset.originalValue.toLowerCase();
        if (email.toLowerCase() == originalEmail) {
            this.element.classList.add('is-valid');
            this.element.classList.remove('is-invalid');
            return;
        }
        var checkEmailUrl = this.urlValue + '?nostack=yes&email=' + encodeURIComponent(email);
        fetch(checkEmailUrl, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                if (data) {
                    this.element.classList.add('is-invalid');
                    this.element.classList.remove('is-valid');
                    this.element.setCustomValidity('This email address is already taken.');
                } else {
                    this.element.classList.add('is-valid');
                    this.element.classList.remove('is-invalid');
                    this.element.setCustomValidity('');
                }
            });
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-unique-email"] = MemberUniqueEmail;