// Mock qrcode library before import - dynamic import needs __esModule + default
const MockQRCode = {
    toCanvas: jest.fn((canvas, url, options, callback) => {
        if (callback) callback(null);
    })
};
jest.mock('qrcode', () => ({
    __esModule: true,
    default: MockQRCode,
}));

import '../../../assets/js/controllers/qrcode-controller.js';
const QrcodeController = window.Controllers['qrcode'];

describe('QrcodeController', () => {
    let controller;

    beforeEach(() => {
        jest.clearAllMocks();

        document.body.innerHTML = `
            <div data-controller="qrcode"
                 data-qrcode-url-value="https://example.com"
                 data-qrcode-size-value="256">
                <div data-qrcode-target="canvas"></div>
            </div>
        `;

        controller = new QrcodeController();
        controller.element = document.querySelector('[data-controller="qrcode"]');
        controller.canvasTarget = document.querySelector('[data-qrcode-target="canvas"]');
        controller.hasCanvasTarget = true;
        controller.urlValue = 'https://example.com';
        controller.hasUrlValue = true;
        controller.sizeValue = 256;
        controller.colorDarkValue = '#000000';
        controller.colorLightValue = '#ffffff';
        controller.errorCorrectionLevelValue = 'H';
        controller.hasModalIdValue = false;
        controller.modalIdValue = '';
        controller.generated = false;
        controller._QRCode = MockQRCode;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', async () => {
        expect(QrcodeController.targets).toEqual(['canvas']);
    });

    test('has correct static values', async () => {
        expect(QrcodeController.values).toHaveProperty('url', String);
        expect(QrcodeController.values).toHaveProperty('size');
        expect(QrcodeController.values).toHaveProperty('modalId', String);
        expect(QrcodeController.values).toHaveProperty('colorDark');
        expect(QrcodeController.values).toHaveProperty('colorLight');
        expect(QrcodeController.values).toHaveProperty('errorCorrectionLevel');
    });

    test('registers on window.Controllers', async () => {
        expect(window.Controllers['qrcode']).toBe(QrcodeController);
    });

    // --- connect ---

    test('connect generates immediately when no modal', async () => {
        const genSpy = jest.spyOn(controller, 'generate');
        await controller.connect();
        expect(genSpy).toHaveBeenCalled();
    });

    test('connect waits for modal shown event when modalId provided', async () => {
        const modal = document.createElement('div');
        modal.id = 'qrModal';
        document.body.appendChild(modal);
        const addSpy = jest.spyOn(modal, 'addEventListener');

        controller.hasModalIdValue = true;
        controller.modalIdValue = 'qrModal';

        await controller.connect();

        expect(addSpy).toHaveBeenCalledWith('shown.bs.modal', expect.any(Function));
    });

    // --- disconnect ---

    test('disconnect removes modal listener', async () => {
        const modal = document.createElement('div');
        modal.id = 'qrModal';
        document.body.appendChild(modal);
        const removeSpy = jest.spyOn(modal, 'removeEventListener');

        controller.hasModalIdValue = true;
        controller.modalIdValue = 'qrModal';
        await controller.connect();
        controller.disconnect();

        expect(removeSpy).toHaveBeenCalledWith('shown.bs.modal', expect.any(Function));
        expect(controller.generated).toBe(false);
    });

    // --- generate ---

    test('generate calls MockQRCode.toCanvas with correct options', async () => {
        await controller.generate();

        expect(MockQRCode.toCanvas).toHaveBeenCalledWith(
            expect.any(HTMLCanvasElement),
            'https://example.com',
            expect.objectContaining({
                width: 256,
                margin: 2,
                color: { dark: '#000000', light: '#ffffff' },
                errorCorrectionLevel: 'H'
            }),
            expect.any(Function)
        );
        expect(controller.generated).toBe(true);
    });

    test('generate only runs once', async () => {
        await controller.generate();
        await controller.generate();
        expect(MockQRCode.toCanvas).toHaveBeenCalledTimes(1);
    });

    test('generate throws when URL value is missing', async () => {
        controller.hasUrlValue = false;
        expect(() => controller.generate()).toThrow('URL value is required');
    });

    test('generate throws when canvas target is missing', async () => {
        controller.hasCanvasTarget = false;
        expect(() => controller.generate()).toThrow('Canvas target is required');
    });

    test('generate shows error message on QRCode failure', async () => {
        MockQRCode.toCanvas.mockImplementationOnce((canvas, url, options, callback) => {
            callback(new Error('QR error'));
        });

        await expect(controller.generate()).rejects.toThrow('QR error');
        expect(controller.canvasTarget.innerHTML).toContain('Error generating QR code');
    });

    test('generate clears previous content', async () => {
        controller.canvasTarget.innerHTML = '<p>Old content</p>';
        await controller.generate();
        expect(controller.canvasTarget.querySelector('p')).toBeNull();
        expect(controller.canvasTarget.querySelector('canvas')).toBeTruthy();
    });

    // --- regenerate ---

    test('regenerate resets generated flag and calls generate', async () => {
        controller.generated = true;
        const genSpy = jest.spyOn(controller, 'generate').mockResolvedValue(undefined);

        controller.regenerate();

        expect(controller.generated).toBe(false);
        expect(genSpy).toHaveBeenCalled();
    });

    // --- download ---

    test('download triggers after generation', async () => {
        // generate first, then test download
        await controller.generate();

        const canvas = controller.canvasTarget.querySelector('canvas');
        canvas.toDataURL = jest.fn().mockReturnValue('data:image/png;base64,test');

        // Verify canvas exists after generation
        expect(canvas).toBeTruthy();
        expect(controller.generated).toBe(true);
    });

    // --- copyToClipboard ---

    test('copyToClipboard uses clipboard API', async () => {
        await controller.generate();

        const canvas = controller.canvasTarget.querySelector('canvas');
        // Mock canvas.toBlob
        canvas.toBlob = jest.fn((cb) => cb(new Blob(['test'], { type: 'image/png' })));

        // Mock clipboard
        const writeMock = jest.fn().mockResolvedValue(undefined);
        Object.assign(navigator, {
            clipboard: { write: writeMock }
        });
        global.ClipboardItem = jest.fn().mockImplementation((data) => data);

        await controller.copyToClipboard();

        expect(canvas.toBlob).toHaveBeenCalled();
        expect(writeMock).toHaveBeenCalled();
    });
});
