/**
 * Waivers Add Requirement Controller
 * 
 * Stimulus.js controller that manages waiver requirement workflows for
 * gathering activities in the KMP Waivers Plugin. Provides interactive
 * interface for adding waiver requirements with dynamic waiver type
 * discovery and validation.
 * 
 * **Core Functionality**:
 * - Waiver requirement form management with activity context
 * - Dynamic waiver type discovery (excluding already assigned types)
 * - Real-time form validation for waiver requirements
 * - Activity-specific waiver requirement workflows
 * 
 * **Dynamic Waiver Type Discovery**:
 * - Fetches available waiver types via AJAX
 * - Excludes waiver types already assigned to the activity
 * - Includes only active, non-deleted waiver types
 * - Handles activity-specific waiver type filtering
 * 
 * **State Management Features**:
 * - Submit button disabled until valid waiver type selected
 * - Waiver type dropdown populated on modal open
 * - Dynamic option population with activity context
 * - Form initialization with proper disabled states
 * 
 * **API Integration**:
 * - RESTful endpoint communication with activity context
 * - JSON response processing for waiver type data
 * - Proper HTTP headers for AJAX requests
 * - Error handling for API failures
 * 
 * **Integration Points**:
 * - GatheringActivityWaivers Controller - Available waiver types API
 * - Waiver Requirement Forms - Form submission integration
 * - Stimulus Application - Global controller registration
 * 
 * **Usage Examples**:
 * ```html
 * <!-- Waiver requirement modal -->
 * <div data-controller="waivers-add-requirement"
 *      data-waivers-add-requirement-url-value="/waivers/gathering-activity-waivers/available-waiver-types">
 *   <input type="hidden" data-waivers-add-requirement-target="activityId" value="1">
 *   <select data-waivers-add-requirement-target="waiverType"
 *           data-action="ready->waivers-add-requirement#loadWaiverTypes">
 *   </select>
 * </div>
 * ```
 * 
 * @see GatheringActivityWaiversController.availableWaiverTypes() Server endpoint
 * @see GatheringActivityWaivers Waiver requirement entity
 */

import { Controller } from "@hotwired/stimulus";

class WaiversAddRequirement extends Controller {
    static values = {
        url: String,
    }
    static targets = ["waiverType", "submitBtn", "activityId"]

    /**
     * Load Available Waiver Types
     * 
     * Fetches list of waiver types that can be added to the activity,
     * excluding types already assigned and including only active types.
     * 
     * **API Request**:
     * - Includes activity ID for filtering assigned types
     * - Fetches only active, non-deleted waiver types
     * - Processes waiver type data for dropdown population
     * - Manages form state during data loading
     * 
     * **State Management**:
     * Populates waiver type dropdown and enables form submission
     * once valid selection is made.
     */
    loadWaiverTypes() {
        let activityId = this.activityIdTarget.value;
        let url = this.urlValue + "/" + activityId;
        
        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                let list = [];
                
                if (data.waiverTypes && data.waiverTypes.length > 0) {
                    data.waiverTypes.forEach((item) => {
                        list.push({
                            value: item.id,
                            text: item.name
                        });
                    });
                }
                
                this.waiverTypeTarget.options = list;
                this.submitBtnTarget.disabled = true;
            })
            .catch(error => {
                console.error('Error loading waiver types:', error);
                this.submitBtnTarget.disabled = true;
            });
    }

    /**
     * Configure AJAX Request Options
     * 
     * Provides standard AJAX request configuration for Waivers API
     * communication with proper headers for JSON responses.
     * 
     * @returns {Object} Fetch options configuration
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
     * Validate Form Completion
     * 
     * Checks if a valid waiver type is selected to enable submission.
     * 
     * **Validation**:
     * - Verifies waiver type selection with numeric validation
     * - Controls submit button state for user experience
     * - Provides immediate feedback on form completion
     */
    checkReadyToSubmit() {
        let waiverTypeValue = this.waiverTypeTarget.value;
        let waiverTypeNum = parseInt(waiverTypeValue);
        
        if (waiverTypeNum > 0) {
            this.submitBtnTarget.disabled = false;
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }

    /**
     * Initialize Submit Button State
     * 
     * Ensures submit button starts disabled to prevent
     * premature form submission.
     */
    submitBtnTargetConnected() {
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = true;
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["waivers-add-requirement"] = WaiversAddRequirement;
