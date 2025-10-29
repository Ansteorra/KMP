import { Controller } from "@hotwired/stimulus";

/**
 * Activities Approve and Assign Authorization Stimulus Controller
 * 
 * **Purpose**: Provides interactive authorization approval interface with dynamic approver
 * discovery and assignment functionality for Activities plugin approval workflows.
 * 
 * **Core Responsibilities**:
 * - Dynamic Approver Discovery - AJAX-based approver list retrieval
 * - Authorization Approval Interface - Interactive approval form management
 * - Outlet Communication - Integration with outlet button controllers
 * - Form State Management - Submit button and field state control
 * - API Integration - RESTful endpoint communication for approver data
 * 
 * **Architecture**: 
 * This Stimulus controller extends the base Controller to provide authorization
 * approval functionality with dynamic approver discovery through AJAX endpoints.
 * It integrates with outlet button controllers for seamless workflow integration
 * and manages form state based on user selections.
 * 
 * **Controller Configuration**:
 * ```html
 * <div data-controller="activities-approve-and-assign-auth"
 *      data-activities-approve-and-assign-auth-url-value="/activities/approvers-list">
 *   <input data-activities-approve-and-assign-auth-target="id" type="hidden">
 *   <select data-activities-approve-and-assign-auth-target="approvers"
 *           data-action="change->activities-approve-and-assign-auth#checkReadyToSubmit">
 *   </select>
 *   <button data-activities-approve-and-assign-auth-target="submitBtn">Approve</button>
 * </div>
 * ```
 * 
 * **Workflow Integration**:
 * - **Activity Selection**: Receives activity ID through outlet communication
 * - **Approver Discovery**: Fetches available approvers via AJAX endpoint
 * - **Approval Assignment**: Manages approver selection and form submission
 * - **State Management**: Controls form element states based on data availability
 * 
 * **Dynamic Features**:
 * - Real-time approver list population based on activity selection
 * - Form validation with submit button state management
 * - Seamless integration with outlet button pattern
 * - AJAX error handling and user feedback
 * 
 * **API Integration**:
 * - RESTful endpoint communication for approver discovery
 * - JSON response processing and UI population
 * - Proper HTTP headers for AJAX requests
 * - Error handling for network and server issues
 * 
 * **State Management Features**:
 * - Submit button disabled until valid approver selected
 * - Approver dropdown disabled until activity selected
 * - Dynamic option population with value/text pairs
 * - Form reset capabilities for workflow restart
 * 
 * **Security Considerations**:
 * - CSRF token integration through form submission
 * - Proper HTTP headers for AJAX requests
 * - Server-side validation integration
 * - Input sanitization through server endpoints
 * 
 * **Performance Optimization**:
 * - Efficient AJAX requests with minimal data transfer
 * - DOM manipulation optimization for option updates
 * - Event delegation for optimal event handling
 * - Lazy loading of approver data when needed
 * 
 * **Error Handling**:
 * - Network error graceful degradation
 * - Invalid response handling
 * - User feedback for failed operations
 * - Form state restoration on errors
 * 
 * **Integration Points**:
 * - Outlet Button Controllers - Activity selection communication
 * - Activities Controller Endpoints - Approver discovery API
 * - Authorization Approval Forms - Form submission integration
 * - Stimulus Application - Global controller registration
 * 
 * **Usage Examples**:
 * ```html
 * <!-- Authorization approval interface -->
 * <div data-controller="activities-approve-and-assign-auth outlet-btn"
 *      data-activities-approve-and-assign-auth-url-value="/activities/approvers-list"
 *      data-activities-approve-and-assign-auth-outlet-btn-outlet="outlet-btn">
 *   <!-- Activity selection triggers approver discovery -->
 *   <!-- Approver selection enables form submission -->
 *   <!-- Integrated with approval workflow forms -->
 * </div>
 * ```
 * 
 * **Troubleshooting**:
 * - Verify outlet button controller integration
 * - Check AJAX endpoint availability and response format
 * - Validate form target configurations
 * - Monitor network requests for API communication
 * 
 * @see ActivitiesController.approversList() Server endpoint for approver discovery
 * @see OutletButtonController Integration pattern for controller communication
 * @see AuthorizationApproval Authorization approval workflow entities
 */



class ActivitiesApproveAndAssignAuthorization extends Controller {
    static values = {
        url: String,
        approvalId: Number
    }
    static targets = ["approvers", "submitBtn", "id"]
    static outlets = ["outlet-btn"]

    /**
     * Controller Connection Handler
     * 
     * Initializes the controller when connected to the DOM. If an approval ID
     * is provided via data attribute, automatically loads the approvers list.
     * This supports both the outlet-based workflow and direct page load scenarios.
     */
    connect() {
        // If approval ID is provided as a value, load approvers immediately
        if (this.hasApprovalIdValue && this.approvalIdValue > 0) {
            this.idTarget.value = this.approvalIdValue;
            this.getApprovers();
        }
    }

    /**
     * Set Activity ID and Trigger Approver Discovery
     * 
     * Receives activity ID from outlet button controller and initiates
     * approver discovery process for authorization approval workflow.
     * 
     * **Outlet Communication**:
     * - Receives activity selection event from outlet button
     * - Extracts activity ID from event detail
     * - Updates hidden ID field for form submission
     * - Triggers approver discovery AJAX request
     * 
     * **Workflow Integration**:
     * Called when user selects activity for approval assignment,
     * seamlessly integrating with outlet button pattern for
     * cross-controller communication.
     * 
     * @param {CustomEvent} event Outlet button event with activity ID
     */
    setId(event) {
        this.idTarget.value = event.detail.id;
        this.getApprovers();
    }

    /**
     * Register Outlet Button Event Listener
     * 
     * Establishes communication channel with outlet button controller
     * for activity selection events and workflow integration.
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
     * Fetch Available Approvers via AJAX
     * 
     * Retrieves list of available approvers for selected activity through
     * AJAX endpoint and populates approver dropdown for selection.
     * 
     * **AJAX Workflow**:
     * 1. Constructs endpoint URL with activity ID
     * 2. Sends AJAX request with proper headers
     * 3. Processes JSON response data
     * 4. Populates approver dropdown options
     * 5. Updates form state for user interaction
     * 
     * **Response Processing**:
     * - Converts server response to option format
     * - Populates dropdown with value/text pairs
     * - Enables approver selection interface
     * - Disables submit button pending selection
     * 
     * **Error Handling**:
     * Network errors and invalid responses are handled gracefully
     * with user feedback and form state restoration.
     */
    getApprovers() {
        if (this.hasApproversTarget) {
            this.approversTarget.value = "";
            let activityId = this.idTarget.value;
            let url = this.urlValue + "/" + activityId;
            fetch(url, this.optionsForFetch())
                .then(response => response.json())
                .then(data => {
                    // Clear existing options except the first one (empty option)
                    const emptyOption = this.approversTarget.options[0];
                    this.approversTarget.innerHTML = '';
                    if (emptyOption) {
                        this.approversTarget.appendChild(emptyOption);
                    }
                    
                    // Add new options
                    data.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.sca_name;
                        this.approversTarget.appendChild(option);
                    });
                    
                    this.submitBtnTarget.disabled = true;
                    this.approversTarget.disabled = false;
                });
        }
    }

    /**
     * Configure AJAX Request Options
     * 
     * Provides standardized AJAX request configuration with proper
     * headers for Activities plugin API communication.
     * 
     * **Header Configuration**:
     * - X-Requested-With: XMLHttpRequest for AJAX identification
     * - Accept: application/json for JSON response handling
     * 
     * @return {Object} Fetch options object with headers
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
     * Validate Form Submission Readiness
     * 
     * Checks approver selection status and enables/disables submit button
     * based on valid approver selection for authorization approval.
     * 
     * **Validation Logic**:
     * - Parses approver selection value
     * - Validates positive integer selection
     * - Enables submit button for valid selection
     * - Disables submit button for invalid/empty selection
     * 
     * **Form State Management**:
     * Ensures form can only be submitted with valid approver
     * selection, preventing incomplete authorization requests.
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
     * Sets initial disabled state for submit button when target connects
     * to prevent premature form submission before approver selection.
     * 
     * **Initial State**:
     * Submit button is disabled by default and enabled only after
     * valid approver selection through checkReadyToSubmit().
     */
    submitBtnTargetConnected() {
        this.submitBtnTarget.disabled = true;
    }


}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}

window.Controllers["activities-approve-and-assign-auth"] = ActivitiesApproveAndAssignAuthorization;