import { Controller } from "@hotwired/stimulus";

/**
 * ActivityToggleController
 * 
 * Handles enabling/disabling the custom description field when an activity
 * checkbox is toggled on the gathering edit form.
 */
class ActivityToggleController extends Controller {
    static targets = ["checkbox", "descriptionField"]
    
    /**
     * Toggle the description field based on checkbox state
     */
    toggleDescription(event) {
        const checkbox = event.target;
        const descriptionField = this.descriptionFieldTarget;
        
        if (checkbox.checked) {
            descriptionField.disabled = false;
        } else {
            descriptionField.disabled = true;
            descriptionField.value = ''; // Clear the value when unchecked
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["activity-toggle"] = ActivityToggleController;
