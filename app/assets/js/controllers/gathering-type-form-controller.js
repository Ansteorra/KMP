import { Controller } from "@hotwired/stimulus";

/**
 * Gathering Type Form Controller
 * 
 * Handles real-time validation and user feedback for gathering type forms.
 * Provides immediate feedback on name availability and description length.
 */
class GatheringTypeFormController extends Controller {
    static targets = ["name", "description", "nameError", "descriptionCount", "submitButton"]
    
    static values = {
        maxDescriptionLength: { type: Number, default: 500 },
        checkNameUrl: String
    }
    
    /**
     * Initialize the controller
     */
    connect() {
        if (this.hasDescriptionTarget) {
            this.updateDescriptionCount();
        }
    }
    
    /**
     * Validate name field on blur
     */
    validateName() {
        if (!this.hasNameTarget) return;
        
        const name = this.nameTarget.value.trim();
        
        if (name.length === 0) {
            this.showNameError("Name is required");
            return false;
        }
        
        if (name.length < 3) {
            this.showNameError("Name must be at least 3 characters");
            return false;
        }
        
        if (name.length > 128) {
            this.showNameError("Name must be less than 128 characters");
            return false;
        }
        
        this.clearNameError();
        return true;
    }
    
    /**
     * Update description character count
     */
    updateDescriptionCount() {
        if (!this.hasDescriptionTarget || !this.hasDescriptionCountTarget) return;
        
        const length = this.descriptionTarget.value.length;
        const remaining = this.maxDescriptionLengthValue - length;
        
        this.descriptionCountTarget.textContent = 
            `${length} / ${this.maxDescriptionLengthValue} characters`;
        
        if (remaining < 50) {
            this.descriptionCountTarget.classList.add('text-warning');
            this.descriptionCountTarget.classList.remove('text-muted');
        } else {
            this.descriptionCountTarget.classList.remove('text-warning');
            this.descriptionCountTarget.classList.add('text-muted');
        }
        
        if (length > this.maxDescriptionLengthValue) {
            this.descriptionCountTarget.classList.add('text-danger');
            this.descriptionCountTarget.classList.remove('text-warning');
            return false;
        }
        
        return true;
    }
    
    /**
     * Show name error message
     */
    showNameError(message) {
        if (this.hasNameErrorTarget) {
            this.nameErrorTarget.textContent = message;
            this.nameErrorTarget.classList.remove('d-none');
        }
        
        if (this.hasNameTarget) {
            this.nameTarget.classList.add('is-invalid');
        }
    }
    
    /**
     * Clear name error message
     */
    clearNameError() {
        if (this.hasNameErrorTarget) {
            this.nameErrorTarget.classList.add('d-none');
        }
        
        if (this.hasNameTarget) {
            this.nameTarget.classList.remove('is-invalid');
            this.nameTarget.classList.add('is-valid');
        }
    }
    
    /**
     * Validate entire form before submission
     */
    validateForm(event) {
        let isValid = true;
        
        if (this.hasNameTarget) {
            isValid = this.validateName() && isValid;
        }
        
        if (this.hasDescriptionTarget) {
            isValid = this.updateDescriptionCount() && isValid;
        }
        
        if (!isValid) {
            event.preventDefault();
            this.showValidationSummary();
        }
        
        return isValid;
    }
    
    /**
     * Show validation summary
     */
    showValidationSummary() {
        // Flash a message at the top of the form
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show';
        alert.role = 'alert';
        alert.innerHTML = `
            <strong>Validation Error:</strong> Please correct the errors below.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        this.element.prepend(alert);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-type-form"] = GatheringTypeFormController;

// Export as default for ES6 import
export default GatheringTypeFormController;
