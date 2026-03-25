// Controller registers on window.Controllers (no default export)
import '../../../assets/js/controllers/permission-manage-policies-controller.js';
const PermissionManagePoliciesController = window.Controllers['permission-manage-policies'];

describe('PermissionManagePoliciesController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="permissions-matrix">
                <div data-controller="permission-manage-policies"
                     data-permission-manage-policies-url-value="/permissions/manage-policies">

                    <!-- Class-level checkbox for MembersController -->
                    <input type="checkbox"
                           data-permission-manage-policies-target="policyClass"
                           data-class-name="App-Policy-MembersPolicy"
                           data-permission-id="1">

                    <!-- Method-level checkboxes -->
                    <input type="checkbox"
                           data-permission-manage-policies-target="policyMethod"
                           data-class-name="App-Policy-MembersPolicy"
                           data-permission-id="1"
                           data-method-name="index"
                           checked>
                    <input type="checkbox"
                           data-permission-manage-policies-target="policyMethod"
                           data-class-name="App-Policy-MembersPolicy"
                           data-permission-id="1"
                           data-method-name="view">
                    <input type="checkbox"
                           data-permission-manage-policies-target="policyMethod"
                           data-class-name="App-Policy-MembersPolicy"
                           data-permission-id="1"
                           data-method-name="edit">
                </div>
            </div>
            <meta name="csrf-token" content="test-csrf-token">
        `;

        controller = new PermissionManagePoliciesController();
        controller.element = document.querySelector('[data-controller="permission-manage-policies"]');

        // Wire up targets
        controller.policyClassTargets = Array.from(
            document.querySelectorAll('[data-permission-manage-policies-target="policyClass"]')
        );
        controller.policyMethodTargets = Array.from(
            document.querySelectorAll('[data-permission-manage-policies-target="policyMethod"]')
        );

        // Wire up values
        controller.urlValue = '/permissions/manage-policies';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) {
            delete global.fetch;
        }
    });

    // --- Instantiation ---

    test('instantiates with correct static targets', () => {
        expect(PermissionManagePoliciesController.targets).toEqual(
            expect.arrayContaining(['policyClass', 'policyMethod'])
        );
    });

    test('instantiates with correct static values', () => {
        expect(PermissionManagePoliciesController.values).toHaveProperty('url', String);
    });

    test('initializes with empty change queue', () => {
        expect(controller.changeQueue).toEqual([]);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['permission-manage-policies']).toBe(PermissionManagePoliciesController);
    });

    // --- checkClass: indeterminate state ---

    test('checkClass sets indeterminate state when some methods are checked', () => {
        // 1 of 3 checked (index is checked in DOM)
        controller.checkClass('App-Policy-MembersPolicy', '1');

        const classCheckbox = document.querySelector(
            "input[data-class-name='App-Policy-MembersPolicy'][data-permission-id='1']:not([data-method-name])"
        );
        expect(classCheckbox.checked).toBe(true);
        expect(classCheckbox.classList.contains('indeterminate-switch')).toBe(true);
    });

    test('checkClass sets fully checked when all methods are checked', () => {
        // Check all method checkboxes
        document.querySelectorAll('[data-method-name]').forEach(cb => { cb.checked = true; });

        controller.checkClass('App-Policy-MembersPolicy', '1');

        const classCheckbox = document.querySelector(
            "input[data-class-name='App-Policy-MembersPolicy'][data-permission-id='1']:not([data-method-name])"
        );
        expect(classCheckbox.checked).toBe(true);
        expect(classCheckbox.classList.contains('indeterminate-switch')).toBe(false);
    });

    test('checkClass sets unchecked when no methods are checked', () => {
        // Uncheck all method checkboxes
        document.querySelectorAll('[data-method-name]').forEach(cb => { cb.checked = false; });

        controller.checkClass('App-Policy-MembersPolicy', '1');

        const classCheckbox = document.querySelector(
            "input[data-class-name='App-Policy-MembersPolicy'][data-permission-id='1']:not([data-method-name])"
        );
        expect(classCheckbox.checked).toBe(false);
        expect(classCheckbox.classList.contains('indeterminate-switch')).toBe(false);
    });

    // --- classClicked ---

    test('classClicked checks all methods when class is checked', () => {
        const changeMethodSpy = jest.spyOn(controller, 'changeMethod').mockImplementation(() => {});
        const classCheckbox = document.querySelector(
            "input[data-class-name='App-Policy-MembersPolicy'][data-permission-id='1']:not([data-method-name])"
        );
        classCheckbox.checked = true;
        const event = { target: classCheckbox };

        controller.classClicked(event);

        const methods = document.querySelectorAll('[data-method-name]');
        methods.forEach(m => {
            expect(m.checked).toBe(true);
        });
        expect(changeMethodSpy).toHaveBeenCalledTimes(3);
        expect(classCheckbox.classList.contains('indeterminate-switch')).toBe(false);
    });

    test('classClicked unchecks all methods when class is unchecked', () => {
        const changeMethodSpy = jest.spyOn(controller, 'changeMethod').mockImplementation(() => {});
        // First check all
        document.querySelectorAll('[data-method-name]').forEach(cb => { cb.checked = true; });

        const classCheckbox = document.querySelector(
            "input[data-class-name='App-Policy-MembersPolicy'][data-permission-id='1']:not([data-method-name])"
        );
        classCheckbox.checked = false;
        const event = { target: classCheckbox };

        controller.classClicked(event);

        const methods = document.querySelectorAll('[data-method-name]');
        methods.forEach(m => {
            expect(m.checked).toBe(false);
        });
    });

    // --- methodClicked ---

    test('methodClicked queues change and updates class state', () => {
        const changeMethodSpy = jest.spyOn(controller, 'changeMethod').mockImplementation(() => {});
        const checkClassSpy = jest.spyOn(controller, 'checkClass').mockImplementation(() => {});

        const methodCheckbox = document.querySelector('[data-method-name="view"]');
        methodCheckbox.checked = true;
        const event = { target: methodCheckbox };

        controller.methodClicked(event);

        expect(checkClassSpy).toHaveBeenCalledWith('App-Policy-MembersPolicy', '1');
        expect(changeMethodSpy).toHaveBeenCalledWith(methodCheckbox, true);
    });

    // --- changeMethod and queue ---

    test('changeMethod adds to queue with correct action', () => {
        jest.spyOn(controller, 'processQueue').mockImplementation(() => {});
        const method = document.querySelector('[data-method-name="index"]');

        controller.changeMethod(method, true);

        expect(controller.changeQueue).toHaveLength(1);
        expect(controller.changeQueue[0]).toEqual(expect.objectContaining({
            permissionId: '1',
            method: 'index',
            action: 'add',
        }));
    });

    test('changeMethod queues delete action when unchecked', () => {
        jest.spyOn(controller, 'processQueue').mockImplementation(() => {});
        const method = document.querySelector('[data-method-name="index"]');

        controller.changeMethod(method, false);

        expect(controller.changeQueue[0].action).toBe('delete');
    });

    test('changeMethod starts queue processing when queue was empty', () => {
        const processQueueSpy = jest.spyOn(controller, 'processQueue').mockImplementation(() => {});
        const method = document.querySelector('[data-method-name="index"]');

        controller.changeMethod(method, true);

        expect(processQueueSpy).toHaveBeenCalled();
    });

    test('changeMethod does not restart queue when items already queued', () => {
        const processQueueSpy = jest.spyOn(controller, 'processQueue').mockImplementation(() => {});
        controller.changeQueue.push({ existing: true });
        const method = document.querySelector('[data-method-name="index"]');

        controller.changeMethod(method, true);

        expect(processQueueSpy).not.toHaveBeenCalled();
    });

    // --- processQueue ---

    test('processQueue sends POST request with CSRF token', async () => {
        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve({ success: true }),
        });
        controller.changeQueue = [{
            permissionId: '1',
            method: 'index',
            className: 'App\\Policy\\MembersPolicy',
            action: 'add',
        }];

        controller.processQueue();

        expect(global.fetch).toHaveBeenCalledWith('/permissions/manage-policies', expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({
                'X-CSRF-Token': 'test-csrf-token',
                'Content-Type': 'application/json',
            }),
        }));
    });

    test('processQueue does nothing when queue is empty', () => {
        global.fetch = jest.fn();
        controller.changeQueue = [];

        controller.processQueue();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    // --- Loading overlay ---

    test('showLoadingOverlay creates overlay element', () => {
        controller.showLoadingOverlay();

        const overlay = controller.element.closest('.permissions-matrix').querySelector('.loading-overlay');
        expect(overlay).toBeTruthy();
        expect(overlay.querySelector('.spinner-border')).toBeTruthy();
    });

    test('hideLoadingOverlay removes overlay element', () => {
        controller.showLoadingOverlay();
        controller.hideLoadingOverlay();

        const overlay = controller.element.closest('.permissions-matrix').querySelector('.loading-overlay');
        expect(overlay).toBeNull();
    });

    test('showLoadingOverlay does not create duplicate overlays', () => {
        controller.showLoadingOverlay();
        controller.showLoadingOverlay();

        const overlays = controller.element.closest('.permissions-matrix').querySelectorAll('.loading-overlay');
        expect(overlays.length).toBe(1);
    });

    // --- Target connected handlers ---

    test('policyClassTargetConnected adds click listener', () => {
        const element = document.createElement('input');
        element.type = 'checkbox';
        const addListenerSpy = jest.spyOn(element, 'addEventListener');

        controller.policyClassTargetConnected(element);

        expect(addListenerSpy).toHaveBeenCalledWith('click', expect.any(Function));
        expect(element.clickEvent).toBeDefined();
    });

    test('policyMethodTargetConnected adds click listener', () => {
        const element = document.createElement('input');
        element.type = 'checkbox';
        const addListenerSpy = jest.spyOn(element, 'addEventListener');

        controller.policyMethodTargetConnected(element);

        expect(addListenerSpy).toHaveBeenCalledWith('click', expect.any(Function));
        expect(element.clickEvent).toBeDefined();
    });

    // --- disconnect ---

    test('disconnect removes event listeners from targets', () => {
        // Set up click events on targets
        controller.policyClassTargets.forEach(el => {
            el.clickEvent = jest.fn();
        });
        controller.policyMethodTargets.forEach(el => {
            el.clickEvent = jest.fn();
        });

        const classRemoveSpy = jest.spyOn(controller.policyClassTargets[0], 'removeEventListener');
        const methodRemoveSpies = controller.policyMethodTargets.map(
            el => jest.spyOn(el, 'removeEventListener')
        );

        controller.disconnect();

        expect(classRemoveSpy).toHaveBeenCalledWith('click', expect.any(Function));
        methodRemoveSpies.forEach(spy => {
            expect(spy).toHaveBeenCalledWith('click', expect.any(Function));
        });
    });
});
