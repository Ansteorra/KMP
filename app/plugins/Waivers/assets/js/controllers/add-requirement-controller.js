/**
 * Waivers Add Requirement Controller
 *
 * Manages waiver requirement form with dynamic waiver type discovery
 * for gathering activities. Fetches available types excluding already assigned.
 *
 * Targets: waiverType, submitBtn, activityId
 * Values: url (String)
 */

import { Controller } from "@hotwired/stimulus";

class WaiversAddRequirement extends Controller {
    static values = {
        url: String,
    }
    static targets = ["waiverType", "submitBtn", "activityId"]

    /** Fetch available waiver types for activity and populate dropdown. */
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

    /** Get standard fetch options with JSON headers. */
    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    /** Enable submit button when valid waiver type selected. */
    checkReadyToSubmit() {
        let waiverTypeValue = this.waiverTypeTarget.value;
        let waiverTypeNum = parseInt(waiverTypeValue);

        if (waiverTypeNum > 0) {
            this.submitBtnTarget.disabled = false;
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }

    /** Disable submit button on connect. */
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
