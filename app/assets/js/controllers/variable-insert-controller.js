import { Controller } from "@hotwired/stimulus"

/**
 * Variable Insert Controller
 * 
 * Provides variable insertion functionality for textarea/input fields.
 * Allows clicking buttons to insert template variables at cursor position.
 * 
 * Usage:
 * <div data-controller="variable-insert">
 *   <textarea data-variable-insert-target="field" id="my-field"></textarea>
 *   <button data-action="variable-insert#insert" 
 *           data-variable-insert-variable-param="email">
 *     Insert {{email}}
 *   </button>
 * </div>
 */
class VariableInsertController extends Controller {
    static targets = ["field"]
    
    /**
     * Insert a variable at the cursor position in the target field
     * 
     * @param {Event} event - Click event from button
     */
    insert(event) {
        event.preventDefault()
        
        const variable = event.params.variable
        if (!variable) {
            console.warn('No variable specified for insertion')
            return
        }
        
        if (!this.hasFieldTarget) {
            console.warn('No field target found')
            return
        }
        
        const field = this.fieldTarget
        
        // Get current cursor position
        const start = field.selectionStart
        const end = field.selectionEnd
        const text = field.value
        
        // Build variable syntax
        const variableText = `{{${variable}}}`
        
        // Insert variable at cursor position
        const before = text.substring(0, start)
        const after = text.substring(end)
        field.value = before + variableText + after
        
        // Move cursor after the inserted text
        const newPosition = start + variableText.length
        field.selectionStart = field.selectionEnd = newPosition
        
        // Focus the field
        field.focus()
        
        // Trigger input event for any listeners
        field.dispatchEvent(new Event('input', { bubbles: true }))
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["variable-insert"] = VariableInsertController;
