

import { Controller } from "@hotwired/stimulus";

/**
 * Awards Recommendation Quick Edit Controller
 * 
 * Specialized Stimulus controller for rapid recommendation updates with streamlined workflow and 
 * administrative efficiency. Provides simplified form functionality for quick recommendation 
 * modifications with reduced complexity and optimized performance for high-volume administrative tasks.
 * 
 * ## Quick Edit Features
 * 
 * **Streamlined Interface:**
 * - Simplified form layout with essential fields only
 * - Reduced validation complexity for rapid processing
 * - Quick state transitions with minimal confirmation requirements
 * - Automated form closure after successful submission
 * 
 * **Administrative Efficiency:**
 * - Bulk edit preparation with outlet communication
 * - Rapid award and specialty selection with existing data preservation
 * - State-aware field management with simplified rule application
 * - Quick access patterns for high-volume recommendation processing
 * 
 * **Workflow Optimization:**
 * - Streamlined state transition workflow with reduced steps
 * - Essential field validation without comprehensive form checks
 * - Quick save functionality with immediate feedback
 * - Modal integration for non-intrusive editing experience
 * 
 * ## State Management Features
 * 
 * **Simplified Rules:**
 * - Essential state rules only for critical workflow validation
 * - Reduced field dependency complexity for quick updates
 * - Streamlined required field management for efficiency
 * - Quick state transition validation without extensive checks
 * 
 * **Dynamic Field Control:**
 * - Visibility management based on recommendation state
 * - Disabled field control for workflow integrity
 * - Required field enforcement for essential data validation
 * - Quick rule application for rapid form updates
 * 
 * **Data Preservation:**
 * - Existing award and specialty data restoration
 * - Autocomplete integration with current selection values
 * - State-aware form initialization for quick editing
 * - Form value persistence during quick update workflow
 * 
 * ## Administrative Integration
 * 
 * **Turbo Frame Support:**
 * - Quick form loading with recommendation ID context
 * - Outlet communication for coordinated updates
 * - Modal integration for streamlined user experience
 * - Real-time form updates without page navigation
 * 
 * **Bulk Operation Preparation:**
 * - Quick edit as preparation for bulk operations
 * - State standardization for batch processing
 * - Rapid validation for administrative efficiency
 * - Workflow optimization for large recommendation queues
 * 
 * ## Usage Examples
 * 
 * ### Quick Edit Modal Integration
 * ```html
 * <!-- Quick edit modal with streamlined form -->
 * <div class="modal fade" id="quickEditModal">
 *   <div class="modal-dialog">
 *     <div class="modal-content">
 *       <form data-controller="awards-rec-quick-edit" 
 *             data-awards-rec-quick-edit-award-list-url-value="/awards/by-domain"
 *             data-awards-rec-quick-edit-form-url-value="/awards/recommendations/quick-edit"
 *             data-awards-rec-quick-edit-turbo-frame-url-value="/awards/recommendations/turbo-quick-edit">
 * 
 *         <div class="modal-header">
 *           <h5>Quick Edit Recommendation</h5>
 *           <button type="button" class="btn-close" id="recommendation_edit_close" 
 *                   data-bs-dismiss="modal"></button>
 *         </div>
 * 
 *         <div class="modal-body">
 *           <!-- Essential fields only -->
 *           <div class="mb-3">
 *             <label>Award Domain</label>
 *             <select data-awards-rec-quick-edit-target="domain" 
 *                     data-action="change->awards-rec-quick-edit#populateAwardDescriptions" 
 *                     class="form-select">
 *               <option value="">Select Domain</option>
 *             </select>
 *           </div>
 * 
 *           <input type="hidden" data-awards-rec-quick-edit-target="award" name="award_id">
 *           <select data-awards-rec-quick-edit-target="specialty" class="form-select">
 *             <option value="">Select Award First</option>
 *           </select>
 * 
 *           <div class="mb-3">
 *             <label>State</label>
 *             <select data-awards-rec-quick-edit-target="state" 
 *                     data-action="change->awards-rec-quick-edit#setFieldRules" 
 *                     class="form-select">
 *               <option value="Submitted">Submitted</option>
 *               <option value="Approved">Approved</option>
 *               <option value="Given">Given</option>
 *             </select>
 *           </div>
 * 
 *           <!-- State-dependent quick fields -->
 *           <div data-awards-rec-quick-edit-target="planToGiveBlock" style="display: none;">
 *             <select data-awards-rec-quick-edit-target="planToGiveEvent" 
 *                     class="form-select">
 *               <option value="">Select Event</option>
 *             </select>
 *           </div>
 * 
 *           <div data-awards-rec-quick-edit-target="givenBlock" style="display: none;">
 *             <input type="date" data-awards-rec-quick-edit-target="givenDate" 
 *                    class="form-control">
 *           </div>
 *         </div>
 * 
 *         <div class="modal-footer">
 *           <button type="submit" data-action="awards-rec-quick-edit#submit" 
 *                   class="btn btn-primary">Quick Update</button>
 *           <button type="button" class="btn btn-secondary" 
 *                   data-bs-dismiss="modal">Cancel</button>
 *         </div>
 *       </form>
 *     </div>
 *   </div>
 * </div>
 * ```
 * 
 * ### Outlet Communication for Quick Access
 * ```html
 * <!-- Quick edit with outlet button communication -->
 * <div data-controller="awards-rec-quick-edit outlet-btn" 
 *      data-awards-rec-quick-edit-outlet-btn-outlet=".quick-edit-button">
 * 
 *   <turbo-frame id="quick-edit-frame" 
 *                data-awards-rec-quick-edit-target="turboFrame">
 *     <!-- Quick form content -->
 *   </turbo-frame>
 * 
 *   <input type="hidden" data-awards-rec-quick-edit-target="recId" value="">
 * </div>
 * ```
 * 
 * ### Administrative Bulk Preparation
 * ```javascript
 * // Quick edit for bulk operation preparation
 * document.addEventListener('DOMContentLoaded', function() {
 *   const quickEditBtns = document.querySelectorAll('.quick-edit-trigger');
 *   quickEditBtns.forEach(btn => {
 *     btn.addEventListener('click', function() {
 *       const recId = this.dataset.recId;
 *       const quickEditModal = document.getElementById('quickEditModal');
 *       const controller = window.Stimulus.getControllerForElementAndIdentifier(
 *         quickEditModal.querySelector('[data-controller*="awards-rec-quick-edit"]'), 
 *         'awards-rec-quick-edit'
 *       );
 *       if (controller) {
 *         controller.setId({ detail: { id: recId } });
 *       }
 *     });
 *   });
 * });
 * ```
 * 
 * @class AwardsRecommendationQuickEditForm
 * @extends {Controller}
 */
class AwardsRecommendationQuickEditForm extends Controller {
    static targets = [
        "domain",
        "award",
        "reason",
        "gatherings",
        "specialty",
        "state",
        "planToGiveBlock",
        "planToGiveGathering",
        "givenBlock",
        "recId",
        "memberId",
        "turboFrame",
        "givenDate",
        "closeReason",
        "closeReasonBlock",
        "stateRulesBlock",
    ];
    static values = {
        publicProfileUrl: String,
        awardListUrl: String,
        formUrl: String,
        turboFrameUrl: String,
        gatheringsUrl: String
    };
    static outlets = ['outlet-btn'];

    /**
     * Set recommendation ID for quick edit context
     * 
     * Updates Turbo Frame source and form action URL based on recommendation ID
     * from outlet communication for streamlined quick editing workflow.
     * 
     * @param {Event} event - Custom event with recommendation ID
     * @returns {void}
     */
    setId(event) {
        if (event.detail.id) {
            this.turboFrameTarget.setAttribute("src", this.turboFrameUrlValue + "/" + event.detail.id);
            this.element.setAttribute("action", this.formUrlValue + "/" + event.detail.id);
        }
    }

    /**
     * Handle outlet button connection
     * 
     * Establishes communication with outlet button controller for
     * coordinated quick edit operations and recommendation ID management.
     * 
     * @param {Object} outlet - Connected outlet controller
     * @param {Element} element - Outlet DOM element
     * @returns {void}
     */
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }

    /**
     * Handle outlet button disconnection
     * 
     * Removes event listener when outlet button disconnects
     * for proper cleanup and memory management.
     * 
     * @param {Object} outlet - Disconnected outlet controller
     * @returns {void}
     */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }

    /**
     * Submit quick edit form
     * 
     * Handles quick form submission and automatically closes the modal
     * for streamlined administrative workflow efficiency.
     * 
     * @param {Event} event - Form submit event
     * @returns {void}
     */
    submit(event) {
        document.getElementById("recommendation_edit_close").click();
    }

    /**
     * Set selected award and populate specialties
     * 
     * Handles award selection and triggers specialty population for
     * quick editing with existing data preservation.
     * Also updates the gatherings list to show only relevant gatherings.
     * 
     * @param {Event} event - Click event from award selection
     * @returns {void}
     */
    setAward(event) {
        let awardId = event.target.dataset.awardId;
        this.awardTarget.value = awardId;
        if (this.awardTarget.value != "") {
            this.populateSpecialties(event);
            this.updateGatherings(awardId);
        }
    }

    /**
     * Update gatherings list based on selected award
     * 
     * Fetches and updates the gatherings list to show only gatherings
     * that have activities linked to the selected award. Marks gatherings
     * where the member has indicated attendance with crown sharing.
     * If status is "Given", shows all gatherings (past and future).
     * 
     * @param {string} awardId - The selected award ID
     * @returns {void}
     */
    updateGatherings(awardId) {
        if (!awardId) {
            return;
        }

        // Get member_id if available
        let memberId = this.hasMemberIdTarget ? this.memberIdTarget.value : '';
        
        // Get status if available
        let status = this.hasStateTarget ? this.stateTarget.value : '';
        
        // Build URL with query params
        let url = this.gatheringsUrlValue + '/' + awardId;
        let params = new URLSearchParams();
        if (memberId) {
            params.append('member_id', memberId);
        }
        if (status) {
            params.append('status', status);
        }
        if (params.toString()) {
            url += '?' + params.toString();
        }

        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                if (data.gatherings) {
                    // Update the gatherings checkboxes
                    const gatheringsContainer = document.getElementById('recommendation__gathering_ids');
                    if (gatheringsContainer) {
                        // Save currently selected values
                        const selectedValues = [];
                        gatheringsContainer.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                            selectedValues.push(cb.value);
                        });

                        // Clear existing options
                        gatheringsContainer.innerHTML = '';

                        // Add new options as checkboxes
                        data.gatherings.forEach(gathering => {
                            const div = document.createElement('div');
                            div.className = 'form-check';
                            
                            const input = document.createElement('input');
                            input.type = 'checkbox';
                            input.className = 'form-check-input';
                            input.name = 'gatherings[_ids][]';
                            input.value = gathering.id;
                            input.id = `gathering-${gathering.id}`;
                            
                            // Restore checked state if it was previously selected
                            if (selectedValues.includes(gathering.id.toString())) {
                                input.checked = true;
                            }
                            
                            const label = document.createElement('label');
                            label.className = 'form-check-label';
                            label.htmlFor = `gathering-${gathering.id}`;
                            label.textContent = gathering.display;
                            
                            div.appendChild(input);
                            div.appendChild(label);
                            gatheringsContainer.appendChild(div);
                        });
                    }
                    
                    // Also update the planToGiveGathering dropdown if it exists
                    if (this.hasPlanToGiveGatheringTarget) {
                        // Try to get current value, fallback to initial value stored on connect
                        const currentValue = this.planToGiveGatheringTarget.value || 
                                            this.planToGiveGatheringTarget.dataset.initialValue || 
                                            '';
                        
                        // Clear existing options
                        this.planToGiveGatheringTarget.innerHTML = '';
                        
                        // Add default option
                        const defaultOption = document.createElement('option');
                        defaultOption.value = '';
                        defaultOption.textContent = 'Select Gathering';
                        this.planToGiveGatheringTarget.appendChild(defaultOption);
                        
                        // Add gathering options
                        data.gatherings.forEach(gathering => {
                            const option = document.createElement('option');
                            option.value = gathering.id;
                            option.textContent = gathering.display;
                            
                            // Restore selected state if it matches current or initial value
                            if (gathering.id.toString() === currentValue) {
                                option.selected = true;
                            }
                            
                            this.planToGiveGatheringTarget.appendChild(option);
                        });
                        
                        // Update the stored value for next time
                        if (currentValue) {
                            this.planToGiveGatheringTarget.dataset.initialValue = currentValue;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching gatherings:', error);
            });
    }

    /**
     * Populate award descriptions for quick selection
     * 
     * Fetches awards for selected domain and populates quick selection interface
     * with streamlined award selection for administrative efficiency.
     * 
     * @param {Event} event - Change event from domain selection
     * @returns {void}
     */
    populateAwardDescriptions(event) {
        let url = this.awardListUrlValue + "/" + event.target.value;
        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                this.awardTarget.value = "";
                let active = "active";
                let show = "show";
                let selected = "true";
                let awardList = [];
                if (data.length > 0) {
                    data.forEach(function (award) {
                        awardList.push({ value: award.id, text: award.name, data: award });
                    });
                    this.awardTarget.options = awardList;
                    this.awardTarget.disabled = false;
                    if (this.awardTarget.dataset.acInitSelectionValue) {
                        let val = JSON.parse(this.awardTarget.dataset.acInitSelectionValue);
                        this.awardTarget.value = val.value;
                        if (this.awardTarget.value != "") {
                            this.populateSpecialties({ target: { value: val.value } });
                        }
                    }
                } else {
                    this.awardTarget.options = [{ value: "No awards available", text: "No awards available" }];
                    this.awardTarget.value = "No awards available";
                    this.awardTarget.disabled = true;
                    this.specialtyTarget.options = [{ value: "No specialties available", text: "No specialties available" }];
                    this.specialtyTarget.value = "No specialties available";
                    this.specialtyTarget.disabled = true
                    this.specialtyTarget.hidden = true;
                }
            });
    }

    /**
     * Populate specialties for quick selection
     * 
     * Updates specialty dropdown based on award selection with
     * streamlined interface for rapid administrative updates.
     * 
     * @param {Event} event - Award selection event
     * @returns {void}
     */
    populateSpecialties(event) {
        let awardId = this.awardTarget.value;
        let options = this.awardTarget.options;
        let award = this.awardTarget.options.find(award => award.value == awardId);
        let specialtyArray = [];
        if (award.data.specialties != null && award.data.specialties.length > 0) {
            award.data.specialties.forEach(function (specialty) {
                specialtyArray.push({ value: specialty, text: specialty });
            });
            this.specialtyTarget.options = specialtyArray;
            this.specialtyTarget.value = "";
            this.specialtyTarget.disabled = false;
            this.specialtyTarget.hidden = false;
            if (this.specialtyTarget.dataset.acInitSelectionValue) {
                let val = JSON.parse(this.specialtyTarget.dataset.acInitSelectionValue);
                this.specialtyTarget.value = val.value;
            }
        } else {
            this.specialtyTarget.options = [{ value: "No specialties available", text: "No specialties available" }];
            this.specialtyTarget.value = "No specialties available";
            this.specialtyTarget.disabled = true
            this.specialtyTarget.hidden = true;
        }
    }

    /**
     * Load SCA member information (simplified)
     * 
     * Placeholder method for member information loading in quick edit context.
     * Simplified for rapid editing workflow without full profile loading.
     * 
     * @param {Event} event - Input change event from member field
     * @returns {void}
     */
    loadScaMemberInfo(event) {
    }

    /**
     * Get fetch options for AJAX requests
     * 
     * Provides standardized headers for JSON API communication with proper
     * AJAX identification and content type specification.
     * 
     * @returns {Object} Fetch options object with headers
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
     * Handle state target connection for quick edit
     * 
     * Initializes simplified field rules when state selector connects
     * for streamlined quick editing workflow.
     * 
     * @returns {void}
     */
    stateTargetConnected() {
        console.log("status connected");
        this.setFieldRules();
    }

    /**
     * Apply simplified field rules for quick editing
     * 
     * Manages essential form field visibility and requirements based on
     * state selection with streamlined rules for administrative efficiency.
     * 
     * @returns {void}
     */
    setFieldRules() {
        console.log("setting field rules");
        var rulesstring = this.stateRulesBlockTarget.textContent;
        var rules = JSON.parse(rulesstring);
        if (this.specialtyTarget.options.length == 0) {
            this.specialtyTarget.hidden = true;
            this.specialtyTarget.disabled = true;
        }

        this.planToGiveBlockTarget.style.display = "none";
        this.givenBlockTarget.style.display = "none";
        
        // Store the current givenDate value before potentially clearing it
        if (this.givenDateTarget.value && !this.givenDateTarget.dataset.initialValue) {
            this.givenDateTarget.dataset.initialValue = this.givenDateTarget.value;
        }
        
        // Only clear givenDate if it doesn't have an initial value stored
        if (!this.givenDateTarget.dataset.initialValue) {
            this.givenDateTarget.value = "";
        } else {
            // Restore the initial value if it was cleared
            if (!this.givenDateTarget.value) {
                this.givenDateTarget.value = this.givenDateTarget.dataset.initialValue;
            }
        }
        
        this.domainTarget.disabled = false;
        this.awardTarget.disabled = false;
        this.specialtyTarget.disabled = this.specialtyTarget.hidden;
        this.planToGiveGatheringTarget.required = false;
        this.givenDateTarget.required = false;
        this.closeReasonBlockTarget.style.display = "none";
        this.closeReasonTarget.required = false;

        var state = this.stateTarget.value;

        //check status rules for the status
        if (rules[state]) {
            var statusRules = rules[state];
            var controller = this;
            if (statusRules["Visible"]) {
                statusRules["Visible"].forEach(function (field) {
                    if (controller[field]) {
                        controller[field].style.display = "block";
                    }
                });
            }
            if (statusRules["Disabled"]) {
                statusRules["Disabled"].forEach(function (field) {
                    if (controller[field]) {
                        controller[field].disabled = true;
                    }
                });
            }
            if (statusRules["Required"]) {
                statusRules["Required"].forEach(function (field) {
                    if (controller[field]) {
                        controller[field].required = true;
                    }
                });
            }
        }
        
        // Update gatherings when state changes (e.g., to/from "Given")
        if (this.hasAwardTarget && this.awardTarget.value) {
            this.updateGatherings(this.awardTarget.value);
        }
    }

    /**
     * Initialize quick edit controller
     * 
     * Sets up the quick edit controller for streamlined recommendation
     * modifications with minimal setup requirements.
     * 
     * @returns {void}
     */
    connect() {
        // Store the initial gathering_id value so it persists through option updates
        if (this.hasPlanToGiveGatheringTarget && this.planToGiveGatheringTarget.value) {
            this.planToGiveGatheringTarget.dataset.initialValue = this.planToGiveGatheringTarget.value;
        }
    }

    /**
     * Handle recommendation ID target connection for quick edit
     * 
     * Updates form action URL when recommendation ID connects for
     * proper quick edit form submission routing.
     * 
     * @returns {void}
     */
    recIdTargetConnected() {
        let recId = this.recIdTarget.value;
        let actionUrl = this.element.getAttribute("action");
        //trim the last / off of the end of the action url
        actionUrl = actionUrl.replace(/\/\d+$/, "");
        actionUrl = actionUrl + "/" + recId;
        this.element.setAttribute("action", actionUrl);
    }
    
    /**
     * Handle planToGiveGathering target connection for quick edit
     * 
     * Stores the initial gathering_id value when the field connects so it
     * persists through dynamic option updates.
     * 
     * @returns {void}
     */
    planToGiveGatheringTargetConnected() {
        // Store the initial value from the server-rendered form
        if (this.planToGiveGatheringTarget.value) {
            this.planToGiveGatheringTarget.dataset.initialValue = this.planToGiveGatheringTarget.value;
        }
    }
    
    /**
     * Handle givenDate target connection for quick edit
     * 
     * Stores the initial given date value when the field connects so it
     * persists through field rule updates.
     * 
     * @returns {void}
     */
    givenDateTargetConnected() {
        // Store the initial value from the server-rendered form
        if (this.givenDateTarget.value) {
            this.givenDateTarget.dataset.initialValue = this.givenDateTarget.value;
        }
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-quick-edit"] = AwardsRecommendationQuickEditForm;