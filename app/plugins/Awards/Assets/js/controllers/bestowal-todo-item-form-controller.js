import { Controller } from "@hotwired/stimulus"

class AwardsBestowalTodoItemForm extends Controller {
    static targets = [
        "assigneeType",
        "sourceGroup",
        "branchMode",
        "branchTypeGroup",
        "requiredField",
        "requiredFieldOptionsGroup",
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
            this.hasRequiredFieldTarget ? this.requiredFieldTarget.value !== "" : false,
            this.hasRequiredFieldOptionsGroupTarget ? this.requiredFieldOptionsGroupTarget : null,
        );
    }

    syncSourceGroups() {
        if (!this.hasAssigneeTypeTarget) {
            return;
        }

        const selectedType = this.assigneeTypeTarget.value;
        this.sourceGroupTargets.forEach((group) => {
            this.syncOptionalGroup(group.dataset.assigneeSourceType === selectedType, group);
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
                    control.dataset.awardsBestowalTodoItemFormManagedDisabled = "true";
                    control.disabled = true;
                }

                return;
            }

            if (control.dataset.awardsBestowalTodoItemFormManagedDisabled === "true") {
                control.disabled = false;
                delete control.dataset.awardsBestowalTodoItemFormManagedDisabled;
            }
        });
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-bestowal-todo-item-form"] = AwardsBestowalTodoItemForm;
