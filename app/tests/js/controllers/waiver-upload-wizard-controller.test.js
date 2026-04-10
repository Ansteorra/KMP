// Mock pdfjs-dist/webpack.mjs before importing the controller
jest.mock('pdfjs-dist/webpack.mjs', () => ({
    GlobalWorkerOptions: { workerSrc: '' },
    getDocument: jest.fn(() => ({
        promise: Promise.resolve({
            numPages: 1,
            getPage: jest.fn(() => Promise.resolve({
                getViewport: jest.fn(() => ({ width: 100, height: 130 })),
                render: jest.fn(() => ({ promise: Promise.resolve() }))
            }))
        })
    }))
}));

import WaiverUploadWizardController from '../../../plugins/Waivers/assets/js/controllers/waiver-upload-wizard-controller.js';

describe('WaiverUploadWizardController', () => {
    let controller;

    function buildWizardDom() {
        return `
            <div data-controller="waiver-upload-wizard"
                 data-waiver-upload-wizard-current-step-value="1"
                 data-waiver-upload-wizard-total-steps-value="3"
                 data-waiver-upload-wizard-gathering-id-value="42"
                 data-waiver-upload-wizard-max-file-size-value="5242880"
                 data-waiver-upload-wizard-total-max-size-value="26214400">

                <div data-waiver-upload-wizard-target="progressBar" style="width: 0%;" aria-valuenow="0"></div>

                <div data-waiver-upload-wizard-target="stepIndicator" data-step="1"></div>
                <div data-waiver-upload-wizard-target="stepIndicator" data-step="2"></div>
                <div data-waiver-upload-wizard-target="stepIndicator" data-step="3"></div>

                <div data-waiver-upload-wizard-target="step" data-step-number="1">
                    <input type="radio" name="waiver_type" value="10" data-name="Minor Waiver"
                           data-exemption-reasons='["Under 18","Guardian present"]'
                           data-action="change->waiver-upload-wizard#selectWaiverType">
                    <input type="radio" name="waiver_type" value="20" data-name="Adult Waiver"
                           data-exemption-reasons='[]'
                           data-action="change->waiver-upload-wizard#selectWaiverType">
                    <input type="radio" name="waiver_type" value="30" data-name="Attested Waiver"
                           disabled data-attested="1" data-name="Already Attested"
                           data-exemption-reasons='[]'>
                </div>

                <div data-waiver-upload-wizard-target="step" data-step-number="2" class="d-none">
                    <div data-waiver-upload-wizard-target="step3Lead"></div>
                    <div data-waiver-upload-wizard-target="modeToggle" class="d-none">
                        <input type="radio" name="mode" id="mode-upload" value="upload" checked>
                        <input type="radio" name="mode" id="mode-attest" value="attest">
                    </div>
                    <div data-waiver-upload-wizard-target="uploadSection">
                        <input type="file" data-waiver-upload-wizard-target="fileInput" multiple>
                        <div data-waiver-upload-wizard-target="pagesPreview"></div>
                    </div>
                    <div data-waiver-upload-wizard-target="attestSection" class="d-none">
                        <div data-waiver-upload-wizard-target="attestReasonList"></div>
                        <textarea data-waiver-upload-wizard-target="attestNotes"></textarea>
                    </div>
                </div>

                <div data-waiver-upload-wizard-target="step" data-step-number="3" class="d-none">
                    <span data-waiver-upload-wizard-target="reviewWaiverType"></span>
                    <div data-waiver-upload-wizard-target="reviewUploadSection">
                        <span data-waiver-upload-wizard-target="reviewPageCount"></span>
                        <div data-waiver-upload-wizard-target="reviewPagesList"></div>
                    </div>
                    <div data-waiver-upload-wizard-target="reviewAttestSection" class="d-none">
                        <span data-waiver-upload-wizard-target="reviewAttestReason"></span>
                        <div data-waiver-upload-wizard-target="reviewAttestNotesSection">
                            <span data-waiver-upload-wizard-target="reviewAttestNotes"></span>
                        </div>
                    </div>
                    <textarea data-waiver-upload-wizard-target="notesField"></textarea>
                </div>

                <button data-waiver-upload-wizard-target="prevButton" class="d-none">Prev</button>
                <button data-waiver-upload-wizard-target="nextButton">Next</button>
                <button data-waiver-upload-wizard-target="submitButton" class="d-none">
                    <span data-waiver-upload-wizard-target="submitButtonText">Submit Waivers</span>
                </button>

                <meta name="csrf-token" content="test-csrf-token">
            </div>
        `;
    }

    function setupTargets(ctrl) {
        const el = ctrl.element;
        const q = (sel) => el.querySelector(sel);
        const qa = (sel) => Array.from(el.querySelectorAll(sel));

        ctrl.stepTargets = qa('[data-waiver-upload-wizard-target="step"]');
        ctrl.stepIndicatorTargets = qa('[data-waiver-upload-wizard-target="stepIndicator"]');
        ctrl.prevButtonTarget = q('[data-waiver-upload-wizard-target="prevButton"]');
        ctrl.hasPrevButtonTarget = true;
        ctrl.nextButtonTarget = q('[data-waiver-upload-wizard-target="nextButton"]');
        ctrl.hasNextButtonTarget = true;
        ctrl.submitButtonTarget = q('[data-waiver-upload-wizard-target="submitButton"]');
        ctrl.hasSubmitButtonTarget = true;
        ctrl.submitButtonTextTarget = q('[data-waiver-upload-wizard-target="submitButtonText"]');
        ctrl.hasSubmitButtonTextTarget = true;
        ctrl.progressBarTarget = q('[data-waiver-upload-wizard-target="progressBar"]');
        ctrl.hasProgressBarTarget = true;

        ctrl.pagesPreviewTarget = q('[data-waiver-upload-wizard-target="pagesPreview"]');
        ctrl.hasPagesPreviewTarget = true;
        ctrl.fileInputTarget = q('[data-waiver-upload-wizard-target="fileInput"]');
        ctrl.hasFileInputTarget = true;

        ctrl.uploadSectionTarget = q('[data-waiver-upload-wizard-target="uploadSection"]');
        ctrl.hasUploadSectionTarget = true;
        ctrl.attestSectionTarget = q('[data-waiver-upload-wizard-target="attestSection"]');
        ctrl.hasAttestSectionTarget = true;
        ctrl.attestReasonListTarget = q('[data-waiver-upload-wizard-target="attestReasonList"]');
        ctrl.hasAttestReasonListTarget = true;
        ctrl.attestNotesTarget = q('[data-waiver-upload-wizard-target="attestNotes"]');
        ctrl.hasAttestNotesTarget = true;

        ctrl.reviewWaiverTypeTarget = q('[data-waiver-upload-wizard-target="reviewWaiverType"]');
        ctrl.hasReviewWaiverTypeTarget = true;
        ctrl.reviewPageCountTarget = q('[data-waiver-upload-wizard-target="reviewPageCount"]');
        ctrl.hasReviewPageCountTarget = true;
        ctrl.reviewPagesListTarget = q('[data-waiver-upload-wizard-target="reviewPagesList"]');
        ctrl.hasReviewPagesListTarget = true;
        ctrl.reviewUploadSectionTarget = q('[data-waiver-upload-wizard-target="reviewUploadSection"]');
        ctrl.hasReviewUploadSectionTarget = true;
        ctrl.reviewAttestSectionTarget = q('[data-waiver-upload-wizard-target="reviewAttestSection"]');
        ctrl.hasReviewAttestSectionTarget = true;
        ctrl.reviewAttestReasonTarget = q('[data-waiver-upload-wizard-target="reviewAttestReason"]');
        ctrl.hasReviewAttestReasonTarget = true;
        ctrl.reviewAttestNotesTarget = q('[data-waiver-upload-wizard-target="reviewAttestNotes"]');
        ctrl.hasReviewAttestNotesTarget = true;
        ctrl.reviewAttestNotesSectionTarget = q('[data-waiver-upload-wizard-target="reviewAttestNotesSection"]');
        ctrl.hasReviewAttestNotesSectionTarget = true;
        ctrl.notesFieldTarget = q('[data-waiver-upload-wizard-target="notesField"]');
        ctrl.hasNotesFieldTarget = true;

        ctrl.modeToggleTarget = q('[data-waiver-upload-wizard-target="modeToggle"]');
        ctrl.hasModeToggleTarget = true;
        ctrl.step3LeadTarget = q('[data-waiver-upload-wizard-target="step3Lead"]');
        ctrl.hasStep3LeadTarget = true;

        // Values
        ctrl.currentStepValue = 1;
        ctrl.totalStepsValue = 3;
        ctrl.gatheringIdValue = 42;
        ctrl.hasMaxFileSizeValue = true;
        ctrl.maxFileSizeValue = 5 * 1024 * 1024;
        ctrl.hasTotalMaxSizeValue = true;
        ctrl.totalMaxSizeValue = 25 * 1024 * 1024;
        ctrl.hasPreSelectedWaiverTypeIdValue = false;
        ctrl.hasAttestUrlValue = false;
        ctrl.hasGatheringViewUrlValue = false;
        ctrl.hasMobileSelectUrlValue = false;
        ctrl.gatheringPublicIdValue = 'abc-123';
    }

    beforeEach(() => {
        // Mock bootstrap.Toast
        window.bootstrap = {
            Toast: jest.fn().mockImplementation(() => ({
                show: jest.fn()
            }))
        };

        document.body.innerHTML = buildWizardDom();
        controller = new WaiverUploadWizardController();
        controller.element = document.querySelector('[data-controller="waiver-upload-wizard"]');
        setupTargets(controller);
        controller.connect();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Registration ---

    test('registers on global Controllers', () => {
        expect(window.Controllers['waiver-upload-wizard']).toBe(WaiverUploadWizardController);
    });

    // --- connect ---

    test('connect initializes state', () => {
        expect(controller.uploadedPages).toEqual([]);
        expect(controller.selectedWaiverType).toBeNull();
        expect(controller.notes).toBe('');
        expect(controller.isAttestMode).toBe(false);
        expect(controller.attestReason).toBeNull();
        expect(controller.attestNotes).toBe('');
    });

    test('connect shows step 1', () => {
        const step1 = controller.stepTargets[0];
        const step2 = controller.stepTargets[1];
        expect(step1.classList.contains('d-none')).toBe(false);
        expect(step2.classList.contains('d-none')).toBe(true);
    });

    // --- Step Navigation ---

    test('showStep shows correct step and hides others', () => {
        controller.showStep(2);
        expect(controller.stepTargets[0].classList.contains('d-none')).toBe(true);
        expect(controller.stepTargets[1].classList.contains('d-none')).toBe(false);
        expect(controller.stepTargets[2].classList.contains('d-none')).toBe(true);
    });

    test('nextStep advances when current step is valid', () => {
        // Select a waiver type to make step 1 valid
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.nextStep();
        expect(controller.currentStepValue).toBe(2);
    });

    test('nextStep does not advance when validation fails', () => {
        controller.selectedWaiverType = null;
        controller.nextStep();
        expect(controller.currentStepValue).toBe(1);
    });

    test('nextStep does not advance past total steps', () => {
        controller.currentStepValue = 3;
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.uploadedPages = [{ file: {}, size: 100, pdfPageCount: 0 }];
        controller.nextStep();
        expect(controller.currentStepValue).toBe(3);
    });

    test('prevStep goes back one step', () => {
        controller.currentStepValue = 2;
        controller.prevStep();
        expect(controller.currentStepValue).toBe(1);
    });

    test('prevStep does not go below step 1', () => {
        controller.currentStepValue = 1;
        controller.prevStep();
        expect(controller.currentStepValue).toBe(1);
    });

    test('goToStep navigates to earlier step', () => {
        controller.currentStepValue = 3;
        controller.goToStep({ currentTarget: { dataset: { step: '1' } } });
        expect(controller.currentStepValue).toBe(1);
    });

    test('goToStep does not navigate to future step', () => {
        controller.currentStepValue = 1;
        controller.goToStep({ currentTarget: { dataset: { step: '3' } } });
        expect(controller.currentStepValue).toBe(1);
    });

    // --- updateStepIndicators ---

    test('updateStepIndicators marks current as active', () => {
        controller.updateStepIndicators(2);
        expect(controller.stepIndicatorTargets[0].classList.contains('completed')).toBe(true);
        expect(controller.stepIndicatorTargets[1].classList.contains('active')).toBe(true);
        expect(controller.stepIndicatorTargets[2].classList.contains('active')).toBe(false);
    });

    // --- updateNavigationButtons ---

    test('hides prev button on step 1', () => {
        controller.updateNavigationButtons(1);
        expect(controller.prevButtonTarget.classList.contains('d-none')).toBe(true);
    });

    test('shows prev button on step 2', () => {
        controller.updateNavigationButtons(2);
        expect(controller.prevButtonTarget.classList.contains('d-none')).toBe(false);
    });

    test('hides next button on last step', () => {
        controller.updateNavigationButtons(3);
        expect(controller.nextButtonTarget.classList.contains('d-none')).toBe(true);
    });

    test('shows submit button on last step', () => {
        controller.updateNavigationButtons(3);
        expect(controller.submitButtonTarget.classList.contains('d-none')).toBe(false);
    });

    test('hides submit button on non-last step', () => {
        controller.updateNavigationButtons(1);
        expect(controller.submitButtonTarget.classList.contains('d-none')).toBe(true);
    });

    test('submit button text reflects attest mode', () => {
        controller.isAttestMode = true;
        controller.updateNavigationButtons(3);
        expect(controller.submitButtonTextTarget.textContent).toBe('Submit Attestation');
    });

    test('submit button text reflects upload mode', () => {
        controller.isAttestMode = false;
        controller.updateNavigationButtons(3);
        expect(controller.submitButtonTextTarget.textContent).toBe('Submit Waivers');
    });

    // --- updateProgressBar ---

    test('updateProgressBar sets correct width', () => {
        controller.updateProgressBar(2);
        const expectedWidth = (2 / 3) * 100;
        expect(controller.progressBarTarget.style.width).toBe(`${expectedWidth}%`);
    });

    // --- Waiver Type Selection ---

    test('selectWaiverType sets selectedWaiverType', () => {
        controller.selectWaiverType({
            currentTarget: { value: '10', dataset: { name: 'Minor Waiver' }, disabled: false }
        });
        expect(controller.selectedWaiverType).toEqual({ id: 10, name: 'Minor Waiver' });
    });

    test('selectWaiverType rejects disabled/attested waiver type', () => {
        const option = { value: '30', dataset: { name: 'Attested', attested: '1' }, disabled: true, checked: true };
        controller.selectWaiverType({ currentTarget: option });
        expect(option.checked).toBe(false);
    });

    // --- isWaiverTypeAttested ---

    test('isWaiverTypeAttested returns true for attested type', () => {
        expect(controller.isWaiverTypeAttested(30)).toBe(true);
    });

    test('isWaiverTypeAttested returns false for normal type', () => {
        expect(controller.isWaiverTypeAttested(10)).toBe(false);
    });

    test('isWaiverTypeAttested returns false for non-existent type', () => {
        expect(controller.isWaiverTypeAttested(999)).toBe(false);
    });

    // --- Validation ---

    test('validateWaiverType fails when no type selected', () => {
        controller.selectedWaiverType = null;
        expect(controller.validateWaiverType()).toBe(false);
    });

    test('validateWaiverType succeeds with valid type', () => {
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        expect(controller.validateWaiverType()).toBe(true);
    });

    test('validateWaiverType fails for attested type', () => {
        controller.selectedWaiverType = { id: 30, name: 'Already Attested' };
        expect(controller.validateWaiverType()).toBe(false);
    });

    test('validateUploadOrAttest fails in upload mode with no pages', () => {
        controller.isAttestMode = false;
        controller.uploadedPages = [];
        expect(controller.validateUploadOrAttest()).toBe(false);
    });

    test('validateUploadOrAttest succeeds in upload mode with pages', () => {
        controller.isAttestMode = false;
        controller.uploadedPages = [{ file: {}, size: 100 }];
        expect(controller.validateUploadOrAttest()).toBe(true);
    });

    test('validateUploadOrAttest fails in attest mode without reason', () => {
        controller.isAttestMode = true;
        controller.attestReason = null;
        expect(controller.validateUploadOrAttest()).toBe(false);
    });

    test('validateUploadOrAttest succeeds in attest mode with reason', () => {
        controller.isAttestMode = true;
        controller.attestReason = 'Under 18';
        expect(controller.validateUploadOrAttest()).toBe(true);
    });

    test('validateCurrentStep dispatches to correct validator', () => {
        controller.currentStepValue = 1;
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        expect(controller.validateCurrentStep()).toBe(true);

        controller.currentStepValue = 2;
        controller.uploadedPages = [{ file: {}, size: 100 }];
        expect(controller.validateCurrentStep()).toBe(true);
    });

    test('validateCurrentStep returns true for unknown steps', () => {
        controller.currentStepValue = 99;
        expect(controller.validateCurrentStep()).toBe(true);
    });

    // --- Mode Toggle ---

    test('setModeUpload shows upload section and hides attest', () => {
        controller.setModeUpload();
        expect(controller.isAttestMode).toBe(false);
        expect(controller.uploadSectionTarget.classList.contains('d-none')).toBe(false);
        expect(controller.attestSectionTarget.classList.contains('d-none')).toBe(true);
    });

    test('setModeAttest shows attest section when exemption reasons exist', () => {
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.setModeAttest();
        expect(controller.isAttestMode).toBe(true);
        expect(controller.uploadSectionTarget.classList.contains('d-none')).toBe(true);
        expect(controller.attestSectionTarget.classList.contains('d-none')).toBe(false);
    });

    test('setModeAttest refuses when no exemption reasons', () => {
        controller.selectedWaiverType = { id: 20, name: 'Adult Waiver' };
        controller.setModeAttest();
        expect(controller.isAttestMode).toBe(false);
    });

    test('setModeAttest refuses when no waiver type selected', () => {
        controller.selectedWaiverType = null;
        controller.setModeAttest();
        expect(controller.isAttestMode).toBe(false);
    });

    // --- checkAttestationAvailability ---

    test('shows mode toggle when exemption reasons exist', () => {
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.checkAttestationAvailability();
        expect(controller.modeToggleTarget.classList.contains('d-none')).toBe(false);
    });

    test('hides mode toggle when no exemption reasons', () => {
        controller.selectedWaiverType = { id: 20, name: 'Adult Waiver' };
        controller.checkAttestationAvailability();
        expect(controller.modeToggleTarget.classList.contains('d-none')).toBe(true);
    });

    test('forces upload mode when no exemption reasons', () => {
        controller.isAttestMode = true;
        controller.selectedWaiverType = { id: 20, name: 'Adult Waiver' };
        controller.checkAttestationAvailability();
        expect(controller.isAttestMode).toBe(false);
    });

    // --- populateAttestationReasons ---

    test('populateAttestationReasons renders radio buttons', () => {
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.populateAttestationReasons();

        const radios = controller.attestReasonListTarget.querySelectorAll('input[type="radio"]');
        expect(radios.length).toBe(2);
        expect(radios[0].value).toBe('Under 18');
        expect(radios[1].value).toBe('Guardian present');
    });

    test('populateAttestationReasons shows warning when no reasons', () => {
        controller.selectedWaiverType = { id: 20, name: 'Adult Waiver' };
        controller.populateAttestationReasons();

        expect(controller.attestReasonListTarget.innerHTML).toContain('No exemption reasons');
    });

    test('populateAttestationReasons preserves selected reason', () => {
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.attestReason = 'Under 18';
        controller.populateAttestationReasons();

        const checkedRadio = controller.attestReasonListTarget.querySelector('input:checked');
        expect(checkedRadio).not.toBeNull();
        expect(checkedRadio.value).toBe('Under 18');
    });

    test('populateAttestationReasons clears reason if not in new list', () => {
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.attestReason = 'Non-existent reason';
        controller.populateAttestationReasons();

        expect(controller.attestReason).toBeNull();
    });

    // --- selectAttestReason ---

    test('selectAttestReason sets attestReason', () => {
        controller.selectAttestReason({ currentTarget: { value: 'Under 18' } });
        expect(controller.attestReason).toBe('Under 18');
    });

    // --- File Handling ---

    test('isValidFile accepts JPEG', () => {
        const file = new File(['x'], 'test.jpg', { type: 'image/jpeg' });
        expect(controller.isValidFile(file)).toBe(true);
    });

    test('isValidFile accepts PDF', () => {
        const file = new File(['x'], 'test.pdf', { type: 'application/pdf' });
        expect(controller.isValidFile(file)).toBe(true);
    });

    test('isValidFile accepts PDF by extension fallback', () => {
        const file = new File(['x'], 'scan.PDF', { type: '' });
        expect(controller.isValidFile(file)).toBe(true);
    });

    test('isValidFile rejects invalid type', () => {
        const file = new File(['x'], 'virus.exe', { type: 'application/x-msdownload' });
        expect(controller.isValidFile(file)).toBe(false);
    });

    test('handleFileSelect rejects oversized files', () => {
        const file = new File(['x'], 'big.jpg', { type: 'image/jpeg' });
        Object.defineProperty(file, 'size', { value: 10 * 1024 * 1024 });

        controller.handleFileSelect({ target: { files: [file], value: '' } });

        expect(controller.uploadedPages).toHaveLength(0);
    });

    test('handleFileSelect rejects files exceeding total max size', () => {
        // Pre-fill with existing pages near limit
        controller.uploadedPages = [{ file: {}, size: 24 * 1024 * 1024, pdfPageCount: 0 }];

        const file = new File(['x'], 'extra.jpg', { type: 'image/jpeg' });
        Object.defineProperty(file, 'size', { value: 2 * 1024 * 1024 });

        controller.handleFileSelect({ target: { files: [file], value: '' } });

        // Should still only have the original page
        expect(controller.uploadedPages).toHaveLength(1);
    });

    test('handleFileSelect clears input after processing', () => {
        const input = { files: [], value: 'some-file.jpg' };
        controller.handleFileSelect({ target: input });
        expect(input.value).toBe('');
    });

    // --- addPage ---

    test('addPage creates page entry via FileReader', () => {
        const mockFileReader = {
            readAsDataURL: jest.fn(),
            result: null,
            onload: null
        };
        jest.spyOn(global, 'FileReader').mockImplementation(() => mockFileReader);

        const file = new File(['img'], 'page1.jpg', { type: 'image/jpeg' });
        Object.defineProperty(file, 'size', { value: 1024 });

        controller.addPage(file);

        // Simulate FileReader callback
        mockFileReader.onload({ target: { result: 'data:image/jpeg;base64,abc' } });

        expect(controller.uploadedPages).toHaveLength(1);
        expect(controller.uploadedPages[0].name).toBe('page1.jpg');
        expect(controller.uploadedPages[0].isPdf).toBe(false);
    });

    // --- removePage ---

    test('removePage removes page and renumbers', () => {
        controller.uploadedPages = [
            { file: {}, number: 1, name: 'a.jpg', size: 100, pdfPageCount: 0 },
            { file: {}, number: 2, name: 'b.jpg', size: 200, pdfPageCount: 0 },
            { file: {}, number: 3, name: 'c.jpg', size: 300, pdfPageCount: 0 }
        ];

        controller.removePage({ currentTarget: { dataset: { index: '1' } } });

        expect(controller.uploadedPages).toHaveLength(2);
        expect(controller.uploadedPages[0].number).toBe(1);
        expect(controller.uploadedPages[1].number).toBe(2);
        expect(controller.uploadedPages[1].name).toBe('c.jpg');
    });

    // --- renderPages ---

    test('renderPages shows empty state when no pages', () => {
        controller.uploadedPages = [];
        controller.renderPages();
        expect(controller.pagesPreviewTarget.innerHTML).toContain('No pages added yet');
    });

    test('renderPages shows page cards', () => {
        controller.uploadedPages = [
            { file: {}, number: 1, name: 'page1.jpg', size: 1024, isPdf: false, dataUrl: 'data:image/jpeg;base64,abc', pdfPageCount: 0, thumbnailUrl: null }
        ];
        controller.renderPages();
        expect(controller.pagesPreviewTarget.innerHTML).toContain('Page 1');
        expect(controller.pagesPreviewTarget.innerHTML).toContain('page1.jpg');
    });

    test('renderPages shows PDF icon for PDF pages without thumbnail', () => {
        controller.uploadedPages = [
            { file: {}, number: 1, name: 'doc.pdf', size: 2048, isPdf: true, dataUrl: 'data:application/pdf;base64,abc', pdfPageCount: 3, thumbnailUrl: null }
        ];
        controller.renderPages();
        expect(controller.pagesPreviewTarget.innerHTML).toContain('bi-file-earmark-pdf');
    });

    test('renderPages shows thumbnail for PDF with thumbnail', () => {
        controller.uploadedPages = [
            { file: {}, number: 1, name: 'doc.pdf', size: 2048, isPdf: true, dataUrl: 'data:application/pdf;base64,abc', pdfPageCount: 2, thumbnailUrl: 'data:image/png;base64,thumb' }
        ];
        controller.renderPages();
        expect(controller.pagesPreviewTarget.innerHTML).toContain('data:image/png;base64,thumb');
        expect(controller.pagesPreviewTarget.innerHTML).toContain('2 pages');
    });

    // --- formatFileSize ---

    test('formatFileSize formats bytes', () => {
        expect(controller.formatFileSize(500)).toBe('500 B');
    });

    test('formatFileSize formats kilobytes', () => {
        expect(controller.formatFileSize(2048)).toBe('2.0 KB');
    });

    test('formatFileSize formats megabytes', () => {
        expect(controller.formatFileSize(5 * 1024 * 1024)).toBe('5.0 MB');
    });

    // --- formatBytes ---

    test('formatBytes returns 0 Bytes for zero', () => {
        expect(controller.formatBytes(0)).toBe('0 Bytes');
    });

    test('formatBytes formats with custom decimals', () => {
        expect(controller.formatBytes(1536, 1)).toBe('1.5 KB');
    });

    // --- updateReviewSection ---

    test('updateReviewSection shows upload review in upload mode', () => {
        controller.isAttestMode = false;
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.uploadedPages = [
            { file: {}, number: 1, name: 'a.jpg', size: 100, isPdf: false, dataUrl: 'data:x', pdfPageCount: 0, thumbnailUrl: null }
        ];

        controller.updateReviewSection();

        expect(controller.reviewWaiverTypeTarget.textContent).toBe('Minor Waiver');
        expect(controller.reviewUploadSectionTarget.classList.contains('d-none')).toBe(false);
        expect(controller.reviewAttestSectionTarget.classList.contains('d-none')).toBe(true);
        expect(controller.reviewPageCountTarget.textContent).toBe('1');
    });

    test('updateReviewSection shows attest review in attest mode', () => {
        controller.isAttestMode = true;
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.attestReason = 'Under 18';
        controller.attestNotesTarget.value = 'Some notes';

        controller.updateReviewSection();

        expect(controller.reviewUploadSectionTarget.classList.contains('d-none')).toBe(true);
        expect(controller.reviewAttestSectionTarget.classList.contains('d-none')).toBe(false);
        expect(controller.reviewAttestReasonTarget.textContent).toBe('Under 18');
    });

    test('updateReviewSection shows total pages for multi-page PDFs', () => {
        controller.isAttestMode = false;
        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.uploadedPages = [
            { file: {}, number: 1, name: 'a.pdf', size: 100, isPdf: true, dataUrl: 'data:x', pdfPageCount: 5, thumbnailUrl: null },
            { file: {}, number: 2, name: 'b.jpg', size: 100, isPdf: false, dataUrl: 'data:y', pdfPageCount: 0, thumbnailUrl: null }
        ];

        controller.updateReviewSection();

        expect(controller.reviewPageCountTarget.textContent).toContain('2 files');
        expect(controller.reviewPageCountTarget.textContent).toContain('6 total pages');
    });

    // --- triggerFileInput ---

    test('triggerFileInput clicks the file input', () => {
        const clickSpy = jest.spyOn(controller.fileInputTarget, 'click');
        controller.triggerFileInput();
        expect(clickSpy).toHaveBeenCalled();
    });

    // --- escapeHtml ---

    test('escapeHtml escapes special characters', () => {
        const result = controller.escapeHtml('<script>alert("xss")</script>');
        expect(result).toContain('&lt;script&gt;');
    });

    // --- getCsrfToken ---

    test('getCsrfToken reads from meta tag', () => {
        const token = controller.getCsrfToken();
        expect(token).toBe('test-csrf-token');
    });

    test('getCsrfToken reads from hidden input fallback', () => {
        // Remove meta tag
        const meta = document.querySelector('meta[name="csrf-token"]');
        meta.remove();

        // Add hidden input
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_csrfToken';
        input.value = 'input-csrf-token';
        document.body.appendChild(input);

        expect(controller.getCsrfToken()).toBe('input-csrf-token');
    });

    test('getCsrfToken returns empty when not found', () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        meta.remove();
        expect(controller.getCsrfToken()).toBe('');
    });

    // --- showError ---

    test('showError creates toast notification', () => {
        controller.showError('Something went wrong');

        const toast = document.querySelector('.toast');
        expect(toast).not.toBeNull();
        expect(toast.textContent).toContain('Something went wrong');
    });

    test('showError creates toast container if not present', () => {
        controller.showError('Error message');

        const toastContainer = document.querySelector('.toast-container');
        expect(toastContainer).not.toBeNull();
    });

    // --- submitForm ---

    test('submitForm prevents default and validates', async () => {
        const event = { preventDefault: jest.fn() };
        controller.selectedWaiverType = null;

        await controller.submitForm(event);

        expect(event.preventDefault).toHaveBeenCalled();
    });

    test('submitForm disables submit button on valid submission', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ success: true, redirectUrl: '/done' })
        }));

        controller.selectedWaiverType = { id: 10, name: 'Minor Waiver' };
        controller.uploadedPages = [{ file: new File(['x'], 'a.jpg', { type: 'image/jpeg' }), size: 100, isPdf: false, dataUrl: 'data:x', pdfPageCount: 0 }];

        const event = { preventDefault: jest.fn() };
        await controller.submitForm(event);

        expect(controller.submitButtonTarget.disabled).toBe(true);
    });

    // --- updateTotalSizeDisplay ---

    test('updateTotalSizeDisplay warns when approaching limit', () => {
        controller.uploadedPages = [
            { file: {}, size: 22 * 1024 * 1024, pdfPageCount: 0 }
        ];
        // Should not throw
        expect(() => controller.updateTotalSizeDisplay()).not.toThrow();
    });
});
