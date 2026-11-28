// Mock EasyMDE before importing the controller - dynamic import needs __esModule + default
jest.mock('easymde', () => ({
    __esModule: true,
    default: jest.fn().mockImplementation((options) => ({
        element: options.element,
        value: jest.fn().mockImplementation(function(val) {
            if (val === undefined) {
                return this._value || '';
            }
            this._value = val;
        }),
        toTextArea: jest.fn(),
        _value: '',
        codemirror: {
            inputField: globalThis.document.createElement("textarea"),
            getInputField() { return this.inputField; },
            getCursor: jest.fn(() => ({ line: 0, ch: 0 })),
            replaceSelection: jest.fn(),
            focus: jest.fn(),
            getDoc: jest.fn(() => ({
                getCursor: jest.fn(() => ({ line: 0, ch: 0 })),
                replaceRange: jest.fn()
            }))
        },
        markdown: jest.fn(text => `<p>${text}</p>`)
    })),
}));

import '../../../assets/js/controllers/markdown-editor-controller.js';

const MarkdownEditorController = window.Controllers['markdown-editor'];

describe('MarkdownEditorController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <label for="body">Body</label>
            <p id="body-help">Markdown help</p>
            <textarea id="body" aria-describedby="body-help" aria-invalid="true" data-controller="markdown-editor"
                      data-markdown-editor-placeholder-value="Enter text..."
                      data-markdown-editor-min-height-value="200px">
            </textarea>
        `;

        controller = new MarkdownEditorController();
        controller.element = document.querySelector('[data-controller="markdown-editor"]');
        controller.placeholderValue = 'Enter text...';
        controller.minHeightValue = '200px';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', async () => {
        expect(window.Controllers['markdown-editor']).toBe(MarkdownEditorController);
    });

    test('has correct static values', async () => {
        expect(MarkdownEditorController.values).toHaveProperty('placeholder');
        expect(MarkdownEditorController.values).toHaveProperty('minHeight');
    });

    test('initialize sets editor to null', async () => {
        controller.initialize();
        expect(controller.editor).toBeNull();
    });

    test('connect creates EasyMDE instance', async () => {
        await controller.connect();
        expect(controller.editor).not.toBeNull();
    });

    test('connect passes correct options to EasyMDE', async () => {
        const EasyMDE = require('easymde').default;
        await controller.connect();
        expect(EasyMDE).toHaveBeenCalledWith(
            expect.objectContaining({
                element: controller.element,
                placeholder: 'Enter text...',
                minHeight: '200px',
                spellChecker: false,
                forceSync: true
            })
        );
    });

    test('preserves the label and help on the active editor input', async () => {
        await controller.connect();
        const input = controller.editor.codemirror.getInputField();
        expect(input.getAttribute('aria-labelledby')).toBe('body-label');
        expect(input.getAttribute('aria-describedby')).toBe('body-help');
        expect(input.getAttribute('aria-invalid')).toBe('true');
        controller.element.labels[0].click();
        expect(controller.editor.codemirror.focus).toHaveBeenCalled();
    });

    test('uses Bootstrap sanitization for Markdown previews', async () => {
        const EasyMDE = require('easymde').default;
        await controller.connect();
        const { sanitizerFunction } = EasyMDE.mock.lastCall[0].renderingConfig;
        const html = sanitizerFunction('<strong>Welcome</strong><img src="/logo.png" onerror="bad()">');
        expect(html).toContain('<strong>Welcome</strong>');
        expect(html).not.toContain('onerror');
    });

    test('disconnect cleans up editor', async () => {
        await controller.connect();
        const toTextArea = controller.editor.toTextArea;
        controller.disconnect();
        expect(toTextArea).toHaveBeenCalled();
        expect(controller.editor).toBeNull();
    });

    test('disconnect handles null editor', async () => {
        controller.editor = null;
        expect(() => controller.disconnect()).not.toThrow();
    });

    test('getValue returns editor value', async () => {
        await controller.connect();
        controller.editor.value.mockReturnValue('# Hello');
        expect(controller.getValue()).toBe('# Hello');
    });

    test('getValue returns empty string when no editor', async () => {
        controller.editor = null;
        expect(controller.getValue()).toBe('');
    });

    test('setValue sets editor value', async () => {
        await controller.connect();
        controller.setValue('# New content');
        expect(controller.editor.value).toHaveBeenCalledWith('# New content');
    });

    test('setValue does nothing when no editor', async () => {
        controller.editor = null;
        expect(() => controller.setValue('test')).not.toThrow();
    });
});
