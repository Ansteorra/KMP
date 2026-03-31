import MobilePhotoSourceController from '../../../assets/js/controllers/mobile-photo-source-controller.js';

describe('MobilePhotoSourceController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="mobile-photo-source">
                <input type="file" data-mobile-photo-source-target="fileInput" accept="image/*">
                <button data-action="click->mobile-photo-source#chooseCamera">Camera</button>
                <button data-action="click->mobile-photo-source#chooseGallery">Gallery</button>
            </div>
        `;

        controller = new MobilePhotoSourceController();
        controller.element = document.querySelector('[data-controller="mobile-photo-source"]');
        controller.fileInputTarget = document.querySelector('[data-mobile-photo-source-target="fileInput"]');
        controller.hasFileInputTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['mobile-photo-source']).toBe(MobilePhotoSourceController);
    });

    test('has correct static targets', () => {
        expect(MobilePhotoSourceController.targets).toEqual(['fileInput']);
    });

    test('chooseCamera sets capture attribute to user', () => {
        const event = { preventDefault: jest.fn() };
        const clickSpy = jest.spyOn(controller.fileInputTarget, 'click').mockImplementation(() => {});
        controller.chooseCamera(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.fileInputTarget.getAttribute('capture')).toBe('user');
        expect(clickSpy).toHaveBeenCalled();
    });

    test('chooseGallery removes capture attribute', () => {
        controller.fileInputTarget.setAttribute('capture', 'user');
        const event = { preventDefault: jest.fn() };
        const clickSpy = jest.spyOn(controller.fileInputTarget, 'click').mockImplementation(() => {});
        controller.chooseGallery(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.fileInputTarget.hasAttribute('capture')).toBe(false);
        expect(clickSpy).toHaveBeenCalled();
    });

    test('openPicker does nothing when fileInput target missing', () => {
        controller.hasFileInputTarget = false;
        expect(() => controller.openPicker('user')).not.toThrow();
    });

    test('openPicker with null captureMode removes capture attribute', () => {
        controller.fileInputTarget.setAttribute('capture', 'user');
        jest.spyOn(controller.fileInputTarget, 'click').mockImplementation(() => {});
        controller.openPicker(null);
        expect(controller.fileInputTarget.hasAttribute('capture')).toBe(false);
    });

    test('openPicker with captureMode sets capture and clicks', () => {
        const clickSpy = jest.spyOn(controller.fileInputTarget, 'click').mockImplementation(() => {});
        controller.openPicker('environment');
        expect(controller.fileInputTarget.getAttribute('capture')).toBe('environment');
        expect(clickSpy).toHaveBeenCalled();
    });
});
