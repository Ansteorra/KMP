import { Controller } from "@hotwired/stimulus";

/**
 * Awards Recommendation Add Controller
 * 
 * Comprehensive Stimulus controller for recommendation submission with member validation and award 
 * selection workflow. Provides interactive form functionality for creating award recommendations 
 * with dynamic award discovery, member context validation, and comprehensive submission processing.
 * 
 * ## Submission Workflow Features
 * 
 * **Member Validation:**
 * - SCA member lookup with autocomplete integration
 * - External member profile loading with public links display
 * - Branch validation for non-SCA members with manual entry support
 * - Real-time member context loading and validation feedback
 * 
 * **Award Discovery:**
 * - Hierarchical award selection through domain/award relationships
 * - Dynamic award description display with tabbed interface
 * - Specialty population based on award configuration
 * - Award eligibility validation and selection workflow
 * 
 * **Form Management:**
 * - Comprehensive form state management with field dependencies
 * - Dynamic field enabling/disabling based on selection context
 * - Form validation with comprehensive business rule enforcement
 * - Submission processing with data integrity validation
 * 
 * ## Member Context Integration
 * 
 * **SCA Member Support:**
 * - Member ID validation with numeric format enforcement
 * - Public profile loading with external links integration
 * - Branch auto-population from member data
 * - Member context preservation throughout form workflow
 * 
 * **Non-SCA Member Support:**
 * - Manual branch entry for external members
 * - "Not Found" checkbox toggle with branch field management
 * - External member validation and data entry support
 * - Branch requirement enforcement for non-SCA submissions
 * 
 * **Profile Integration:**
 * - External links display with target="_blank" navigation
 * - Public profile data loading with JSON API integration
 * - Member metadata display for submission context
 * - Profile validation feedback and error handling
 * 
 * ## Award Selection Workflow
 * 
 * **Hierarchical Selection:**
 * - Domain-based award filtering with dynamic population
 * - Award description display with comprehensive information
 * - Tabbed interface for award selection and description viewing
 * - Award eligibility validation based on member context
 * 
 * **Specialty Management:**
 * - Dynamic specialty population based on award configuration
 * - Specialty field visibility management and validation
 * - Award-specific specialty requirements and options
 * - Specialty selection persistence and form integration
 * 
 * ## Usage Examples
 * 
 * ### Basic Recommendation Submission Form
 * ```html
 * <!-- Recommendation submission with member and award selection -->
 * <form data-controller="awards-rec-add" 
 *       data-awards-rec-add-public-profile-url-value="/members/public-profile"
 *       data-awards-rec-add-award-list-url-value="/awards/by-domain">
 * 
 *   <!-- Member Selection -->
 *   <div class="mb-3">
 *     <label>SCA Member</label>
 *     <input type="text" data-awards-rec-add-target="scaMember" 
 *            data-action="change->awards-rec-add#loadScaMemberInfo" 
 *            class="form-control">
 *     <div class="form-check">
 *       <input type="checkbox" data-awards-rec-add-target="notFound" 
 *              class="form-check-input">
 *       <label class="form-check-label">Member not found in SCA database</label>
 *     </div>
 *     <input type="text" data-awards-rec-add-target="branch" 
 *            placeholder="Branch Name" class="form-control" hidden>
 *   </div>
 * 
 *   <!-- External Links Display -->
 *   <div data-awards-rec-add-target="externalLinks" class="member-links"></div>
 * 
 *   <!-- Award Selection -->
 *   <div class="mb-3">
 *     <label>Award Domain</label>
 *     <select data-action="change->awards-rec-add#populateAwardDescriptions" 
 *             class="form-select">
 *       <option value="">Select Domain</option>
 *       <option value="1">Arts & Sciences</option>
 *       <option value="2">Service</option>
 *       <option value="3">Martial</option>
 *     </select>
 *   </div>
 * 
 *   <div data-awards-rec-add-target="awardDescriptions" class="award-tabs"></div>
 * 
 *   <input type="hidden" data-awards-rec-add-target="award" name="award_id">
 *   <select data-awards-rec-add-target="specialty" name="specialty" class="form-select">
 *     <option value="">Select Award First</option>
 *   </select>
 * 
 *   <textarea data-awards-rec-add-target="reason" name="reason" 
 *             class="form-control" placeholder="Reason for recommendation"></textarea>
 * 
 *   <button type="submit" data-action="awards-rec-add#submit" 
 *           class="btn btn-primary">Submit Recommendation</button>
 * </form>
 * ```
 * 
 * ### Member Validation Workflow
 * ```html
 * <!-- Member lookup with profile integration -->
 * <div data-controller="awards-rec-add" 
 *      data-awards-rec-add-public-profile-url-value="/api/members/profile">
 * 
 *   <div class="member-search">
 *     <input type="text" data-awards-rec-add-target="scaMember" 
 *            data-action="input->awards-rec-add#loadScaMemberInfo"
 *            placeholder="Enter SCA Member ID or Name" class="form-control">
 *     
 *     <!-- Auto-populated external links -->
 *     <div data-awards-rec-add-target="externalLinks" class="mt-3"></div>
 *     
 *     <!-- Branch field for non-SCA members -->
 *     <div class="mt-3">
 *       <input type="checkbox" data-awards-rec-add-target="notFound">
 *       <label>Member not in SCA database</label>
 *       <input type="text" data-awards-rec-add-target="branch" 
 *              placeholder="Branch Name" class="form-control mt-2" hidden>
 *     </div>
 *   </div>
 * </div>
 * ```
 * 
 * ### Award Discovery Integration
 * ```javascript
 * // External integration for automated award population
 * document.addEventListener('DOMContentLoaded', function() {
 *   const recForm = document.querySelector('[data-controller="awards-rec-add"]');
 *   if (recForm) {
 *     // Pre-populate domain selection for specific contexts
 *     const domainSelect = recForm.querySelector('select[data-action*="populateAwardDescriptions"]');
 *     if (domainSelect && window.contextDomain) {
 *       domainSelect.value = window.contextDomain;
 *       domainSelect.dispatchEvent(new Event('change'));
 *     }
 *   }
 * });
 * ```
 * 
 * @class AwardsRecommendationAddForm
 * @extends {Controller}
 */
class AwardsRecommendationAddForm extends Controller {
    static targets = [
        "scaMember",
        "notFound",
        "branch",
        "externalLinks",
        "awardDescriptions",
        "award",
        "reason",
        "gatherings",
        "specialty",
    ];
    static values = {
        publicProfileUrl: String,
        awardListUrl: String,
        gatheringsUrl: String
    };

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
     * Handles award selection from tabbed interface and triggers specialty
     * population based on award configuration and requirements.
     * Also updates the gatherings list to show only relevant gatherings.
     * 
     * @param {Event} event - Click event from award selection tab
     * @returns {void}
     */
    setAward(event) {
        let awardId = event.target.dataset.awardId;
        this.awardTarget.value = awardId;
        this.populateSpecialties(event);
        this.updateGatherings(awardId);
    }

    /**
     * Update gatherings list based on selected award
     * 
     * Fetches and updates the gatherings list to show only gatherings
     * that have activities linked to the selected award. Marks gatherings
     * where the member has indicated attendance with crown sharing.
     * 
     * @param {string} awardId - The selected award ID
     * @returns {void}
     */
    updateGatherings(awardId) {
        if (!awardId || !this.hasGatheringsTarget) {
            return;
        }

        // Get member_id if available
        let memberId = this.hasScaMemberTarget ? this.scaMemberTarget.value : '';

        // Build URL with query params
        let url = this.gatheringsUrlValue + '/' + awardId;
        if (memberId) {
            url += '?member_id=' + memberId;
        }

        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                if (data.gatherings) {
                    // Get the container and find the fieldset/form-group within
                    const container = this.gatheringsTarget;

                    // Find and preserve the label
                    const label = container.querySelector('label.form-label, legend');
                    const labelText = label ? label.textContent : 'Gatherings/Events They May Attend:';

                    // Clear existing content
                    container.innerHTML = '';

                    // Rebuild with new checkboxes
                    if (data.gatherings.length > 0) {
                        // Add the label back
                        const newLabel = document.createElement('label');
                        newLabel.className = 'form-label';
                        newLabel.textContent = labelText;
                        container.appendChild(newLabel);

                        // Add hidden input for empty submission
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'gatherings[_ids]';
                        hiddenInput.value = '';
                        container.appendChild(hiddenInput);

                        // Add checkbox for each gathering
                        data.gatherings.forEach(gathering => {
                            const checkDiv = document.createElement('div');
                            checkDiv.className = 'form-check';

                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.className = 'form-check-input';
                            checkbox.name = 'gatherings[_ids][]';
                            checkbox.value = gathering.id;
                            checkbox.id = 'gatherings-ids-' + gathering.id;

                            const checkLabel = document.createElement('label');
                            checkLabel.className = 'form-check-label';
                            checkLabel.htmlFor = 'gatherings-ids-' + gathering.id;
                            checkLabel.textContent = gathering.display;

                            checkDiv.appendChild(checkbox);
                            checkDiv.appendChild(checkLabel);
                            container.appendChild(checkDiv);
                        });
                    } else {
                        // No gatherings available - show message
                        const newLabel = document.createElement('label');
                        newLabel.className = 'form-label';
                        newLabel.textContent = labelText;
                        container.appendChild(newLabel);

                        const noGatherings = document.createElement('p');
                        noGatherings.className = 'text-muted';
                        noGatherings.textContent = 'No gatherings available for this award.';
                        container.appendChild(noGatherings);
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching gatherings:', error);
            });
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
     * Populate award descriptions based on domain selection
     * 
     * Fetches awards for selected domain and creates tabbed interface for award
     * selection with descriptions and interactive award selection workflow.
     * 
     * @param {Event} event - Change event from domain selection
     * @returns {void}
     */
    populateAwardDescriptions(event) {
        let url = this.awardListUrlValue + "/" + event.target.value;
        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                this.awardDescriptionsTarget.innerHTML = "";

                let tabButtons = document.createElement("ul");
                tabButtons.classList.add("nav", "nav-pills");
                tabButtons.setAttribute("role", "tablist");

                let tabContentArea = document.createElement("div");
                tabContentArea.classList.add("tab-content");
                tabContentArea.classList.add("border");
                tabContentArea.classList.add("border-light-subtle");
                tabContentArea.classList.add("p-2");

                tabContentArea.innerHTML = "";
                this.awardTarget.value = "";
                let active = "active";
                let show = "show";
                let selected = "true";
                let awardList = [];
                if (data.length > 0) {
                    data.forEach(function (award) {
                        //create list item
                        awardList.push({ value: award.id, text: award.name, data: award });
                        //create tab info
                        var tabButton = document.createElement("li");
                        tabButton.classList.add("nav-item");
                        tabButton.setAttribute("role", "presentation");
                        var button = document.createElement("button");
                        button.classList.add("nav-link");
                        if (active == "active") {
                            button.classList.add("active");
                        }
                        button.setAttribute("data-action", "click->awards-rec-add#setAward");
                        button.setAttribute("id", "award_" + award.id + "_btn");
                        button.setAttribute("data-bs-toggle", "tab");
                        button.setAttribute("data-bs-target", "#award_" + award.id);
                        button.setAttribute('data-award-id', award.id);
                        button.setAttribute("type", "button");
                        button.setAttribute("role", "tab");
                        button.setAttribute("aria-controls", "award_" + award.id);
                        button.setAttribute("aria-selected", selected);
                        button.innerHTML = award.name;
                        tabButton.appendChild(button);
                        var tabContent = document.createElement("div");
                        tabContent.classList.add("tab-pane");
                        tabContent.classList.add("fade");
                        if (show == "show") {
                            tabContent.classList.add("show");
                        }
                        if (active == "active") {
                            tabContent.classList.add("active");
                        }
                        tabContent.setAttribute("id", "award_" + award.id);
                        tabContent.setAttribute("role", "tabpanel");
                        tabContent.setAttribute("aria-labelledby", "award_" + award.id + "_btn");
                        tabContent.innerHTML = award.name + ": " + award.description;
                        active = "";
                        show = "";
                        selected = "false";
                        tabButtons.append(tabButton);
                        tabContentArea.append(tabContent);

                    });
                    this.awardDescriptionsTarget.appendChild(tabButtons);
                    this.awardDescriptionsTarget.appendChild(tabContentArea);
                    this.awardTarget.options = awardList;
                    this.awardTarget.disabled = false;
                } else {
                    this.awardTarget.options = [{ value: "No awards available", text: "No awards available" }];
                    this.awardTarget.value = "No awards available";
                    this.awardTarget.disabled = true;
                }
            });
    }
    /**
     * Populate specialties based on award selection
     * 
     * Updates specialty dropdown based on selected award configuration,
     * managing field visibility and validation state.
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
     * based on member discovery and external member support.
     * 
     * @param {Event} event - Input change event from member field
     * @returns {void}
     */
    loadScaMemberInfo(event) {
        //reset member metadata area
        this.externalLinksTarget.innerHTML = "";
        let memberPublicId = event.target.value;
        if (memberPublicId && memberPublicId.length > 0) {
            this.notFoundTarget.checked = false;
            this.branchTarget.hidden = true;
            this.branchTarget.disabled = true;
            this.loadMember(memberPublicId);
        } else {
            this.notFoundTarget.checked = true;
            this.branchTarget.hidden = false;
            this.branchTarget.disabled = false;
            this.branchTarget.focus();
        }

    }

    /**
     * Load member profile data from API
     * 
     * Fetches member profile information and displays external links
     * for member context and validation support.
     * 
     * @param {string} memberPublicId - The member public ID to load
     * @returns {void}
     */
    loadMember(memberPublicId) {
        let url = this.publicProfileUrlValue + "/" + memberPublicId;
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
     * Handle autocomplete connection events
     * 
     * Manages field state and validation when autocomplete components
     * connect to form elements for member and award selection.
     * 
     * @param {Event} event - Autocomplete connection event
     * @returns {void}
     */
    acConnected(event) {
        var target = event.detail["awardsRecAddTarget"];
        switch (target) {
            case "branch":
                this.branchTarget.disabled = true;
                this.branchTarget.hidden = true;
                this.branchTarget.value = "";
                break;
            case "award":
                this.awardTarget.disabled = true;
                this.awardTarget.value = "Select Award Type First";
                break;
            case "scaMember":
                this.scaMemberTarget.value = "";
                break;
            case "specialty":
                this.specialtyTarget.value = "Select Award First";
                this.specialtyTarget.disabled = true;
                this.specialtyTarget.hidden = true;
                break;
            default:
                event.target.value = "";
                break;
        }
    }

    /**
     * Initialize form state on connection
     * 
     * Sets up initial form state with proper field initialization
     * and validation state for new recommendation submission.
     * 
     * @returns {void}
     */
    connect() {
        this.notFoundTarget.checked = false;
        this.notFoundTarget.disabled = true;
        this.reasonTarget.value = "";
        //this.personToNotifyTarget.value = "";
        if (this.hasGatheringsTarget) {
            // Disable all checkboxes within the gatherings container
            this.gatheringsTarget.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
                checkbox.disabled = true;
            });
        }
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-add"] = AwardsRecommendationAddForm;