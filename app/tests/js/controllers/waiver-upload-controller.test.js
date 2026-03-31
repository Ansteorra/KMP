// Controller has no default export; it registers on window.Controllers
import '../../../plugins/Waivers/assets/js/controllers/waiver-upload-controller.js';
const WaiverUploadController = window.Controllers['waiver-upload'];

// Mock DataTransfer (not available in jsdom)
class MockDataTransfer {
    constructor() {
        this._files = [];
        this.items = {
            add: (file) => this._files.push(file)
        };
    }
    get files() {
        // Return an array-like that jsdom accepts via defineProperty
        return this._files;
    }
}
global.DataTransfer = MockDataTransfer;

describe('WaiverUploadController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="waiver-upload">
                <select data-waiver-upload-target="waiverType">
                    <option value="">-- Select --</option>
                    <option value="1">Minor</option>
                </select>
                <input type="file" data-waiver-upload-target="fileInput" multiple>
                <div data-waiver-upload-target="preview" style="display: none;">
                    <div id="file-preview-list"></div>
                </div>
                <div data-waiver-upload-target="progress" style="display: none;">
                    <div data-waiver-upload-target="progressBar" 
                         style="width: 0%"
                         aria-valuenow="0"></div>
                    <span data-waiver-upload-target="progressText">0%</span>
                </div>
                <button data-waiver-upload-target="submitButton">Upload</button>
            </div>
        `;

        controller = new WaiverUploadController();
        controller.element = document.querySelector('[data-controller="waiver-upload"]');

        const fileInput = document.querySelector('[data-waiver-upload-target="fileInput"]');
        // Make the files property writable (jsdom rejects non-FileList values)
        let storedFiles = fileInput.files;
        Object.defineProperty(fileInput, 'files', {
            get() { return storedFiles; },
            set(val) { storedFiles = val; },
            configurable: true
        });

        controller.waiverTypeTarget = document.querySelector('[data-waiver-upload-target="waiverType"]');
        controller.hasWaiverTypeTarget = true;
        controller.fileInputTarget = fileInput;
        controller.hasFileInputTarget = true;
        controller.previewTarget = document.querySelector('[data-waiver-upload-target="preview"]');
        controller.hasPreviewTarget = true;
        controller.progressTarget = document.querySelector('[data-waiver-upload-target="progress"]');
        controller.hasProgressTarget = true;
        controller.progressBarTarget = document.querySelector('[data-waiver-upload-target="progressBar"]');
        controller.hasProgressBarTarget = true;
        controller.progressTextTarget = document.querySelector('[data-waiver-upload-target="progressText"]');
        controller.hasProgressTextTarget = true;
        controller.submitButtonTarget = document.querySelector('[data-waiver-upload-target="submitButton"]');
        controller.hasSubmitButtonTarget = true;

        controller.connect();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        jest.useRealTimers();
    });

    // --- Instantiation ---

    test('registers on global Controllers', () => {
        expect(window.Controllers['waiver-upload']).toBe(WaiverUploadController);
    });

    test('connect initializes selectedFiles as empty array', () => {
        expect(controller.selectedFiles).toEqual([]);
    });

    // --- Static properties ---

    test('MAX_FILE_SIZE is 25MB', () => {
        expect(WaiverUploadController.MAX_FILE_SIZE).toBe(25 * 1024 * 1024);
    });

    test('ALLOWED_TYPES includes expected MIME types', () => {
        expect(WaiverUploadController.ALLOWED_TYPES).toContain('image/jpeg');
        expect(WaiverUploadController.ALLOWED_TYPES).toContain('image/png');
        expect(WaiverUploadController.ALLOWED_TYPES).toContain('application/pdf');
        expect(WaiverUploadController.ALLOWED_TYPES).toContain('image/webp');
    });

    // --- validateFile ---

    test('validateFile accepts a valid JPEG file', () => {
        const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });
        const result = controller.validateFile(file);
        expect(result.valid).toBe(true);
    });

    test('validateFile accepts a valid PDF file', () => {
        const file = new File(['pdf'], 'doc.pdf', { type: 'application/pdf' });
        const result = controller.validateFile(file);
        expect(result.valid).toBe(true);
    });

    test('validateFile accepts PDF by extension fallback', () => {
        const file = new File(['data'], 'scan.PDF', { type: '' });
        const result = controller.validateFile(file);
        expect(result.valid).toBe(true);
    });

    test('validateFile rejects oversized file', () => {
        const file = new File(['x'], 'big.jpg', { type: 'image/jpeg' });
        Object.defineProperty(file, 'size', { value: 30 * 1024 * 1024 });
        const result = controller.validateFile(file);
        expect(result.valid).toBe(false);
        expect(result.error).toContain('exceeds maximum');
    });

    test('validateFile rejects invalid file type', () => {
        const file = new File(['x'], 'virus.exe', { type: 'application/x-msdownload' });
        const result = controller.validateFile(file);
        expect(result.valid).toBe(false);
        expect(result.error).toContain('Invalid file type');
    });

    test('validateFile accepts BMP variants', () => {
        const bmp1 = new File(['x'], 'img.bmp', { type: 'image/bmp' });
        const bmp2 = new File(['x'], 'img.bmp', { type: 'image/x-ms-bmp' });
        expect(controller.validateFile(bmp1).valid).toBe(true);
        expect(controller.validateFile(bmp2).valid).toBe(true);
    });

    // --- formatFileSize ---

    test('formatFileSize returns 0 Bytes for zero', () => {
        expect(controller.formatFileSize(0)).toBe('0 Bytes');
    });

    test('formatFileSize formats kilobytes', () => {
        expect(controller.formatFileSize(1024)).toBe('1 KB');
    });

    test('formatFileSize formats megabytes', () => {
        const result = controller.formatFileSize(1048576);
        expect(result).toBe('1 MB');
    });

    // --- handleFileSelect ---

    test('handleFileSelect ignores empty file selection', () => {
        controller.handleFileSelect({ target: { files: [] } });
        expect(controller.selectedFiles).toEqual([]);
    });

    test('handleFileSelect adds valid files to selectedFiles', () => {
        const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });

        controller.handleFileSelect({ target: { files: [file] } });

        expect(controller.selectedFiles).toHaveLength(1);
        expect(controller.selectedFiles[0].name).toBe('photo.jpg');
    });

    test('handleFileSelect filters out invalid files', () => {
        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
        const goodFile = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });
        const badFile = new File(['x'], 'virus.exe', { type: 'application/x-msdownload' });

        controller.handleFileSelect({ target: { files: [goodFile, badFile] } });

        expect(controller.selectedFiles).toHaveLength(1);
        expect(controller.selectedFiles[0].name).toBe('photo.jpg');
        expect(alertSpy).toHaveBeenCalled();
    });

    test('handleFileSelect appends new files to existing selection', () => {
        const file1 = new File(['a'], 'a.jpg', { type: 'image/jpeg' });
        const file2 = new File(['b'], 'b.png', { type: 'image/png' });

        controller.handleFileSelect({ target: { files: [file1] } });
        controller.handleFileSelect({ target: { files: [file2] } });

        expect(controller.selectedFiles).toHaveLength(2);
    });

    test('handleFileSelect shows preview when files are valid', () => {
        const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });

        controller.handleFileSelect({ target: { files: [file] } });

        expect(controller.previewTarget.style.display).toBe('block');
    });

    // --- showPreview ---

    test('showPreview renders file list items', () => {
        controller.selectedFiles = [
            new File(['a'], 'page1.jpg', { type: 'image/jpeg' }),
            new File(['b'], 'page2.png', { type: 'image/png' })
        ];

        controller.showPreview();

        const previewList = document.getElementById('file-preview-list');
        expect(previewList.children).toHaveLength(2);
    });

    test('showPreview does nothing without preview target', () => {
        controller.hasPreviewTarget = false;
        controller.selectedFiles = [new File(['a'], 'test.jpg', { type: 'image/jpeg' })];
        // Should not throw
        expect(() => controller.showPreview()).not.toThrow();
    });

    // --- hidePreview ---

    test('hidePreview hides preview area', () => {
        controller.previewTarget.style.display = 'block';
        controller.hidePreview();
        expect(controller.previewTarget.style.display).toBe('none');
    });

    // --- removeFile ---

    test('removeFile removes file at index and updates preview', () => {
        const file1 = new File(['a'], 'a.jpg', { type: 'image/jpeg' });
        const file2 = new File(['b'], 'b.jpg', { type: 'image/jpeg' });
        controller.selectedFiles = [file1, file2];

        controller.removeFile(0);

        expect(controller.selectedFiles).toHaveLength(1);
        expect(controller.selectedFiles[0].name).toBe('b.jpg');
    });

    test('removeFile hides preview and clears input when last file removed', () => {
        const file = new File(['a'], 'a.jpg', { type: 'image/jpeg' });
        controller.selectedFiles = [file];

        controller.removeFile(0);

        expect(controller.selectedFiles).toHaveLength(0);
        expect(controller.previewTarget.style.display).toBe('none');
    });

    // --- handleSubmit ---

    test('handleSubmit prevents submission when no waiver type selected', () => {
        const event = { preventDefault: jest.fn() };
        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});

        controller.waiverTypeTarget.value = '';
        controller.handleSubmit(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(alertSpy).toHaveBeenCalledWith('Please select a waiver type');
    });

    test('handleSubmit prevents submission when no files selected', () => {
        const event = { preventDefault: jest.fn() };
        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});

        controller.waiverTypeTarget.value = '1';
        controller.selectedFiles = [];
        controller.handleSubmit(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(alertSpy).toHaveBeenCalledWith('Please select at least one image file to upload');
    });

    test('handleSubmit shows progress and disables button on valid submission', () => {
        jest.useFakeTimers();
        const event = { preventDefault: jest.fn() };

        controller.waiverTypeTarget.value = '1';
        controller.selectedFiles = [new File(['img'], 'p.jpg', { type: 'image/jpeg' })];

        controller.handleSubmit(event);

        expect(event.preventDefault).not.toHaveBeenCalled();
        expect(controller.progressTarget.style.display).toBe('block');
        expect(controller.submitButtonTarget.disabled).toBe(true);
        expect(controller.submitButtonTarget.innerHTML).toContain('Uploading');

        jest.runAllTimers();
    });

    // --- updateProgress ---

    test('updateProgress sets width and text', () => {
        controller.updateProgress(50);

        expect(controller.progressBarTarget.style.width).toBe('50%');
        expect(controller.progressBarTarget.getAttribute('aria-valuenow')).toBe('50');
        expect(controller.progressTextTarget.textContent).toBe('50%');
    });

    test('updateProgress does nothing without targets', () => {
        controller.hasProgressBarTarget = false;
        controller.hasProgressTextTarget = false;

        // Should not throw
        expect(() => controller.updateProgress(75)).not.toThrow();
    });

    // --- simulateProgress ---

    test('simulateProgress increments up to 95%', () => {
        jest.useFakeTimers();
        controller.simulateProgress();

        // Advance enough intervals (each 200ms, increments of 5, 19 * 5 = 95)
        for (let i = 0; i < 20; i++) {
            jest.advanceTimersByTime(200);
        }

        expect(controller.progressBarTarget.style.width).toBe('95%');
    });

    // --- escapeHtml ---

    test('escapeHtml escapes HTML entities', () => {
        const result = controller.escapeHtml('<b>bold</b>');
        expect(result).toContain('&lt;b&gt;');
    });
});
