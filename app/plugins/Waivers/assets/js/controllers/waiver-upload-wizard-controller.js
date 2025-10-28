import { Controller } from "@hotwired/stimulus"

/**
 * Waiver Upload Wizard Controller
 * 
 * Manages a multi-step wizard for uploading waivers:
 * Step 1: Select activities
 * Step 2: Select waiver type (filtered by selected activities)
 * Step 3: Add waiver pages/images
 * Step 4: Review details
 * Step 5: Confirmation
 * 
 * All data is held client-side until final submission.
 */
class WaiverUploadWizardController extends Controller {
    static targets = [
        "step",
        "stepIndicator",
        "prevButton",
        "nextButton",
        "submitButton",
        "activityCheckbox",
        "waiverTypeOption",
        "waiverTypeSelect",
        "pagesList",
        "pagesPreview",
        "fileInput",
        "reviewActivities",
        "reviewWaiverType",
        "reviewPageCount",
        "reviewPagesList",
        "notesField",
        "progressBar"
    ]

    static values = {
        currentStep: { type: Number, default: 1 },
        totalSteps: { type: Number, default: 4 },
        gatheringId: Number,
        maxFileSize: Number,           // Maximum single file size in bytes
        totalMaxSize: Number           // Maximum total upload size in bytes
    }

    connect() {
        console.log("Waiver Upload Wizard connected")
        this.uploadedPages = []
        this.selectedActivities = []
        this.selectedWaiverType = null
        this.notes = ""
        
        this.showStep(1)
    }

    // Step Navigation
    nextStep() {
        if (this.validateCurrentStep()) {
            if (this.currentStepValue < this.totalStepsValue) {
                this.currentStepValue++
                this.showStep(this.currentStepValue)
            }
        }
    }

    prevStep() {
        if (this.currentStepValue > 1) {
            this.currentStepValue--
            this.showStep(this.currentStepValue)
        }
    }

    goToStep(event) {
        const step = parseInt(event.currentTarget.dataset.step)
        if (step < this.currentStepValue) {
            this.currentStepValue = step
            this.showStep(this.currentStepValue)
        }
    }

    showStep(stepNumber) {
        // Hide all steps
        this.stepTargets.forEach(step => {
            step.classList.add('d-none')
        })

        // Show current step
        const currentStep = this.stepTargets.find(step => 
            parseInt(step.dataset.stepNumber) === stepNumber
        )
        if (currentStep) {
            currentStep.classList.remove('d-none')
        }

        // Update step indicators
        this.updateStepIndicators(stepNumber)

        // Update navigation buttons
        this.updateNavigationButtons(stepNumber)

        // Update progress bar
        this.updateProgressBar(stepNumber)

        // Perform step-specific actions
        this.onStepChange(stepNumber)
    }

    updateStepIndicators(currentStep) {
        this.stepIndicatorTargets.forEach(indicator => {
            const step = parseInt(indicator.dataset.step)
            indicator.classList.remove('active', 'completed')
            
            if (step === currentStep) {
                indicator.classList.add('active')
            } else if (step < currentStep) {
                indicator.classList.add('completed')
            }
        })
    }

    updateNavigationButtons(stepNumber) {
        // Previous button
        if (this.hasPrevButtonTarget) {
            if (stepNumber === 1) {
                this.prevButtonTarget.classList.add('d-none')
            } else {
                this.prevButtonTarget.classList.remove('d-none')
            }
        }

        // Next button
        if (this.hasNextButtonTarget) {
            if (stepNumber === this.totalStepsValue) {
                this.nextButtonTarget.classList.add('d-none')
            } else {
                this.nextButtonTarget.classList.remove('d-none')
                
                // Update button text based on step
                if (stepNumber === 3) {
                    this.nextButtonTarget.innerHTML = '<i class="bi bi-arrow-right"></i> Review'
                } else {
                    this.nextButtonTarget.innerHTML = '<i class="bi bi-arrow-right"></i> Next'
                }
            }
        }

        // Submit button
        if (this.hasSubmitButtonTarget) {
            if (stepNumber === this.totalStepsValue) {
                this.submitButtonTarget.classList.remove('d-none')
            } else {
                this.submitButtonTarget.classList.add('d-none')
            }
        }
    }

    updateProgressBar(stepNumber) {
        if (this.hasProgressBarTarget) {
            const progress = (stepNumber / this.totalStepsValue) * 100
            this.progressBarTarget.style.width = `${progress}%`
            this.progressBarTarget.setAttribute('aria-valuenow', progress)
        }
    }

    onStepChange(stepNumber) {
        switch(stepNumber) {
            case 2:
                this.updateWaiverTypeOptions()
                break
            case 4:
                this.updateReviewSection()
                break
        }
    }

    // Step 1: Activity Selection
    toggleActivity(event) {
        const checkbox = event.currentTarget
        const activityId = parseInt(checkbox.value)
        const activityName = checkbox.dataset.name

        if (checkbox.checked) {
            if (!this.selectedActivities.find(a => a.id === activityId)) {
                this.selectedActivities.push({
                    id: activityId,
                    name: activityName,
                    waiverTypes: JSON.parse(checkbox.dataset.waiverTypes || '[]')
                })
            }
        } else {
            this.selectedActivities = this.selectedActivities.filter(a => a.id !== activityId)
        }

        console.log("Selected activities:", this.selectedActivities)
    }

    validateStep1() {
        if (this.selectedActivities.length === 0) {
            this.showError("Please select at least one activity")
            return false
        }
        return true
    }

    // Step 2: Waiver Type Selection
    updateWaiverTypeOptions() {
        if (!this.hasWaiverTypeSelectTarget) return

        // Get waiver types that are common to all selected activities
        const commonWaiverTypes = this.getCommonWaiverTypes()

        console.log("Common waiver types:", commonWaiverTypes)

        // Show/hide waiver type options (card divs)
        this.waiverTypeOptionTargets.forEach(optionCard => {
            const waiverTypeId = parseInt(optionCard.dataset.waiverTypeId)
            console.log("Checking waiver type:", waiverTypeId, "in common:", commonWaiverTypes)
            
            if (commonWaiverTypes.includes(waiverTypeId)) {
                optionCard.classList.remove('d-none')
            } else {
                optionCard.classList.add('d-none')
                // Deselect radio button if hidden
                const radioInput = optionCard.querySelector('input[type="radio"]')
                if (radioInput && radioInput.checked) {
                    radioInput.checked = false
                }
            }
        })

        // If no common waiver types, show warning
        if (commonWaiverTypes.length === 0) {
            this.showError("No waiver types are required by all selected activities. Please adjust your activity selection.")
        }
    }

    getCommonWaiverTypes() {
        if (this.selectedActivities.length === 0) return []

        // Start with waiver types from first activity
        let common = [...this.selectedActivities[0].waiverTypes]

        // Find intersection with other activities
        for (let i = 1; i < this.selectedActivities.length; i++) {
            const activityTypes = this.selectedActivities[i].waiverTypes
            common = common.filter(typeId => activityTypes.includes(typeId))
        }

        return common
    }

    selectWaiverType(event) {
        const option = event.currentTarget
        this.selectedWaiverType = {
            id: parseInt(option.value),
            name: option.dataset.name
        }
        console.log("Selected waiver type:", this.selectedWaiverType)
    }

    validateStep2() {
        if (!this.selectedWaiverType) {
            this.showError("Please select a waiver type")
            return false
        }
        return true
    }

    // Step 3: Add Pages
    triggerFileInput() {
        if (this.hasFileInputTarget) {
            this.fileInputTarget.click()
        }
    }

    handleFileSelect(event) {
        const files = Array.from(event.target.files)
        
        // Get max file size (use configured value or fallback to 10MB)
        const maxFileSize = this.hasMaxFileSizeValue ? this.maxFileSizeValue : (10 * 1024 * 1024)
        const totalMaxSize = this.hasTotalMaxSizeValue ? this.totalMaxSizeValue : maxFileSize
        
        // Calculate current total size
        const currentTotalSize = this.uploadedPages.reduce((sum, page) => sum + page.size, 0)
        
        files.forEach(file => {
            // Validate file type
            if (!this.isValidImageFile(file)) {
                this.showError(`Invalid file type: ${file.name}. Please upload JPEG, PNG, or TIFF images.`)
                return
            }

            // Validate individual file size
            if (file.size > maxFileSize) {
                const maxFormatted = this.formatBytes(maxFileSize)
                const fileFormatted = this.formatBytes(file.size)
                this.showError(`File too large: ${file.name} (${fileFormatted}). Maximum size per file is ${maxFormatted}.`)
                return
            }

            // Check if adding this file would exceed total size limit
            const newTotalSize = currentTotalSize + file.size
            if (newTotalSize > totalMaxSize) {
                const totalFormatted = this.formatBytes(newTotalSize)
                const maxFormatted = this.formatBytes(totalMaxSize)
                const currentFormatted = this.formatBytes(currentTotalSize)
                const fileFormatted = this.formatBytes(file.size)
                
                this.showError(
                    `Cannot add ${file.name} (${fileFormatted}). ` +
                    `Current total: ${currentFormatted}. ` +
                    `Adding this file would exceed the maximum total upload size of ${maxFormatted} ` +
                    `(would be ${totalFormatted}).`
                )
                return
            }

            // Add to uploaded pages
            this.addPage(file)
        })

        // Clear input so same file can be selected again
        event.target.value = ''
        
        // Show total size info if we have files
        if (this.uploadedPages.length > 0) {
            this.updateTotalSizeDisplay()
        }
    }

    isValidImageFile(file) {
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/tiff']
        return validTypes.includes(file.type)
    }

    addPage(file) {
        const pageNumber = this.uploadedPages.length + 1
        const reader = new FileReader()

        reader.onload = (e) => {
            const page = {
                file: file,
                dataUrl: e.target.result,
                number: pageNumber,
                name: file.name,
                size: file.size
            }

            this.uploadedPages.push(page)
            this.renderPages()
        }

        reader.readAsDataURL(file)
    }

    removePage(event) {
        const index = parseInt(event.currentTarget.dataset.index)
        this.uploadedPages.splice(index, 1)
        
        // Renumber pages
        this.uploadedPages.forEach((page, idx) => {
            page.number = idx + 1
        })

        this.renderPages()
        
        // Update total size display after removal
        if (this.uploadedPages.length > 0) {
            this.updateTotalSizeDisplay()
        }
    }

    renderPages() {
        if (!this.hasPagesPreviewTarget) return

        if (this.uploadedPages.length === 0) {
            this.pagesPreviewTarget.innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="bi bi-file-earmark-image" style="font-size: 3rem;"></i>
                    <p class="mt-3">No pages added yet</p>
                    <p class="small">Click "Add Page" to select images</p>
                </div>
            `
            return
        }

        const html = this.uploadedPages.map((page, index) => `
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <img src="${page.dataUrl}" 
                                     class="img-thumbnail" 
                                     style="width: 100px; height: 130px; object-fit: cover;"
                                     alt="Page ${page.number}">
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>Page ${page.number}</strong><br>
                                        <small class="text-muted">${page.name}</small><br>
                                        <small class="text-muted">${this.formatFileSize(page.size)}</small>
                                    </div>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger"
                                            data-index="${index}"
                                            data-action="click->waiver-upload-wizard#removePage">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('')

        this.pagesPreviewTarget.innerHTML = html
    }

    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B'
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
    }

    validateStep3() {
        if (this.uploadedPages.length === 0) {
            this.showError("Please add at least one page")
            return false
        }
        return true
    }

    // Step 4: Review
    updateReviewSection() {
        // Activities
        if (this.hasReviewActivitiesTarget) {
            const html = this.selectedActivities.map(activity => 
                `<li>${activity.name}</li>`
            ).join('')
            this.reviewActivitiesTarget.innerHTML = html
        }

        // Waiver Type
        if (this.hasReviewWaiverTypeTarget && this.selectedWaiverType) {
            this.reviewWaiverTypeTarget.textContent = this.selectedWaiverType.name
        }

        // Page Count
        if (this.hasReviewPageCountTarget) {
            this.reviewPageCountTarget.textContent = this.uploadedPages.length
        }

        // Pages List
        if (this.hasReviewPagesListTarget) {
            const html = this.uploadedPages.map(page => `
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <img src="${page.dataUrl}" 
                             class="card-img-top" 
                             style="height: 200px; object-fit: cover;"
                             alt="Page ${page.number}">
                        <div class="card-body p-2 text-center">
                            <small>Page ${page.number}</small>
                        </div>
                    </div>
                </div>
            `).join('')
            this.reviewPagesListTarget.innerHTML = html
        }

        // Get notes if available
        if (this.hasNotesFieldTarget) {
            this.notes = this.notesFieldTarget.value
        }
    }

    validateStep4() {
        // Final validation before submission
        return this.validateStep1() && this.validateStep2() && this.validateStep3()
    }

    // Form Submission
    async submitForm(event) {
        event.preventDefault()

        if (!this.validateStep4()) {
            return
        }

        // Disable submit button
        this.submitButtonTarget.disabled = true
        this.submitButtonTarget.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Uploading...'

        try {
            const formData = new FormData()
            
            // Add gathering ID
            formData.append('gathering_id', this.gatheringIdValue)

            // Add waiver type
            formData.append('waiver_type_id', this.selectedWaiverType.id)

            // Add activity IDs
            this.selectedActivities.forEach(activity => {
                formData.append('activity_ids[]', activity.id)
            })

            // Add notes
            formData.append('notes', this.notes)

            // Add all page files
            this.uploadedPages.forEach((page, index) => {
                formData.append('waiver_images[]', page.file)
            })

            // Get CSRF token and add to form data (CakePHP expects it in the body)
            const csrfToken = this.getCsrfToken()
            if (csrfToken) {
                formData.append('_csrfToken', csrfToken)
            }
            
            // Submit via fetch
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                // Check content type
                const contentType = response.headers.get('content-type')
                console.log('Response content-type:', contentType)
                
                // If response is HTML (redirect happened), follow it
                if (contentType && contentType.includes('text/html')) {
                    console.log('Received HTML response, following redirect')
                    const html = await response.text()
                    // Check if it contains a redirect meta tag or just reload
                    window.location.reload()
                    return
                }
                
                // Parse JSON response
                const data = await response.json().catch((err) => {
                    console.error('Failed to parse JSON response:', err)
                    return {}
                })
                
                console.log('Upload response data:', data)
                
                // If we have a redirect URL (mobile mode), redirect immediately
                if (data.redirectUrl) {
                    console.log('Redirecting to:', data.redirectUrl)
                    window.location.href = data.redirectUrl
                    return
                }
                
                // Otherwise show success step (desktop mode)
                this.showSuccessStep()
            } else {
                const data = await response.json().catch(() => ({}))
                this.showError(data.message || 'Upload failed. Please try again.')
                this.submitButtonTarget.disabled = false
                this.submitButtonTarget.innerHTML = '<i class="bi bi-check-circle"></i> Upload Waiver'
            }

        } catch (error) {
            console.error('Upload error:', error)
            this.showError('An error occurred during upload. Please try again.')
            this.submitButtonTarget.disabled = false
            this.submitButtonTarget.innerHTML = '<i class="bi bi-check-circle"></i> Upload Waiver'
        }
    }

    showSuccessStep() {
        // Hide all regular steps
        this.stepTargets.forEach(step => step.classList.add('d-none'))

        // Show success message
        const successHtml = `
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                </div>
                <h2 class="mb-3">Waiver Uploaded Successfully!</h2>
                <p class="lead text-muted mb-4">
                    Your waiver has been uploaded and is being processed.
                </p>
                <div class="alert alert-info d-inline-block">
                    <i class="bi bi-info-circle"></i>
                    Uploaded ${this.uploadedPages.length} page(s) for ${this.selectedActivities.length} activity(s)
                </div>
                <div class="mt-4">
                    <p class="text-muted">Redirecting to gathering view...</p>
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        `

        const container = this.element.querySelector('.wizard-container') || this.element
        container.innerHTML = successHtml

        // Redirect after 2 seconds
        setTimeout(() => {
            window.location.href = `/gatherings/view/${this.gatheringIdValue}`
        }, 2000)
    }

    // Validation
    validateCurrentStep() {
        switch(this.currentStepValue) {
            case 1: return this.validateStep1()
            case 2: return this.validateStep2()
            case 3: return this.validateStep3()
            case 4: return this.validateStep4()
            default: return true
        }
    }

    // Error Handling
    showError(message) {
        // Create toast notification
        const toastHtml = `
            <div class="toast align-items-center text-white bg-danger border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-exclamation-triangle me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `

        // Add to toast container or create one
        let container = document.querySelector('.toast-container')
        if (!container) {
            container = document.createElement('div')
            container.className = 'toast-container position-fixed top-0 end-0 p-3'
            document.body.appendChild(container)
        }

        container.insertAdjacentHTML('beforeend', toastHtml)
        const toastElement = container.lastElementChild
        const toast = new bootstrap.Toast(toastElement)
        toast.show()

        // Remove after hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove()
        })
    }

    getCsrfToken() {
        // Try to get from meta tag first (CakePHP default)
        const metaTag = document.querySelector('meta[name="csrfToken"]')
        if (metaTag) {
            return metaTag.content
        }

        // Try to get from cookie as fallback
        const match = document.cookie.match(/csrfToken=([^;]+)/)
        if (match) {
            return match[1]
        }

        // Try to get from hidden input in any form
        const hiddenInput = document.querySelector('input[name="_csrfToken"]')
        if (hiddenInput) {
            return hiddenInput.value
        }

        console.error('CSRF token not found')
        return ''
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
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
    }
    
    /**
     * Update the display to show current total size
     */
    updateTotalSizeDisplay() {
        const totalSize = this.uploadedPages.reduce((sum, page) => sum + page.size, 0)
        const totalFormatted = this.formatBytes(totalSize)
        
        // If we're getting close to the limit, show a warning
        if (this.hasTotalMaxSizeValue) {
            const percentUsed = (totalSize / this.totalMaxSizeValue) * 100
            
            if (percentUsed > 80 && percentUsed <= 100) {
                // Show warning when using 80-100% of limit
                const remaining = this.totalMaxSizeValue - totalSize
                const remainingFormatted = this.formatBytes(remaining)
                const maxFormatted = this.formatBytes(this.totalMaxSizeValue)
                
                console.warn(
                    `Upload size warning: ${totalFormatted} of ${maxFormatted} used. ` +
                    `${remainingFormatted} remaining.`
                )
            }
        }
    }
}

// Register controller
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["waiver-upload-wizard"] = WaiverUploadWizardController

export default WaiverUploadWizardController
