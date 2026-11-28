import { BaseGatheringFormController } from "./base-gathering-form-controller";

/**
 * Gathering Form Controller
 *
 * Manages client-side validation and UX improvements for gathering forms.
 * Extends BaseGatheringFormController for shared date validation logic.
 *
 * Features:
 * - Automatically defaults end date to start date when start date changes
 * - Validates that end date is not before start date
 * - Provides real-time feedback to users
 */
class GatheringFormController extends BaseGatheringFormController {
    static targets = [
        ...BaseGatheringFormController.targets,
        "branch",
        "branchSelect",
        "branchAutocomplete",
        "typeAvailability",
    ]

    static values = { typesByBranch: Object }

    connect() {
        super.connect();
        this.syncGatheringTypes();
    }

    activateBranchAutocomplete() {
        this.branchSelectTarget.querySelector('select').disabled = true;
        this.branchSelectTarget.hidden = true;
        this.branchAutocompleteTarget.disabled = false;
        this.branchAutocompleteTarget.hidden = false;
        this.syncGatheringTypes();
    }

    /** Refresh choices when the branch select or autocomplete changes. */
    branchChanged(event) {
        if (this.hasBranchTarget && this.branchTarget.contains(event.target)) {
            this.syncGatheringTypes();
        }
    }

    /** Use server-authorized choices; submission rechecks current permissions. */
    syncGatheringTypes() {
        if (!this.hasTypesByBranchValue || !this.hasBranchTarget || !this.hasGatheringTypeSelectTarget) return;
        const branchId = this.branchTarget.querySelector('[name="branch_id"]:enabled')?.value;
        const select = this.gatheringTypeSelectTarget;
        const selectedId = select.value;
        const choices = this.typesByBranchValue[branchId] || [];
        const placeholder = select.options[0].cloneNode(true);
        select.replaceChildren(placeholder);
        for (const choice of choices) {
            const option = new Option(choice.text, String(choice.value));
            option.dataset.description = choice['data-description'];
            select.add(option);
        }
        select.value = choices.some(choice => String(choice.value) === selectedId) ? selectedId : '';
        this._updateTypeDescription(select);
        if (this.hasTypeAvailabilityTarget) {
            this.typeAvailabilityTarget.textContent = !branchId
                ? 'Select a branch to see the gathering types you can create.'
                : choices.length === 0
                    ? 'No gathering types are available for your permissions in this branch.'
                    : selectedId && !select.value
                        ? 'The previous type is unavailable in this branch. Choose another type.'
                        : 'Only types whose default activities you can assign are available.';
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-form"] = GatheringFormController;
