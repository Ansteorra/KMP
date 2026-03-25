// Mock EasyMDE before importing the controller
jest.mock('easymde', () => {
    return jest.fn().mockImplementation((options) => ({
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
            getCursor: jest.fn(() => ({ line: 0, ch: 0 })),
            replaceSelection: jest.fn(),
            focus: jest.fn(),
            getDoc: jest.fn(() => ({
                getCursor: jest.fn(() => ({ line: 0, ch: 0 })),
                replaceRange: jest.fn()
            }))
        },
        markdown: jest.fn(text => `<p>${text}</p>`)
    }));
});

import '../../../assets/js/controllers/markdown-editor-controller.js';

const MarkdownEditorController = window.Controllers['markdown-editor'];

describe('MarkdownEditorController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <textarea data-controller="markdown-editor"
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

    test('registers on window.Controllers', () => {
        expect(window.Controllers['markdown-editor']).toBe(MarkdownEditorController);
    });

    test('has correct static values', () => {
        expect(MarkdownEditorController.values).toHaveProperty('placeholder');
        expect(MarkdownEditorController.values).toHaveProperty('minHeight');
    });

    test('initialize sets editor to null', () => {
        controller.initialize();
        expect(controller.editor).toBeNull();
    });

    test('connect creates EasyMDE instance', () => {
        controller.connect();
        expect(controller.editor).not.toBeNull();
    });

    test('connect passes correct options to EasyMDE', () => {
        const EasyMDE = require('easymde');
        controller.connect();
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

    test('disconnect cleans up editor', () => {
        controller.connect();
        const toTextArea = controller.editor.toTextArea;
        controller.disconnect();
        expect(toTextArea).toHaveBeenCalled();
        expect(controller.editor).toBeNull();
    });

    test('disconnect handles null editor', () => {
        controller.editor = null;
        expect(() => controller.disconnect()).not.toThrow();
    });

    test('getValue returns editor value', () => {
        controller.connect();
        controller.editor.value.mockReturnValue('# Hello');
        expect(controller.getValue()).toBe('# Hello');
    });

    test('getValue returns empty string when no editor', () => {
        controller.editor = null;
        expect(controller.getValue()).toBe('');
    });

    test('setValue sets editor value', () => {
        controller.connect();
        controller.setValue('# New content');
        expect(controller.editor.value).toHaveBeenCalledWith('# New content');
    });

    test('setValue does nothing when no editor', () => {
        controller.editor = null;
        expect(() => controller.setValue('test')).not.toThrow();
    });
});
