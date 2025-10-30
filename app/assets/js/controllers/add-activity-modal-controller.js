import { Controller } from "@hotwired/stimulus";

/**
 * AddActivityModalController
 * 
 * Handles the add activity modal, updating the default description
 * when an activity is selected from the dropdown.
 */
class AddActivityModalController extends Controller {
    static targets = ["activitySelect", "defaultDescription", "customDescription", "activityData"]
    
    /**
     * Update the default description display when an activity is selected
     */
    updateDefaultDescription(event) {
        const selectedActivityId = event.target.value;
        
        if (!selectedActivityId) {
            this.defaultDescriptionTarget.textContent = 'Select an activity to see its default description';
            return;
        }
        
        // Find the activity data element with matching ID
        const activityDataElement = this.activityDataTargets.find(
            element => element.dataset.activityId === selectedActivityId
        );
        
        if (activityDataElement) {
            const description = activityDataElement.dataset.activityDescription;
            this.defaultDescriptionTarget.textContent = description || 'No default description available';
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["add-activity-modal"] = AddActivityModalController;
