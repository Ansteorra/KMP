import { Controller } from "@hotwired/stimulus";

/**
 * Awards Bestowal Bulk To-Do Controller
 *
 * Populates the grid "Complete Check" bulk-action modal with the bestowals
 * selected in the grid when the modal opens, so a single named check can be
 * completed across all of them. Server-side eligibility decides which selected
 * bestowals are actually flipped.
 */
class AwardsBestowalBulkTodo extends Controller {
    static targets = ["ids", "summary", "submit"];

    connect() {
        this.boundHandleShow = this.handleShow.bind(this);
        this.element.addEventListener("show.bs.modal", this.boundHandleShow);
    }

    disconnect() {
        this.element.removeEventListener("show.bs.modal", this.boundHandleShow);
    }

    /** Read the current grid selection when the modal opens. */
    handleShow(event) {
        const ids = this.readSelection(event.relatedTarget);
        this.applySelection(ids);
    }

    /**
     * Resolve the selected bestowal ids from the live grid checkboxes, falling
     * back to the serialized selection stashed on the bulk-action button.
     *
     * @param {HTMLElement|null} button
     * @return {string[]}
     */
    readSelection(button) {
        if (!button) {
            return [];
        }

        const grid = button.closest("turbo-frame")
            || button.closest("[data-controller~=\"grid-view\"]")
            || document;
        const checked = Array.from(
            grid.querySelectorAll("[data-grid-view-target~=\"rowCheckbox\"]:checked:not(:disabled)"),
        );
        if (checked.length > 0) {
            return checked.map((cb) => cb.value).filter(Boolean);
        }

        if (button.dataset.bulkActionSelection) {
            try {
                const parsed = JSON.parse(button.dataset.bulkActionSelection);
                if (Array.isArray(parsed.ids)) {
                    return parsed.ids.filter(Boolean);
                }
            } catch (error) {
                // Ignore malformed selection payloads.
            }
        }

        return [];
    }

    /** @param {string[]} ids */
    applySelection(ids) {
        const unique = [...new Set(ids.map(String))];

        if (this.hasIdsTarget) {
            this.idsTarget.value = unique.join(",");
        }
        if (this.hasSummaryTarget) {
            this.summaryTarget.textContent = unique.length === 1
                ? "This check will be completed on 1 selected bestowal where you are the assigned doer."
                : `This check will be completed on ${unique.length} selected bestowals where you are the assigned doer.`;
        }
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = unique.length === 0;
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-bestowal-bulk-todo"] = AwardsBestowalBulkTodo;

export default AwardsBestowalBulkTodo;
