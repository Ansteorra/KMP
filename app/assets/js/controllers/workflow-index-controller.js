import { Controller } from "@hotwired/stimulus";

/**
 * WorkflowIndexController
 *
 * Handles search/filter and active toggle on the workflow definitions index.
 */
class WorkflowIndexController extends Controller {
    static targets = ["search", "body"]

    static values = {
        toggleUrl: String,
        csrf: String,
    }

    filter() {
        const query = this.searchTarget.value.toLowerCase().trim();
        this.bodyTarget.querySelectorAll("tr[data-search-text]").forEach((row) => {
            const text = row.dataset.searchText || "";
            row.style.display = text.includes(query) ? "" : "none";
        });
    }

    async toggleActive(event) {
        const toggle = event.currentTarget;
        const id = toggle.dataset.workflowId;
        const url = this.toggleUrlValue.replace("__id__", id);
        try {
            const resp = await fetch(url, {
                method: "POST",
                headers: {
                    "X-CSRF-Token": this.csrfValue,
                    Accept: "application/json",
                },
            });
            if (!resp.ok) {
                toggle.checked = !toggle.checked;
                alert("Failed to update status.");
            }
        } catch (e) {
            toggle.checked = !toggle.checked;
            alert("Error updating status.");
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["workflow-index"] = WorkflowIndexController;
