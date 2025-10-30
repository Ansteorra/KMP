import { Controller } from "@hotwired/stimulus"

/**
 * Email Template Form Controller
 * 
 * Manages the dynamic behavior of the email template form:
 * - Populates action methods based on selected mailer class
 * - Updates available variables when action is selected
 * - Updates default subject when action is selected
 * 
 * Usage:
 * <div data-controller="email-template-form"
 *      data-email-template-form-mailers-value='[...]'>
 *   <select data-email-template-form-target="mailerSelect"
 *           data-action="email-template-form#mailerChanged"></select>
 *   <select data-email-template-form-target="actionSelect"
 *           data-action="email-template-form#actionChanged"></select>
 *   <input data-email-template-form-target="availableVars">
 *   <input data-email-template-form-target="subjectTemplate">
 * </div>
 */
class EmailTemplateFormController extends Controller {
    static targets = ["mailerSelect", "actionSelect", "availableVars", "subjectTemplate"]
    
    static values = {
        mailers: { type: Array, default: [] }
    }

    /**
     * Handle mailer class selection change
     * Populates the action method dropdown with methods from the selected mailer
     */
    mailerChanged(event) {
        const selectedClass = this.mailerSelectTarget.value
        
        // Clear action select
        this.actionSelectTarget.innerHTML = '<option value="">-- Select Action --</option>'
        
        if (!selectedClass) {
            return
        }
        
        // Find the selected mailer
        const mailer = this.mailersValue.find(m => m.class === selectedClass)
        
        if (!mailer || !mailer.methods) {
            return
        }
        
        // Populate action methods
        mailer.methods.forEach(method => {
            const option = document.createElement('option')
            option.value = method.name
            option.textContent = method.name
            option.dataset.vars = JSON.stringify(method.availableVars || [])
            option.dataset.subject = method.defaultSubject || ''
            this.actionSelectTarget.appendChild(option)
        })
        
        console.log(`Populated ${mailer.methods.length} methods for mailer: ${mailer.shortName}`)
    }

    /**
     * Handle action method selection change
     * Updates available variables and default subject
     */
    actionChanged(event) {
        const selectedOption = this.actionSelectTarget.selectedOptions[0]
        
        // If no option selected, clear everything and return
        if (!selectedOption) {
            return
        }
        
        // Always update available vars (clear if dataset.vars is missing)
        if (this.hasAvailableVarsTarget) {
            const varsValue = selectedOption.dataset.vars || ''
            this.availableVarsTarget.value = varsValue
            if (varsValue) {
                console.log('Updated available vars:', varsValue)
            }
        }
        
        // Always update subject template (clear if dataset.subject is missing)
        if (this.hasSubjectTemplateTarget) {
            const subjectValue = selectedOption.dataset.subject || ''
            this.subjectTemplateTarget.value = subjectValue
            if (subjectValue) {
                console.log('Updated subject template:', subjectValue)
            }
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["email-template-form"] = EmailTemplateFormController;
