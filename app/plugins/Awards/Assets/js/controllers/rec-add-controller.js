import { Controller } from "@hotwired/stimulus";

/**
 * Awards Recommendation Add Form Controller
 *
 * Handles new recommendation submission with member validation, hierarchical award
 * selection via tabbed interface, and dynamic specialty population.
 *
 * Targets: scaMember, notFound, branch, externalLinks, awardDescriptions, award,
 *          reason, gatherings, specialty
 * Values: publicProfileUrl (String), awardListUrl (String), gatheringsUrl (String)
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

    /** Enable disabled fields before form submission. */
    submit(event) {
        this.notFoundTarget.disabled = false;
        this.scaMemberTarget.disabled = false;
        this.specialtyTarget.disabled = false;
    }

    /** Handle award tab selection, populate specialties, and update gatherings. */
    setAward(event) {
        let awardId = event.target.dataset.awardId;
        this.awardTarget.value = awardId;
        this.populateSpecialties(event);
        this.updateGatherings(awardId);
    }

    /** Fetch gatherings filtered by award and update checkboxes. */
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

    /** Get standard fetch options with JSON headers. */
    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    /** Fetch awards for domain and create tabbed selection interface. */
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
    /** Update specialty dropdown based on selected award's configuration. */
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
        // Also update gatherings when award changes via autocomplete selection
        this.updateGatherings(awardId);
    }

    /** Handle member field change, load profile or show branch field if not found. */
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

    /** Fetch and display member profile external links. */
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

    /** Initialize field state when autocomplete connects. */
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

    /** Initialize form state with disabled fields and empty values. */
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