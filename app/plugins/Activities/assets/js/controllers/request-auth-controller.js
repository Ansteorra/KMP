/**
 * Request Authorization Controller - Authorization request with approver selection
 *
 * Targets: activity, approvers, submitBtn, memberId
 * Values: url (String)
 */

import { Controller } from "@hotwired/stimulus"

class ActivitiesRequestAuthorization extends Controller {
    static values = {
        url: String,
    }
    static targets = ["activity", "approvers", "submitBtn", "memberId"]

    /** Fetch approvers from API for selected activity and member. */
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

                if (list.length === 1) {
                    this.approversTarget.value = list[0].value;
                    this.checkReadyToSubmit();
                }
            });
    }

    /** Disable approvers dropdown on initial connection. */
    acConnected() {
        if (this.hasApproversTarget) {
            this.approversTarget.disabled = true;
        }
    }

    /** Return AJAX request headers. */
    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    /** Enable submit button when valid approver is selected. */
    checkReadyToSubmit() {
        let approverValue = this.approversTarget.value;
        let approverNum = parseInt(approverValue);
        if (approverNum > 0) {
            this.submitBtnTarget.disabled = false;
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }

    /** Disable submit button on initial connection. */
    submitBtnTargetConnected() {
        this.submitBtnTarget.disabled = true;
    }

    /** Disable approvers dropdown on initial connection. */
    approversTargetConnected() {
        this.approversTarget.disabled = true;
    }
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["activities-request-auth"] = ActivitiesRequestAuthorization;