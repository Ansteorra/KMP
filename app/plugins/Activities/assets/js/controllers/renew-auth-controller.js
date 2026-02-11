import { Controller } from "@hotwired/stimulus"

/**
 * Renew Authorization Controller - Authorization renewal with approver selection
 *
 * Targets: activity, approvers, submitBtn, memberId, id
 * Values: url (String)
 * Outlets: outlet-btn
 */
class ActivitiesRenewAuthorization extends Controller {
    static values = {
        url: String,
    }
    static targets = ["activity", "approvers", "submitBtn", "memberId", "id"]
    static outlets = ["outlet-btn"]

    /** Set authorization and activity IDs from outlet event, then fetch approvers. */
    setId(event) {
        this.idTarget.value = event.detail.id;
        this.activityTarget.value = event.detail.activity;
        this.getApprovers();
    }

    /** Register setId listener when outlet button connects. */
    outletBtnOutletConnected(outlet, element) {
        this._boundSetId = this._boundSetId || this.setId.bind(this);
        outlet.addListener(this._boundSetId);
    }

    /** Remove setId listener when outlet button disconnects. */
    outletBtnOutletDisconnected(outlet) {
        if (this._boundSetId) {
            outlet.removeListener(this._boundSetId);
            this._boundSetId = null;
        }
    }

    /** Fetch approvers for selected activity and member, populate dropdown. */
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

                    if (list.length === 1) {
                        this.approversTarget.value = list[0].value;
                        this.checkReadyToSubmit();
                    }
                });
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
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["activities-renew-auth"] = ActivitiesRenewAuthorization;