import { Controller } from "@hotwired/stimulus"

/**
 * Mobile Authorization Request Controller - Touch-optimized auth request workflow
 *
 * Targets: form, activitySelect, approverSelect, approverHelp, submitBtn, submitText, onlineStatus
 * Values: approversUrl (String), memberId (Number)
 */
class MobileRequestAuthController extends Controller {
    static targets = [
        "form", "activitySelect", "approverSelect", "approverHelp",
        "submitBtn", "submitText", "onlineStatus"
    ]

    static values = {
        approversUrl: String,
        memberId: Number
    }

    /** Cache bound event handlers for proper cleanup. */
    initialize() {
        this._onOnline = this.checkOnlineStatus.bind(this)
        this._onOffline = this.checkOnlineStatus.bind(this)
        this._onSubmit = this.handleSubmit.bind(this)
    }

    /** Setup online/offline listeners and initial validation. */
    connect() {
        console.log("Mobile Request Auth controller connected")
        this.checkOnlineStatus()
        window.addEventListener('online', this._onOnline)
        window.addEventListener('offline', this._onOffline)
        this.formTarget.addEventListener('submit', this._onSubmit)
        this.validateForm()
    }

    /** Remove event listeners on disconnect. */
    disconnect() {
        window.removeEventListener('online', this._onOnline)
        window.removeEventListener('offline', this._onOffline)
        if (this.hasFormTarget) {
            this.formTarget.removeEventListener('submit', this._onSubmit)
        }
    }

    /** Update UI based on online/offline status. */
    checkOnlineStatus() {
        const isOnline = navigator.onLine

        if (!isOnline) {
            this.onlineStatusTarget.hidden = false
            this.onlineStatusTarget.classList.add('offline')
            this.activitySelectTarget.disabled = true
            this.approverSelectTarget.disabled = true
            this.submitBtnTarget.disabled = true
            this.approverHelpTarget.textContent = "You must be online to submit requests"
        } else {
            this.onlineStatusTarget.hidden = true
            this.onlineStatusTarget.classList.remove('offline')
            this.activitySelectTarget.disabled = false
            this.approverSelectTarget.disabled = false
            this.validateForm()
            if (!this.activitySelectTarget.value) {
                this.approverHelpTarget.textContent = "Select an activity to see available approvers"
            }
        }
    }

    /** Fetch approvers from API when activity is selected. */
    async loadApprovers(event) {
        const activityId = event.target.value

        this.approverSelectTarget.innerHTML = '<option value="">-- Loading approvers... --</option>'
        this.approverSelectTarget.disabled = true
        this.approverHelpTarget.textContent = "Loading approvers..."
        this.submitBtnTarget.disabled = true

        if (!activityId) {
            this.approverSelectTarget.innerHTML = '<option value="">-- Select activity first --</option>'
            this.approverHelpTarget.textContent = "Select an activity to see available approvers"
            return
        }

        if (!navigator.onLine) {
            this.approverSelectTarget.innerHTML = '<option value="">-- You must be online --</option>'
            this.approverHelpTarget.textContent = "You must be online to load approvers"
            return
        }

        try {
            const url = `${this.approversUrlValue}/${activityId}/${this.memberIdValue}`
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const data = await response.json()
            this.approverSelectTarget.innerHTML = ''

            const emptyOption = document.createElement('option')
            emptyOption.value = ''
            emptyOption.textContent = '-- Choose an approver --'
            this.approverSelectTarget.appendChild(emptyOption)

            if (data && Array.isArray(data) && data.length > 0) {
                data.forEach(approver => {
                    const option = document.createElement('option')
                    option.value = approver.id
                    option.textContent = approver.sca_name
                    this.approverSelectTarget.appendChild(option)
                })

                this.approverSelectTarget.disabled = false
                this.approverHelpTarget.textContent = `${data.length} approver(s) available`
            } else {
                const noApprovers = document.createElement('option')
                noApprovers.value = ''
                noApprovers.textContent = '-- No approvers available --'
                this.approverSelectTarget.appendChild(noApprovers)
                this.approverHelpTarget.textContent = "No approvers found for this activity"
            }

        } catch (error) {
            console.error('Error loading approvers:', error)
            this.approverSelectTarget.innerHTML = '<option value="">-- Error loading approvers --</option>'
            this.approverHelpTarget.textContent = "Failed to load approvers. Please try again."
        }

        this.validateForm()
    }

    /** Enable submit button only when activity and approver are selected and online. */
    validateForm() {
        const activitySelected = this.activitySelectTarget.value !== ''
        const approverSelected = this.approverSelectTarget.value !== ''
        const isOnline = navigator.onLine
        this.submitBtnTarget.disabled = !(activitySelected && approverSelected && isOnline)
    }

    /** Prevent submission if offline, show loading state otherwise. */
    handleSubmit(event) {
        if (!navigator.onLine) {
            event.preventDefault()
            alert('You must be online to submit authorization requests')
            return false
        }

        this.submitBtnTarget.disabled = true
        this.submitTextTarget.innerHTML = '<span class="loading-spinner"></span>Submitting...'
        return true
    }
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["mobile-request-auth"] = MobileRequestAuthController

export default MobileRequestAuthController
