import { Controller } from "@hotwired/stimulus";

/**
 * Base Gathering Form Controller
 * 
 * Provides shared date validation logic for gathering forms.
 * Extended by gathering-form-controller and gathering-clone-controller.
 * 
 * Features:
 * - Automatically defaults end date to start date when start date changes
 * - Validates that end date is not before start date
 * - Provides real-time feedback to users
 */
export class BaseGatheringFormController extends Controller {
    // Define targets - elements this controller interacts with
    static targets = ["startDate", "endDate", "submitButton"]
    
    /**
     * Connect function - runs when controller connects to DOM
     */
    connect() {
        // Set up initial validation when page loads
        if (this.hasStartDateTarget && this.hasEndDateTarget) {
            this.validateDates();
        }
    }
    
    /**
     * Handle start date changes
     * Automatically updates end date to match start date if end date is empty or before start date
     */
    startDateChanged(event) {
        const startDate = this.startDateTarget.value;
        const endDate = this.endDateTarget.value;
        
        // If end date is empty or before start date, set it to start date
        if (!endDate || endDate < startDate) {
            this.endDateTarget.value = startDate;
        }
        
        // Validate dates
        this.validateDates();
    }
    
    /**
     * Handle end date changes
     * Validates that end date is not before start date
     */
    endDateChanged(event) {
        this.validateDates();
    }
    
    /**
     * Validate dates
     * Ensures end date is on or after start date
     */
    validateDates() {
        if (!this.hasStartDateTarget || !this.hasEndDateTarget) {
            return true;
        }
        
        const startDate = this.startDateTarget.value;
        const endDate = this.endDateTarget.value;
        
        // Clear any previous validation messages
        this.clearValidationMessages();
        
        if (startDate && endDate && endDate < startDate) {
            // End date is before start date - show error
            this.showValidationError(
                this.endDateTarget,
                'End date cannot be before start date'
            );
            
            // Disable submit button
            if (this.hasSubmitButtonTarget) {
                this.submitButtonTarget.disabled = true;
            }
            
            return false;
        } else {
            // Dates are valid - enable submit button
            if (this.hasSubmitButtonTarget) {
                this.submitButtonTarget.disabled = false;
            }
            
            return true;
        }
    }
    
    /**
     * Validate form before submission
     */
    validateForm(event) {
        if (!this.validateDates()) {
            event.preventDefault();
            return false;
        }
        return true;
    }
    
    /**
     * Show validation error message
     */
    showValidationError(element, message) {
        // Add invalid class to element
        element.classList.add('is-invalid');
        
        // Create or update feedback element
        let feedbackElement = element.parentElement.querySelector('.invalid-feedback');
        if (!feedbackElement) {
            feedbackElement = document.createElement('div');
            feedbackElement.className = 'invalid-feedback';
            element.parentElement.appendChild(feedbackElement);
        }
        feedbackElement.textContent = message;
        feedbackElement.style.display = 'block';
    }
    
    /**
     * Clear validation messages
     */
    clearValidationMessages() {
        // Remove invalid classes
        if (this.hasStartDateTarget) {
            this.startDateTarget.classList.remove('is-invalid');
        }
        if (this.hasEndDateTarget) {
            this.endDateTarget.classList.remove('is-invalid');
        }
        
        // Remove feedback elements
        const feedbackElements = this.element.querySelectorAll('.invalid-feedback');
        feedbackElements.forEach(el => {
            el.style.display = 'none';
        });
    }
}
