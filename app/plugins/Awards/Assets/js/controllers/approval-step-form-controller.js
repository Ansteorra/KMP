import { Controller } from "@hotwired/stimulus"

class AwardsApprovalStepForm extends Controller {
    static targets = [
        "approverType",
        "sourceGroup",
        "branchMode",
        "branchTypeGroup",
        "thresholdMode",
        "requiredCountGroup",
    ]

    connect() {
        this.sync();
    }

    sync() {
        this.syncSourceGroups();
        this.syncOptionalGroup(
            this.hasBranchModeTarget ? this.branchModeTarget.value === "ancestor_branch_type" : false,
            this.hasBranchTypeGroupTarget ? this.branchTypeGroupTarget : null,
        );
        this.syncOptionalGroup(
            this.hasThresholdModeTarget ? this.thresholdModeTarget.value === "count" : false,
            this.hasRequiredCountGroupTarget ? this.requiredCountGroupTarget : null,
        );
    }

    syncSourceGroups() {
        if (!this.hasApproverTypeTarget) {
            return;
        }

        const selectedType = this.approverTypeTarget.value;
        this.sourceGroupTargets.forEach((group) => {
            this.syncOptionalGroup(group.dataset.approverSourceType === selectedType, group);
        });
    }

    syncOptionalGroup(show, group) {
        if (!group) {
            return;
        }

        group.hidden = !show;
        this.setControlsDisabled(group, !show);
    }

    setControlsDisabled(group, disabled) {
        group.querySelectorAll("input, select, textarea, button").forEach((control) => {
            if (disabled) {
                if (!control.disabled) {
                    control.dataset.awardsApprovalStepFormManagedDisabled = "true";
                    control.disabled = true;
                }

                return;
            }

            if (control.dataset.awardsApprovalStepFormManagedDisabled === "true") {
                control.disabled = false;
                delete control.dataset.awardsApprovalStepFormManagedDisabled;
            }
        });
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-approval-step-form"] = AwardsApprovalStepForm;
