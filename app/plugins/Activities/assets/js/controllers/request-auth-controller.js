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
        this.setComboBoxValue(this.approversTarget, "");
        let activityId = this.getComboBoxValue(this.activityTarget);
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
                this.setComboBoxOptions(this.approversTarget, list);
                this.submitBtnTarget.disabled = true;
                this.setComboBoxDisabled(this.approversTarget, false);

                if (list.length === 1) {
                    this.setComboBoxValue(this.approversTarget, list[0].value);
                    this.checkReadyToSubmit();
                }
            });
    }

    /** Disable approvers dropdown on initial connection. */
    acConnected() {
        if (this.hasApproversTarget) {
            this.setComboBoxDisabled(this.approversTarget, true);
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
    checkReadyToSubmit(event) {
        let approverValue = event?.detail?.value ?? this.getComboBoxValue(this.approversTarget);
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
        this.setComboBoxDisabled(this.approversTarget, true);
    }

    getComboBoxController(element) {
        return window.Stimulus?.getControllerForElementAndIdentifier(element, "ac");
    }

    getComboBoxValue(element) {
        let controller = this.getComboBoxController(element);

        return controller ? controller.value : element.value;
    }

    setComboBoxValue(element, value) {
        let controller = this.getComboBoxController(element);
        if (controller) {
            controller.value = value;
        } else {
            element.value = value;
        }
    }

    setComboBoxOptions(element, options) {
        let controller = this.getComboBoxController(element);
        if (controller) {
            controller.options = options;
        } else {
            element.options = options;
        }
    }

    setComboBoxDisabled(element, disabled) {
        let controller = this.getComboBoxController(element);
        if (controller) {
            controller.disabled = disabled;
        } else {
            element.disabled = disabled;
        }
    }
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["activities-request-auth"] = ActivitiesRequestAuthorization;