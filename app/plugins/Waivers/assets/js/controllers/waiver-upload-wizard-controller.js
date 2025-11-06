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
        "submitButtonText",
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
        "progressBar",
        "uploadSection",
        "attestSection",
        "attestReasonList",
        "attestNotes",
        "reviewUploadSection",
        "reviewAttestSection",
        "reviewAttestReason",
        "reviewAttestNotes",
        "reviewAttestNotesSection",
        "modeToggle",
        "step3Lead"
    ]

    static values = {
        currentStep: { type: Number, default: 1 },
        totalSteps: { type: Number, default: 4 },
        gatheringId: Number,
        gatheringPublicId: String,
        maxFileSize: Number,           // Maximum single file size in bytes
        totalMaxSize: Number,          // Maximum total upload size in bytes
        preSelectedActivityId: Number, // Pre-selected activity ID from URL
        preSelectedWaiverTypeId: Number // Pre-selected waiver type ID from URL
    }

    connect() {
        console.log("Waiver Upload Wizard connected")
        this.uploadedPages = []
        this.selectedActivities = []
        this.selectedWaiverType = null
        this.notes = ""
        this.isAttestMode = false  // Track if we're in attestation mode
        this.attestReason = null
        this.attestNotes = ""
        
        // Handle pre-selected values (skip to step 3 if both are provided)
        if (this.hasPreSelectedActivityIdValue && this.hasPreSelectedWaiverTypeIdValue) {
            // Use setTimeout to ensure DOM is fully ready
            setTimeout(() => {
                // Pre-select the activity checkbox
                const activityCheckbox = this.activityCheckboxTargets.find(
                    cb => parseInt(cb.value) === this.preSelectedActivityIdValue
                )
                if (activityCheckbox) {
                    activityCheckbox.checked = true
                    // Manually populate selectedActivities array
                    this.selectedActivities = [{
                        id: this.preSelectedActivityIdValue,
                        name: activityCheckbox.dataset.name,
                        waiverTypes: JSON.parse(activityCheckbox.dataset.waiverTypes || '[]')
                    }]
                }
                
                // Pre-select the waiver type
                // Find the waiver type radio button to get both ID and name
                const waiverTypeRadio = document.querySelector(
                    `input[name="waiver_type"][value="${this.preSelectedWaiverTypeIdValue}"]`
                )
                if (waiverTypeRadio) {
                    waiverTypeRadio.checked = true
                    this.selectedWaiverType = {
                        id: this.preSelectedWaiverTypeIdValue,
                        name: waiverTypeRadio.dataset.name
                    }
                }
                
                // Jump directly to step 3 (file upload)
                this.currentStepValue = 3
                this.showStep(3)
                
                // Explicitly check attestation availability after showing step 3
                // (in case onStepChange doesn't fire properly)
                setTimeout(() => {
                    this.checkAttestationAvailability()
                }, 150)
            }, 100)
        } else {
            // Normal flow - start at step 1
            this.showStep(1)
        }
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
                // Update submit button text based on mode
                if (this.hasSubmitButtonTextTarget) {
                    this.submitButtonTextTarget.textContent = this.isAttestMode ? 'Submit Attestation' : 'Submit Waivers'
                }
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
            case 3:
                this.checkAttestationAvailability()
                break
            case 4:
                this.updateReviewSection()
                break
        }
    }

    // Check if attestation is available for selected waiver type
    checkAttestationAvailability() {
        console.log("Checking attestation availability, selectedWaiverType:", this.selectedWaiverType)
        
        if (!this.selectedWaiverType) {
            console.log("No waiver type selected yet")
            return
        }

        // Find the waiver type to get exemption reasons
        const waiverTypeRadio = document.querySelector(
            `input[name="waiver_type"][value="${this.selectedWaiverType.id}"]`
        )
        
        console.log("Found waiver type radio:", waiverTypeRadio)
        
        let exemptionReasons = []
        if (waiverTypeRadio && waiverTypeRadio.dataset.exemptionReasons) {
            try {
                exemptionReasons = JSON.parse(waiverTypeRadio.dataset.exemptionReasons)
                console.log("Parsed exemption reasons:", exemptionReasons)
            } catch (e) {
                console.error('Failed to parse exemption reasons:', e)
            }
        } else {
            console.log("No exemption reasons data attribute found")
        }

        // Show/hide mode toggle and update lead text based on exemption reasons availability
        if (exemptionReasons.length > 0) {
            console.log("Exemption reasons available - showing toggle")
            // Has exemption reasons - show toggle
            if (this.hasModeToggleTarget) {
                this.modeToggleTarget.classList.remove('d-none')
            } else {
                console.log("WARNING: modeToggleTarget not available")
            }
            if (this.hasStep3LeadTarget) {
                this.step3LeadTarget.textContent = 'Add one or more pages to your waiver document, or attest that a waiver is not needed'
            } else {
                console.log("WARNING: step3LeadTarget not available")
            }
        } else {
            console.log("No exemption reasons - hiding toggle, forcing upload mode")
            // No exemption reasons - hide toggle, force upload mode
            if (this.hasModeToggleTarget) {
                this.modeToggleTarget.classList.add('d-none')
            }
            if (this.hasStep3LeadTarget) {
                this.step3LeadTarget.textContent = 'Add one or more pages to your waiver document'
            }
            // Force upload mode
            this.isAttestMode = false
            const uploadRadio = document.getElementById('mode-upload')
            if (uploadRadio) {
                uploadRadio.checked = true
            }
            if (this.hasUploadSectionTarget && this.hasAttestSectionTarget) {
                this.uploadSectionTarget.classList.remove('d-none')
                this.attestSectionTarget.classList.add('d-none')
            }
        }

        // Populate attestation reasons if available
        if (exemptionReasons.length > 0) {
            this.populateAttestationReasons()
        }
    }

    // Step 3: Mode Toggle (Upload vs Attest)
    setModeUpload(event) {
        this.isAttestMode = false
        if (this.hasUploadSectionTarget && this.hasAttestSectionTarget) {
            this.uploadSectionTarget.classList.remove('d-none')
            this.attestSectionTarget.classList.add('d-none')
        }
    }

    setModeAttest(event) {
        // Verify exemption reasons are available before allowing switch
        if (!this.selectedWaiverType) return

        const waiverTypeRadio = document.querySelector(
            `input[name="waiver_type"][value="${this.selectedWaiverType.id}"]`
        )
        
        let exemptionReasons = []
        if (waiverTypeRadio && waiverTypeRadio.dataset.exemptionReasons) {
            try {
                exemptionReasons = JSON.parse(waiverTypeRadio.dataset.exemptionReasons)
            } catch (e) {
                console.error('Failed to parse exemption reasons:', e)
            }
        }

        if (exemptionReasons.length === 0) {
            // No exemption reasons - prevent switching to attest mode
            this.showError('Attestation is not available for this waiver type.')
            const uploadRadio = document.getElementById('mode-upload')
            if (uploadRadio) {
                uploadRadio.checked = true
            }
            return
        }

        this.isAttestMode = true
        if (this.hasUploadSectionTarget && this.hasAttestSectionTarget) {
            this.uploadSectionTarget.classList.add('d-none')
            this.attestSectionTarget.classList.remove('d-none')
        }
        this.populateAttestationReasons()
    }

    populateAttestationReasons() {
        if (!this.hasAttestReasonListTarget || !this.selectedWaiverType) return

        // Find the waiver type to get exemption reasons
        const waiverTypeRadio = document.querySelector(
            `input[name="waiver_type"][value="${this.selectedWaiverType.id}"]`
        )
        
        let exemptionReasons = []
        if (waiverTypeRadio && waiverTypeRadio.dataset.exemptionReasons) {
            try {
                exemptionReasons = JSON.parse(waiverTypeRadio.dataset.exemptionReasons)
            } catch (e) {
                console.error('Failed to parse exemption reasons:', e)
            }
        }

        if (exemptionReasons.length === 0) {
            this.attestReasonListTarget.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    No exemption reasons have been configured for this waiver type.
                </div>
            `
            return
        }

        // Build radio buttons
        let html = '<div class="list-group">'
        exemptionReasons.forEach((reason, index) => {
            const id = `attest_reason_${index}`
            html += `
                <label class="list-group-item list-group-item-action">
                    <input class="form-check-input me-2" type="radio" name="attest_reason" 
                           id="${id}" value="${this.escapeHtml(reason)}"
                           data-action="change->waiver-upload-wizard#selectAttestReason">
                    ${this.escapeHtml(reason)}
                </label>
            `
        })
        html += '</div>'

        this.attestReasonListTarget.innerHTML = html
    }

    selectAttestReason(event) {
        this.attestReason = event.currentTarget.value
        console.log("Selected attestation reason:", this.attestReason)
    }

    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
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
        
        // Get max file size (use configured value or fallback to 5MB)
        const maxFileSize = this.hasMaxFileSizeValue ? this.maxFileSizeValue : (5 * 1024 * 1024)
        const totalMaxSize = this.hasTotalMaxSizeValue ? this.totalMaxSizeValue : maxFileSize
        
        // Calculate current total size
        const currentTotalSize = this.uploadedPages.reduce((sum, page) => sum + page.size, 0)
        
        files.forEach(file => {
            // Validate file type
            if (!this.isValidImageFile(file)) {
                this.showError(`Invalid file type: ${file.name}. Please upload raster images only (JPEG, PNG, GIF, BMP, or WEBP). SVG and TIFF files are not supported.`)
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
        const validTypes = [
            'image/jpeg', 
            'image/jpg', 
            'image/png', 
            'image/gif', 
            'image/bmp',
            'image/webp',
            'image/x-ms-bmp', // Alternative MIME type for BMP
            'image/x-windows-bmp' // Another BMP variant
        ]
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
        if (this.isAttestMode) {
            // Validate attestation mode
            if (!this.attestReason) {
                this.showError("Please select a reason for the exemption")
                return false
            }
            return true
        } else {
            // Validate upload mode
            if (this.uploadedPages.length === 0) {
                this.showError("Please add at least one page")
                return false
            }
            return true
        }
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

        // Show/hide sections based on mode
        if (this.isAttestMode) {
            // Attestation Mode
            if (this.hasReviewUploadSectionTarget) {
                this.reviewUploadSectionTarget.classList.add('d-none')
            }
            if (this.hasReviewAttestSectionTarget) {
                this.reviewAttestSectionTarget.classList.remove('d-none')
            }

            // Display attestation reason
            if (this.hasReviewAttestReasonTarget) {
                this.reviewAttestReasonTarget.textContent = this.attestReason || 'Not selected'
            }

            // Get notes from attest section
            if (this.hasAttestNotesTarget) {
                this.attestNotes = this.attestNotesTarget.value
            }

            // Display attestation notes if provided
            if (this.hasReviewAttestNotesTarget && this.hasReviewAttestNotesSectionTarget) {
                if (this.attestNotes && this.attestNotes.trim().length > 0) {
                    this.reviewAttestNotesTarget.textContent = this.attestNotes
                    this.reviewAttestNotesSectionTarget.classList.remove('d-none')
                } else {
                    this.reviewAttestNotesSectionTarget.classList.add('d-none')
                }
            }
        } else {
            // Upload Mode
            if (this.hasReviewUploadSectionTarget) {
                this.reviewUploadSectionTarget.classList.remove('d-none')
            }
            if (this.hasReviewAttestSectionTarget) {
                this.reviewAttestSectionTarget.classList.add('d-none')
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

        // Disable submit button and show processing page immediately
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true
        }
        this.showProcessingStep()

        // Track when we started (for minimum 2-second display)
        const startTime = Date.now()

        try {
            if (this.isAttestMode) {
                // Submit attestation
                await this.submitAttestation(startTime)
            } else {
                // Submit waiver upload
                await this.submitWaiverUpload(startTime)
            }
        } catch (error) {
            console.error('Submission error:', error)
            this.showError('An error occurred during submission. Please try again.')
            if (this.hasSubmitButtonTarget) {
                this.submitButtonTarget.disabled = false
            }
            if (this.hasSubmitButtonTextTarget) {
                this.submitButtonTextTarget.textContent = this.isAttestMode ? 'Submit Attestation' : 'Submit Waivers'
            }
        }
    }

    async submitAttestation(startTime) {
        // Submit attestation for all selected activities at once
        // Create ONE exemption waiver associated with multiple activities
        const activityIds = this.selectedActivities.map(activity => activity.id)
        
        const response = await fetch('/waivers/gathering-waivers/attest', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.getCsrfToken()
            },
            body: JSON.stringify({
                gathering_id: this.gatheringIdValue,
                gathering_activity_ids: activityIds,  // Send all activity IDs
                waiver_type_id: this.selectedWaiverType.id,
                reason: this.attestReason,
                notes: this.attestNotes
            })
        })

        try {
            const data = await response.json()
            
            if (response.ok && data.success) {
                // Attestation succeeded
                const elapsed = Date.now() - startTime
                const remainingTime = Math.max(0, 2000 - elapsed)
                
                setTimeout(() => {
                    if (data.redirectUrl) {
                        window.location.href = data.redirectUrl
                    } else {
                        // Fallback redirect using public_id
                        window.location.href = `/gatherings/view/${this.gatheringPublicIdValue}?tab=gathering-waivers`
                    }
                }, remainingTime)
            } else {
                // Attestation failed
                this.showError(data.message || 'Attestation failed. Please try again.')
                if (this.hasSubmitButtonTarget) {
                    this.submitButtonTarget.disabled = false
                }
                if (this.hasSubmitButtonTextTarget) {
                    this.submitButtonTextTarget.textContent = 'Submit Attestation'
                }
            }
        } catch (error) {
            this.showError('Network error. Please try again.')
            if (this.hasSubmitButtonTarget) {
                this.submitButtonTarget.disabled = false
            }
            if (this.hasSubmitButtonTextTarget) {
                this.submitButtonTextTarget.textContent = 'Submit Attestation'
            }
        }
    }

    async submitWaiverUpload(startTime) {
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
            // Parse JSON response
            const data = await response.json().catch((err) => {
                console.error('Failed to parse JSON response:', err)
                return {}
            })
            
            console.log('Upload response data:', data)
            
            // Calculate how long to wait (minimum 2 seconds total)
            const elapsed = Date.now() - startTime
            const remainingTime = Math.max(0, 2000 - elapsed)
            
            // Wait for remaining time, then redirect
            setTimeout(() => {
                if (data.redirectUrl) {
                    console.log('Redirecting to:', data.redirectUrl)
                    window.location.href = data.redirectUrl
                } else {
                    // Fallback redirect
                    window.location.href = `/gatherings/view/${this.gatheringIdValue}`
                }
            }, remainingTime)
            
        } else {
            const data = await response.json().catch(() => ({}))
            this.showError(data.message || 'Upload failed. Please try again.')
            if (this.hasSubmitButtonTarget) {
                this.submitButtonTarget.disabled = false
            }
            if (this.hasSubmitButtonTextTarget) {
                this.submitButtonTextTarget.textContent = 'Submit Waivers'
            }
        }
    }

    showProcessingStep() {
        // Hide all regular steps
        this.stepTargets.forEach(step => step.classList.add('d-none'))

        // Show processing message based on mode
        let processingHtml
        if (this.isAttestMode) {
            processingHtml = `
                <div class="text-center py-5">
                    <div class="mb-4">
                        <div class="spinner-border text-primary" role="status" style="width: 5rem; height: 5rem;">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                    </div>
                    <h2 class="mb-3">Processing Your Attestation</h2>
                    <p class="lead text-muted mb-4">
                        Please wait while we record your attestation...
                    </p>
                    <div class="alert alert-info d-inline-block">
                        <i class="bi bi-shield-check"></i>
                        Attesting waiver not needed for ${this.selectedActivities.length} activity(s)
                    </div>
                </div>
            `
        } else {
            processingHtml = `
                <div class="text-center py-5">
                    <div class="mb-4">
                        <div class="spinner-border text-primary" role="status" style="width: 5rem; height: 5rem;">
                            <span class="visually-hidden">Uploading...</span>
                        </div>
                    </div>
                    <h2 class="mb-3">Processing Your Waiver</h2>
                    <p class="lead text-muted mb-4">
                        Please wait while we upload and process your waiver...
                    </p>
                    <div class="alert alert-info d-inline-block">
                        <i class="bi bi-info-circle"></i>
                        Uploading ${this.uploadedPages.length} page(s) for ${this.selectedActivities.length} activity(s)
                    </div>
                </div>
            `
        }

        const container = this.element.querySelector('.wizard-container') || this.element
        container.innerHTML = processingHtml
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
        // Check if we're showing the processing screen (wizard container innerHTML was replaced)
        const container = this.element.querySelector('.wizard-container') || this.element
        const isProcessing = container.querySelector('h2') && 
            (container.querySelector('h2').textContent.includes('Processing Your Attestation') ||
             container.querySelector('h2').textContent.includes('Processing Your Waiver'))
        
        if (isProcessing) {
            // We're in the processing screen, so we can't show a toast
            // Check if we're in mobile mode by checking the URL
            const isMobile = window.location.pathname.includes('mobile-upload')
            
            if (isMobile) {
                // Redirect to mobile card - the URL will be in the user's browser history
                // We'll redirect to the mobile select gathering page as a safe fallback
                window.location.href = `/waivers/gathering-waivers/mobile-select-gathering?error=${encodeURIComponent(message)}`
            } else {
                // Desktop mode - redirect back to the gathering with a flash message
                const gatheringPublicId = this.gatheringPublicIdValue
                window.location.href = `/gatherings/view/${gatheringPublicId}?tab=gathering-waivers&error=${encodeURIComponent(message)}`
            }
            return
        }
        
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
        let toastContainer = document.querySelector('.toast-container')
        if (!toastContainer) {
            toastContainer = document.createElement('div')
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3'
            document.body.appendChild(toastContainer)
        }

        toastContainer.insertAdjacentHTML('beforeend', toastHtml)
        const toastElement = toastContainer.lastElementChild
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
