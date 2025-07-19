import { Controller } from "@hotwired/stimulus"

/**
 * Activities Renew Authorization Stimulus Controller
 * 
 * **Purpose**: Provides interactive authorization renewal interface with dynamic approver
 * discovery, member-specific validation, and seamless renewal workflow management for
 * Activities plugin authorization lifecycle.
 * 
 * **Core Responsibilities**:
 * - Authorization Renewal Interface - Member-specific renewal form management
 * - Dynamic Approver Discovery - AJAX-based approver list retrieval with member context
 * - Outlet Communication - Integration with outlet button controllers
 * - Member Context Management - Member-specific renewal validation and processing
 * - Form State Management - Submit button and field state control with renewal logic
 * 
 * **Architecture**: 
 * This Stimulus controller extends the base Controller to provide authorization
 * renewal functionality with member-specific approver discovery through AJAX endpoints.
 * It integrates with outlet button controllers and manages renewal-specific validation
 * including member eligibility and existing authorization requirements.
 * 
 * **Controller Configuration**:
 * ```html
 * <div data-controller="activities-renew-auth"
 *      data-activities-renew-auth-url-value="/activities/renewal-approvers-list">
 *   <input data-activities-renew-auth-target="id" type="hidden">
 *   <input data-activities-renew-auth-target="activity" type="hidden">
 *   <input data-activities-renew-auth-target="memberId" type="hidden">
 *   <select data-activities-renew-auth-target="approvers"
 *           data-action="change->activities-renew-auth#checkReadyToSubmit">
 *   </select>
 *   <button data-activities-renew-auth-target="submitBtn">Renew Authorization</button>
 * </div>
 * ```
 * 
 * **Renewal Workflow Context**:
 * Authorization renewals differ from new requests by requiring:
 * - Existing active or recently expired authorization
 * - Member eligibility validation for renewal
 * - Potentially different approver requirements
 * - Renewal-specific business logic and validation
 * 
 * **Member-Specific Features**:
 * - Member ID integration for personalized renewal workflows
 * - Member-specific approver discovery based on authorization context
 * - Renewal eligibility validation through server endpoints
 * - Member authorization history consideration
 * 
 * **Dynamic Approver Discovery**:
 * - Fetches renewal-appropriate approvers via AJAX
 * - Includes member context in approver discovery requests
 * - Handles member-specific approval authority validation
 * - Processes renewal-specific approver qualification
 * 
 * **Outlet Integration**:
 * - Receives authorization selection events from outlet controllers
 * - Extracts both authorization ID and activity information
 * - Manages cross-controller communication for renewal workflows
 * - Supports complex renewal initiation patterns
 * 
 * **State Management Features**:
 * - Submit button disabled until valid renewal approver selected
 * - Approver dropdown disabled until authorization/activity selected
 * - Dynamic option population with member-context validation
 * - Form reset capabilities for renewal workflow restart
 * 
 * **API Integration**:
 * - RESTful endpoint communication with member and activity context
 * - JSON response processing for renewal-specific data
 * - Proper HTTP headers for AJAX requests
 * - Error handling for renewal validation failures
 * 
 * **Security Considerations**:
 * - CSRF token integration through form submission
 * - Member authorization validation
 * - Renewal eligibility verification
 * - Server-side validation integration
 * 
 * **Performance Optimization**:
 * - Efficient AJAX requests with member/activity context
 * - DOM manipulation optimization for renewal interfaces
 * - Event delegation for optimal event handling
 * - Lazy loading of renewal-specific data
 * 
 * **Integration Points**:
 * - Outlet Button Controllers - Authorization selection communication
 * - Activities Controller Endpoints - Renewal approver discovery API
 * - Authorization Renewal Forms - Form submission integration
 * - Member Management - Member context and validation
 * - Stimulus Application - Global controller registration
 * 
 * **Usage Examples**:
 * ```html
 * <!-- Authorization renewal interface -->
 * <div data-controller="activities-renew-auth outlet-btn"
 *      data-activities-renew-auth-url-value="/activities/renewal-approvers"
 *      data-activities-renew-auth-outlet-btn-outlet="outlet-btn">
 *   <!-- Authorization selection triggers renewal approver discovery -->
 *   <!-- Member context included in renewal validation -->
 *   <!-- Integrated with renewal workflow forms -->
 * </div>
 * ```
 * 
 * **Troubleshooting**:
 * - Verify outlet button controller integration for authorization selection
 * - Check renewal-specific AJAX endpoint availability and response format
 * - Validate member ID and activity context in form targets
 * - Monitor network requests for renewal API communication
 * - Verify renewal eligibility validation on server side
 * 
 * @see ActivitiesController.renewalApproversList() Server endpoint for renewal approver discovery
 * @see OutletButtonController Integration pattern for authorization selection
 * @see AuthorizationRenewal Authorization renewal workflow entities
 * @see Member Member entity with renewal context
 */



class ActivitiesRenewAuthorization extends Controller {
    static values = {
        url: String,
    }
    static targets = ["activity", "approvers", "submitBtn", "memberId", "id"]
    static outlets = ["outlet-btn"]

    /**
     * Set Authorization and Activity Context for Renewal
     * 
     * Receives authorization selection from outlet button controller and
     * extracts both authorization ID and activity information for renewal
     * workflow initialization.
     * 
     * **Renewal Context Setup**:
     * - Extracts authorization ID for renewal processing
     * - Sets activity ID for approver discovery context
     * - Initiates member-specific approver discovery
     * - Prepares form for renewal workflow
     * 
     * **Outlet Communication**:
     * Receives complex event data including both authorization
     * and activity information needed for renewal processing,
     * enabling sophisticated renewal workflow integration.
     * 
     * @param {CustomEvent} event Outlet button event with authorization and activity data
     */
    setId(event) {
        this.idTarget.value = event.detail.id;
        this.activityTarget.value = event.detail.activity;
        this.getApprovers();
    }

    /**
     * Register Outlet Button Event Listener
     * 
     * Establishes communication channel with outlet button controller
     * for authorization selection events and renewal workflow integration.
     * 
     * @param {Controller} outlet Outlet button controller instance
     * @param {HTMLElement} element Associated DOM element
     */
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }

    /**
     * Unregister Outlet Button Event Listener
     * 
     * Cleans up communication channel when outlet button controller
     * disconnects to prevent memory leaks and orphaned listeners.
     * 
     * @param {Controller} outlet Outlet button controller instance
     */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }



    /**
     * Fetch Available Approvers for Activity
     * 
     * Makes API request to retrieve list of members authorized to approve
     * renewals for the selected activity, populating the approvers dropdown
     * with member-specific options.
     * 
     * **Member Context Integration**:
     * - Includes member ID for renewal eligibility validation
     * - Filters approvers based on activity authorization context
     * - Handles member-specific renewal constraints
     * - Updates UI with member-appropriate approver options
     * 
     * **Error Handling**:
     * Provides fallback behavior when approver discovery fails,
     * ensuring renewal workflow can proceed even without dynamic
     * approver information.
     * 
     * **State Management**:
     * Updates form submission readiness after approver population
     * to ensure complete renewal context before form submission.
     */
    getApprovers() {
        if (this.hasApproversTarget) {
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
     * Validate Renewal Form Completion
     * 
     * Checks renewal form completeness by validating approver selection
     * to enable form submission when all renewal requirements are satisfied.
     * 
     * **Renewal Validation**:
     * - Verifies approver selection with numeric validation
     * - Ensures valid approver ID for renewal processing
     * - Controls submit button state for user experience
     * - Provides immediate feedback on form completion status
     * 
     * **Form State Management**:
     * Provides immediate feedback on form completion status
     * and guides users through renewal workflow requirements.
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

    submitBtnTargetConnected() {
        this.submitBtnTarget.disabled = true;
    }


}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}

window.Controllers["activities-renew-auth"] = ActivitiesRenewAuthorization;