import '../../../assets/js/controllers/image-preview-controller.js';
const ImagePreviewController = window.Controllers['image-preview'];

describe('ImagePreviewController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="image-preview">
                <input type="file" data-image-preview-target="file" accept="image/*">
                <div data-image-preview-target="loading">Loading...</div>
                <img data-image-preview-target="preview" hidden>
            </div>
        `;

        controller = new ImagePreviewController();
        controller.element = document.querySelector('[data-controller="image-preview"]');
        controller.fileTarget = document.querySelector('[data-image-preview-target="file"]');
        controller.previewTarget = document.querySelector('[data-image-preview-target="preview"]');
        controller.loadingTarget = document.querySelector('[data-image-preview-target="loading"]');
        controller.hasMaxSizeValue = false;
        controller.maxSizeValue = 0;
        controller.hasMaxSizeFormattedValue = false;
        controller.maxSizeFormattedValue = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(ImagePreviewController.targets).toEqual(expect.arrayContaining(['file', 'preview', 'loading']));
    });

    test('has correct static values', () => {
        expect(ImagePreviewController.values).toHaveProperty('maxSize', Number);
        expect(ImagePreviewController.values).toHaveProperty('maxSizeFormatted', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['image-preview']).toBe(ImagePreviewController);
    });

    // --- connect ---

    test('connect inherits maxSize from parent file-size-validator', () => {
        document.body.innerHTML = `
            <div data-file-size-validator-max-size-value="5242880"
                 data-file-size-validator-max-size-formatted-value="5MB">
                <div data-controller="image-preview">
                    <input type="file" data-image-preview-target="file">
                    <div data-image-preview-target="loading"></div>
                    <img data-image-preview-target="preview" hidden>
                </div>
            </div>
        `;

        const ctrl = new ImagePreviewController();
        ctrl.element = document.querySelector('[data-controller="image-preview"]');
        ctrl.hasMaxSizeValue = false;
        ctrl.maxSizeValue = 0;
        ctrl.hasMaxSizeFormattedValue = false;
        ctrl.maxSizeFormattedValue = '';

        ctrl.connect();

        expect(ctrl.maxSizeValue).toBe(5242880);
        expect(ctrl.maxSizeFormattedValue).toBe('5MB');
    });

    test('connect does not override existing maxSize', () => {
        controller.hasMaxSizeValue = true;
        controller.maxSizeValue = 1000;
        controller.connect();
        expect(controller.maxSizeValue).toBe(1000);
    });

    // --- formatBytes ---

    test('formatBytes returns 0 Bytes for zero', () => {
        expect(controller.formatBytes(0)).toBe('0 Bytes');
    });

    test('formatBytes formats kilobytes', () => {
        expect(controller.formatBytes(1024)).toBe('1 KB');
    });

    test('formatBytes formats megabytes', () => {
        expect(controller.formatBytes(1048576)).toBe('1 MB');
    });

    test('formatBytes returns 0 Bytes for null/undefined', () => {
        expect(controller.formatBytes(null)).toBe('0 Bytes');
        expect(controller.formatBytes(undefined)).toBe('0 Bytes');
    });

    // --- buildOversizeMessage ---

    test('buildOversizeMessage includes file name and size', () => {
        controller.maxSizeFormattedValue = '5MB';
        const file = { name: 'photo.jpg', size: 10 * 1024 * 1024 };
        const msg = controller.buildOversizeMessage(file);
        expect(msg).toContain('photo.jpg');
        expect(msg).toContain('5MB');
    });

    test('buildOversizeMessage uses formatBytes when no formatted value', () => {
        controller.maxSizeFormattedValue = '';
        controller.maxSizeValue = 5242880;
        const file = { name: 'test.jpg', size: 10485760 };
        const msg = controller.buildOversizeMessage(file);
        expect(msg).toContain('test.jpg');
        expect(msg).toContain('5');
    });

    // --- preview ---

    test('preview reads file and shows image', () => {
        const file = new File(['test'], 'photo.jpg', { type: 'image/jpeg' });
        const event = { target: { files: [file] } };

        // Mock FileReader
        const mockReader = {
            readAsDataURL: jest.fn(),
            onload: null
        };
        jest.spyOn(window, 'FileReader').mockImplementation(() => mockReader);

        controller.preview(event);

        expect(mockReader.readAsDataURL).toHaveBeenCalledWith(file);

        // Simulate load
        mockReader.result = 'data:image/jpeg;base64,test';
        mockReader.onload();

        expect(controller.previewTarget.src).toContain('data:image/jpeg');
        expect(controller.loadingTarget.classList.contains('d-none')).toBe(true);
        expect(controller.previewTarget.hidden).toBe(false);
    });

    test('preview does nothing when no files selected', () => {
        const event = { target: { files: [] } };
        const spy = jest.spyOn(window, 'FileReader');
        controller.preview(event);
        expect(spy).not.toHaveBeenCalled();
    });

    test('preview alerts and clears input when file exceeds max size', () => {
        controller.hasMaxSizeValue = true;
        controller.maxSizeValue = 1000;
        controller.maxSizeFormattedValue = '1KB';

        const file = { name: 'big.jpg', size: 5000 };
        const event = { target: { files: [file], value: 'big.jpg' } };

        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
        controller.preview(event);

        expect(alertSpy).toHaveBeenCalled();
        expect(event.target.value).toBe('');
    });
});
