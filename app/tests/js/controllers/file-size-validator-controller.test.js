import FileSizeValidatorController from '../../../assets/js/controllers/file-size-validator-controller.js';

describe('FileSizeValidatorController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="file-size-validator">
                <input type="file" data-file-size-validator-target="fileInput">
                <div data-file-size-validator-target="warning" class="d-none"></div>
                <button type="submit" data-file-size-validator-target="submitButton">Upload</button>
            </div>
        `;

        controller = new FileSizeValidatorController();
        controller.element = document.querySelector('[data-controller="file-size-validator"]');

        const fileInput = document.querySelector('[data-file-size-validator-target="fileInput"]');
        const warning = document.querySelector('[data-file-size-validator-target="warning"]');
        const submitButton = document.querySelector('[data-file-size-validator-target="submitButton"]');

        controller.fileInputTargets = [fileInput];
        controller.fileInputTarget = fileInput;
        controller.hasFileInputTarget = true;
        controller.warningTarget = warning;
        controller.hasWarningTarget = true;
        controller.submitButtonTargets = [submitButton];
        controller.submitButtonTarget = submitButton;
        controller.hasSubmitButtonTarget = true;

        controller.maxSizeValue = 25 * 1024 * 1024; // 25MB
        controller.maxSizeFormattedValue = '25MB';
        controller.totalMaxSizeValue = 50 * 1024 * 1024; // 50MB
        controller.hasTotalMaxSizeValue = true;
        controller.showWarningValue = true;
        controller.warningClassValue = 'alert alert-warning';
        controller.errorClassValue = 'alert alert-danger';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Instantiation & connect ---

    test('registers on global Controllers', () => {
        expect(window.Controllers['file-size-validator']).toBe(FileSizeValidatorController);
    });

    test('connect sets totalMaxSize from maxSize when not specified', () => {
        controller.hasTotalMaxSizeValue = false;
        controller.totalMaxSizeValue = 0;
        controller.connect();
        expect(controller.totalMaxSizeValue).toBe(controller.maxSizeValue);
    });

    test('connect keeps totalMaxSize when already specified', () => {
        const totalMax = 50 * 1024 * 1024;
        controller.totalMaxSizeValue = totalMax;
        controller.hasTotalMaxSizeValue = true;
        controller.connect();
        expect(controller.totalMaxSizeValue).toBe(totalMax);
    });

    // --- formatBytes ---

    test('formatBytes returns 0 Bytes for zero', () => {
        expect(controller.formatBytes(0)).toBe('0 Bytes');
    });

    test('formatBytes formats kilobytes', () => {
        expect(controller.formatBytes(1024)).toBe('1KB');
    });

    test('formatBytes formats megabytes', () => {
        expect(controller.formatBytes(1048576)).toBe('1MB');
    });

    test('formatBytes formats gigabytes', () => {
        expect(controller.formatBytes(1073741824)).toBe('1GB');
    });

    test('formatBytes with custom decimals', () => {
        expect(controller.formatBytes(1536, 1)).toBe('1.5KB');
    });

    // --- escapeHtml ---

    test('escapeHtml escapes special characters', () => {
        const result = controller.escapeHtml('<script>alert("xss")</script>');
        expect(result).not.toContain('<script>');
        expect(result).toContain('&lt;script&gt;');
    });

    test('escapeHtml returns plain text unchanged', () => {
        expect(controller.escapeHtml('hello world')).toBe('hello world');
    });

    // --- checkFileSizes ---

    test('checkFileSizes returns valid for small files', () => {
        const files = [
            new File(['x'.repeat(100)], 'small.txt', { type: 'text/plain' })
        ];
        const result = controller.checkFileSizes(files);
        expect(result.valid).toBe(true);
        expect(result.warning).toBe(false);
    });

    test('checkFileSizes returns invalid for oversized file', () => {
        const oversized = { name: 'big.zip', size: 30 * 1024 * 1024 };
        const result = controller.checkFileSizes([oversized]);
        expect(result.valid).toBe(false);
        expect(result.invalidFiles).toHaveLength(1);
        expect(result.invalidFiles[0].name).toBe('big.zip');
    });

    test('checkFileSizes returns warning when total exceeds totalMaxSize', () => {
        controller.totalMaxSizeValue = 40 * 1024 * 1024;
        const files = [
            { name: 'a.txt', size: 22 * 1024 * 1024 },
            { name: 'b.txt', size: 22 * 1024 * 1024 }
        ];
        const result = controller.checkFileSizes(files);
        expect(result.valid).toBe(true);
        expect(result.warning).toBe(true);
    });

    test('checkFileSizes tracks total file count', () => {
        const files = [
            { name: 'a.txt', size: 100 },
            { name: 'b.txt', size: 200 },
            { name: 'c.txt', size: 300 }
        ];
        const result = controller.checkFileSizes(files);
        expect(result.totalFileCount).toBe(3);
        expect(result.totalSize).toBe(600);
    });

    // --- buildInvalidFilesMessage ---

    test('builds message for single invalid file', () => {
        const invalidFiles = [{
            name: 'big.pdf',
            size: 30 * 1024 * 1024,
            formattedSize: '30MB',
            exceededBy: 5 * 1024 * 1024
        }];
        const msg = controller.buildInvalidFilesMessage(invalidFiles);
        expect(msg).toContain('big.pdf');
        expect(msg).toContain('30MB');
        expect(msg).toContain('25MB');
    });

    test('builds message for multiple invalid files', () => {
        const invalidFiles = [
            { name: 'a.pdf', formattedSize: '30MB' },
            { name: 'b.pdf', formattedSize: '28MB' }
        ];
        const msg = controller.buildInvalidFilesMessage(invalidFiles);
        expect(msg).toContain('2 file(s)');
        expect(msg).toContain('a.pdf');
        expect(msg).toContain('b.pdf');
    });

    // --- buildTotalSizeWarningMessage ---

    test('builds total size warning for single file', () => {
        const msg = controller.buildTotalSizeWarningMessage(60 * 1024 * 1024, 1);
        expect(msg).toContain('Warning');
        expect(msg).toContain('file size');
    });

    test('builds total size warning for multiple files', () => {
        const msg = controller.buildTotalSizeWarningMessage(60 * 1024 * 1024, 3);
        expect(msg).toContain('3 file(s)');
        expect(msg).toContain('combined size');
    });

    // --- validateFiles ---

    test('validateFiles clears warning and enables submit when no files', () => {
        const fileInput = controller.fileInputTarget;
        Object.defineProperty(fileInput, 'files', { value: [], configurable: true });

        controller.validateFiles({ target: fileInput });

        expect(controller.warningTarget.classList.contains('d-none')).toBe(true);
        expect(controller.submitButtonTarget.disabled).toBe(false);
    });

    test('validateFiles disables submit for oversized file', () => {
        const oversizedFile = new File(['x'], 'big.zip', { type: 'application/zip' });
        Object.defineProperty(oversizedFile, 'size', { value: 30 * 1024 * 1024 });

        const fileInput = controller.fileInputTarget;
        Object.defineProperty(fileInput, 'files', {
            value: [oversizedFile],
            configurable: true
        });

        controller.validateFiles({ target: fileInput });

        expect(controller.submitButtonTarget.disabled).toBe(true);
    });

    test('validateFiles enables submit for valid file', () => {
        const smallFile = new File(['hello'], 'small.txt', { type: 'text/plain' });

        const fileInput = controller.fileInputTarget;
        Object.defineProperty(fileInput, 'files', {
            value: [smallFile],
            configurable: true
        });

        controller.validateFiles({ target: fileInput });

        expect(controller.submitButtonTarget.disabled).toBe(false);
    });

    test('validateFiles dispatches invalid event for oversized file', () => {
        const dispatchSpy = jest.spyOn(controller.element, 'dispatchEvent');
        const oversizedFile = { name: 'big.zip', size: 30 * 1024 * 1024 };

        const fileInput = controller.fileInputTarget;
        Object.defineProperty(fileInput, 'files', {
            value: [oversizedFile],
            configurable: true
        });

        controller.validateFiles({ target: fileInput });

        const invalidEvent = dispatchSpy.mock.calls.find(
            call => call[0].type === 'file-size-validator:invalid'
        );
        expect(invalidEvent).toBeDefined();
    });

    test('validateFiles dispatches valid event for good files', () => {
        const dispatchSpy = jest.spyOn(controller.element, 'dispatchEvent');
        const smallFile = new File(['hello'], 'small.txt', { type: 'text/plain' });

        const fileInput = controller.fileInputTarget;
        Object.defineProperty(fileInput, 'files', {
            value: [smallFile],
            configurable: true
        });

        controller.validateFiles({ target: fileInput });

        const validEvent = dispatchSpy.mock.calls.find(
            call => call[0].type === 'file-size-validator:valid'
        );
        expect(validEvent).toBeDefined();
    });

    test('validateFiles dispatches warning event when total exceeds limit', () => {
        controller.totalMaxSizeValue = 40 * 1024 * 1024;
        const dispatchSpy = jest.spyOn(controller.element, 'dispatchEvent');

        const file1 = { name: 'a.txt', size: 22 * 1024 * 1024 };
        const file2 = { name: 'b.txt', size: 22 * 1024 * 1024 };

        const fileInput = controller.fileInputTarget;
        Object.defineProperty(fileInput, 'files', {
            value: [file1, file2],
            configurable: true
        });

        controller.validateFiles({ target: fileInput });

        const warningEvent = dispatchSpy.mock.calls.find(
            call => call[0].type === 'file-size-validator:warning'
        );
        expect(warningEvent).toBeDefined();
    });

    // --- showInvalidFilesWarning ---

    test('showInvalidFilesWarning shows error class on warning target', () => {
        const validation = {
            message: 'File too large',
            invalidFiles: [{ name: 'big.zip', formattedSize: '30MB' }]
        };

        controller.showInvalidFilesWarning(validation);

        expect(controller.warningTarget.className).toBe('alert alert-danger');
        expect(controller.warningTarget.classList.contains('d-none')).toBe(false);
    });

    test('showInvalidFilesWarning falls back to alert when no warning target', () => {
        controller.hasWarningTarget = false;
        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});

        controller.showInvalidFilesWarning({ message: 'Too large' });

        expect(alertSpy).toHaveBeenCalledWith('Too large');
    });

    // --- showTotalSizeWarning ---

    test('showTotalSizeWarning shows warning class on warning target', () => {
        const validation = { message: 'Total size warning' };

        controller.showTotalSizeWarning(validation);

        expect(controller.warningTarget.className).toBe('alert alert-warning');
        expect(controller.warningTarget.classList.contains('d-none')).toBe(false);
    });

    // --- clearWarning ---

    test('clearWarning hides and empties warning target', () => {
        controller.warningTarget.innerHTML = 'Some error';
        controller.warningTarget.classList.remove('d-none');

        controller.clearWarning();

        expect(controller.warningTarget.classList.contains('d-none')).toBe(true);
        expect(controller.warningTarget.innerHTML).toBe('');
    });

    // --- disableSubmit / enableSubmit ---

    test('disableSubmit disables all submit buttons', () => {
        const btn1 = document.createElement('button');
        const btn2 = document.createElement('button');
        controller.submitButtonTargets = [btn1, btn2];

        controller.disableSubmit();

        expect(btn1.disabled).toBe(true);
        expect(btn2.disabled).toBe(true);
    });

    test('enableSubmit enables all submit buttons', () => {
        const btn1 = document.createElement('button');
        btn1.disabled = true;
        const btn2 = document.createElement('button');
        btn2.disabled = true;
        controller.submitButtonTargets = [btn1, btn2];

        controller.enableSubmit();

        expect(btn1.disabled).toBe(false);
        expect(btn2.disabled).toBe(false);
    });

    // --- formatWarningMessage ---

    test('formatWarningMessage uses error icon for error type', () => {
        const result = controller.formatWarningMessage('Bad file', 'error');
        expect(result).toContain('bi-exclamation-triangle-fill');
        expect(result).toContain('Bad file');
    });

    test('formatWarningMessage uses warning icon for warning type', () => {
        const result = controller.formatWarningMessage('Watch out', 'warning');
        expect(result).toContain('bi-exclamation-circle-fill');
    });

    test('formatWarningMessage preserves line breaks as <br>', () => {
        const result = controller.formatWarningMessage('line1\nline2');
        expect(result).toContain('<br>');
    });

    // --- dispatch ---

    test('dispatch fires custom event on element', () => {
        const handler = jest.fn();
        controller.element.addEventListener('file-size-validator:valid', handler);

        controller.dispatch('valid', { detail: { files: [] } });

        expect(handler).toHaveBeenCalled();
    });
});
