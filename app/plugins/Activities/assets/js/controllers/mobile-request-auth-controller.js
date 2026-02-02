import MobileControllerBase from "../../../../../assets/js/controllers/mobile-controller-base.js"

/**
 * Mobile Authorization Request Controller
 * 
 * Touch-optimized auth request workflow with offline queue integration.
 * Extends MobileControllerBase for centralized connection handling.
 */
class MobileRequestAuthController extends MobileControllerBase {
    static targets = [
        "form", "activitySelect", "approverSelect", "approverHelp",
        "submitBtn", "submitText", "onlineStatus"
    ]

    static values = {
        approversUrl: String,
        memberId: Number
    }

    initialize() {
        super.initialize();
    }

    /**
     * Called after base class connect
     */
    onConnect() {
        console.log("Mobile Request Auth controller connected");
        this.updateOnlineUI();
        this.formTarget.addEventListener('submit', this.bindHandler('submit', this.handleSubmit));
        this.validateForm();
    }

    /**
     * Called when connection state changes (from base class)
     */
    onConnectionStateChanged(isOnline) {
        this.updateOnlineUI();
    }

    /**
     * Called after base class disconnect
     */
    onDisconnect() {
        const submitHandler = this.getHandler('submit');
        if (submitHandler && this.hasFormTarget) {
            this.formTarget.removeEventListener('submit', submitHandler);
        }
    }

    /**
     * Update UI based on online/offline status
     */
    updateOnlineUI() {
        if (!this.online) {
            this.onlineStatusTarget.hidden = false;
            this.onlineStatusTarget.classList.add('offline');
            this.activitySelectTarget.disabled = true;
            this.approverSelectTarget.disabled = true;
            this.submitBtnTarget.disabled = true;
            this.approverHelpTarget.textContent = "You must be online to submit requests";
        } else {
            this.onlineStatusTarget.hidden = true;
            this.onlineStatusTarget.classList.remove('offline');
            this.activitySelectTarget.disabled = false;
            this.approverSelectTarget.disabled = false;
            this.validateForm();
            if (!this.activitySelectTarget.value) {
                this.approverHelpTarget.textContent = "Select an activity to see available approvers";
            }
        }
    }

    /**
     * Fetch approvers with retry logic
     */
    async loadApprovers(event) {
        const activityId = event.target.value;

        this.approverSelectTarget.innerHTML = '<option value="">-- Loading approvers... --</option>';
        this.approverSelectTarget.disabled = true;
        this.approverHelpTarget.textContent = "Loading approvers...";
        this.submitBtnTarget.disabled = true;

        if (!activityId) {
            this.approverSelectTarget.innerHTML = '<option value="">-- Select activity first --</option>';
            this.approverHelpTarget.textContent = "Select an activity to see available approvers";
            return;
        }

        if (!this.online) {
            this.approverSelectTarget.innerHTML = '<option value="">-- You must be online --</option>';
            this.approverHelpTarget.textContent = "You must be online to load approvers";
            return;
        }

        try {
            const url = `${this.approversUrlValue}/${activityId}/${this.memberIdValue}`;
            // Use base class fetchWithRetry for reliability
            const response = await this.fetchWithRetry(url);
            const data = await response.json();
            
            this.approverSelectTarget.innerHTML = '';

            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = '-- Choose an approver --';
            this.approverSelectTarget.appendChild(emptyOption);

            if (data && Array.isArray(data) && data.length > 0) {
                data.forEach(approver => {
                    const option = document.createElement('option');
                    option.value = approver.id;
                    option.textContent = approver.sca_name;
                    this.approverSelectTarget.appendChild(option);
                });

                this.approverSelectTarget.disabled = false;
                this.approverHelpTarget.textContent = `${data.length} approver(s) available`;
            } else {
                const noApprovers = document.createElement('option');
                noApprovers.value = '';
                noApprovers.textContent = '-- No approvers available --';
                this.approverSelectTarget.appendChild(noApprovers);
                this.approverHelpTarget.textContent = "No approvers found for this activity";
            }

        } catch (error) {
            console.error('Error loading approvers:', error);
            this.approverSelectTarget.innerHTML = '<option value="">-- Error loading approvers --</option>';
            this.approverHelpTarget.textContent = "Failed to load approvers. Please try again.";
        }

        this.validateForm();
    }

    /**
     * Enable submit button only when valid and online
     */
    validateForm() {
        const activitySelected = this.activitySelectTarget.value !== '';
        const approverSelected = this.approverSelectTarget.value !== '';
        this.submitBtnTarget.disabled = !(activitySelected && approverSelected && this.online);
    }

    /**
     * Handle form submission
     */
    handleSubmit(event) {
        if (!this.online) {
            event.preventDefault();
            alert('You must be online to submit authorization requests');
            return false;
        }

        this.submitBtnTarget.disabled = true;
        this.submitTextTarget.innerHTML = '<span class="loading-spinner"></span>Submitting...';
        return true;
    }
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["mobile-request-auth"] = MobileRequestAuthController;

export default MobileRequestAuthController;
