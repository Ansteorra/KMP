// Controller has no default export; it registers on window.Controllers
import '../../../plugins/Waivers/assets/js/controllers/camera-capture-controller.js';
const CameraCaptureController = window.Controllers['camera-capture'];

describe('CameraCaptureController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="camera-capture">
                <input type="file" accept="image/*" capture="environment"
                       data-camera-capture-target="cameraInput">
                <button data-camera-capture-target="cameraButton">Take Photo</button>
                <div data-camera-capture-target="preview" style="display: none;"></div>
            </div>
        `;

        controller = new CameraCaptureController();
        controller.element = document.querySelector('[data-controller="camera-capture"]');
        controller.cameraInputTarget = document.querySelector('[data-camera-capture-target="cameraInput"]');
        controller.hasCameraInputTarget = true;
        controller.cameraButtonTarget = document.querySelector('[data-camera-capture-target="cameraButton"]');
        controller.hasCameraButtonTarget = true;
        controller.previewTarget = document.querySelector('[data-camera-capture-target="preview"]');
        controller.hasPreviewTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Instantiation ---

    test('registers on global Controllers', () => {
        expect(window.Controllers['camera-capture']).toBe(CameraCaptureController);
    });

    test('connect calls detectMobileDevice', () => {
        const spy = jest.spyOn(controller, 'detectMobileDevice');
        controller.connect();
        expect(spy).toHaveBeenCalled();
    });

    // --- detectMobileDevice ---

    test('detectMobileDevice detects mobile user agent', () => {
        const originalUA = navigator.userAgent;
        Object.defineProperty(navigator, 'userAgent', {
            value: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
            configurable: true
        });

        // Add a form-text help element
        const helpText = document.createElement('small');
        helpText.className = 'form-text';
        helpText.textContent = 'Take a photo';
        controller.cameraInputTarget.parentElement.appendChild(helpText);

        controller.detectMobileDevice();

        expect(helpText.classList.contains('text-success')).toBe(true);
        expect(helpText.innerHTML).toContain('bi-camera-fill');

        Object.defineProperty(navigator, 'userAgent', {
            value: originalUA,
            configurable: true
        });
    });

    test('detectMobileDevice does not modify help text on desktop', () => {
        Object.defineProperty(navigator, 'userAgent', {
            value: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
            configurable: true
        });

        const helpText = document.createElement('small');
        helpText.className = 'form-text';
        helpText.textContent = 'Take a photo';
        controller.cameraInputTarget.parentElement.appendChild(helpText);

        controller.detectMobileDevice();

        expect(helpText.classList.contains('text-success')).toBe(false);
    });

    // --- triggerCamera ---

    test('triggerCamera clicks the camera input', () => {
        const clickSpy = jest.spyOn(controller.cameraInputTarget, 'click');
        controller.triggerCamera({ preventDefault: jest.fn() });
        expect(clickSpy).toHaveBeenCalled();
    });

    test('triggerCamera does nothing without camera input target', () => {
        controller.hasCameraInputTarget = false;
        // Should not throw
        expect(() => controller.triggerCamera({ preventDefault: jest.fn() })).not.toThrow();
    });

    // --- handleCapture ---

    test('handleCapture dispatches imageCaptured event', () => {
        const dispatchSpy = jest.spyOn(controller, 'dispatch');
        const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });

        controller.handleCapture({ target: { files: [file] } });

        expect(dispatchSpy).toHaveBeenCalledWith('imageCaptured', {
            detail: { files: [file] }
        });
    });

    test('handleCapture does nothing when no files', () => {
        const dispatchSpy = jest.spyOn(controller, 'dispatch');
        controller.handleCapture({ target: { files: [] } });
        expect(dispatchSpy).not.toHaveBeenCalled();
    });

    test('handleCapture does nothing when files is null-ish', () => {
        const dispatchSpy = jest.spyOn(controller, 'dispatch');
        controller.handleCapture({ target: { files: null } });
        expect(dispatchSpy).not.toHaveBeenCalled();
    });

    test('handleCapture shows image preview', () => {
        const mockFileReader = {
            readAsDataURL: jest.fn(),
            result: 'data:image/jpeg;base64,abc123',
            onload: null
        };
        jest.spyOn(global, 'FileReader').mockImplementation(() => mockFileReader);

        const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });
        Object.defineProperty(file, 'size', { value: 2048 });

        controller.handleCapture({ target: { files: [file] } });

        // Simulate FileReader onload
        mockFileReader.onload({ target: { result: 'data:image/jpeg;base64,abc123' } });

        expect(controller.previewTarget.innerHTML).toContain('img');
        expect(controller.previewTarget.style.display).toBe('block');
    });

    test('handleCapture skips preview without preview target', () => {
        controller.hasPreviewTarget = false;
        const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });

        // Should not throw
        expect(() => controller.handleCapture({ target: { files: [file] } })).not.toThrow();
    });

    // --- formatFileSize ---

    test('formatFileSize returns 0 Bytes for zero', () => {
        expect(controller.formatFileSize(0)).toBe('0 Bytes');
    });

    test('formatFileSize formats kilobytes', () => {
        expect(controller.formatFileSize(1024)).toBe('1 KB');
    });

    test('formatFileSize formats megabytes', () => {
        expect(controller.formatFileSize(1048576)).toBe('1 MB');
    });

    // --- supportsCameraCapture ---

    test('supportsCameraCapture returns boolean', () => {
        const result = CameraCaptureController.supportsCameraCapture();
        expect(typeof result).toBe('boolean');
    });

    // --- showImagePreview ---

    test('showImagePreview renders card with image', () => {
        const mockFileReader = {
            readAsDataURL: jest.fn(),
            result: null,
            onload: null
        };
        jest.spyOn(global, 'FileReader').mockImplementation(() => mockFileReader);

        const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });
        Object.defineProperty(file, 'size', { value: 5120 });

        controller.showImagePreview(file);

        // Simulate FileReader onload
        mockFileReader.onload({ target: { result: 'data:image/jpeg;base64,abc' } });

        expect(controller.previewTarget.innerHTML).toContain('card');
        expect(controller.previewTarget.innerHTML).toContain('photo.jpg');
        expect(controller.previewTarget.style.display).toBe('block');
    });
});
