/**
 * Office Form Controller - Manages deputy/reporting structure toggle
 *
 * Targets: reportsTo, reportsToBlock, deputyTo, deputyToBlock, isDeputy
 */

import { Controller } from "@hotwired/stimulus"

class OfficeFormController extends Controller {
    static targets = [
        "reportsTo",
        "reportsToBlock",
        "deputyTo",
        "deputyToBlock",
        "isDeputy",
    ];

    /** Toggle between deputy and reports-to fields based on isDeputy checkbox. */
    toggleIsDeputy() {
        if (this.isDeputyTarget.checked) {
            this.deputyToBlockTarget.hidden = false;
            this.setHierarchyControlDisabled(this.deputyToTarget, false);
            this.reportsToBlockTarget.hidden = true;
            this.setHierarchyControlDisabled(this.reportsToTarget, true);
        } else {
            this.deputyToBlockTarget.hidden = true;
            this.clearHierarchyControl(this.deputyToTarget);
            this.setHierarchyControlDisabled(this.deputyToTarget, true);
            this.reportsToBlockTarget.hidden = false;
            this.setHierarchyControlDisabled(this.reportsToTarget, false);
        }
    }

    /** Return the shared autocomplete controller for a hierarchy control. */
    getAutocompleteController(target) {
        const getController = window.Stimulus?.getControllerForElementAndIdentifier;

        return typeof getController === "function"
            ? getController.call(window.Stimulus, target, "ac")
            : null;
    }

    /** Enable or disable a hierarchy autocomplete, including pre-connect fallback fields. */
    setHierarchyControlDisabled(target, disabled) {
        const autocomplete = this.getAutocompleteController(target);
        if (autocomplete) {
            autocomplete.disabled = disabled;
        }
        target.disabled = disabled;

        const input = target.querySelector?.("[data-ac-target='input']");
        const hidden = target.querySelector?.("[data-ac-target='hidden']");
        const hiddenText = target.querySelector?.("[data-ac-target='hiddenText']");
        const clearButton = target.querySelector?.("[data-ac-target='clearBtn']");

        if (hidden) hidden.disabled = disabled;
        if (hiddenText) hiddenText.disabled = disabled;
        if (input) {
            const locksSelectedValue = input.value !== "" && target.dataset.acAllowOtherValue !== "true";
            input.disabled = disabled || locksSelectedValue;
        }
        if (clearButton) clearButton.disabled = disabled || input?.value === "";
    }

    /** Clear a hierarchy autocomplete when switching away from deputy mode. */
    clearHierarchyControl(target) {
        const autocomplete = this.getAutocompleteController(target);
        if (autocomplete) {
            autocomplete.value = "";
        }
        target.value = "";
        target.querySelectorAll?.("[data-ac-target='hidden'], [data-ac-target='hiddenText'], [data-ac-target='input']")
            .forEach((field) => {
                field.value = "";
            });
    }

    /** Initialize form state on controller connect. */
    connect() {
        this.toggleIsDeputy();
    }
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["office-form"] = OfficeFormController;
