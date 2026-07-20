import { Controller } from "@hotwired/stimulus";

/**
 * Awards Recommendation Group Controller
 *
 * Handles the grouping modal and validates member compatibility.
 * Recommendations can be grouped when selected rows share one member or have no member.
 */
class AwardsRecommendationGroupController extends Controller {
    static targets = ["selectedIds", "validationMessage"];

    connect() {
        this.boundHandleGridBulkAction = this.handleGridBulkAction.bind(this);
        this.boundHandleSelectionChanged = this.handleSelectionChanged.bind(this);
        document.addEventListener('grid-view:bulk-action', this.boundHandleGridBulkAction);
        document.addEventListener('grid-view:selection-changed', this.boundHandleSelectionChanged);
    }

    disconnect() {
        document.removeEventListener('grid-view:bulk-action', this.boundHandleGridBulkAction);
        document.removeEventListener('grid-view:selection-changed', this.boundHandleSelectionChanged);
    }

    /**
     * Check if selected recommendations can be grouped (same or null member_id).
     */
    canGroup(checkboxes) {
        if (!checkboxes || checkboxes.length < 2) return false;
        const memberIds = checkboxes
            .map(cb => cb.memberId || '')
            .filter(id => id !== '');
        const unique = new Set(memberIds);
        return unique.size <= 1;
    }

    /**
     * On selection change, enable/disable the Group bulk action button.
     */
    handleSelectionChanged(event) {
        const { ids, checkboxes } = event.detail || {};
        const groupBtn = document.querySelector('[data-bulk-action-key="group-recs"]');
        if (!groupBtn) return;

        if (!ids || ids.length < 2) {
            groupBtn.disabled = true;
            groupBtn.title = 'Select at least 2 recommendations to group';
            return;
        }

        if (!this.canGroup(checkboxes)) {
            groupBtn.disabled = true;
            groupBtn.title = 'Cannot group recommendations for different members';
        } else {
            groupBtn.disabled = false;
            groupBtn.title = 'Group selected recommendations';
        }
    }

    handleGridBulkAction(event) {
        const ids = event.detail?.ids;
        if (!ids || !ids.length) return;

        const container = this.selectedIdsTarget;
        container.innerHTML = '';

        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'recommendation_ids[]';
            input.value = id;
            container.appendChild(input);
        });

        // Update modal validation message
        if (this.hasValidationMessageTarget) {
            const checkboxes = event.detail?.checkboxes || [];
            if (!this.canGroup(checkboxes)) {
                this.validationMessageTarget.className = 'alert alert-danger';
                this.validationMessageTarget.textContent = 'These recommendations cannot be grouped — they are for different members.';
            } else {
                this.validationMessageTarget.className = 'alert alert-info';
                this.validationMessageTarget.textContent =
                    `${ids.length} recommendations will be grouped together. The first selected recommendation will become the group head.`;
            }
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-group"] = AwardsRecommendationGroupController;
