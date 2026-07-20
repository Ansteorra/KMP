import { Controller } from "@hotwired/stimulus"

/**
 * OutletButton Stimulus Controller
 *
 * Manages inter-controller communication through data passing and event dispatching.
 * Provides a button component that can collect data from other controllers and
 * dispatch custom events for complex workflow coordination.
 *
 * Values:
 * - btnData: String - JSON payload in data-outlet-btn-btn-data-value (parsed safely)
 * - requireData: Boolean - Whether data is required for button activation
 */
class OutletButton extends Controller {
    static values = {
        btnData: String,
        requireData: Boolean,
    }

    /**
     * @param {string|null|undefined} raw Attribute value from data-outlet-btn-btn-data-value
     * @return {Record<string, unknown>}
     */
    parseBtnData(raw) {
        if (raw === null || raw === undefined) {
            return {};
        }

        const trimmed = String(raw).trim();
        if (trimmed === "" || trimmed === "{}") {
            return {};
        }

        try {
            const parsed = JSON.parse(trimmed);
            if (typeof parsed === "object" && parsed !== null && !Array.isArray(parsed)) {
                return parsed;
            }
        } catch (error) {
            if (trimmed !== "{") {
                console.warn("outlet-btn: invalid btn-data-value JSON", trimmed, error);
            }
        }

        return {};
    }

    /**
     * @return {Record<string, unknown>}
     */
    getBtnData() {
        return this.parseBtnData(this.btnDataValue);
    }

    /**
     * Handle button data value changes
     * Updates button state based on data availability and requirements
     */
    btnDataValueChanged() {
        if (this.btnDataValue === null || this.btnDataValue === undefined) {
            this.btnDataValue = "";
        }

        const data = this.getBtnData();
        if (this.requireDataValue && Object.keys(data).length === 0) {
            this.element.disabled = true;
        } else {
            this.element.disabled = false;
        }
    }

    /**
     * Add data to button for communication
     *
     * @param {Record<string, unknown>} data - Data object to associate with button
     */
    addBtnData(data) {
        this.btnDataValue = JSON.stringify(data ?? {});
    }

    /**
     * Fire custom event with button data
     *
     * @param {Event} event - Click event from button
     */
    fireNotice(event) {
        this.dispatch("outlet-button-clicked", { detail: this.getBtnData() });
    }

    /**
     * @param {Function} callback - Event handler function
     */
    addListener(callback) {
        this.element.addEventListener("outlet-btn:outlet-button-clicked", callback);
    }

    /**
     * @param {Function} callback - Event handler function to remove
     */
    removeListener(callback) {
        this.element.removeEventListener("outlet-btn:outlet-button-clicked", callback);
    }
}
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["outlet-btn"] = OutletButton;
