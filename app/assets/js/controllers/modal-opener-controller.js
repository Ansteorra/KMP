import { Controller } from "@hotwired/stimulus"

/**
 * ModalOpener Stimulus Controller
 * 
 * Provides programmatic modal triggering through value-driven activation.
 * Automatically opens Bootstrap modals when the modalBtn value is set,
 * enabling remote modal control and confirmation workflows.
 * 
 * Features:
 * - Value-driven modal activation
 * - Automatic modal triggering
 * - Bootstrap modal integration
 * - Programmatic control from other controllers
 * - Confirmation dialog support
 * - Remote modal opening capability
 * 
 * Values:
 * - modalBtn: String - ID of modal button element to trigger
 * 
 * Usage:
 * <div data-controller="modal-opener" 
 *      data-modal-opener-modal-btn-value="">
 *   <!-- Modal will be opened when modalBtn value is set -->
 * </div>
 * 
 * <button id="confirmModal" data-bs-toggle="modal" data-bs-target="#myModal">
 *   Hidden trigger button
 * </button>
 * 
 * JavaScript usage:
 * controller.modalBtnValue = "confirmModal"; // Triggers modal opening
 */
class ModalOpener extends Controller {
    static values = { modalBtn: String }

    /**
     * Handle modal button value changes
     * Automatically triggers modal opening when value is set
     */
    modalBtnValueChanged() {
        let modal = document.getElementById(this.modalBtnValue);
        modal.click();
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["modal-opener"] = ModalOpener;