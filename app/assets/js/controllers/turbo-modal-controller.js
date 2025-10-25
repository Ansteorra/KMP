import { Controller } from "@hotwired/stimulus";

/**
 * TurboModal Stimulus Controller
 * 
 * Handles modal closing before form submission to prevent modal being open during
 * Turbo Stream updates. Closes the modal when form is submitted, allowing the
 * background page to update cleanly.
 * 
 * Features:
 * - Modal closing before form submission
 * - Bootstrap modal integration
 * - Turbo Form submission handling
 * - Prevents modal from interfering with page updates
 * 
 * Usage:
 * <form data-controller="turbo-modal" 
 *       data-action="turbo:submit-start->turbo-modal#closeModalBeforeSubmit"
 *       data-turbo="true">
 *   <!-- Form contents -->
 * </form>
 * 
 * The modal will close immediately when the form is submitted.
 */
class TurboModal extends Controller {
    /**
     * Initialize - log when controller connects
     */
    connect() {
        console.log('TurboModal controller connected');
    }
    
    /**
     * Close the modal before form submission starts
     * 
     * @param {Event} event - The turbo:submit-start event
     */
    closeModalBeforeSubmit(event) {
        console.log('turbo:submit-start - closing modal before submission');
        
        // Find the modal element (closest modal parent)
        const modal = this.element.closest('.modal');
        if (modal) {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                console.log('Closing modal...');
                modalInstance.hide();
            }
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["turbo-modal"] = TurboModal;
