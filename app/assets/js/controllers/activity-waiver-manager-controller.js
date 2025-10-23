import { Controller } from "@hotwired/stimulus";

/**
 * Activity Waiver Manager Controller
 * 
 * Manages the waiver selection interface for gathering activities.
 * Provides visual feedback and validation for waiver associations.
 */
class ActivityWaiverManagerController extends Controller {
    static targets = ["waiverCheckbox", "selectedCount", "waiverList"]
    
    static values = {
        minWaivers: { type: Number, default: 0 },
        maxWaivers: { type: Number, default: 99 }
    }
    
    /**
     * Initialize the controller
     */
    connect() {
        this.updateSelectedCount();
        this.updateVisualState();
    }
    
    /**
     * Handle waiver checkbox toggle
     */
    toggleWaiver(event) {
        this.updateSelectedCount();
        this.updateVisualState();
        this.validateSelection();
    }
    
    /**
     * Update the selected waiver count display
     */
    updateSelectedCount() {
        if (!this.hasSelectedCountTarget) return;
        
        const selectedCount = this.getSelectedWaivers().length;
        const countText = selectedCount === 0 
            ? "No waivers selected" 
            : selectedCount === 1 
                ? "1 waiver selected" 
                : `${selectedCount} waivers selected`;
        
        this.selectedCountTarget.textContent = countText;
    }
    
    /**
     * Update visual state of selected waivers
     */
    updateVisualState() {
        this.waiverCheckboxTargets.forEach(checkbox => {
            const container = checkbox.closest('.form-check, .checkbox');
            if (!container) return;
            
            if (checkbox.checked) {
                container.classList.add('selected');
                container.style.backgroundColor = '#e7f3ff';
                container.style.borderLeft = '3px solid #0d6efd';
                container.style.paddingLeft = '0.5rem';
            } else {
                container.classList.remove('selected');
                container.style.backgroundColor = '';
                container.style.borderLeft = '';
                container.style.paddingLeft = '';
            }
        });
    }
    
    /**
     * Validate waiver selection
     */
    validateSelection() {
        const selectedCount = this.getSelectedWaivers().length;
        const isValid = selectedCount >= this.minWaiversValue && 
                       selectedCount <= this.maxWaiversValue;
        
        // Update validation state
        if (this.hasWaiverListTarget) {
            if (!isValid && selectedCount > 0) {
                this.waiverListTarget.classList.add('is-invalid');
            } else {
                this.waiverListTarget.classList.remove('is-invalid');
            }
        }
        
        return isValid;
    }
    
    /**
     * Get array of selected waiver IDs
     */
    getSelectedWaivers() {
        return this.waiverCheckboxTargets
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);
    }
    
    /**
     * Select all waivers
     */
    selectAll() {
        this.waiverCheckboxTargets.forEach(checkbox => {
            checkbox.checked = true;
        });
        this.updateSelectedCount();
        this.updateVisualState();
        this.validateSelection();
    }
    
    /**
     * Deselect all waivers
     */
    deselectAll() {
        this.waiverCheckboxTargets.forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateSelectedCount();
        this.updateVisualState();
        this.validateSelection();
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["activity-waiver-manager"] = ActivityWaiverManagerController;

// Export as default for ES6 import
export default ActivityWaiverManagerController;
