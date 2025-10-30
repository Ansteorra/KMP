import { Controller } from "@hotwired/stimulus";

/**
 * Gathering Type Form Controller
 * 
 * Handles real-time validation and user feedback for gathering type forms.
 * Provides immediate feedback on name availability and description length.
 */
class GatheringTypeFormController extends Controller {
    static targets = ["name", "description", "nameError", "descriptionCount", "descriptionError", "submitButton"]
    
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
            // Set maxLength attribute to prevent typing beyond limit
            this.descriptionTarget.setAttribute('maxlength', this.maxDescriptionLengthValue);
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
        if (!this.hasDescriptionTarget || !this.hasDescriptionCountTarget) return true;
        
        let length = this.descriptionTarget.value.length;
        
        // Prevent typing beyond max length
        if (length > this.maxDescriptionLengthValue) {
            this.descriptionTarget.value = this.descriptionTarget.value.substring(0, this.maxDescriptionLengthValue);
            length = this.maxDescriptionLengthValue;
        }
        
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
            this.showDescriptionError(`Description cannot exceed ${this.maxDescriptionLengthValue} characters`);
            this.disableSubmit();
            return false;
        } else {
            this.descriptionCountTarget.classList.remove('text-danger');
            this.clearDescriptionError();
            this.enableSubmit();
            return true;
        }
    }
    
    /**
     * Show description error message
     */
    showDescriptionError(message) {
        if (this.hasDescriptionTarget) {
            this.descriptionTarget.classList.add('is-invalid');
            this.descriptionTarget.setAttribute('aria-invalid', 'true');
        }
        
        // Create or update error element if it doesn't exist
        let errorElement;
        if (this.hasDescriptionErrorTarget) {
            errorElement = this.descriptionErrorTarget;
        } else {
            // Create error element dynamically
            errorElement = document.createElement('div');
            errorElement.className = 'invalid-feedback';
            errorElement.id = 'description-error';
            errorElement.setAttribute('data-gathering-type-form-target', 'descriptionError');
            this.descriptionTarget.parentElement.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        errorElement.classList.remove('d-none');
        errorElement.style.display = 'block';
        
        if (this.hasDescriptionTarget) {
            this.descriptionTarget.setAttribute('aria-describedby', 'description-error');
        }
    }
    
    /**
     * Clear description error message
     */
    clearDescriptionError() {
        if (this.hasDescriptionTarget) {
            this.descriptionTarget.classList.remove('is-invalid');
            this.descriptionTarget.removeAttribute('aria-invalid');
            this.descriptionTarget.removeAttribute('aria-describedby');
        }
        
        if (this.hasDescriptionErrorTarget) {
            this.descriptionErrorTarget.classList.add('d-none');
            this.descriptionErrorTarget.style.display = 'none';
        }
    }
    
    /**
     * Disable submit button
     */
    disableSubmit() {
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true;
        }
    }
    
    /**
     * Enable submit button
     */
    enableSubmit() {
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = false;
        }
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
        // Check for existing alert and clean it up
        const existingAlert = this.element.querySelector('.alert.alert-danger.validation-summary');
        if (existingAlert) {
            // Clear any pending timeout
            if (existingAlert.dataset.timeoutId) {
                clearTimeout(parseInt(existingAlert.dataset.timeoutId));
            }
            existingAlert.remove();
        }
        
        // Flash a message at the top of the form
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show validation-summary';
        alert.role = 'alert';
        alert.innerHTML = `
            <strong>Validation Error:</strong> Please correct the errors below.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        this.element.prepend(alert);
        
        // Store timeout ID for cleanup
        const timeoutId = setTimeout(() => {
            alert.remove();
        }, 5000);
        alert.dataset.timeoutId = timeoutId.toString();
        
        // Handle manual close button click
        const closeButton = alert.querySelector('.btn-close');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                if (alert.dataset.timeoutId) {
                    clearTimeout(parseInt(alert.dataset.timeoutId));
                }
            });
        }
        
        // Handle Bootstrap dismissal event
        alert.addEventListener('closed.bs.alert', () => {
            if (alert.dataset.timeoutId) {
                clearTimeout(parseInt(alert.dataset.timeoutId));
            }
        });
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-type-form"] = GatheringTypeFormController;

// Export as default for ES6 import
export default GatheringTypeFormController;
