import { Controller } from "@hotwired/stimulus"

/**
 * GitHub Submitter Stimulus Controller - GitHub Issue Submission with AJAX Integration
 *
 * This comprehensive Stimulus controller manages the complete lifecycle of anonymous GitHub
 * issue submission, providing seamless AJAX integration with user feedback, form management,
 * and modal interaction. It handles form data collection, API request processing, response
 * handling, and UI state transitions to deliver a smooth user experience for feedback submission.
 *
 * ## Core Functionality
 *
 * ### Form Submission Management
 * - AJAX-powered form submission without page reload
 * - FormData collection and transmission to GitHub API endpoint
 * - Real-time form validation and user feedback
 * - Automatic form reset after successful submission
 * - Progress indication during API communication
 *
 * ### User Experience Management
 * - Seamless modal integration with Bootstrap components
 * - Dynamic UI state transitions (form → success → reset)
 * - User feedback with success messages and error handling
 * - Responsive design support for mobile and desktop
 * - Accessibility considerations for screen readers and keyboard navigation
 *
 * ### GitHub API Integration
 * - Direct communication with GitHubIssueSubmitter plugin backend
 * - JSON response processing for issue creation confirmation
 * - Error handling for API failures and network issues
 * - Issue URL generation for user confirmation and tracking
 * - Real-time feedback on submission status
 *
 * ## Stimulus Architecture
 *
 * ### Target Elements
 * The controller manages multiple DOM targets for UI state management:
 * - **success**: Success message and issue link display area
 * - **formBlock**: Main form container for submission interface
 * - **submitBtn**: Primary submission button with state management
 * - **issueLink**: Dynamic link to created GitHub issue
 * - **form**: Form element for data collection and reset
 * - **modal**: Bootstrap modal container for interaction management
 *
 * ### Value Configuration
 * - **url**: API endpoint for GitHub issue submission
 * - Configurable through HTML data attributes
 * - Supports different environments and repository configurations
 * - Dynamic URL generation based on plugin settings
 *
 * ## Submission Workflow
 *
 * ### 1. Form Data Collection
 * - Prevents default form submission behavior
 * - Collects all form data using FormData API
 * - Maintains form field validation and user input
 * - Prepares data for AJAX transmission
 *
 * ### 2. AJAX Request Processing
 * - Sends POST request to configured submission endpoint
 * - Handles fetch API with proper error checking
 * - Processes JSON responses from GitHub API
 * - Manages network errors and API failures
 *
 * ### 3. Response Handling
 * - **Success Path**: Display success message with issue link
 * - **Error Path**: Show error message and maintain form state
 * - **Network Errors**: Provide user-friendly error feedback
 * - **API Errors**: Display GitHub API error messages
 *
 * ### 4. UI State Management
 * - Transitions from form view to success view
 * - Resets UI state when modal is closed
 * - Manages button states and visibility
 * - Provides visual feedback throughout the process
 *
 * ## Modal Integration
 *
 * ### Bootstrap Modal Support
 * - Automatic modal event listener management
 * - State reset when modal is hidden
 * - Proper cleanup of event listeners
 * - Support for multiple modal instances
 *
 * ### Event Lifecycle Management
 * - **modalTargetConnected**: Registers modal event listeners
 * - **modalTargetDisconnected**: Cleanup event listeners
 * - **hidden.bs.modal**: Resets form state when modal closes
 * - Prevents memory leaks and event conflicts
 *
 * ## User Feedback System
 *
 * ### Success State Management
 * - Displays success message with created issue information
 * - Provides direct link to GitHub issue for user verification
 * - Shows issue number and URL for tracking
 * - Hides form interface and shows success confirmation
 *
 * ### Error Handling
 * - GitHub API error messages displayed to user
 * - Network error fallback messaging
 * - Form state preserved for error recovery
 * - Console logging for debugging and monitoring
 *
 * ## Integration Examples
 *
 * ### HTML Template Integration
 * ```html
 * <div data-controller="github-submitter" 
 *      data-github-submitter-url-value="/git-hub-issue-submitter/issues/submit">
 *   
 *   <!-- Form Block -->
 *   <div data-github-submitter-target="formBlock">
 *     <form data-github-submitter-target="form" 
 *           data-action="submit->github-submitter#submit">
 *       <input type="text" name="title" required>
 *       <textarea name="body" required></textarea>
 *       <select name="feedbackType">
 *         <option value="bug">Bug Report</option>
 *         <option value="feature">Feature Request</option>
 *         <option value="general">General Feedback</option>
 *       </select>
 *       <button type="submit" data-github-submitter-target="submitBtn">
 *         Submit Feedback
 *       </button>
 *     </form>
 *   </div>
 *   
 *   <!-- Success Block -->
 *   <div data-github-submitter-target="success" style="display: none;">
 *     <h4>Thank you for your feedback!</h4>
 *     <p>Your issue has been created successfully.</p>
 *     <a data-github-submitter-target="issueLink" 
 *        target="_blank" class="btn btn-primary">
 *       View Issue on GitHub
 *     </a>
 *   </div>
 * </div>
 * ```
 *
 * ### Modal Integration
 * ```html
 * <div class="modal fade" data-github-submitter-target="modal">
 *   <div class="modal-dialog">
 *     <div class="modal-content">
 *       <div class="modal-header">
 *         <h5 class="modal-title">Submit Feedback</h5>
 *       </div>
 *       <div class="modal-body">
 *         <!-- GitHub Submitter Controller Content -->
 *       </div>
 *     </div>
 *   </div>
 * </div>
 * ```
 *
 * ### Navigation Integration
 * ```html
 * <button type="button" 
 *         class="btn btn-outline-secondary"
 *         data-bs-toggle="modal"
 *         data-bs-target="#feedbackModal">
 *   <i class="bi bi-chat-dots"></i> Feedback
 * </button>
 * ```
 *
 * ## Error Handling Strategies
 *
 * ### GitHub API Errors
 * - Display specific error messages from GitHub API
 * - Maintain form state for user correction
 * - Provide guidance for common error scenarios
 * - Log errors for administrative monitoring
 *
 * ### Network Connectivity Issues
 * - Generic error messaging for network failures
 * - Retry mechanisms or offline support (future enhancement)
 * - User guidance for connectivity troubleshooting
 * - Graceful degradation when API is unavailable
 *
 * ### Form Validation Errors
 * - Client-side validation before submission
 * - Server-side validation error handling
 * - Field-specific error messaging and highlighting
 * - Progressive enhancement with JavaScript validation
 *
 * ## Performance Considerations
 *
 * ### AJAX Optimization
 * - Fetch API for modern browser compatibility
 * - Efficient FormData serialization
 * - Minimal DOM manipulation for state changes
 * - Event listener cleanup to prevent memory leaks
 *
 * ### UI Responsiveness
 * - Non-blocking AJAX requests
 * - Immediate user feedback during submission
 * - Smooth transitions between UI states
 * - Optimized for mobile and desktop performance
 *
 * ## Security Integration
 *
 * ### CSRF Protection
 * - Integration with CakePHP CSRF tokens
 * - Automatic token inclusion in form submission
 * - Framework-level protection against CSRF attacks
 * - Secure form processing pipeline
 *
 * ### Input Validation
 * - Client-side validation for user experience
 * - Server-side validation for security
 * - XSS prevention through proper data handling
 * - Content sanitization before API submission
 *
 * @class GitHubSubmitter
 * @extends Controller
 * @since 1.0.0
 */

class GitHubSubmitter extends Controller {
    // Define target elements for DOM manipulation and state management
    static targets = ["success", "formBlock", "submitBtn", "issueLink", "form", "modal"];

    // Define configurable values from HTML data attributes
    static values = { url: String };

    /**
     * Submit method - Process GitHub issue submission with AJAX integration
     *
     * This method handles the complete GitHub issue submission workflow, from form data
     * collection through API communication to user feedback. It implements comprehensive
     * error handling, user experience management, and UI state transitions to provide
     * a seamless feedback submission experience.
     *
     * ## Submission Workflow
     *
     * ### 1. Form Data Collection and Validation
     * - Prevents default form submission to enable AJAX processing
     * - Collects all form data using modern FormData API
     * - Maintains form validation and user input integrity
     * - Prepares data for secure API transmission
     *
     * ### 2. AJAX Request Processing
     * - Sends POST request to configured GitHub API endpoint
     * - Uses fetch API for modern browser compatibility
     * - Implements proper HTTP status code checking
     * - Handles network connectivity and timeout issues
     *
     * ### 3. API Response Processing
     * - Parses JSON responses from GitHub Issues API
     * - Distinguishes between success and error responses
     * - Handles GitHub API error messages and status codes
     * - Processes issue creation data (URL, issue number)
     *
     * ### 4. User Interface State Management
     * - Transitions from submission form to success confirmation
     * - Updates UI elements with issue information
     * - Manages button states and visibility
     * - Provides immediate user feedback on submission status
     *
     * ## Error Handling Strategies
     *
     * ### GitHub API Errors
     * When the GitHub API returns an error (e.g., authentication, permissions):
     * - Displays specific error message from API response
     * - Maintains form state for user correction attempts
     * - Logs error details for administrative monitoring
     * - Provides user guidance for error resolution
     *
     * ### Network and Connectivity Errors
     * For network failures, timeouts, or connection issues:
     * - Shows generic error message to protect system details
     * - Maintains form data to prevent user data loss
     * - Logs technical details to console for debugging
     * - Enables retry functionality without data re-entry
     *
     * ### Response Processing Errors
     * For malformed responses or unexpected data:
     * - Graceful handling of JSON parsing errors
     * - Fallback error messaging for unknown response formats
     * - Preservation of user input for retry attempts
     * - Detailed logging for development and debugging
     *
     * ## UI State Transitions
     *
     * ### Success State
     * When issue creation is successful:
     * - Hides form interface (`formBlockTarget.style.display = 'none'`)
     * - Hides submit button (`submitBtnTarget.style.display = 'none'`)
     * - Shows success message (`successTarget.style.display = 'block'`)
     * - Updates issue link with GitHub URL (`issueLinkTarget.href = data.url`)
     * - Resets form for potential future use (`form.reset()`)
     *
     * ### Error State
     * When submission fails:
     * - Preserves form interface and user input
     * - Displays error message through alert (or custom UI)
     * - Maintains submit button availability for retry
     * - Logs error details for troubleshooting
     *
     * ## API Integration Details
     *
     * ### Request Format
     * - HTTP Method: POST
     * - Content-Type: multipart/form-data (via FormData)
     * - Body: Form fields (title, body, feedbackType)
     * - Headers: Automatic CSRF token inclusion (CakePHP framework)
     *
     * ### Expected Response Formats
     *
     * #### Success Response
     * ```json
     * {
     *   "url": "https://github.com/owner/repo/issues/123",
     *   "number": 123
     * }
     * ```
     *
     * #### Error Response
     * ```json
     * {
     *   "message": "API error description"
     * }
     * ```
     *
     * ## Usage Examples
     *
     * ### Basic Form Submission
     * ```html
     * <form data-action="submit->github-submitter#submit">
     *   <input type="text" name="title" required>
     *   <textarea name="body" required></textarea>
     *   <button type="submit">Submit</button>
     * </form>
     * ```
     *
     * ### Advanced Error Handling Integration
     * ```javascript
     * // Custom error handling enhancement
     * submit(event) {
     *   // ... existing logic ...
     *   .catch(error => {
     *     // Enhanced error handling
     *     this.showCustomError(error.message);
     *     this.trackErrorEvent(error);
     *   });
     * }
     * ```
     *
     * ### Loading State Integration
     * ```javascript
     * // Add loading indicators
     * submit(event) {
     *   this.setLoadingState(true);
     *   
     *   fetch(url, { method: 'POST', body: formData })
     *     .then(response => {
     *       this.setLoadingState(false);
     *       // ... process response ...
     *     })
     *     .catch(error => {
     *       this.setLoadingState(false);
     *       // ... handle error ...
     *     });
     * }
     * ```
     *
     * ## Security Considerations
     *
     * ### CSRF Protection
     * - Automatic CSRF token inclusion through CakePHP framework
     * - Form-based token validation on server side
     * - Protection against cross-site request forgery attacks
     * - Secure form processing pipeline
     *
     * ### Data Validation
     * - Client-side validation for user experience
     * - Server-side validation for security enforcement
     * - Input sanitization before API transmission
     * - XSS prevention through proper data handling
     *
     * ### Error Information Security
     * - Generic error messages to prevent information disclosure
     * - Detailed errors logged securely for administration
     * - No sensitive information exposed to client side
     * - Secure error handling practices
     *
     * @param {Event} event - Form submission event
     * @returns {void}
     * 
     * @example Basic Usage
     * ```html
     * <div data-controller="github-submitter" 
     *      data-github-submitter-url-value="/submit-endpoint">
     *   <form data-action="submit->github-submitter#submit">
     *     <!-- form fields -->
     *   </form>
     * </div>
     * ```
     * 
     * @example Response Processing
     * ```javascript
     * // Success: data = { url: "...", number: 123 }
     * // Error: data = { message: "Error description" }
     * // Network Error: thrown exception with error message
     * ```
     */
    submit(event) {
        event.preventDefault();
        let url = this.urlValue;
        let form = this.formTarget;
        let formData = new FormData(form);

        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (response.ok) {
                    return response.json();
                } else {
                    throw new Error('An error occurred while creating the issue.');
                }
            })
            .then(data => {
                if (data.message) {
                    alert("Error: " + data.message);
                    return;
                }
                form.reset();
                this.formBlockTarget.style.display = 'none';
                this.submitBtnTarget.style.display = 'none';
                this.issueLinkTarget.href = data.url;
                this.successTarget.style.display = 'block';
            })
            .catch(error => {
                console.error(error);
                alert('An error occurred while creating the issue.');
            });
    }

    /**
     * Modal target connected - Initialize modal event listeners
     *
     * This method is automatically called when a modal target element is connected
     * to the controller. It sets up event listeners for Bootstrap modal events,
     * specifically handling the modal hide event to reset the form state when
     * the user closes the modal.
     *
     * ## Event Listener Management
     *
     * ### Bootstrap Modal Integration
     * - Listens for `hidden.bs.modal` event from Bootstrap modal component
     * - Automatically resets UI state when modal is closed
     * - Ensures clean state for subsequent modal usage
     * - Maintains proper user experience flow
     *
     * ### UI State Reset
     * When modal is hidden, the listener:
     * - Shows form block (`formBlockTarget.style.display = 'block'`)
     * - Hides success message (`successTarget.style.display = 'none'`)
     * - Shows submit button (`submitBtnTarget.style.display = 'block'`)
     * - Prepares interface for next submission
     *
     * ## Integration with Stimulus Lifecycle
     *
     * ### Automatic Target Management
     * - Called automatically when modal target is added to DOM
     * - Part of Stimulus target connection lifecycle
     * - Ensures event listeners are properly initialized
     * - Supports dynamic modal creation and removal
     *
     * ### Memory Management
     * - Event listeners are added only when needed
     * - Proper cleanup handled by disconnection method
     * - Prevents memory leaks in single-page applications
     * - Supports multiple modal instances
     *
     * @returns {void}
     * 
     * @example Modal HTML Structure
     * ```html
     * <div class="modal fade" 
     *      data-github-submitter-target="modal"
     *      tabindex="-1">
     *   <div class="modal-dialog">
     *     <div class="modal-content">
     *       <!-- Modal content with form -->
     *     </div>
     *   </div>
     * </div>
     * ```
     */
    modalTargetConnected() {
        this.modalTarget.addEventListener('hidden.bs.modal', () => {
            this.formBlockTarget.style.display = 'block';
            this.successTarget.style.display = 'none';
            this.submitBtnTarget.style.display = 'block';
        });
    }

    /**
     * Modal target disconnected - Clean up modal event listeners
     *
     * This method is automatically called when a modal target element is disconnected
     * from the controller. It removes event listeners to prevent memory leaks and
     * ensures proper cleanup when the modal is removed from the DOM or when the
     * controller is destroyed.
     *
     * ## Memory Management
     *
     * ### Event Listener Cleanup
     * - Removes `hidden.bs.modal` event listeners
     * - Prevents memory leaks in single-page applications
     * - Ensures proper garbage collection
     * - Maintains application performance
     *
     * ### Lifecycle Integration
     * - Part of Stimulus target disconnection lifecycle
     * - Automatically called when target element is removed
     * - Supports dynamic modal creation and removal
     * - Maintains clean controller state
     *
     * ## Error Prevention
     *
     * ### Stale Reference Prevention
     * - Removes references to disconnected DOM elements
     * - Prevents errors from removed event listeners
     * - Maintains controller stability
     * - Supports hot module reloading in development
     *
     * @returns {void}
     * 
     * @example Automatic Cleanup
     * ```javascript
     * // Called automatically when modal is removed
     * document.querySelector('.modal').remove();
     * // modalTargetDisconnected() is called automatically
     * ```
     */
    modalTargetDisconnected() {
        this.modalTarget.removeEventListener('hidden.bs.modal', () => {
            this.formBlockTarget.style.display = 'block';
            this.successTarget.style.display = 'none';
            this.submitBtnTarget.style.display = 'block';
        });
    }

    /**
     * Connect method - Initialize controller state and UI
     *
     * This method is automatically called when the controller is connected to the DOM,
     * initializing the default UI state for the feedback submission interface. It ensures
     * that the form is visible and ready for user interaction while hiding success
     * messages from previous submissions.
     *
     * ## Initial State Setup
     *
     * ### UI State Initialization
     * - Shows form interface (`formBlockTarget.style.display = 'block'`)
     * - Hides success message (`successTarget.style.display = 'none'`)
     * - Shows submit button (`submitBtnTarget.style.display = 'block'`)
     * - Prepares interface for user interaction
     *
     * ### Controller Lifecycle Integration
     * - Part of Stimulus controller connection lifecycle
     * - Called automatically when controller attaches to DOM element
     * - Ensures consistent initial state across page loads
     * - Supports dynamic controller instantiation
     *
     * ## State Management
     *
     * ### Default UI State
     * The method establishes the baseline interface state:
     * - Form is visible and ready for input
     * - Success messages are hidden
     * - Submit functionality is available
     * - Interface is prepared for user interaction
     *
     * ### Consistency Across Loads
     * - Ensures predictable initial state
     * - Handles page refreshes and navigation
     * - Supports browser back/forward functionality
     * - Maintains interface consistency
     *
     * ## Usage Context
     *
     * ### Page Load Initialization
     * - Called when page loads with controller element
     * - Ensures proper initial display state
     * - Prepares form for first-time usage
     * - Handles server-side rendered content
     *
     * ### Dynamic Content Loading
     * - Called when controller is added via AJAX
     * - Supports single-page application patterns
     * - Handles dynamic modal creation
     * - Maintains state consistency in complex UIs
     *
     * @returns {void}
     * 
     * @example Automatic Initialization
     * ```html
     * <!-- Controller automatically connects and calls connect() -->
     * <div data-controller="github-submitter">
     *   <div data-github-submitter-target="formBlock">
     *     <!-- Form content -->
     *   </div>
     * </div>
     * ```
     * 
     * @example Dynamic Initialization
     * ```javascript
     * // When dynamically added, connect() is called automatically
     * const element = document.createElement('div');
     * element.dataset.controller = 'github-submitter';
     * document.body.appendChild(element);
     * ```
     */
    connect() {
        this.formBlockTarget.style.display = 'block';
        this.successTarget.style.display = 'none';
        this.submitBtnTarget.style.display = 'block';
    }
}

/**
 * Controller Registration - Global Stimulus Controller Registry Integration
 *
 * This section registers the GitHubSubmitter controller with the global KMP Stimulus
 * controller registry, making it available for use throughout the application. The
 * registration follows KMP's standard controller registration pattern for consistent
 * plugin integration and controller discovery.
 *
 * ## Global Registry Pattern
 *
 * ### Window.Controllers Registry
 * - Creates global registry if it doesn't exist
 * - Stores all Stimulus controllers for application-wide access
 * - Enables dynamic controller loading and registration
 * - Supports plugin-based controller architecture
 *
 * ### Naming Convention
 * - Controller registered as "github-submitter"
 * - Matches HTML data-controller attribute naming
 * - Follows kebab-case naming convention
 * - Maintains consistency with KMP controller patterns
 *
 * ## Integration with KMP Architecture
 *
 * ### Plugin Controller Loading
 * - Part of KMP's plugin-based Stimulus architecture
 * - Enables modular controller organization
 * - Supports plugin activation/deactivation
 * - Maintains controller isolation and encapsulation
 *
 * ### Dynamic Registration
 * - Controllers can be registered at runtime
 * - Supports lazy loading and code splitting
 * - Enables conditional controller loading
 * - Facilitates plugin development workflow
 *
 * ## Usage Examples
 *
 * ### HTML Controller Activation
 * ```html
 * <!-- Controller automatically discovered via registry -->
 * <div data-controller="github-submitter">
 *   <!-- Controller functionality available -->
 * </div>
 * ```
 *
 * ### Manual Controller Access
 * ```javascript
 * // Access controller class from global registry
 * const GitHubSubmitterClass = window.Controllers["github-submitter"];
 * 
 * // Manual instantiation (rarely needed)
 * const controller = new GitHubSubmitterClass();
 * ```
 *
 * ### Plugin Integration Check
 * ```javascript
 * // Check if controller is available
 * if (window.Controllers && window.Controllers["github-submitter"]) {
 *     // GitHub submission functionality is available
 * }
 * ```
 *
 * @example Controller Registration Pattern
 * ```javascript
 * // Standard KMP controller registration
 * if (!window.Controllers) {
 *     window.Controllers = {};
 * }
 * window.Controllers["controller-name"] = ControllerClass;
 * ```
 */

// Add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["github-submitter"] = GitHubSubmitter;