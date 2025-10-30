import { Controller } from "@hotwired/stimulus"

/**
 * Mobile Authorization Request Controller
 * 
 * Handles mobile-optimized authorization request workflow including:
 * - Online/offline detection
 * - Dynamic approver loading based on activity selection
 * - Form validation
 * - Touch-optimized interactions
 */
class MobileRequestAuthController extends Controller {
    static targets = [
        "form",
        "activitySelect",
        "approverSelect",
        "approverHelp",
        "submitBtn",
        "submitText",
        "onlineStatus"
    ]

    static values = {
        approversUrl: String,
        memberId: Number
    }

    /**
     * Initialize controller - cache bound event handlers
     */
    initialize() {
        // Cache bound handlers for proper cleanup
        this._onOnline = this.checkOnlineStatus.bind(this)
        this._onOffline = this.checkOnlineStatus.bind(this)
        this._onSubmit = this.handleSubmit.bind(this)
    }

    /**
     * Initialize controller and check online status
     */
    connect() {
        console.log("Mobile Request Auth controller connected")
        
        // Check online status on load
        this.checkOnlineStatus()
        
        // Listen for online/offline events using cached handlers
        window.addEventListener('online', this._onOnline)
        window.addEventListener('offline', this._onOffline)
        
        // Prevent form submission if offline using cached handler
        this.formTarget.addEventListener('submit', this._onSubmit)
        
        // Initial validation state
        this.validateForm()
    }

    /**
     * Cleanup event listeners
     */
    disconnect() {
        // Remove listeners using same cached handlers
        window.removeEventListener('online', this._onOnline)
        window.removeEventListener('offline', this._onOffline)
        
        if (this.hasFormTarget) {
            this.formTarget.removeEventListener('submit', this._onSubmit)
        }
    }

    /**
     * Check if user is online and update UI accordingly
     */
    checkOnlineStatus() {
        const isOnline = navigator.onLine
        
        if (!isOnline) {
            // Show offline warning
            this.onlineStatusTarget.hidden = false
            this.onlineStatusTarget.classList.add('offline')
            
            // Disable form elements
            this.activitySelectTarget.disabled = true
            this.approverSelectTarget.disabled = true
            this.submitBtnTarget.disabled = true
            
            // Update help text
            this.approverHelpTarget.textContent = "You must be online to submit requests"
        } else {
            // Hide offline warning
            this.onlineStatusTarget.hidden = true
            this.onlineStatusTarget.classList.remove('offline')
            
            // Re-enable form elements
            this.activitySelectTarget.disabled = false
            this.approverSelectTarget.disabled = false
            
            // Restore previous state
            this.validateForm()
            
            // Update help text
            if (!this.activitySelectTarget.value) {
                this.approverHelpTarget.textContent = "Select an activity to see available approvers"
            }
        }
    }

    /**
     * Load approvers when activity is selected
     */
    async loadApprovers(event) {
        const activityId = event.target.value
        
        // Reset approver dropdown
        this.approverSelectTarget.innerHTML = '<option value="">-- Loading approvers... --</option>'
        this.approverSelectTarget.disabled = true
        this.approverHelpTarget.textContent = "Loading approvers..."
        this.submitBtnTarget.disabled = true
        
        if (!activityId) {
            this.approverSelectTarget.innerHTML = '<option value="">-- Select activity first --</option>'
            this.approverHelpTarget.textContent = "Select an activity to see available approvers"
            return
        }

        // Check if online
        if (!navigator.onLine) {
            this.approverSelectTarget.innerHTML = '<option value="">-- You must be online --</option>'
            this.approverHelpTarget.textContent = "You must be online to load approvers"
            return
        }

        try {
            // Fetch approvers from API
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

            // Clear loading option
            this.approverSelectTarget.innerHTML = ''

            // Add empty option
            const emptyOption = document.createElement('option')
            emptyOption.value = ''
            emptyOption.textContent = '-- Choose an approver --'
            this.approverSelectTarget.appendChild(emptyOption)

            // Add approver options
            // API returns array directly, not wrapped in approvers property
            if (data && Array.isArray(data) && data.length > 0) {
                data.forEach(approver => {
                    const option = document.createElement('option')
                    option.value = approver.id
                    // API returns sca_name, not name
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

        // Revalidate form
        this.validateForm()
    }

    /**
     * Validate form and enable/disable submit button
     */
    validateForm() {
        const activitySelected = this.activitySelectTarget.value !== ''
        const approverSelected = this.approverSelectTarget.value !== ''
        const isOnline = navigator.onLine

        const isValid = activitySelected && approverSelected && isOnline

        this.submitBtnTarget.disabled = !isValid
    }

    /**
     * Handle form submission
     */
    handleSubmit(event) {
        // Prevent submission if offline
        if (!navigator.onLine) {
            event.preventDefault()
            alert('You must be online to submit authorization requests')
            return false
        }

        // Show loading state
        this.submitBtnTarget.disabled = true
        this.submitTextTarget.innerHTML = '<span class="loading-spinner"></span>Submitting...'

        // Form will submit normally to the controller action
        return true
    }
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["mobile-request-auth"] = MobileRequestAuthController

export default MobileRequestAuthController
