/**
 * Activities Request Authorization Controller
 * 
 * Stimulus.js controller that manages authorization request workflows within
 * the KMP Activities Plugin. Provides interactive interface for members to
 * request activity authorizations with dynamic approver discovery and
 * member-specific validation.
 * 
 * **Core Functionality**:
 * - Authorization request form management with activity context
 * - Dynamic approver discovery based on activity and member
 * - Real-time form validation for authorization requests
 * - Member-specific authorization request workflows
 * 
 * **Request Context Integration**:
 * - Member ID integration for personalized request workflows
 * - Activity-specific approver discovery for request routing
 * - Request eligibility validation through server endpoints
 * - Member authorization status consideration for duplicate prevention
 * 
 * **Dynamic Approver Discovery**:
 * - Fetches request-appropriate approvers via AJAX
 * - Includes member context in approver discovery requests
 * - Handles member-specific approval authority validation
 * - Processes request-specific approver qualification
 * 
 * **State Management Features**:
 * - Submit button disabled until valid approver selected
 * - Approver dropdown disabled until activity selected
 * - Dynamic option population with member-context validation
 * - Form initialization with proper disabled states
 * 
 * **API Integration**:
 * - RESTful endpoint communication with member and activity context
 * - JSON response processing for request-specific data
 * - Proper HTTP headers for AJAX requests
 * - Error handling for request validation failures
 * 
 * **Security Considerations**:
 * - CSRF token integration through form submission
 * - Member authorization validation
 * - Request eligibility verification
 * - Server-side validation integration
 * 
 * **Performance Optimization**:
 * - Efficient AJAX requests with member/activity context
 * - DOM manipulation optimization for request interfaces
 * - Event delegation for optimal event handling
 * - Lazy loading of request-specific data
 * 
 * **Integration Points**:
 * - Activities Controller Endpoints - Request approver discovery API
 * - Authorization Request Forms - Form submission integration
 * - Member Management - Member context and validation
 * - Stimulus Application - Global controller registration
 * 
 * **Usage Examples**:
 * ```html
 * <!-- Authorization request interface -->
 * <div data-controller="activities-request-auth"
 *      data-activities-request-auth-url-value="/activities/request-approvers">
 *   <!-- Activity selection triggers request approver discovery -->
 *   <!-- Member context included in request validation -->
 *   <!-- Integrated with authorization request forms -->
 * </div>
 * ```
 * 
 * **Troubleshooting**:
 * - Verify request-specific AJAX endpoint availability and response format
 * - Check member ID and activity context in form targets
 * - Monitor network requests for request API communication
 * - Verify request eligibility validation on server side
 * - Ensure proper form initialization for disabled states
 * 
 * @see ActivitiesController.requestApproversList() Server endpoint for request approver discovery
 * @see AuthorizationRequest Authorization request workflow entities
 * @see Member Member entity with request context
 */

import { Controller } from "@hotwired/stimulus"

class ActivitiesRequestAuthorization extends Controller {
    static values = {
        url: String,
    }
    static targets = ["activity", "approvers", "submitBtn", "memberId"]

    /**
     * Fetch Available Approvers for Activity Request
     * 
     * Makes API request to retrieve list of members authorized to approve
     * authorization requests for the selected activity, populating the
     * approvers dropdown with member-specific options.
     * 
     * **Member Context Integration**:
     * - Includes member ID for request eligibility validation
     * - Filters approvers based on activity authorization context
     * - Handles member-specific request constraints
     * - Updates UI with member-appropriate approver options
     * 
     * **Request Processing**:
     * - Clears existing approver selections for fresh requests
     * - Constructs member and activity specific API endpoints
     * - Processes approver data for dropdown population
     * - Manages form state during approver discovery
     * 
     * **State Management**:
     * Resets form state and populates approver options while
     * maintaining proper disabled states until valid selection.
     * 
     * @param {Event} event Activity selection change event
     */
    getApprovers(event) {
        this.approversTarget.value = "";
        let activityId = this.activityTarget.value;
        let url = this.urlValue + "/" + activityId + "/" + this.memberIdTarget.value;
        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                let list = [];
                data.forEach((item) => {
                    list.push({
                        value: item.id,
                        text: item.sca_name
                    });
                });
                this.approversTarget.options = list;
                this.submitBtnTarget.disabled = true;
                this.approversTarget.disabled = false;
            });
    }

    /**
     * Initialize Controller State
     * 
     * Sets up initial form state with proper disabled controls
     * to guide user through request workflow sequence.
     * 
     * **Initial State Setup**:
     * - Disables approver dropdown until activity selected
     * - Ensures consistent form initialization
     * - Prevents premature form submission
     */
    acConnected() {
        if (this.hasApproversTarget) {
            this.approversTarget.disabled = true;
        }
    }

    /**
     * Configure AJAX Request Options
     * 
     * Provides standard AJAX request configuration for Activities API
     * communication with proper headers for JSON responses and AJAX
     * request identification.
     * 
     * **Headers Configuration**:
     * - X-Requested-With for AJAX identification
     * - Accept header for JSON content type specification
     * - Enables proper server-side AJAX detection
     * 
     * @returns {Object} Fetch options configuration for Activities API requests
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
     * Validate Request Form Completion
     * 
     * Checks request form completeness by validating approver selection
     * to enable form submission when all request requirements are satisfied.
     * 
     * **Request Validation**:
     * - Verifies approver selection with numeric validation
     * - Ensures valid approver ID for request processing
     * - Controls submit button state for user experience
     * - Provides immediate feedback on form completion status
     * 
     * **Form State Management**:
     * Provides immediate feedback on form completion status
     * and guides users through request workflow requirements.
     */
    checkReadyToSubmit() {
        let approverValue = this.approversTarget.value;
        let approverNum = parseInt(approverValue);
        if (approverNum > 0) {
            this.submitBtnTarget.disabled = false;
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }

    /**
     * Initialize Submit Button State
     * 
     * Ensures submit button starts in disabled state
     * to prevent premature form submission.
     */
    submitBtnTargetConnected() {
        this.submitBtnTarget.disabled = true;
    }

    /**
     * Initialize Approvers Dropdown State
     * 
     * Ensures approvers dropdown starts in disabled state
     * until activity selection triggers approver discovery.
     */
    approversTargetConnected() {
        this.approversTarget.disabled = true;
    }


}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["activities-request-auth"] = ActivitiesRequestAuthorization;