import { Controller } from "@hotwired/stimulus"

/**
 * Gathering Clone Controller
 * 
 * Handles the clone gathering modal form interactions
 */
class GatheringCloneController extends Controller {
    static targets = ["nameInput", "startDate", "endDate"]
    
    connect() {
        console.log("Gathering clone controller connected")
    }
    
    /**
     * Validate that end date is not before start date
     */
    validateDates() {
        if (!this.hasStartDateTarget || !this.hasEndDateTarget) {
            return
        }
        
        const startDate = new Date(this.startDateTarget.value)
        const endDate = new Date(this.endDateTarget.value)
        
        if (startDate > endDate) {
            this.endDateTarget.setCustomValidity("End date must be on or after start date")
            this.endDateTarget.classList.add("is-invalid")
        } else {
            this.endDateTarget.setCustomValidity("")
            this.endDateTarget.classList.remove("is-invalid")
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-clone"] = GatheringCloneController;
