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
    static targets = [
        "ids",
        "summary",
        "submit",
        "checkSelect",
        "gatheringSection",
        "gatheringControl",
        "gatheringHelp",
        "includePast",
    ];
    static values = {
        lookupUrl: String,
    };

    connect() {
        this.boundHandleShow = this.handleShow.bind(this);
        this.boundCheckChange = this.handleCheckChange.bind(this);
        this.boundGatheringChange = this.updateSubmitState.bind(this);
        this.element.addEventListener("show.bs.modal", this.boundHandleShow);
        if (this.hasCheckSelectTarget) {
            this.checkSelectTarget.addEventListener("change", this.boundCheckChange);
        }
        if (this.hasGatheringControlTarget) {
            this.gatheringControlTarget.addEventListener("autocomplete.change", this.boundGatheringChange);
            this.gatheringControlTarget.addEventListener("change", this.boundGatheringChange);
        }
    }

    disconnect() {
        this.element.removeEventListener("show.bs.modal", this.boundHandleShow);
        if (this.hasCheckSelectTarget) {
            this.checkSelectTarget.removeEventListener("change", this.boundCheckChange);
        }
        if (this.hasGatheringControlTarget) {
            this.gatheringControlTarget.removeEventListener("autocomplete.change", this.boundGatheringChange);
            this.gatheringControlTarget.removeEventListener("change", this.boundGatheringChange);
        }
    }

    /** Read the current grid selection when the modal opens. */
    handleShow(event) {
        const selection = this.readSelectionDetails(event.relatedTarget);
        this.applySelection(selection.ids, selection.rows);
    }

    /**
     * Resolve the selected bestowal ids from the live grid checkboxes, falling
     * back to the serialized selection stashed on the bulk-action button.
     *
     * @param {HTMLElement|null} button
     * @return {string[]}
     */
    readSelection(button) {
        return this.readSelectionDetails(button).ids;
    }

    /**
     * Resolve the selected bestowal ids and row metadata from the live grid
     * checkboxes, falling back to the serialized selection payload.
     *
     * @param {HTMLElement|null} button
     * @return {{ids: string[], rows: Array<{id: string, options: Array<object>}>}}
     */
    readSelectionDetails(button) {
        if (!button) {
            return { ids: [], rows: [] };
        }

        const grid = button.closest("turbo-frame")
            || button.closest("[data-controller~=\"grid-view\"]")
            || document;
        const checked = Array.from(
            grid.querySelectorAll("[data-grid-view-target~=\"rowCheckbox\"]:checked:not(:disabled)"),
        );
        if (checked.length > 0) {
            return this.selectionFromCheckboxes(checked);
        }

        if (button.dataset.bulkActionSelection) {
            try {
                const parsed = JSON.parse(button.dataset.bulkActionSelection);
                if (Array.isArray(parsed.ids)) {
                    const rows = Array.isArray(parsed.checkboxes)
                        ? parsed.checkboxes.map((row) => this.selectionRowFromDataset(row))
                        : [];
                    return {
                        ids: parsed.ids.filter(Boolean).map(String),
                        rows,
                    };
                }
            } catch (error) {
                // Ignore malformed selection payloads.
            }
        }

        return { ids: [], rows: [] };
    }

    /** @param {HTMLInputElement[]} checkboxes */
    selectionFromCheckboxes(checkboxes) {
        const rows = checkboxes.map((checkbox) => this.selectionRowFromDataset({
            id: checkbox.value,
            bulkTodoOptions: checkbox.dataset.bulkTodoOptions || "[]",
        }));

        return {
            ids: rows.map((row) => row.id).filter(Boolean),
            rows,
        };
    }

    /** @param {{id?: string, bulkTodoOptions?: string}} row */
    selectionRowFromDataset(row) {
        return {
            id: String(row.id || ""),
            options: this.parseOptions(row.bulkTodoOptions || "[]"),
        };
    }

    /** @param {string} raw */
    parseOptions(raw) {
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                return parsed.filter((option) => option && typeof option.key === "string");
            }
        } catch (error) {
            // Ignore malformed row option payloads.
        }

        return [];
    }

    /**
     * @param {string[]} ids
     * @param {Array<{id: string, options: Array<object>}>} rows
     */
    applySelection(ids, rows = []) {
        const unique = [...new Set(ids.map(String))];
        this.currentRows = rows;
        this.currentOptions = this.buildOptions(rows);
        if (this.hasIncludePastTarget) {
            this.includePastTarget.checked = false;
        }

        if (this.hasIdsTarget) {
            this.idsTarget.value = unique.join(",");
        }
        if (this.hasSummaryTarget) {
            this.summaryTarget.textContent = this.summaryText(unique.length, this.currentOptions.length);
        }
        this.populateCheckOptions();
        this.resetGathering();
        this.toggleGatheringSection(false);
        this.updateSubmitState();
    }

    summaryText(selectionCount, optionCount) {
        if (selectionCount === 0) {
            return "Select bestowals in the grid to complete a check across them.";
        }
        if (optionCount === 0) {
            return selectionCount === 1
                ? "No open checks assigned to you are available on the selected bestowal."
                : "No open checks assigned to you are available on the selected bestowals.";
        }

        return selectionCount === 1
            ? "Choose an open check assigned to you for 1 selected bestowal."
            : `Choose an open check assigned to you for ${selectionCount} selected bestowals.`;
    }

    /** @param {Array<{id: string, options: Array<object>}>} rows */
    buildOptions(rows) {
        const options = new Map();
        rows.forEach((row) => {
            row.options.forEach((option) => {
                if (!options.has(option.key)) {
                    options.set(option.key, {
                        key: option.key,
                        label: option.label || option.key,
                        requiresGathering: option.requiresGathering === true,
                        gatheringHelp: option.gatheringHelp || "",
                        applicableIds: new Set(),
                    });
                }
                const existing = options.get(option.key);
                existing.applicableIds.add(row.id);
                existing.requiresGathering = existing.requiresGathering || option.requiresGathering === true;
                if (!existing.gatheringHelp && option.gatheringHelp) {
                    existing.gatheringHelp = option.gatheringHelp;
                }
            });
        });

        return Array.from(options.values())
            .map((option) => ({
                ...option,
                applicableIds: Array.from(option.applicableIds),
            }))
            .sort((a, b) => a.label.localeCompare(b.label));
    }

    populateCheckOptions() {
        if (!this.hasCheckSelectTarget) {
            return;
        }

        this.checkSelectTarget.replaceChildren();
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = this.currentOptions.length > 0
            ? "— Select a check —"
            : "No assigned open checks";
        this.checkSelectTarget.appendChild(placeholder);

        this.currentOptions.forEach((option) => {
            const optionElement = document.createElement("option");
            optionElement.value = option.key;
            optionElement.textContent = option.applicableIds.length > 1
                ? `${option.label} (${option.applicableIds.length})`
                : option.label;
            optionElement.dataset.requiresGathering = option.requiresGathering ? "1" : "0";
            this.checkSelectTarget.appendChild(optionElement);
        });

        this.checkSelectTarget.disabled = this.currentOptions.length === 0;
    }

    handleCheckChange() {
        this.resetGathering();
        const option = this.selectedOption();
        this.toggleGatheringSection(option?.requiresGathering === true);
        if (option?.requiresGathering === true) {
            this.updateLookupUrl(option.applicableIds);
            if (this.hasGatheringHelpTarget && option.gatheringHelp) {
                this.gatheringHelpTarget.textContent = option.gatheringHelp;
            }
        }
        this.updateSubmitState();
    }

    handleIncludePastChange() {
        const option = this.selectedOption();
        if (option?.requiresGathering === true) {
            this.resetGathering();
            this.updateLookupUrl(option.applicableIds);
        }
    }

    selectedOption() {
        if (!this.hasCheckSelectTarget || !this.checkSelectTarget.value) {
            return null;
        }

        return this.currentOptions.find((option) => option.key === this.checkSelectTarget.value) || null;
    }

    toggleGatheringSection(show) {
        if (this.hasGatheringSectionTarget) {
            this.gatheringSectionTarget.hidden = !show;
        }
        if (!this.hasGatheringControlTarget) {
            return;
        }

        const input = this.gatheringControlTarget.querySelector("[data-ac-target=\"input\"]");
        const clearBtn = this.gatheringControlTarget.querySelector("[data-ac-target=\"clearBtn\"]");
        if (input) {
            input.disabled = !show;
            input.required = show;
        }
        if (clearBtn) {
            clearBtn.disabled = true;
        }
    }

    /** @param {string[]} ids */
    updateLookupUrl(ids) {
        if (!this.hasGatheringControlTarget || !this.hasLookupUrlValue) {
            return;
        }

        const url = new URL(this.lookupUrlValue, window.location.href);
        url.searchParams.set("bestowal_ids", [...new Set(ids.map(String))].join(","));
        if (this.hasIncludePastTarget && this.includePastTarget.checked) {
            url.searchParams.set("include_past", "1");
        } else {
            url.searchParams.delete("include_past");
        }
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

        const hasIds = this.hasIdsTarget && this.idsTarget.value !== "";
        const selectedOption = this.selectedOption();
        const gatheringId = this.hasGatheringControlTarget
            ? this.gatheringControlTarget.querySelector("[data-ac-target=\"hidden\"]")?.value || ""
            : "";
        this.submitTarget.disabled = !hasIds
            || selectedOption === null
            || (selectedOption.requiresGathering === true && gatheringId === "");
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-bestowal-bulk-todo"] = AwardsBestowalBulkTodo;

export default AwardsBestowalBulkTodo;
