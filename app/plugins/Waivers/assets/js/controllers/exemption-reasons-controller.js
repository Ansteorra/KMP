import { Controller } from "@hotwired/stimulus"

/**
 * Exemption Reasons Controller
 * 
 * Manages the dynamic list of exemption reasons for waiver types.
 * Allows administrators to add/remove reasons that can be selected when
 * attesting that a waiver is not needed for a gathering activity.
 */
class ExemptionReasonsController extends Controller {
    static targets = ["container", "template", "hiddenInput", "reasonInput"]
    
    static values = {
        reasons: { type: Array, default: [] }
    }
    
    connect() {
        // Initialize with existing reasons or add one empty field
        if (this.reasonsValue && this.reasonsValue.length > 0) {
            this.reasonsValue.forEach(reason => {
                this.addReason(reason)
            })
        } else {
            this.addReason("")
        }
        
        this.updateHiddenInput()
    }
    
    /**
     * Add a new reason input field
     */
    addReason(value = "") {
        const template = this.templateTarget.content.cloneNode(true)
        const input = template.querySelector('input[type="text"]')
        const container = template.querySelector('.exemption-reason-item')
        
        if (value) {
            input.value = value
        }
        
        this.containerTarget.appendChild(template)
        
        // Focus the new input if it's empty
        if (!value) {
            input.focus()
        }
    }
    
    /**
     * Remove a reason input field
     */
    removeReason(event) {
        const item = event.target.closest('.exemption-reason-item')
        
        // Always keep at least one field
        if (this.containerTarget.children.length > 1) {
            item.remove()
            this.updateHiddenInput()
        } else {
            // Clear the last field instead of removing it
            const input = item.querySelector('input[type="text"]')
            input.value = ""
            this.updateHiddenInput()
        }
    }
    
    /**
     * Update the hidden input with JSON array of reasons
     */
    updateHiddenInput() {
        const reasons = []
        
        this.reasonInputTargets.forEach(input => {
            const value = input.value.trim()
            if (value) {
                reasons.push(value)
            }
        })
        
        this.hiddenInputTarget.value = JSON.stringify(reasons)
    }
    
    /**
     * Handle input changes to update hidden field
     */
    reasonChanged(event) {
        this.updateHiddenInput()
    }
    
    /**
     * Handle when an input loses focus
     * Add a new empty field if the last input now has a value
     */
    reasonBlurred(event) {
        const inputs = this.reasonInputTargets
        const lastInput = inputs[inputs.length - 1]
        
        // If this is the last input and it has a value, add a new empty field
        if (event.target === lastInput && event.target.value.trim()) {
            this.addReason("")
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["exemption-reasons"] = ExemptionReasonsController;
