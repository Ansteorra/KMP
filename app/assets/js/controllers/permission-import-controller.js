import { Controller } from "@hotwired/stimulus"

/**
 * Permission Import Controller
 * 
 * Handles permission policy import workflow with preview modal showing
 * what policies will be added and removed during the sync operation.
 * 
 * Features:
 * - File upload handling for JSON import files
 * - Preview analysis showing additions and removals
 * - Confirmation modal before applying changes
 * - Progress feedback during import
 * 
 * @class PermissionImport
 * @extends Controller
 * 
 * HTML Structure Example:
 * ```html
 * <div data-controller="permission-import"
 *      data-permission-import-preview-url-value="/permissions/preview-import"
 *      data-permission-import-import-url-value="/permissions/import-policies">
 *   <input type="file" data-permission-import-target="fileInput" data-action="change->permission-import#handleFileSelect">
 *   <button data-action="click->permission-import#triggerFileSelect">Import</button>
 *   
 *   <!-- Modal for preview -->
 *   <div class="modal" data-permission-import-target="modal">
 *     <div data-permission-import-target="modalContent"></div>
 *     <button data-action="click->permission-import#confirmImport">Confirm</button>
 *     <button data-action="click->permission-import#cancelImport">Cancel</button>
 *   </div>
 * </div>
 * ```
 */
class PermissionImport extends Controller {
    static targets = ["fileInput", "modal", "modalContent", "addList", "removeList", "confirmBtn", "summary", "loadingOverlay"]
    static values = {
        previewUrl: String,
        importUrl: String,
        buttonContainer: String,  // Selector for external button container
    }

    /** @type {string|null} Base64 encoded import data for final submission */
    importData = null
    
    /** @type {HTMLInputElement|null} External file input reference */
    externalFileInput = null

    /**
     * Initialize controller
     */
    connect() {
        this.importData = null
        
        // If buttons are in an external container, wire them up
        if (this.hasButtonContainerValue && this.buttonContainerValue) {
            const container = document.querySelector(this.buttonContainerValue)
            if (container) {
                // Find the file input in the external container
                this.externalFileInput = container.querySelector('input[type="file"]')
                if (this.externalFileInput) {
                    this.externalFileInput.addEventListener('change', this.handleFileSelect.bind(this))
                }
                
                // Find the import button and wire it up
                const importBtn = container.querySelector('[data-action*="triggerFileSelect"]')
                if (importBtn) {
                    importBtn.addEventListener('click', this.triggerFileSelect.bind(this))
                }
            }
        }
    }

    /**
     * Trigger file input click
     * Called when the import button is clicked
     */
    triggerFileSelect(event) {
        event.preventDefault()
        // Use external file input if available, otherwise use target
        const fileInput = this.externalFileInput || (this.hasFileInputTarget ? this.fileInputTarget : null)
        if (fileInput) {
            fileInput.click()
        }
    }

    /**
     * Handle file selection
     * Validates file type and initiates preview request
     * 
     * @param {Event} event - Change event from file input
     */
    handleFileSelect(event) {
        const file = event.target.files[0]
        if (!file) return

        // Validate file type
        if (!file.name.endsWith('.json')) {
            alert('Please select a JSON file.')
            event.target.value = ''
            return
        }

        this.showLoadingOverlay()
        this.previewImport(file)
    }

    /**
     * Get the file input element (either external or target)
     * @returns {HTMLInputElement|null}
     */
    getFileInput() {
        return this.externalFileInput || (this.hasFileInputTarget ? this.fileInputTarget : null)
    }

    /**
     * Reset the file input value
     */
    resetFileInput() {
        const fileInput = this.getFileInput()
        if (fileInput) {
            fileInput.value = ''
        }
    }

    /**
     * Send file to server for preview analysis
     * 
     * @param {File} file - The uploaded JSON file
     */
    async previewImport(file) {
        const formData = new FormData()
        formData.append('import_file', file)

        try {
            const response = await fetch(this.previewUrlValue, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': document.querySelector("meta[name='csrf-token']").content,
                },
                body: formData,
            })

            const data = await response.json()

            if (!response.ok || data.error) {
                this.hideLoadingOverlay()
                alert(data.error || 'Failed to preview import file.')
                this.resetFileInput()
                return
            }

            // Store import data for final submission
            this.importData = data.import_data

            // Display preview modal
            this.displayPreview(data.changes)
            this.hideLoadingOverlay()
            this.showModal()

        } catch (error) {
            console.error('Preview error:', error)
            this.hideLoadingOverlay()
            alert('An error occurred while analyzing the import file.')
            this.resetFileInput()
        }
    }

    /**
     * Display preview information in modal
     * 
     * @param {Object} changes - The changes object from preview response
     */
    displayPreview(changes) {
        // Update summary
        if (this.hasSummaryTarget) {
            let summaryContent = `
                <div class="alert alert-info">
                    <strong>Import Summary</strong>
                    ${changes.source_permission ? `<br><small class="text-muted">Source: ${this.escapeHtml(changes.source_permission)}</small>` : ''}
                    <ul class="mb-0 mt-2">
                        <li><span class="badge bg-success">${changes.summary.total_add}</span> policies will be added</li>
                        <li><span class="badge bg-danger">${changes.summary.total_remove}</span> policies will be removed</li>
                    </ul>
                </div>
            `
            this.summaryTarget.innerHTML = summaryContent
        }

        // Display policies to add
        if (this.hasAddListTarget) {
            if (changes.policies_to_add.length > 0) {
                let addHtml = '<h6 class="text-success"><i class="bi bi-plus-circle me-1"></i>Policies to Add:</h6>'
                addHtml += '<div class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">'
                changes.policies_to_add.forEach(policy => {
                    addHtml += `
                        <div class="list-group-item list-group-item-success py-1 px-2">
                            <small><code>${this.formatPolicyName(policy.policy_class)}::${this.escapeHtml(policy.policy_method)}</code></small>
                        </div>
                    `
                })
                addHtml += '</div>'
                this.addListTarget.innerHTML = addHtml
            } else {
                this.addListTarget.innerHTML = '<p class="text-muted small">No policies to add.</p>'
            }
        }

        // Display policies to remove
        if (this.hasRemoveListTarget) {
            if (changes.policies_to_remove.length > 0) {
                let removeHtml = '<h6 class="text-danger"><i class="bi bi-dash-circle me-1"></i>Policies to Remove:</h6>'
                removeHtml += '<div class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">'
                changes.policies_to_remove.forEach(policy => {
                    removeHtml += `
                        <div class="list-group-item list-group-item-danger py-1 px-2">
                            <small><code>${this.formatPolicyName(policy.policy_class)}::${this.escapeHtml(policy.policy_method)}</code></small>
                        </div>
                    `
                })
                removeHtml += '</div>'
                this.removeListTarget.innerHTML = removeHtml
            } else {
                this.removeListTarget.innerHTML = '<p class="text-muted small">No policies to remove.</p>'
            }
        }

        // Enable/disable confirm button based on changes
        if (this.hasConfirmBtnTarget) {
            const hasChanges = changes.summary.total_add > 0 || changes.summary.total_remove > 0
            this.confirmBtnTarget.disabled = !hasChanges
            if (!hasChanges) {
                this.summaryTarget.innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i><strong>No changes needed!</strong>
                        The current permission policies match the import file exactly.
                    </div>
                `
            }
        }
    }

    /**
     * Format policy class name for display
     * Extracts just the class name from full namespace
     * 
     * @param {string} policyClass - Full policy class name
     * @returns {string} Formatted class name
     */
    formatPolicyName(policyClass) {
        const parts = policyClass.split('\\')
        return parts[parts.length - 1]
    }

    /**
     * Escape HTML to prevent XSS
     * 
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    /**
     * Show the preview modal
     */
    showModal() {
        if (this.hasModalTarget) {
            const bsModal = new bootstrap.Modal(this.modalTarget)
            bsModal.show()
        }
    }

    /**
     * Hide the preview modal
     */
    hideModal() {
        if (this.hasModalTarget) {
            const bsModal = bootstrap.Modal.getInstance(this.modalTarget)
            if (bsModal) {
                bsModal.hide()
            }
        }
    }

    /**
     * Show loading overlay during processing
     */
    showLoadingOverlay() {
        if (this.hasLoadingOverlayTarget) {
            this.loadingOverlayTarget.classList.remove('d-none')
        }
    }

    /**
     * Hide loading overlay
     */
    hideLoadingOverlay() {
        if (this.hasLoadingOverlayTarget) {
            this.loadingOverlayTarget.classList.add('d-none')
        }
    }

    /**
     * Cancel import operation
     * Closes modal and resets state
     */
    cancelImport(event) {
        event.preventDefault()
        this.hideModal()
        this.importData = null
        this.resetFileInput()
        this.resetModalContent()
    }

    /**
     * Reset modal content for next use
     */
    resetModalContent() {
        if (this.hasSummaryTarget) this.summaryTarget.innerHTML = ''
        if (this.hasAddListTarget) this.addListTarget.innerHTML = ''
        if (this.hasRemoveListTarget) this.removeListTarget.innerHTML = ''
    }

    /**
     * Confirm and execute the import
     * Sends the import data to the server for processing
     */
    async confirmImport(event) {
        event.preventDefault()

        if (!this.importData) {
            alert('No import data available.')
            return
        }

        if (this.hasConfirmBtnTarget) {
            this.confirmBtnTarget.disabled = true
            this.confirmBtnTarget.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing...'
        }

        try {
            const response = await fetch(this.importUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.querySelector("meta[name='csrf-token']").content,
                },
                body: JSON.stringify({
                    import_data: this.importData,
                }),
            })

            const data = await response.json()

            if (!response.ok || data.error) {
                alert(data.error || 'Import failed.')
                return
            }

            // Show success message
            const results = data.results
            let message = `Import completed successfully!\n\nAdded: ${results.added} policies\nRemoved: ${results.removed} policies`
            if (results.errors && results.errors.length > 0) {
                message += `\n\nWarnings:\n${results.errors.join('\n')}`
            }
            alert(message)

            // Close modal and refresh page
            this.hideModal()
            window.location.reload()

        } catch (error) {
            console.error('Import error:', error)
            alert('An error occurred during import.')
        } finally {
            if (this.hasConfirmBtnTarget) {
                this.confirmBtnTarget.disabled = false
                this.confirmBtnTarget.innerHTML = 'Confirm Import'
            }
            this.importData = null
            this.resetFileInput()
        }
    }

    /**
     * Clean up on disconnect
     */
    disconnect() {
        this.importData = null
        // Remove event listeners from external elements
        if (this.externalFileInput) {
            this.externalFileInput.removeEventListener('change', this.handleFileSelect.bind(this))
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["permission-import"] = PermissionImport
