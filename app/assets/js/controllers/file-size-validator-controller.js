import { Controller } from "@hotwired/stimulus"

/**
 * File Size Validator Controller
 * 
 * Validates file sizes against PHP upload limits before submission.
 * Provides immediate feedback to users when files exceed server limits,
 * preventing failed uploads and improving user experience.
 * 
 * Features:
 * - Pre-upload file size validation
 * - Multiple file support
 * - Customizable warning messages
 * - Integration with existing upload controls
 * - Real-time feedback on file selection
 * 
 * Values:
 * - maxSize: Maximum file size in bytes (from PHP upload_max_filesize/post_max_size)
 * - maxSizeFormatted: Human-readable max size (e.g., '25MB')
 * - totalMaxSize: Maximum total size for multiple files (defaults to maxSize)
 * - showWarning: Whether to show warning messages (default: true)
 * 
 * Targets:
 * - fileInput: File input element(s) to monitor
 * - warning: Container for warning messages (optional)
 * - submitButton: Submit button to disable when files are invalid (optional)
 * 
 * Events Dispatched:
 * - file-size-validator:valid - All files are valid
 * - file-size-validator:invalid - One or more files exceed limits
 * - file-size-validator:warning - Warning displayed to user
 * 
 * Usage:
 * ```html
 * <div data-controller="file-size-validator"
 *      data-file-size-validator-max-size-value="26214400"
 *      data-file-size-validator-max-size-formatted-value="25MB">
 *   
 *   <input type="file" 
 *          data-file-size-validator-target="fileInput"
 *          data-action="change->file-size-validator#validateFiles">
 *   
 *   <div data-file-size-validator-target="warning" 
 *        class="alert alert-warning d-none"></div>
 *   
 *   <button type="submit" 
 *           data-file-size-validator-target="submitButton">
 *     Upload
 *   </button>
 * </div>
 * ```
 * 
 * @example Multiple Files
 * ```html
 * <input type="file" 
 *        multiple
 *        data-file-size-validator-target="fileInput"
 *        data-action="change->file-size-validator#validateFiles">
 * ```
 * 
 * @example Custom Total Limit
 * ```html
 * <div data-controller="file-size-validator"
 *      data-file-size-validator-max-size-value="26214400"
 *      data-file-size-validator-total-max-size-value="52428800">
 * ```
 */
class FileSizeValidatorController extends Controller {
    static targets = ["fileInput", "warning", "submitButton"]
    
    static values = {
        maxSize: Number,              // Maximum single file size in bytes
        maxSizeFormatted: String,     // Human-readable format (e.g., '25MB')
        totalMaxSize: Number,         // Maximum total size for multiple files
        showWarning: { type: Boolean, default: true },
        warningClass: { type: String, default: 'alert alert-warning' },
        errorClass: { type: String, default: 'alert alert-danger' }
    }

    /**
     * Initialize controller
     */
    connect() {
        console.log('FileSizeValidatorController connected', {
            maxSize: this.maxSizeValue,
            maxSizeFormatted: this.maxSizeFormattedValue,
            totalMaxSize: this.totalMaxSizeValue
        })
        
        // Default total max to single max if not specified
        if (!this.hasTotalMaxSizeValue) {
            this.totalMaxSizeValue = this.maxSizeValue
        }
        
        // Validate any pre-selected files
        if (this.hasFileInputTarget) {
            this.fileInputTargets.forEach(input => {
                if (input.files && input.files.length > 0) {
                    this.validateFiles({ target: input })
                }
            })
        }
    }

    /**
     * Validate selected files
     * 
     * @param {Event} event - File input change event
     */
    validateFiles(event) {
        const input = event.target
        
        // Collect all files from all file inputs in this controller's scope
        let allFiles = []
        
        if (this.hasFileInputTarget) {
            this.fileInputTargets.forEach(inputEl => {
                if (inputEl.files && inputEl.files.length > 0) {
                    allFiles = allFiles.concat(Array.from(inputEl.files))
                }
            })
        }
        
        if (allFiles.length === 0) {
            this.clearWarning()
            this.enableSubmit()
            return
        }

        const validation = this.checkFileSizes(allFiles)
        
        if (!validation.valid) {
            this.showInvalidFilesWarning(validation)
            this.disableSubmit()
            
            // Dispatch invalid event
            this.dispatch('invalid', { 
                detail: { 
                    files: validation.invalidFiles,
                    message: validation.message 
                } 
            })
        } else if (validation.warning) {
            this.showTotalSizeWarning(validation)
            
            // Still allow submission but warn user
            this.enableSubmit()
            
            // Dispatch warning event
            this.dispatch('warning', { 
                detail: { 
                    totalSize: validation.totalSize,
                    message: validation.message 
                } 
            })
        } else {
            this.clearWarning()
            this.enableSubmit()
            
            // Dispatch valid event
            this.dispatch('valid', { 
                detail: { 
                    files: allFiles.map(f => ({ name: f.name, size: f.size })),
                    totalSize: validation.totalSize 
                } 
            })
        }
    }

    /**
     * Check file sizes and return validation result
     * 
     * @param {File[]} files - Array of File objects
     * @returns {Object} Validation result
     */
    checkFileSizes(files) {
        const invalidFiles = []
        let totalSize = 0
        
        files.forEach(file => {
            totalSize += file.size
            
            if (file.size > this.maxSizeValue) {
                invalidFiles.push({
                    name: file.name,
                    size: file.size,
                    formattedSize: this.formatBytes(file.size),
                    exceededBy: file.size - this.maxSizeValue
                })
            }
        })
        
        // Check if any individual files exceed limit
        if (invalidFiles.length > 0) {
            return {
                valid: false,
                invalidFiles,
                totalSize,
                totalFileCount: files.length,
                message: this.buildInvalidFilesMessage(invalidFiles)
            }
        }
        
        // Check if total size exceeds limit (for multiple files or accumulated uploads)
        // Show warning when total size exceeds the post_max_size limit
        if (totalSize > this.totalMaxSizeValue) {
            return {
                valid: true,
                warning: true,
                totalSize,
                totalFileCount: files.length,
                formattedTotal: this.formatBytes(totalSize),
                message: this.buildTotalSizeWarningMessage(totalSize, files.length)
            }
        }
        
        return {
            valid: true,
            warning: false,
            totalSize,
            totalFileCount: files.length,
            formattedTotal: this.formatBytes(totalSize)
        }
    }

    /**
     * Escape HTML special characters to prevent XSS
     * 
     * @param {string} str - String to escape
     * @returns {string} Escaped string
     */
    escapeHtml(str) {
        const div = document.createElement('div')
        div.textContent = str
        return div.innerHTML
    }

    /**
     * Build error message for invalid files
     * 
     * @param {Array} invalidFiles - Array of invalid file objects
     * @returns {string} Error message with HTML-escaped file names
     */
    buildInvalidFilesMessage(invalidFiles) {
        const maxSize = this.maxSizeFormattedValue || this.formatBytes(this.maxSizeValue)
        
        if (invalidFiles.length === 1) {
            const file = invalidFiles[0]
            const escapedName = this.escapeHtml(file.name)
            return `The file "${escapedName}" (${file.formattedSize}) exceeds the maximum upload size of ${maxSize}.`
        }
        
        const fileList = invalidFiles.map(f => 
            `• ${this.escapeHtml(f.name)} (${f.formattedSize})`
        ).join('\n')
        
        return `${invalidFiles.length} file(s) exceed the maximum upload size of ${maxSize}:\n\n${fileList}\n\nPlease remove or replace these files before uploading.`
    }

    /**
     * Build warning message for total size
     * 
     * @param {number} totalSize - Total size in bytes
     * @param {number} fileCount - Number of files
     * @returns {string} Warning message
     */
    buildTotalSizeWarningMessage(totalSize, fileCount) {
        const totalFormatted = this.formatBytes(totalSize)
        const maxFormatted = this.formatBytes(this.totalMaxSizeValue)
        
        if (fileCount === 1) {
            return `Warning: The file size (${totalFormatted}) exceeds the recommended upload limit of ${maxFormatted}. The upload may fail depending on server configuration.`
        }
        
        return `Warning: You have selected ${fileCount} file(s) with a combined size of ${totalFormatted}, which exceeds the recommended limit of ${maxFormatted}. The upload may fail depending on server configuration.`
    }

    /**
     * Show warning for invalid files
     * 
     * @param {Object} validation - Validation result
     */
    showInvalidFilesWarning(validation) {
        if (!this.showWarningValue || !this.hasWarningTarget) {
            // Still show browser alert if no warning target
            alert(validation.message)
            return
        }
        
        this.warningTarget.innerHTML = this.formatWarningMessage(validation.message, 'error')
        this.warningTarget.className = this.errorClassValue
        this.warningTarget.classList.remove('d-none')
    }

    /**
     * Show warning for total size
     * 
     * @param {Object} validation - Validation result
     */
    showTotalSizeWarning(validation) {
        if (!this.showWarningValue || !this.hasWarningTarget) {
            // Still show browser alert if no warning target
            alert(validation.message)
            return
        }
        
        this.warningTarget.innerHTML = this.formatWarningMessage(validation.message, 'warning')
        this.warningTarget.className = this.warningClassValue
        this.warningTarget.classList.remove('d-none')
    }

    /**
     * Format warning message with icon
     * 
     * @param {string} message - Warning message
     * @param {string} type - Message type ('error' or 'warning')
     * @returns {string} Formatted HTML
     */
    formatWarningMessage(message, type = 'warning') {
        const icon = type === 'error' 
            ? '<i class="bi bi-exclamation-triangle-fill"></i>'
            : '<i class="bi bi-exclamation-circle-fill"></i>'
        
        // Preserve line breaks
        const formattedMessage = message.replace(/\n/g, '<br>')
        
        return `${icon} ${formattedMessage}`
    }

    /**
     * Clear warning message
     */
    clearWarning() {
        if (this.hasWarningTarget) {
            this.warningTarget.classList.add('d-none')
            this.warningTarget.innerHTML = ''
        }
    }

    /**
     * Disable submit button
     */
    disableSubmit() {
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTargets.forEach(button => {
                button.disabled = true
            })
        }
    }

    /**
     * Enable submit button
     */
    enableSubmit() {
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTargets.forEach(button => {
                button.disabled = false
            })
        }
    }

    /**
     * Format bytes to human-readable string
     * 
     * @param {number} bytes - Size in bytes
     * @param {number} decimals - Number of decimal places
     * @returns {string} Formatted size string
     */
    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes'
        
        const k = 1024
        const dm = decimals < 0 ? 0 : decimals
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
        
        const i = Math.floor(Math.log(bytes) / Math.log(k))
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + sizes[i]
    }

    /**
     * Dispatch custom event
     * 
     * @param {string} eventName - Event name (without prefix)
     * @param {Object} options - Event options
     */
    dispatch(eventName, options = {}) {
        const event = new CustomEvent(`file-size-validator:${eventName}`, {
            bubbles: true,
            cancelable: true,
            ...options
        })
        
        this.element.dispatchEvent(event)
    }
}

// Register controller
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["file-size-validator"] = FileSizeValidatorController

export default FileSizeValidatorController
