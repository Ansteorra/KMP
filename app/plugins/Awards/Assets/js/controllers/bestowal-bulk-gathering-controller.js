import { Controller } from "@hotwired/stimulus";

class AwardsBestowalBulkGathering extends Controller {
    static targets = ["ids", "summary", "submit", "gatheringControl"];
    static values = {
        lookupUrl: String,
    };

    connect() {
        this.boundHandleShow = this.handleShow.bind(this);
        this.boundGatheringChange = this.updateSubmitState.bind(this);
        this.element.addEventListener("show.bs.modal", this.boundHandleShow);
        if (this.hasGatheringControlTarget) {
            this.gatheringControlTarget.addEventListener("autocomplete.change", this.boundGatheringChange);
            this.gatheringControlTarget.addEventListener("change", this.boundGatheringChange);
        }
    }

    disconnect() {
        this.element.removeEventListener("show.bs.modal", this.boundHandleShow);
        if (this.hasGatheringControlTarget) {
            this.gatheringControlTarget.removeEventListener("autocomplete.change", this.boundGatheringChange);
            this.gatheringControlTarget.removeEventListener("change", this.boundGatheringChange);
        }
    }

    handleShow(event) {
        const ids = this.readSelection(event.relatedTarget);
        this.applySelection(ids);
        this.resetGathering();
        this.updateLookupUrl(ids);
        this.updateSubmitState();
    }

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

    applySelection(ids) {
        const unique = [...new Set(ids.map(String))];
        if (this.hasIdsTarget) {
            this.idsTarget.value = unique.join(",");
        }
        if (this.hasSummaryTarget) {
            this.summaryTarget.textContent = unique.length === 1
                ? "A gathering will be assigned to 1 selected bestowal."
                : `A gathering will be assigned to ${unique.length} selected bestowals.`;
        }
    }

    updateLookupUrl(ids) {
        if (!this.hasGatheringControlTarget || !this.hasLookupUrlValue) {
            return;
        }

        const url = new URL(this.lookupUrlValue, window.location.href);
        url.searchParams.set("bestowal_ids", [...new Set(ids.map(String))].join(","));
        this.gatheringControlTarget.dataset.acUrlValue = url.toString();
    }

    resetGathering() {
        if (!this.hasGatheringControlTarget) {
            return;
        }

        const input = this.gatheringControlTarget.querySelector("[data-ac-target=\"input\"]");
        const hidden = this.gatheringControlTarget.querySelector("[data-ac-target=\"hidden\"]");
        const hiddenText = this.gatheringControlTarget.querySelector("[data-ac-target=\"hiddenText\"]");
        const clearBtn = this.gatheringControlTarget.querySelector("[data-ac-target=\"clearBtn\"]");
        if (input) {
            input.value = "";
            input.disabled = false;
            input.removeAttribute("aria-invalid");
        }
        if (hidden) {
            hidden.value = "";
        }
        if (hiddenText) {
            hiddenText.value = "";
        }
        if (clearBtn) {
            clearBtn.disabled = true;
        }
    }

    updateSubmitState() {
        if (!this.hasSubmitTarget) {
            return;
        }
        const ids = this.hasIdsTarget ? this.idsTarget.value : "";
        const gatheringId = this.hasGatheringControlTarget
            ? this.gatheringControlTarget.querySelector("[data-ac-target=\"hidden\"]")?.value || ""
            : "";
        this.submitTarget.disabled = ids === "" || gatheringId === "";
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-bestowal-bulk-gathering"] = AwardsBestowalBulkGathering;

export default AwardsBestowalBulkGathering;
