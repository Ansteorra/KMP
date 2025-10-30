
import { Controller } from "@hotwired/stimulus";

/**
 * Awards Recommendation Edit Controller
 * 
 * Comprehensive Stimulus controller for recommendation editing with state management and workflow 
 * control. Provides interactive form functionality for modifying existing award recommendations 
 * with dynamic state validation, award selection workflow, and comprehensive administrative management.
 * 
 * ## Edit Workflow Features
 * 
 * **State Management:**
 * - Dynamic state transition validation with business rule enforcement
 * - Field visibility control based on recommendation state and workflow rules
 * - Required field management with state-aware validation
 * - Form state persistence with Turbo Frame integration
 * 
 * **Award Configuration:**
 * - Domain/award hierarchy management with existing data restoration
 * - Specialty population based on award configuration and current selection
 * - Award eligibility validation with existing recommendation context
 * - Dynamic form field management based on award selection
 * 
 * **Member Integration:**
 * - Member profile loading with external links display for context
 * - Branch management for SCA and non-SCA members
 * - Member validation with existing recommendation data preservation
 * - Profile context display for administrative review
 * 
 * ## Administrative Interface Features
 * 
 * **Turbo Frame Integration:**
 * - Dynamic form loading with recommendation ID context
 * - Form URL management with recommendation-specific routing
 * - Real-time form updates without page refresh
 * - Outlet communication for coordinated interface updates
 * 
 * **Workflow Control:**
 * - State-based field rules with dynamic application
 * - Business rule enforcement through state validation
 * - Administrative override capabilities with proper authorization
 * - Form validation with comprehensive error handling
 * 
 * **Data Restoration:**
 * - Existing recommendation data loading and form population
 * - Autocomplete initialization with current selection values
 * - State-aware form configuration on load
 * - Field dependency management with existing data
 * 
 * ## State Transition Management
 * 
 * **Dynamic Rules Application:**
 * - JSON-based state rules parsing and application
 * - Field visibility control based on recommendation state
 * - Required field enforcement with state-specific requirements
 * - Disabled field management for workflow control
 * 
 * **Business Logic Integration:**
 * - Event planning integration with date validation
 * - Award ceremony coordination with event selection
 * - Close reason management for workflow completion
 * - Administrative note integration with state tracking
 * 
 * ## Usage Examples
 * 
 * ### Basic Recommendation Edit Form
 * ```html
 * <!-- Recommendation edit with state management -->
 * <form data-controller="awards-rec-edit" 
 *       data-awards-rec-edit-public-profile-url-value="/members/public-profile"
 *       data-awards-rec-edit-award-list-url-value="/awards/by-domain"
 *       data-awards-rec-edit-form-url-value="/awards/recommendations/edit"
 *       data-awards-rec-edit-turbo-frame-url-value="/awards/recommendations/turbo-edit-form">
 * 
 *   <!-- Hidden state rules for dynamic field management -->
 *   <script type="application/json" data-awards-rec-edit-target="stateRulesBlock">
 *     {
 *       "Approved": {
 *         "Visible": ["planToGiveBlock"],
 *         "Required": ["planToGiveEvent"]
 *       },
 *       "Given": {
 *         "Visible": ["givenBlock"],
 *         "Required": ["givenDate"],
 *         "Disabled": ["domain", "award", "specialty"]
 *       },
 *       "Closed": {
 *         "Visible": ["closeReasonBlock"],
 *         "Required": ["closeReason"],
 *         "Disabled": ["domain", "award", "specialty", "scaMember"]
 *       }
 *     }
 *   </script>
 * 
 *   <!-- Member Information -->
 *   <div class="mb-3">
 *     <label>SCA Member</label>
 *     <input type="text" data-awards-rec-edit-target="scaMember" 
 *            data-action="change->awards-rec-edit#loadScaMemberInfo" 
 *            class="form-control">
 *     <div data-awards-rec-edit-target="externalLinks" class="member-links"></div>
 *     <div class="form-check">
 *       <input type="checkbox" data-awards-rec-edit-target="notFound">
 *       <label>Member not found in SCA database</label>
 *     </div>
 *     <input type="text" data-awards-rec-edit-target="branch" 
 *            placeholder="Branch Name" class="form-control" hidden>
 *   </div>
 * 
 *   <!-- Award Selection -->
 *   <div class="mb-3">
 *     <label>Award Domain</label>
 *     <select data-awards-rec-edit-target="domain" 
 *             data-action="change->awards-rec-edit#populateAwardDescriptions" 
 *             class="form-select">
 *       <option value="">Select Domain</option>
 *     </select>
 *   </div>
 * 
 *   <input type="hidden" data-awards-rec-edit-target="award" name="award_id">
 *   <select data-awards-rec-edit-target="specialty" name="specialty" 
 *           class="form-select">
 *     <option value="">Select Award First</option>
 *   </select>
 * 
 *   <!-- State Management -->
 *   <div class="mb-3">
 *     <label>Recommendation State</label>
 *     <select data-awards-rec-edit-target="state" 
 *             data-action="change->awards-rec-edit#setFieldRules" 
 *             class="form-select">
 *       <option value="Submitted">Submitted</option>
 *       <option value="Under Review">Under Review</option>
 *       <option value="Approved">Approved</option>
 *       <option value="Given">Given</option>
 *       <option value="Closed">Closed</option>
 *     </select>
 *   </div>
 * 
 *   <!-- State-dependent fields -->
 *   <div data-awards-rec-edit-target="planToGiveBlock" style="display: none;">
 *     <label>Plan to Give at Event</label>
 *     <select data-awards-rec-edit-target="planToGiveEvent" name="event_id" 
 *             class="form-select">
 *       <option value="">Select Event</option>
 *     </select>
 *   </div>
 * 
 *   <div data-awards-rec-edit-target="givenBlock" style="display: none;">
 *     <label>Date Given</label>
 *     <input type="date" data-awards-rec-edit-target="givenDate" 
 *            name="given_date" class="form-control">
 *   </div>
 * 
 *   <div data-awards-rec-edit-target="closeReasonBlock" style="display: none;">
 *     <label>Close Reason</label>
 *     <textarea data-awards-rec-edit-target="closeReason" name="close_reason" 
 *               class="form-control"></textarea>
 *   </div>
 * 
 *   <button type="submit" data-action="awards-rec-edit#submit" 
 *           class="btn btn-primary">Update Recommendation</button>
 * </form>
 * ```
 * 
 * ### Turbo Frame Integration
 * ```html
 * <!-- Edit form with outlet communication -->
 * <div data-controller="awards-rec-edit outlet-btn" 
 *      data-awards-rec-edit-outlet-btn-outlet=".edit-button-controller"
 *      data-awards-rec-edit-form-url-value="/awards/recommendations/edit"
 *      data-awards-rec-edit-turbo-frame-url-value="/awards/recommendations/turbo-edit-form">
 * 
 *   <turbo-frame id="recommendation-edit-frame" 
 *                data-awards-rec-edit-target="turboFrame">
 *     <!-- Dynamic form content loaded here -->
 *   </turbo-frame>
 * 
 *   <input type="hidden" data-awards-rec-edit-target="recId" value="">
 * </div>
 * ```
 * 
 * ### State Rules Configuration
 * ```javascript
 * // Example state rules for dynamic field management
 * const stateRules = {
 *   "Submitted": {
 *     "Disabled": [],
 *     "Required": ["award", "reason"],
 *     "Visible": []
 *   },
 *   "Approved": {
 *     "Disabled": ["scaMember"],
 *     "Required": ["award", "reason", "planToGiveEvent"],
 *     "Visible": ["planToGiveBlock"]
 *   },
 *   "Given": {
 *     "Disabled": ["domain", "award", "specialty", "scaMember"],
 *     "Required": ["award", "reason", "givenDate"],
 *     "Visible": ["givenBlock"]
 *   }
 * };
 * ```
 * 
 * @class AwardsRecommendationEditForm
 * @extends {Controller}
 */
class AwardsRecommendationEditForm extends Controller {
    static targets = [
        "scaMember",
        "notFound",
        "branch",
        "externalLinks",
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
     * Set recommendation ID for form context
     * 
     * Updates Turbo Frame source and form action URL based on recommendation ID
     * from outlet communication for coordinated interface updates.
     * 
     * @param {Event} event - Custom event with recommendation ID
     * @returns {void}
     */
    setId(event) {
        this.turboFrameTarget.setAttribute("src", this.turboFrameUrlValue + "/" + event.detail.id);
        this.element.setAttribute("action", this.formUrlValue + "/" + event.detail.id);
    }

    /**
     * Handle outlet button connection
     * 
     * Establishes communication with outlet button controller for
     * coordinated form updates and recommendation ID management.
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
     * Submit form with field validation
     * 
     * Enables all form fields before submission to ensure data integrity
     * and proper form processing by the backend controller.
     * 
     * @param {Event} event - Form submit event
     * @returns {void}
     */
    submit(event) {
        this.notFoundTarget.disabled = false;
        this.scaMemberTarget.disabled = false;
        this.specialtyTarget.disabled = false;
    }

    /**
     * Set selected award and populate specialties
     * 
     * Handles award selection and triggers specialty population based on
     * award configuration with existing data preservation.
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
        let memberId = this.hasScaMemberTarget ? this.scaMemberTarget.value : '';
        
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
     * Populate award descriptions based on domain selection
     * 
     * Fetches awards for selected domain and populates award selection interface
     * with existing data restoration and autocomplete initialization.
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
     * Populate specialties based on award selection
     * 
     * Updates specialty dropdown based on selected award configuration with
     * existing data restoration and autocomplete initialization.
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
     * Load SCA member information and context
     * 
     * Handles member ID validation, profile loading, and branch field management
     * based on member discovery with existing recommendation context.
     * 
     * @param {Event} event - Input change event from member field
     * @returns {void}
     */
    loadScaMemberInfo(event) {
        this.externalLinksTarget.innerHTML = "";

        let memberId = Number(event.target.value.replace(/_/g, ""));
        if (memberId > 0) {
            this.notFoundTarget.checked = false;
            this.branchTarget.hidden = true;
            this.branchTarget.disabled = true;
            this.loadMember(memberId);
        } else {
            this.notFoundTarget.checked = true;
            this.branchTarget.hidden = false;
            this.branchTarget.disabled = false;
            this.branchTarget.focus();
        }

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
     * Load member profile data from API
     * 
     * Fetches member profile information and displays external links
     * for member context and administrative review.
     * 
     * @param {number} memberId - The member ID to load
     * @returns {void}
     */
    loadMember(memberId) {
        let url = this.publicProfileUrlValue + "/" + memberId;
        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                this.externalLinksTarget.innerHTML = "";
                let keys = Object.keys(data.external_links);
                if (keys.length > 0) {
                    var LinksTitle = document.createElement("div");
                    LinksTitle.innerHTML = "<h5>Public Links</h5>";
                    LinksTitle.classList.add("col-12");
                    this.externalLinksTarget.appendChild(LinksTitle);
                    for (let key in data.external_links) {
                        let div = document.createElement("div");
                        div.classList.add("col-12");
                        let a = document.createElement("a");
                        a.href = data.external_links[key];
                        a.text = key;
                        a.target = "_blank";
                        div.appendChild(a);
                        this.externalLinksTarget.appendChild(div);
                    }
                } else {
                    var noLink = document.createElement("div");
                    noLink.innerHTML = "<h5>No links available</h5>";
                    noLink.classList.add("col-12");
                    this.externalLinksTarget.appendChild(noLink);
                }
            });
    }

    /**
     * Handle SCA member target connection
     * 
     * Initializes member information loading when member field connects
     * if existing value is present for data restoration.
     * 
     * @returns {void}
     */
    scaMemberTargetConnected() {
        if (this.scaMemberTarget.value != "") {
            this.loadScaMemberInfo({ target: { value: this.scaMemberTarget.value } });
        }
    }

    /**
     * Handle state target connection
     * 
     * Initializes field rules when state selector connects to ensure
     * proper form configuration for existing recommendations.
     * 
     * @returns {void}
     */
    stateTargetConnected() {
        console.log("status connected");
        this.setFieldRules();
    }

    /**
     * Apply dynamic field rules based on recommendation state
     * 
     * Manages form field visibility, requirements, and disabled state based on
     * the recommendation state with comprehensive rule application and data preservation.
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
        this.scaMemberTarget.disabled = false;
        this.planToGiveGatheringTarget.required = false;
        this.givenDateTarget.required = false;
        this.closeReasonBlockTarget.style.display = "none";
        this.closeReasonTarget.required = false;
        if (this.notFoundTarget.checked) {
            this.branchTarget.disabled = false;
            this.branchTarget.hidden = false;
        } else {
            this.branchTarget.disabled = true;
            this.branchTarget.hidden = true;
        }

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
     * Initialize edit controller
     * 
     * Sets up the edit controller for recommendation modification
     * with proper form state and outlet communication.
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
     * Handle recommendation ID target connection
     * 
     * Updates form action URL when recommendation ID connects to ensure
     * proper form submission routing for specific recommendations.
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
     * Handle planToGiveGathering target connection
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
     * Handle givenDate target connection
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
window.Controllers["awards-rec-edit"] = AwardsRecommendationEditForm;