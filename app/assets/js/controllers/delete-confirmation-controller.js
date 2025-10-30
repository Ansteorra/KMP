import { Controller } from "@hotwired/stimulus";

/**
 * Delete Confirmation Controller
 * 
 * Provides enhanced confirmation dialogs for delete actions with
 * context-aware messaging and undo capability hints.
 */
class DeleteConfirmationController extends Controller {
    static values = {
        itemType: String,
        itemName: String,
        hasReferences: { type: Boolean, default: false },
        referenceCount: { type: Number, default: 0 }
    }
    
    /**
     * Handle delete button click
     */
    confirm(event) {
        const message = this.buildConfirmMessage();
        
        if (!confirm(message)) {
            event.preventDefault();
            event.stopPropagation();
            return false;
        }
        
        return true;
    }
    
    /**
     * Build context-aware confirmation message
     */
    buildConfirmMessage() {
        let message = `Are you sure you want to delete this ${this.itemTypeValue}?`;
        
        if (this.hasItemNameValue) {
            message = `Are you sure you want to delete "${this.itemNameValue}"?`;
        }
        
        if (this.hasReferencesValue) {
            message += `\n\nWarning: This ${this.itemTypeValue} is referenced by `;
            message += this.referenceCountValue === 1 
                ? "1 other item" 
                : `${this.referenceCountValue} other items`;
            message += ". Deleting it may affect those items.";
        }
        
        message += "\n\nThis action cannot be undone.";
        
        return message;
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["delete-confirmation"] = DeleteConfirmationController;

// Export as default for ES6 import
export default DeleteConfirmationController;
