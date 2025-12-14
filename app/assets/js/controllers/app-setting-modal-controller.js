import { Controller } from "@hotwired/stimulus"

/**
 * AppSettingModal Stimulus Controller
 * 
 * Manages the edit modal for app settings, handling:
 * - Loading edit form via turbo-frame when edit button is clicked
 * - Coordinating with outlet-btn controller for data passing
 * - Managing modal state and turbo-frame loading
 * 
 * Features:
 * - Dynamic form loading via turbo-frame
 * - Integration with outlet-btn for row data
 * - Bootstrap modal coordination
 * - Loading state management
 * 
 * Values:
 * - editUrl: String - Base URL for edit action
 * - modalId: String - ID of the modal element (default: editAppSettingModal)
 * - frameId: String - ID of the turbo-frame element (default: editAppSettingFrame)
 * 
 * Note: This controller uses direct DOM queries for the modal and frame elements
 * because they are rendered in the modals block which is outside the controller's
 * DOM scope. Stimulus targets only work within the controller's element tree.
 * 
 * Usage:
 * <div data-controller="app-setting-modal"
 *      data-app-setting-modal-edit-url-value="/app-settings/edit"
 *      data-app-setting-modal-modal-id-value="editAppSettingModal"
 *      data-app-setting-modal-frame-id-value="editAppSettingFrame">
 *   <!-- Grid with edit buttons -->
 * </div>
 * <!-- Modal rendered separately in modals block -->
 * <div id="editAppSettingModal" class="modal">
 *   <turbo-frame id="editAppSettingFrame">
 *     <!-- Content loaded here -->
 *   </turbo-frame>
 * </div>
 */
class AppSettingModalController extends Controller {
    static values = {
        editUrl: String,
        modalId: { type: String, default: 'editAppSettingModal' },
        frameId: { type: String, default: 'editAppSettingFrame' }
    }

    /**
     * Initialize controller
     */
    initialize() {
        this.modalInstance = null
        this.boundHandleOutletClick = this.handleOutletClick.bind(this)
    }

    /**
     * Get the modal element by ID
     * Uses direct DOM query since modal is outside controller scope
     */
    get modalElement() {
        return document.getElementById(this.modalIdValue)
    }

    /**
     * Get the frame element by ID
     * Uses direct DOM query since frame is outside controller scope
     */
    get frameElement() {
        return document.getElementById(this.frameIdValue)
    }

    /**
     * Connect - set up event listeners
     */
    connect() {
        console.log('AppSettingModal controller connected')

        // Initialize Bootstrap modal (deferred until first use)
        // Listen for outlet-btn clicks from the grid
        document.addEventListener('outlet-btn:outlet-button-clicked', this.boundHandleOutletClick)
    }

    /**
     * Disconnect - clean up event listeners
     */
    disconnect() {
        document.removeEventListener('outlet-btn:outlet-button-clicked', this.boundHandleOutletClick)
    }

    /**
     * Handle outlet button click event
     * Loads the edit form for the clicked setting
     * 
     * @param {CustomEvent} event - The outlet-button-clicked event
     */
    handleOutletClick(event) {
        const data = event.detail

        // Check if this is for our modal (the button target is our modal)
        const clickedButton = event.target
        if (!clickedButton) return

        const modalTarget = clickedButton.getAttribute('data-bs-target')
        if (modalTarget !== `#${this.modalIdValue}`) return

        console.log('AppSettingModal: Edit clicked for setting:', data)

        if (data && data.id) {
            this.loadEditForm(data.id)
        }
    }

    /**
     * Load the edit form into the turbo-frame
     * 
     * @param {string|number} id - The app setting ID to edit
     */
    loadEditForm(id) {
        const frameEl = this.frameElement
        if (!frameEl) {
            console.error('AppSettingModal: Frame element not found with ID:', this.frameIdValue)
            return
        }

        // Build the edit URL with the setting ID
        const editUrl = `${this.editUrlValue}/${id}`
        console.log('AppSettingModal: Loading edit form from:', editUrl)

        // Reset frame content to show loading state first
        frameEl.innerHTML = `
            <div class="modal-header">
                <h5 class="modal-title">Edit App Setting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading setting...</p>
                </div>
            </div>
        `

        // Set the src to trigger turbo-frame load
        frameEl.src = editUrl
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["app-setting-modal"] = AppSettingModalController;
