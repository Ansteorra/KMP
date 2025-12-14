import { Controller } from "@hotwired/stimulus";

/**
 * Approve and Assign Authorization Controller - AJAX-based approver selection
 *
 * Targets: approvers, submitBtn, id
 * Values: url (String), approvalId (Number)
 * Outlets: outlet-btn
 */
class ActivitiesApproveAndAssignAuthorization extends Controller {
    static values = {
        url: String,
        approvalId: Number
    }
    static targets = ["approvers", "submitBtn", "id"]
    static outlets = ["outlet-btn"]

    /** Load approvers on connect if approvalId is pre-set. */
    connect() {
        if (this.hasApprovalIdValue && this.approvalIdValue > 0) {
            this.idTarget.value = this.approvalIdValue;
            this.getApprovers();
        }
    }

    /** Set activity ID from outlet event and fetch approvers. */
    setId(event) {
        this.idTarget.value = event.detail.id;
        this.getApprovers();
    }

    /** Register setId listener when outlet button connects. */
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }

    /** Remove setId listener when outlet button disconnects. */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }

    /** Fetch approvers from server and populate dropdown. */
    getApprovers() {
        if (this.hasApproversTarget) {
            this.approversTarget.value = "";
            let activityId = this.idTarget.value;
            let url = this.urlValue + "/" + activityId;
            fetch(url, this.optionsForFetch())
                .then(response => response.json())
                .then(data => {
                    const emptyOption = this.approversTarget.options[0];
                    this.approversTarget.innerHTML = '';
                    if (emptyOption) {
                        this.approversTarget.appendChild(emptyOption);
                    }

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
window.Controllers["activities-approve-and-assign-auth"] = ActivitiesApproveAndAssignAuthorization;