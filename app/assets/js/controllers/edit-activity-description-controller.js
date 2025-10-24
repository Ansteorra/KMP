import { Controller } from "@hotwired/stimulus";

/**
 * EditActivityDescriptionController
 * 
 * Handles populating the edit activity description modal with data
 * when the edit button is clicked.
 */
class EditActivityDescriptionController extends Controller {
    static targets = ["activityId", "activityName", "defaultDescription", "customDescription"]
    
    connect() {
        // Listen for modal show event to populate data
        const modal = document.getElementById('editActivityDescriptionModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', this.handleModalShow.bind(this));
        }
    }
    
    /**
     * Handle modal show event - populate with data from the clicked button
     */
    handleModalShow(event) {
        // Get the button that triggered the modal
        const button = event.relatedTarget;
        
        if (button) {
            // Extract data from button attributes
            const activityId = button.getAttribute('data-activity-id');
            const activityName = button.getAttribute('data-activity-name');
            const defaultDescription = button.getAttribute('data-default-description');
            const customDescription = button.getAttribute('data-custom-description');
            
            // Populate the modal fields
            if (this.hasActivityIdTarget) {
                this.activityIdTarget.value = activityId;
            }
            
            if (this.hasActivityNameTarget) {
                this.activityNameTarget.textContent = activityName;
            }
            
            if (this.hasDefaultDescriptionTarget) {
                this.defaultDescriptionTarget.textContent = defaultDescription || 'No default description';
            }
            
            if (this.hasCustomDescriptionTarget) {
                this.customDescriptionTarget.value = customDescription || '';
            }
        }
    }
    
    disconnect() {
        const modal = document.getElementById('editActivityDescriptionModal');
        if (modal) {
            modal.removeEventListener('show.bs.modal', this.handleModalShow.bind(this));
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["edit-activity-description"] = EditActivityDescriptionController;
