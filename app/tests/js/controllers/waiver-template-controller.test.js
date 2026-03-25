// Controller registers on window.Controllers (no default export)
import '../../../plugins/Waivers/assets/js/controllers/waiver-template-controller.js';
const WaiverTemplateController = window.Controllers['waiver-template'];

describe('WaiverTemplateController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="waiver-template"
                 data-waiver-template-source-value="none">
                <select data-waiver-template-target="sourceSelect">
                    <option value="none">No Template</option>
                    <option value="upload">Upload PDF</option>
                    <option value="url">External URL</option>
                </select>
                <div data-waiver-template-target="uploadSection">
                    <input type="file" data-waiver-template-target="fileInput">
                </div>
                <div data-waiver-template-target="urlSection">
                    <input type="text" data-waiver-template-target="urlInput" value="">
                </div>
            </div>
        `;

        controller = new WaiverTemplateController();
        controller.element = document.querySelector('[data-controller="waiver-template"]');

        // Wire up targets
        controller.uploadSectionTarget = document.querySelector('[data-waiver-template-target="uploadSection"]');
        controller.urlSectionTarget = document.querySelector('[data-waiver-template-target="urlSection"]');
        controller.fileInputTarget = document.querySelector('[data-waiver-template-target="fileInput"]');
        controller.urlInputTarget = document.querySelector('[data-waiver-template-target="urlInput"]');
        controller.sourceSelectTarget = document.querySelector('[data-waiver-template-target="sourceSelect"]');

        // Wire up has* checks
        controller.hasUploadSectionTarget = true;
        controller.hasUrlSectionTarget = true;
        controller.hasFileInputTarget = true;
        controller.hasUrlInputTarget = true;
        controller.hasSourceSelectTarget = true;

        // Wire up values
        controller.sourceValue = 'none';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(WaiverTemplateController.targets).toEqual(
            expect.arrayContaining(['uploadSection', 'urlSection', 'fileInput', 'urlInput', 'sourceSelect'])
        );
    });

    test('has correct static values', () => {
        expect(WaiverTemplateController.values).toHaveProperty('source');
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['waiver-template']).toBe(WaiverTemplateController);
    });

    // --- connect ---

    test('connect calls updateDisplay', () => {
        const spy = jest.spyOn(controller, 'updateDisplay');
        controller.connect();
        expect(spy).toHaveBeenCalled();
    });

    // --- updateDisplay ---

    test('updateDisplay hides both sections when source is none', () => {
        controller.sourceValue = 'none';
        controller.updateDisplay();

        expect(controller.uploadSectionTarget.style.display).toBe('none');
        expect(controller.urlSectionTarget.style.display).toBe('none');
    });

    test('updateDisplay shows upload section and hides url when source is upload', () => {
        controller.sourceValue = 'upload';
        controller.updateDisplay();

        expect(controller.uploadSectionTarget.style.display).toBe('block');
        expect(controller.urlSectionTarget.style.display).toBe('none');
    });

    test('updateDisplay shows url section and hides upload when source is url', () => {
        controller.sourceValue = 'url';
        controller.updateDisplay();

        expect(controller.uploadSectionTarget.style.display).toBe('none');
        expect(controller.urlSectionTarget.style.display).toBe('block');
    });

    test('updateDisplay clears file input when not in upload mode', () => {
        controller.sourceValue = 'url';
        controller.updateDisplay();
        expect(controller.fileInputTarget.value).toBe('');
    });

    test('updateDisplay clears url input when not in url mode', () => {
        controller.urlInputTarget.value = 'https://example.com';
        controller.sourceValue = 'upload';
        controller.updateDisplay();
        expect(controller.urlInputTarget.value).toBe('');
    });

    // --- toggleSource ---

    test('toggleSource updates source value and display', () => {
        const spy = jest.spyOn(controller, 'updateDisplay');
        const event = { target: { value: 'upload' } };
        controller.toggleSource(event);

        expect(controller.sourceValue).toBe('upload');
        expect(spy).toHaveBeenCalled();
    });

    // --- fileSelected ---

    test('fileSelected auto-selects upload option when file is picked', () => {
        controller.sourceValue = 'none';
        const event = { target: { files: [{ name: 'test.pdf' }] } };
        controller.fileSelected(event);

        expect(controller.sourceValue).toBe('upload');
        expect(controller.sourceSelectTarget.value).toBe('upload');
    });

    test('fileSelected does nothing when no files selected', () => {
        controller.sourceValue = 'none';
        const event = { target: { files: [] } };
        controller.fileSelected(event);

        expect(controller.sourceValue).toBe('none');
    });

    // --- sourceValueChanged ---

    test('sourceValueChanged calls updateDisplay', () => {
        const spy = jest.spyOn(controller, 'updateDisplay');
        controller.sourceValueChanged();
        expect(spy).toHaveBeenCalled();
    });
});
