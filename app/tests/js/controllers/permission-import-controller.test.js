// Controller registers on window.Controllers (no default export)
import '../../../assets/js/controllers/permission-import-controller.js';
const PermissionImportController = window.Controllers['permission-import'];

describe('PermissionImportController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="permission-import"
                 data-permission-import-preview-url-value="/permissions/preview-import"
                 data-permission-import-import-url-value="/permissions/import-policies">
                <input type="file" data-permission-import-target="fileInput" accept=".json">
                <button id="import-btn" data-action="click->permission-import#triggerFileSelect">Import</button>

                <div class="modal" data-permission-import-target="modal">
                    <div data-permission-import-target="modalContent">
                        <div data-permission-import-target="summary"></div>
                        <div data-permission-import-target="addList"></div>
                        <div data-permission-import-target="removeList"></div>
                        <button data-permission-import-target="confirmBtn">Confirm Import</button>
                    </div>
                </div>
                <div class="d-none" data-permission-import-target="loadingOverlay"></div>
            </div>
            <meta name="csrf-token" content="test-csrf-token">
        `;

        controller = new PermissionImportController();
        controller.element = document.querySelector('[data-controller="permission-import"]');

        // Wire up targets
        controller.fileInputTarget = document.querySelector('[data-permission-import-target="fileInput"]');
        controller.hasFileInputTarget = true;
        controller.modalTarget = document.querySelector('[data-permission-import-target="modal"]');
        controller.hasModalTarget = true;
        controller.modalContentTarget = document.querySelector('[data-permission-import-target="modalContent"]');
        controller.hasModalContentTarget = true;
        controller.summaryTarget = document.querySelector('[data-permission-import-target="summary"]');
        controller.hasSummaryTarget = true;
        controller.addListTarget = document.querySelector('[data-permission-import-target="addList"]');
        controller.hasAddListTarget = true;
        controller.removeListTarget = document.querySelector('[data-permission-import-target="removeList"]');
        controller.hasRemoveListTarget = true;
        controller.confirmBtnTarget = document.querySelector('[data-permission-import-target="confirmBtn"]');
        controller.hasConfirmBtnTarget = true;
        controller.loadingOverlayTarget = document.querySelector('[data-permission-import-target="loadingOverlay"]');
        controller.hasLoadingOverlayTarget = true;

        // Wire up values
        controller.previewUrlValue = '/permissions/preview-import';
        controller.importUrlValue = '/permissions/import-policies';
        controller.hasButtonContainerValue = false;
        controller.buttonContainerValue = '';

        // Mock bootstrap.Modal
        global.bootstrap = {
            Modal: jest.fn().mockImplementation(() => ({
                show: jest.fn(),
                hide: jest.fn(),
            })),
        };
        global.bootstrap.Modal.getInstance = jest.fn().mockReturnValue({
            show: jest.fn(),
            hide: jest.fn(),
        });
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.bootstrap;
        if (global.fetch) {
            delete global.fetch;
        }
    });

    // --- Instantiation and connect ---

    test('instantiates with correct static targets', () => {
        expect(PermissionImportController.targets).toEqual(
            expect.arrayContaining(['fileInput', 'modal', 'modalContent', 'addList', 'removeList', 'confirmBtn', 'summary', 'loadingOverlay'])
        );
    });

    test('instantiates with correct static values', () => {
        expect(PermissionImportController.values).toHaveProperty('previewUrl', String);
        expect(PermissionImportController.values).toHaveProperty('importUrl', String);
        expect(PermissionImportController.values).toHaveProperty('buttonContainer', String);
    });

    test('connect() resets importData to null', () => {
        controller.importData = 'stale-data';
        controller.connect();
        expect(controller.importData).toBeNull();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['permission-import']).toBe(PermissionImportController);
    });

    // --- File validation ---

    test('handleFileSelect rejects non-JSON files', () => {
        const event = {
            target: {
                files: [{ name: 'data.csv' }],
                value: 'data.csv',
            },
        };

        controller.handleFileSelect(event);

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Please select a JSON file.', { assertive: true });
        expect(event.target.value).toBe('');
    });

    test('handleFileSelect accepts JSON files and calls previewImport', () => {
        const previewSpy = jest.spyOn(controller, 'previewImport').mockImplementation(() => {});
        const showOverlaySpy = jest.spyOn(controller, 'showLoadingOverlay').mockImplementation(() => {});
        const file = { name: 'policies.json' };
        const event = { target: { files: [file] } };

        controller.handleFileSelect(event);

        expect(showOverlaySpy).toHaveBeenCalled();
        expect(previewSpy).toHaveBeenCalledWith(file);
    });

    test('handleFileSelect does nothing when no file selected', () => {
        const previewSpy = jest.spyOn(controller, 'previewImport').mockImplementation(() => {});
        const event = { target: { files: [] } };

        controller.handleFileSelect(event);

        expect(previewSpy).not.toHaveBeenCalled();
    });

    // --- triggerFileSelect ---

    test('triggerFileSelect clicks file input', () => {
        const clickSpy = jest.spyOn(controller.fileInputTarget, 'click').mockImplementation(() => {});
        const event = { preventDefault: jest.fn() };

        controller.triggerFileSelect(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(clickSpy).toHaveBeenCalled();
    });

    test('triggerFileSelect prefers external file input when available', () => {
        const externalInput = document.createElement('input');
        externalInput.type = 'file';
        const clickSpy = jest.spyOn(externalInput, 'click').mockImplementation(() => {});
        controller.externalFileInput = externalInput;
        const event = { preventDefault: jest.fn() };

        controller.triggerFileSelect(event);

        expect(clickSpy).toHaveBeenCalled();
    });

    // --- Loading overlay ---

    test('showLoadingOverlay removes d-none class', () => {
        controller.loadingOverlayTarget.classList.add('d-none');
        controller.showLoadingOverlay();
        expect(controller.loadingOverlayTarget.classList.contains('d-none')).toBe(false);
    });

    test('hideLoadingOverlay adds d-none class', () => {
        controller.loadingOverlayTarget.classList.remove('d-none');
        controller.hideLoadingOverlay();
        expect(controller.loadingOverlayTarget.classList.contains('d-none')).toBe(true);
    });

    // --- escapeHtml ---

    test('escapeHtml escapes HTML entities to prevent XSS', () => {
        const result = controller.escapeHtml('<script>alert("xss")</script>');
        expect(result).not.toContain('<script>');
        expect(result).toContain('&lt;script&gt;');
    });

    test('escapeHtml passes through safe text unchanged', () => {
        expect(controller.escapeHtml('Hello World')).toBe('Hello World');
    });

    // --- formatPolicyName ---

    test('formatPolicyName extracts class name from namespace', () => {
        expect(controller.formatPolicyName('App\\Policy\\MembersPolicy')).toBe('MembersPolicy');
    });

    test('formatPolicyName handles simple names', () => {
        expect(controller.formatPolicyName('SimplePolicy')).toBe('SimplePolicy');
    });

    // --- displayPreview ---

    test('displayPreview renders additions and removals', () => {
        const changes = {
            source_permission: 'TestPermission',
            summary: { total_add: 2, total_remove: 1 },
            policies_to_add: [
                { policy_class: 'App\\Policy\\MembersPolicy', policy_method: 'index' },
                { policy_class: 'App\\Policy\\MembersPolicy', policy_method: 'view' },
            ],
            policies_to_remove: [
                { policy_class: 'App\\Policy\\BranchesPolicy', policy_method: 'delete' },
            ],
        };

        controller.displayPreview(changes);

        expect(controller.summaryTarget.innerHTML).toContain('2');
        expect(controller.summaryTarget.innerHTML).toContain('1');
        expect(controller.addListTarget.innerHTML).toContain('MembersPolicy');
        expect(controller.addListTarget.innerHTML).toContain('index');
        expect(controller.removeListTarget.innerHTML).toContain('BranchesPolicy');
        expect(controller.confirmBtnTarget.disabled).toBe(false);
    });

    test('displayPreview disables confirm when no changes', () => {
        const changes = {
            summary: { total_add: 0, total_remove: 0 },
            policies_to_add: [],
            policies_to_remove: [],
        };

        controller.displayPreview(changes);

        expect(controller.confirmBtnTarget.disabled).toBe(true);
        expect(controller.summaryTarget.innerHTML).toContain('No changes needed');
    });

    // --- cancelImport ---

    test('cancelImport resets state and modal content', () => {
        controller.importData = 'some-data';
        const hideModalSpy = jest.spyOn(controller, 'hideModal').mockImplementation(() => {});
        const resetFileSpy = jest.spyOn(controller, 'resetFileInput').mockImplementation(() => {});
        const event = { preventDefault: jest.fn() };

        controller.cancelImport(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(hideModalSpy).toHaveBeenCalled();
        expect(controller.importData).toBeNull();
        expect(resetFileSpy).toHaveBeenCalled();
        expect(controller.summaryTarget.innerHTML).toBe('');
        expect(controller.addListTarget.innerHTML).toBe('');
        expect(controller.removeListTarget.innerHTML).toBe('');
    });

    // --- previewImport ---

    test('previewImport sends file and shows modal on success', async () => {
        const mockChanges = {
            summary: { total_add: 1, total_remove: 0 },
            policies_to_add: [{ policy_class: 'App\\Policy\\Test', policy_method: 'view' }],
            policies_to_remove: [],
        };
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ import_data: 'base64data', changes: mockChanges }),
        });

        const showModalSpy = jest.spyOn(controller, 'showModal').mockImplementation(() => {});
        const file = new File(['{}'], 'test.json', { type: 'application/json' });

        await controller.previewImport(file);

        expect(global.fetch).toHaveBeenCalledWith('/permissions/preview-import', expect.objectContaining({
            method: 'POST',
        }));
        expect(controller.importData).toBe('base64data');
        expect(showModalSpy).toHaveBeenCalled();
    });

    test('previewImport announces on error response', async () => {
        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            json: () => Promise.resolve({ error: 'Invalid file' }),
        });
        const file = new File(['{}'], 'test.json', { type: 'application/json' });

        await controller.previewImport(file);

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Invalid file', { assertive: true });
    });

    test('previewImport handles fetch exception', async () => {
        global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));
        const file = new File(['{}'], 'test.json', { type: 'application/json' });

        await controller.previewImport(file);

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith(
            'An error occurred while analyzing the import file.',
            { assertive: true }
        );
    });

    // --- confirmImport ---

    test('confirmImport sends import data and reloads on success', async () => {
        controller.importData = 'base64data';
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ results: { added: 3, removed: 1, errors: [] } }),
        });
        const hideModalSpy = jest.spyOn(controller, 'hideModal').mockImplementation(() => {});
        // Mock window.location.reload
        delete window.location;
        window.location = { reload: jest.fn() };

        const event = { preventDefault: jest.fn() };
        await controller.confirmImport(event);

        expect(global.fetch).toHaveBeenCalledWith('/permissions/import-policies', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ import_data: 'base64data' }),
        }));
        expect(window.KMP_accessibility.alert).toHaveBeenCalledWith(
            expect.stringContaining('Added: 3'),
            expect.any(Object)
        );
        expect(hideModalSpy).toHaveBeenCalled();
        expect(controller.importData).toBeNull();
    });

    test('confirmImport announces when no import data', async () => {
        controller.importData = null;
        const event = { preventDefault: jest.fn() };

        await controller.confirmImport(event);

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('No import data available.', { assertive: true });
    });

    // --- disconnect ---

    test('disconnect cleans up external event listeners', () => {
        const externalInput = document.createElement('input');
        const removeListenerSpy = jest.spyOn(externalInput, 'removeEventListener');
        controller.externalFileInput = externalInput;
        controller.boundHandleFileSelect = jest.fn();
        controller.externalImportButton = document.createElement('button');
        controller.boundTriggerFileSelect = jest.fn();

        controller.disconnect();

        expect(removeListenerSpy).toHaveBeenCalledWith('change', expect.any(Function));
        expect(controller.importData).toBeNull();
        expect(controller.externalFileInput).toBeNull();
        expect(controller.externalImportButton).toBeNull();
    });

    // --- connect with external button container ---

    test('connect wires up external button container', () => {
        document.body.innerHTML += `
            <div id="ext-buttons">
                <input type="file">
                <button data-action="triggerFileSelect">Import</button>
            </div>
        `;
        controller.hasButtonContainerValue = true;
        controller.buttonContainerValue = '#ext-buttons';

        controller.connect();

        expect(controller.externalFileInput).toBeTruthy();
        expect(controller.boundHandleFileSelect).toBeTruthy();
    });
});
