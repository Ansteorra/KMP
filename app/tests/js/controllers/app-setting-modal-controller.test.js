import '../../../assets/js/controllers/app-setting-modal-controller.js';

const AppSettingModalController = window.Controllers['app-setting-modal'];

describe('AppSettingModalController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="app-setting-modal"
                 data-app-setting-modal-edit-url-value="/app-settings/edit"
                 data-app-setting-modal-modal-id-value="editAppSettingModal"
                 data-app-setting-modal-frame-id-value="editAppSettingFrame">
            </div>
            <div id="editAppSettingModal" class="modal">
                <turbo-frame id="editAppSettingFrame"></turbo-frame>
            </div>
        `;

        controller = new AppSettingModalController();
        controller.element = document.querySelector('[data-controller="app-setting-modal"]');
        controller.editUrlValue = '/app-settings/edit';
        controller.modalIdValue = 'editAppSettingModal';
        controller.frameIdValue = 'editAppSettingFrame';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['app-setting-modal']).toBe(AppSettingModalController);
    });

    test('has correct static values', () => {
        expect(AppSettingModalController.values).toHaveProperty('editUrl', String);
        expect(AppSettingModalController.values.modalId).toEqual({ type: String, default: 'editAppSettingModal' });
        expect(AppSettingModalController.values.frameId).toEqual({ type: String, default: 'editAppSettingFrame' });
    });

    test('initialize sets up instance properties', () => {
        controller.initialize();
        expect(controller.modalInstance).toBeNull();
        expect(controller.boundHandleOutletClick).toBeInstanceOf(Function);
    });

    test('modalElement getter returns correct element', () => {
        expect(controller.modalElement).toBe(document.getElementById('editAppSettingModal'));
    });

    test('frameElement getter returns correct element', () => {
        expect(controller.frameElement).toBe(document.getElementById('editAppSettingFrame'));
    });

    test('connect adds event listener for outlet-btn clicks', () => {
        const addSpy = jest.spyOn(document, 'addEventListener');
        controller.initialize();
        controller.connect();
        expect(addSpy).toHaveBeenCalledWith(
            'outlet-btn:outlet-button-clicked',
            controller.boundHandleOutletClick
        );
    });

    test('disconnect removes event listener', () => {
        const removeSpy = jest.spyOn(document, 'removeEventListener');
        controller.initialize();
        controller.connect();
        controller.disconnect();
        expect(removeSpy).toHaveBeenCalledWith(
            'outlet-btn:outlet-button-clicked',
            controller.boundHandleOutletClick
        );
    });

    test('handleOutletClick ignores events without target', () => {
        controller.initialize();
        const event = new CustomEvent('outlet-btn:outlet-button-clicked', {
            detail: { id: '5' }
        });
        Object.defineProperty(event, 'target', { value: null });
        // Should not throw
        controller.handleOutletClick(event);
    });

    test('handleOutletClick ignores events for different modals', () => {
        controller.initialize();
        const button = document.createElement('button');
        button.setAttribute('data-bs-target', '#someOtherModal');

        const event = new CustomEvent('outlet-btn:outlet-button-clicked', {
            detail: { id: '5' }
        });
        Object.defineProperty(event, 'target', { value: button });

        const loadSpy = jest.spyOn(controller, 'loadEditForm').mockImplementation(() => {});
        controller.handleOutletClick(event);
        expect(loadSpy).not.toHaveBeenCalled();
    });

    test('handleOutletClick calls loadEditForm for matching modal', () => {
        controller.initialize();
        const button = document.createElement('button');
        button.setAttribute('data-bs-target', '#editAppSettingModal');

        const event = new CustomEvent('outlet-btn:outlet-button-clicked', {
            detail: { id: '5' }
        });
        Object.defineProperty(event, 'target', { value: button });

        const loadSpy = jest.spyOn(controller, 'loadEditForm').mockImplementation(() => {});
        controller.handleOutletClick(event);
        expect(loadSpy).toHaveBeenCalledWith('5');
    });

    test('loadEditForm sets frame src and shows loading state', () => {
        controller.loadEditForm('42');

        const frame = document.getElementById('editAppSettingFrame');
        expect(frame.src).toBe('/app-settings/edit/42');
        expect(frame.innerHTML).toContain('Loading setting...');
        expect(frame.innerHTML).toContain('spinner-border');
    });

    test('loadEditForm handles missing frame element', () => {
        controller.frameIdValue = 'nonexistent';
        expect(() => controller.loadEditForm('1')).not.toThrow();
    });
});
