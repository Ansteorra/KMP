import { Controller } from "@hotwired/stimulus"
import * as pdfjsLib from 'pdfjs-dist'

// Set up PDF.js worker
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.min.mjs',
    import.meta.url
).toString()

/**
 * Waiver Upload Wizard Controller
 *
 * Gathering-level workflow:
 * Step 1: Select waiver type
 * Step 2: Upload pages or attest not needed
 * Step 3: Review & submit
 */
class WaiverUploadWizardController extends Controller {
    static targets = [
        "step",
        "stepIndicator",
        "prevButton",
        "nextButton",
        "submitButton",
        "submitButtonText",
        "waiverTypeOption",
        "waiverTypeSelect",
        "pagesPreview",
        "fileInput",
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
        totalSteps: { type: Number, default: 3 },
        gatheringId: Number,
        gatheringPublicId: String,
        maxFileSize: Number,           // Maximum single file size in bytes
        totalMaxSize: Number,          // Maximum total upload size in bytes
        preSelectedWaiverTypeId: Number, // Pre-selected waiver type ID from URL
        attestUrl: String,             // URL for attestation endpoint
        gatheringViewUrl: String,      // URL for gathering view page
        mobileSelectUrl: String        // URL for mobile select gathering page
    }

    connect() {
        this.uploadedPages = []
        this.selectedWaiverType = null
        this.notes = ""
        this.isAttestMode = false
        this.attestReason = null
        this.attestNotes = ""

        if (this.hasPreSelectedWaiverTypeIdValue) {
            setTimeout(() => {
                const waiverTypeRadio = document.querySelector(
                    `input[name="waiver_type"][value="${this.preSelectedWaiverTypeIdValue}"]`
                )

                if (waiverTypeRadio) {
                    if (this.isWaiverTypeAttested(this.preSelectedWaiverTypeIdValue)) {
                        this.showError('This waiver type has been attested as not needed for this gathering.')
                        waiverTypeRadio.checked = false
                        return
                    }
                    waiverTypeRadio.checked = true
                    this.selectedWaiverType = {
                        id: this.preSelectedWaiverTypeIdValue,
                        name: waiverTypeRadio.dataset.name
                    }
                    this.checkAttestationAvailability()
                }
            }, 50)
        }

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
        switch (stepNumber) {
            case 2:
                this.checkAttestationAvailability()
                break
            case 3:
                this.updateReviewSection()
                break
        }
    }

    // Check if attestation is available for selected waiver type
    checkAttestationAvailability() {
        if (!this.selectedWaiverType) {
            return
        }

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

        // Show/hide mode toggle and update lead text based on exemption reasons availability
        if (exemptionReasons.length > 0) {
            // Has exemption reasons - show toggle
            if (this.hasModeToggleTarget) {
                this.modeToggleTarget.classList.remove('d-none')
            }
            if (this.hasStep3LeadTarget) {
                this.step3LeadTarget.textContent = 'Add one or more pages to your waiver document, or attest that a waiver is not needed'
            }
        } else {
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
        let hasMatchingSelection = false
        exemptionReasons.forEach((reason, index) => {
            const id = `attest_reason_${index}`
            const isSelected = this.attestReason === reason
            if (isSelected) {
                hasMatchingSelection = true
            }
            html += `
                <label class="list-group-item list-group-item-action">
                    <input class="form-check-input me-2" type="radio" name="attest_reason" 
                           id="${id}" value="${this.escapeHtml(reason)}"
                           data-action="change->waiver-upload-wizard#selectAttestReason"
                           ${isSelected ? 'checked' : ''}>
                    ${this.escapeHtml(reason)}
                </label>
            `
        })
        html += '</div>'

        this.attestReasonListTarget.innerHTML = html

        // Clear attestReason if the previously selected reason is not available for the current waiver type
        if (!hasMatchingSelection) {
            this.attestReason = null
        }
    }

    selectAttestReason(event) {
        this.attestReason = event.currentTarget.value
    }

    selectWaiverType(event) {
        const option = event.currentTarget
        if (option.disabled || option.dataset.attested === '1') {
            option.checked = false
            this.showError('This waiver type has been attested as not needed for this gathering.')
            return
        }
        this.selectedWaiverType = {
            id: parseInt(option.value),
            name: option.dataset.name
        }
        this.checkAttestationAvailability()
    }

    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    isWaiverTypeAttested(waiverTypeId) {
        const waiverTypeRadio = document.querySelector(
            `input[name="waiver_type"][value="${waiverTypeId}"]`
        )
        if (!waiverTypeRadio) {
            return false
        }
        return waiverTypeRadio.disabled || waiverTypeRadio.dataset.attested === '1'
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
            if (!this.isValidFile(file)) {
                this.showError(`Invalid file type: ${file.name}. Please upload images (JPEG, PNG, GIF, BMP, WEBP) or PDF files.`)
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

    isValidFile(file) {
        const validTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/webp',
            'image/x-ms-bmp', // Alternative MIME type for BMP
            'image/x-windows-bmp', // Another BMP variant
            'application/pdf' // PDF files
        ]
        return validTypes.includes(file.type) || file.name.toLowerCase().endsWith('.pdf')
    }

    addPage(file) {
        const pageNumber = this.uploadedPages.length + 1
        const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
        const reader = new FileReader()

        reader.onload = async (e) => {
            const page = {
                file: file,
                dataUrl: e.target.result,
                number: pageNumber,
                name: file.name,
                size: file.size,
                isPdf: isPdf,
                pdfPageCount: 0,
                thumbnailUrl: null
            }

            // Generate thumbnail for PDFs
            if (isPdf) {
                try {
                    const pdfData = new Uint8Array(e.target.result.split(',')[1] ? 
                        atob(e.target.result.split(',')[1]).split('').map(c => c.charCodeAt(0)) :
                        [])
                    
                    if (pdfData.length > 0) {
                        const pdf = await pdfjsLib.getDocument({ data: pdfData }).promise
                        page.pdfPageCount = pdf.numPages
                        
                        // Render first page as thumbnail
                        const pdfPage = await pdf.getPage(1)
                        const scale = 0.5
                        const viewport = pdfPage.getViewport({ scale })
                        
                        const canvas = document.createElement('canvas')
                        const context = canvas.getContext('2d')
                        canvas.width = viewport.width
                        canvas.height = viewport.height
                        
                        await pdfPage.render({ canvasContext: context, viewport }).promise
                        page.thumbnailUrl = canvas.toDataURL('image/png')
                    }
                } catch (err) {
                    console.warn('Could not generate PDF thumbnail:', err)
                }
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
                    <p class="small">Click "Add Page" to select images or PDFs</p>
                </div>
            `
            return
        }

        const html = this.uploadedPages.map((page, index) => {
            // Generate preview: PDF thumbnail, PDF icon fallback, or image
            let previewHtml
            if (page.isPdf) {
                if (page.thumbnailUrl) {
                    // Show rendered PDF thumbnail with page count badge
                    const pageCountBadge = page.pdfPageCount > 1 
                        ? `<span class="badge bg-danger position-absolute top-0 end-0 m-1">${page.pdfPageCount} pages</span>` 
                        : ''
                    previewHtml = `<div class="position-relative">
                        <img src="${page.thumbnailUrl}" 
                             class="img-thumbnail" 
                             style="width: 100px; height: 130px; object-fit: contain; background: #f8f9fa;"
                             alt="PDF Preview">
                        ${pageCountBadge}
                    </div>`
                } else {
                    // Fallback to PDF icon
                    previewHtml = `<div class="d-flex align-items-center justify-content-center bg-light border rounded" 
                            style="width: 100px; height: 130px;">
                         <div class="text-center">
                           <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 2.5rem;"></i>
                           <div class="small text-muted mt-1">PDF</div>
                         </div>
                       </div>`
                }
            } else {
                previewHtml = `<img src="${page.dataUrl}" 
                        class="img-thumbnail" 
                        style="width: 100px; height: 130px; object-fit: cover;"
                        alt="Page ${page.number}">`
            }

            // Show page count info for multi-page PDFs
            const pageInfo = page.isPdf && page.pdfPageCount > 1 
                ? `<small class="text-info">${page.pdfPageCount} pages</small><br>` 
                : ''

            return `
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                ${previewHtml}
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>Page ${page.number}</strong><br>
                                        ${pageInfo}<small class="text-muted">${page.name}</small><br>
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
        `}).join('')

        this.pagesPreviewTarget.innerHTML = html
    }

    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B'
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
    }

    // Step 3: Review
    updateReviewSection() {
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

            // Page Count - calculate total pages including multi-page PDFs
            const totalPages = this.uploadedPages.reduce((sum, page) => {
                return sum + (page.pdfPageCount > 1 ? page.pdfPageCount : 1)
            }, 0)
            
            if (this.hasReviewPageCountTarget) {
                if (totalPages !== this.uploadedPages.length) {
                    this.reviewPageCountTarget.textContent = `${this.uploadedPages.length} files (${totalPages} total pages)`
                } else {
                    this.reviewPageCountTarget.textContent = this.uploadedPages.length
                }
            }

            // Pages List
            if (this.hasReviewPagesListTarget) {
                const html = this.uploadedPages.map(page => {
                    // Use thumbnail for PDFs, dataUrl for images
                    const imgSrc = page.isPdf && page.thumbnailUrl ? page.thumbnailUrl : page.dataUrl
                    const imgStyle = page.isPdf 
                        ? 'height: 200px; object-fit: contain; background: #f8f9fa;' 
                        : 'height: 200px; object-fit: cover;'
                    
                    // Show page count badge for multi-page PDFs
                    const pageCountBadge = page.isPdf && page.pdfPageCount > 1 
                        ? `<span class="badge bg-danger position-absolute top-0 end-0 m-2">${page.pdfPageCount} pages</span>` 
                        : ''
                    
                    // PDF icon overlay if no thumbnail available
                    const pdfFallback = page.isPdf && !page.thumbnailUrl
                        ? `<div class="d-flex align-items-center justify-content-center" style="height: 200px; background: #f8f9fa;">
                             <div class="text-center">
                               <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 4rem;"></i>
                               <div class="text-muted">PDF Document</div>
                               ${page.pdfPageCount > 1 ? `<div class="text-info">${page.pdfPageCount} pages</div>` : ''}
                             </div>
                           </div>`
                        : `<img src="${imgSrc}" class="card-img-top" style="${imgStyle}" alt="Page ${page.number}">`
                    
                    return `
                    <div class="col-md-3 mb-3">
                        <div class="card position-relative">
                            ${pdfFallback}
                            ${pageCountBadge}
                            <div class="card-body p-2 text-center">
                                <small>${page.isPdf ? 'PDF' : 'Page'} ${page.number}</small>
                            </div>
                        </div>
                    </div>
                `}).join('')
                this.reviewPagesListTarget.innerHTML = html
            }

            // Get notes if available
            if (this.hasNotesFieldTarget) {
                this.notes = this.notesFieldTarget.value
            }
        }
    }

    validateWaiverType() {
        if (!this.selectedWaiverType) {
            this.showError("Please select a waiver type")
            return false
        }
        if (this.isWaiverTypeAttested(this.selectedWaiverType.id)) {
            this.showError("This waiver type has been attested as not needed for this gathering.")
            return false
        }
        return true
    }

    validateUploadOrAttest() {
        if (this.isAttestMode) {
            if (!this.attestReason) {
                this.showError("Please select a reason for the exemption")
                return false
            }
            return true
        }

        if (this.uploadedPages.length === 0) {
            this.showError("Please add at least one page")
            return false
        }

        return true
    }

    validateReview() {
        return this.validateWaiverType() && this.validateUploadOrAttest()
    }

    // Form Submission
    async submitForm(event) {
        event.preventDefault()

        if (!this.validateReview()) {
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
        const attestUrl = this.hasAttestUrlValue ? this.attestUrlValue : '/waivers/gathering-waivers/attest'

        const response = await fetch(attestUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.getCsrfToken()
            },
            body: JSON.stringify({
                gathering_id: this.gatheringIdValue,
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
                        // Fallback redirect using gatheringViewUrl or construct from public_id
                        const fallbackUrl = this.hasGatheringViewUrlValue
                            ? this.gatheringViewUrlValue
                            : `/gatherings/view/${this.gatheringPublicIdValue}?tab=gathering-waivers`
                        window.location.href = fallbackUrl
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

        // Add notes
        formData.append('notes', this.notes)

        // Add all page files
        this.uploadedPages.forEach((page, index) => {
            formData.append('waiver_images[]', page.file)
        })

        // If any PDF has a client-generated thumbnail, send the first one
        const pdfWithThumbnail = this.uploadedPages.find(p => p.isPdf && p.thumbnailUrl)
        if (pdfWithThumbnail) {
            formData.append('client_thumbnail', pdfWithThumbnail.thumbnailUrl)
        }

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
                    // Fallback redirect using gatheringViewUrl or construct from gathering ID
                    const fallbackUrl = this.hasGatheringViewUrlValue
                        ? this.gatheringViewUrlValue
                        : `/gatherings/view/${this.gatheringIdValue}`
                    window.location.href = fallbackUrl
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
                        Attesting that a waiver is not needed for this gathering
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
                        Uploading ${this.uploadedPages.length} page(s)
                    </div>
                </div>
            `
        }

        const container = this.element.querySelector('.wizard-container') || this.element
        container.innerHTML = processingHtml
    }

    // Validation
    validateCurrentStep() {
        switch (this.currentStepValue) {
            case 1: return this.validateWaiverType()
            case 2: return this.validateUploadOrAttest()
            case 3: return this.validateReview()
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
                // Redirect to mobile card using mobileSelectUrl if available, otherwise construct URL
                const mobileUrl = this.hasMobileSelectUrlValue
                    ? `${this.mobileSelectUrlValue}?error=${encodeURIComponent(message)}`
                    : `/waivers/gathering-waivers/mobile-select-gathering?error=${encodeURIComponent(message)}`
                window.location.href = mobileUrl
            } else {
                // Desktop mode - redirect back to the gathering with a flash message
                const gatheringPublicId = this.gatheringPublicIdValue
                const desktopUrl = this.hasGatheringViewUrlValue
                    ? `${this.gatheringViewUrlValue}&error=${encodeURIComponent(message)}`
                    : `/gatherings/view/${gatheringPublicId}?tab=gathering-waivers&error=${encodeURIComponent(message)}`
                window.location.href = desktopUrl
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
        const metaTag = document.querySelector('meta[name="csrf-token"]')
            || document.querySelector('meta[name="csrfToken"]')
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
