import { Controller } from "@hotwired/stimulus"

/**
 * Waiver Upload Controller
 * 
 * Handles file selection, validation, preview, and upload progress for waiver images.
 * Supports multiple file uploads with mobile camera capture integration.
 * 
 * Targets:
 * - waiverType: Waiver type select dropdown
 * - fileInput: File input element
 * - preview: Preview area container
 * - progress: Progress bar container
 * - progressBar: Progress bar element
 * - progressText: Progress text element
 * - submitButton: Submit button
 * 
 * Actions:
 * - handleFileSelect: Triggered when files are selected
 * - handleSubmit: Triggered when form is submitted
 */
class WaiverUploadController extends Controller {
    static targets = [
        "waiverType",
        "fileInput",
        "preview",
        "progress",
        "progressBar",
        "progressText",
        "submitButton"
    ]

    /**
     * Maximum file size in bytes (25MB)
     */
    static MAX_FILE_SIZE = 25 * 1024 * 1024

    /**
     * Allowed MIME types for image uploads
     */
    static ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/tiff']

    /**
     * Initialize controller
     */
    connect() {
        console.log('WaiverUploadController connected')
        this.selectedFiles = []
    }

    /**
     * Handle file selection from input
     * 
     * @param {Event} event File input change event
     */
    handleFileSelect(event) {
        const files = Array.from(event.target.files)
        
        if (files.length === 0) {
            return
        }

        // Validate files
        const validationResults = files.map(file => this.validateFile(file))
        const invalidFiles = validationResults.filter(result => !result.valid)

        if (invalidFiles.length > 0) {
            // Show error messages
            const errors = invalidFiles.map(result => result.error).join('\n')
            alert(`File validation errors:\n\n${errors}`)
        }
        
        // Filter to only valid files and append to existing selection
        const validFiles = files.filter((file, index) => 
            validationResults[index].valid
        )
        
        // Append new valid files to existing selection
        this.selectedFiles = [...this.selectedFiles, ...validFiles]
        
        // Create a new DataTransfer to update the file input with all selected files
        const dataTransfer = new DataTransfer()
        this.selectedFiles.forEach(file => {
            dataTransfer.items.add(file)
        })
        this.fileInputTarget.files = dataTransfer.files

        // Update preview
        if (this.selectedFiles.length > 0) {
            this.showPreview()
        } else {
            this.hidePreview()
        }
    }

    /**
     * Validate a single file
     * 
     * @param {File} file File to validate
     * @returns {Object} Validation result {valid: boolean, error: string}
     */
    validateFile(file) {
        // Check file size
        if (file.size > WaiverUploadController.MAX_FILE_SIZE) {
            return {
                valid: false,
                error: `${file.name}: File size (${this.formatFileSize(file.size)}) exceeds maximum of 25MB`
            }
        }

        // Check file type
        if (!WaiverUploadController.ALLOWED_TYPES.includes(file.type)) {
            return {
                valid: false,
                error: `${file.name}: Invalid file type (${file.type}). Only JPEG, PNG, and TIFF images are allowed.`
            }
        }

        return { valid: true }
    }

    /**
     * Format file size for display
     * 
     * @param {number} bytes File size in bytes
     * @returns {string} Formatted file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes'
        
        const k = 1024
        const sizes = ['Bytes', 'KB', 'MB', 'GB']
        const i = Math.floor(Math.log(bytes) / Math.log(k))
        
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
    }

    /**
     * Show file preview area with selected files
     */
    showPreview() {
        if (!this.hasPreviewTarget) return

        // Build preview HTML
        const previewList = document.getElementById('file-preview-list')
        if (!previewList) return

        previewList.innerHTML = ''
        
        this.selectedFiles.forEach((file, index) => {
            const item = document.createElement('div')
            item.className = 'list-group-item d-flex justify-content-between align-items-center'
            item.innerHTML = `
                <div>
                    <i class="bi bi-file-image text-primary"></i>
                    <strong>${this.escapeHtml(file.name)}</strong>
                    <br>
                    <small class="text-muted">${this.formatFileSize(file.size)}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" data-index="${index}">
                    <i class="bi bi-x"></i>
                </button>
            `
            
            // Add click handler to remove button
            const removeBtn = item.querySelector('button')
            removeBtn.addEventListener('click', () => this.removeFile(index))
            
            previewList.appendChild(item)
        })

        // Show preview area
        this.previewTarget.style.display = 'block'
    }

    /**
     * Hide file preview area
     */
    hidePreview() {
        if (!this.hasPreviewTarget) return
        this.previewTarget.style.display = 'none'
    }

    /**
     * Remove a file from selection
     * 
     * @param {number} index File index to remove
     */
    removeFile(index) {
        this.selectedFiles.splice(index, 1)
        
        if (this.selectedFiles.length === 0) {
            this.hidePreview()
            this.fileInputTarget.value = ''
        } else {
            // Update file input with remaining files
            const dataTransfer = new DataTransfer()
            this.selectedFiles.forEach(file => {
                dataTransfer.items.add(file)
            })
            this.fileInputTarget.files = dataTransfer.files
            
            this.showPreview()
        }
    }

    /**
     * Handle form submission
     * 
     * @param {Event} event Form submit event
     */
    handleSubmit(event) {
        // Validate waiver type is selected
        if (!this.waiverTypeTarget.value) {
            event.preventDefault()
            alert('Please select a waiver type')
            return
        }

        // Validate files are selected
        if (this.selectedFiles.length === 0) {
            event.preventDefault()
            alert('Please select at least one image file to upload')
            return
        }

        // Show progress bar
        if (this.hasProgressTarget) {
            this.progressTarget.style.display = 'block'
            this.updateProgress(0)
        }

        // Disable submit button
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true
            this.submitButtonTarget.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading & Converting...'
        }

        // Form will submit normally - progress will be indeterminate
        // since we're doing synchronous conversion on the server
        this.simulateProgress()
    }

    /**
     * Simulate upload progress (since conversion is synchronous)
     */
    simulateProgress() {
        let progress = 0
        const interval = setInterval(() => {
            progress += 5
            if (progress >= 95) {
                progress = 95 // Stop at 95% until server responds
                clearInterval(interval)
            }
            this.updateProgress(progress)
        }, 200)
    }

    /**
     * Update progress bar
     * 
     * @param {number} percent Progress percentage (0-100)
     */
    updateProgress(percent) {
        if (!this.hasProgressBarTarget || !this.hasProgressTextTarget) return
        
        this.progressBarTarget.style.width = `${percent}%`
        this.progressBarTarget.setAttribute('aria-valuenow', percent)
        this.progressTextTarget.textContent = `${Math.round(percent)}%`
    }

    /**
     * Escape HTML to prevent XSS
     * 
     * @param {string} text Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["waiver-upload"] = WaiverUploadController
