// Mock EasyMDE before importing the controller - dynamic import needs __esModule + default
jest.mock('easymde', () => ({
    __esModule: true,
    default: jest.fn().mockImplementation((options) => {
        const mockDoc = {
            getCursor: jest.fn(() => ({ line: 0, ch: 0 })),
            replaceRange: jest.fn()
        };
        const instance = {
            element: options.element,
            value: jest.fn().mockImplementation(function(val) {
                if (val === undefined) {
                    return instance._value || '';
                }
                instance._value = val;
            }),
            toTextArea: jest.fn(),
            _value: '',
            codemirror: {
                getCursor: jest.fn(() => ({ line: 0, ch: 0 })),
                replaceSelection: jest.fn(),
                focus: jest.fn(),
                getDoc: jest.fn(() => mockDoc)
            },
            markdown: jest.fn(text => `<p>${text}</p>`)
        };
        return instance;
    }),
}));

import '../../../assets/js/controllers/email-template-editor-controller.js';

const EmailTemplateEditorController = window.Controllers['email-template-editor'];

describe('EmailTemplateEditorController', () => {
    let controller;

    const testVariables = [
        { name: 'userName', description: 'User full name' },
        { name: 'email', description: 'User email address' },
        { name: 'siteName', description: 'Website name' }
    ];

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="email-template-editor"
                 data-email-template-editor-variables-value='${JSON.stringify(testVariables)}'>
                <textarea data-email-template-editor-target="editor"></textarea>
                <div data-email-template-editor-target="variableButtons"></div>
            </div>
        `;

        controller = new EmailTemplateEditorController();
        controller.element = document.querySelector('[data-controller="email-template-editor"]');
        controller.editorTarget = document.querySelector('[data-email-template-editor-target="editor"]');
        controller.variableButtonsTarget = document.querySelector('[data-email-template-editor-target="variableButtons"]');
        controller.hasEditorTarget = true;
        controller.hasVariableButtonsTarget = true;
        controller.variablesValue = testVariables;
        controller.placeholderValue = 'Enter email template...';
        controller.minHeightValue = '400px';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', async () => {
        expect(window.Controllers['email-template-editor']).toBe(EmailTemplateEditorController);
    });

    test('has correct static targets', async () => {
        expect(EmailTemplateEditorController.targets).toEqual(
            expect.arrayContaining(['editor', 'variableButtons'])
        );
    });

    test('has correct static values', async () => {
        expect(EmailTemplateEditorController.values).toHaveProperty('variables');
        expect(EmailTemplateEditorController.values).toHaveProperty('placeholder');
        expect(EmailTemplateEditorController.values).toHaveProperty('minHeight');
    });

    test('initialize sets editor to null', async () => {
        controller.initialize();
        expect(controller.editor).toBeNull();
    });

    test('connect creates EasyMDE instance', async () => {
        await controller.connect();
        expect(controller.editor).not.toBeNull();
    });

    test('connect renders variable buttons when variables exist', async () => {
        await controller.connect();
        const buttons = controller.variableButtonsTarget.querySelectorAll('button');
        expect(buttons.length).toBe(3);
    });

    test('connect does not render variable buttons when no variables', async () => {
        controller.variablesValue = [];
        await controller.connect();
        const buttons = controller.variableButtonsTarget.querySelectorAll('button');
        expect(buttons.length).toBe(0);
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

    // buildToolbar tests
    test('buildToolbar includes variable button when variables exist', async () => {
        const toolbar = controller.buildToolbar();
        const customButton = toolbar.find(item => typeof item === 'object' && item.name === 'insert-variable');
        expect(customButton).toBeDefined();
        expect(customButton.className).toBe('fa fa-code');
        expect(customButton.title).toBe('Insert Variable');
    });

    test('buildToolbar omits variable button when no variables', async () => {
        controller.variablesValue = [];
        const toolbar = controller.buildToolbar();
        const customButton = toolbar.find(item => typeof item === 'object' && item.name === 'insert-variable');
        expect(customButton).toBeUndefined();
    });

    test('buildToolbar always includes guide', async () => {
        const toolbar = controller.buildToolbar();
        expect(toolbar[toolbar.length - 1]).toBe('guide');
    });

    // renderVariableButtons tests
    test('renderVariableButtons creates buttons with correct labels', async () => {
        controller.renderVariableButtons();
        const buttons = controller.variableButtonsTarget.querySelectorAll('button');
        expect(buttons[0].textContent).toBe('{{userName}}');
        expect(buttons[1].textContent).toBe('{{email}}');
        expect(buttons[2].textContent).toBe('{{siteName}}');
    });

    test('renderVariableButtons sets title from description', async () => {
        controller.renderVariableButtons();
        const buttons = controller.variableButtonsTarget.querySelectorAll('button');
        expect(buttons[0].title).toBe('User full name');
    });

    test('renderVariableButtons includes syntax help alert', async () => {
        controller.renderVariableButtons();
        const helpAlert = controller.variableButtonsTarget.querySelector('.alert-info');
        expect(helpAlert).not.toBeNull();
        expect(helpAlert.innerHTML).toContain('{{variableName}}');
    });

    // insertVariable tests
    test('insertVariable calls codemirror replaceRange', async () => {
        await controller.connect();
        const mockDoc = controller.editor.codemirror.getDoc();
        controller.insertVariable('userName');
        expect(mockDoc.replaceRange).toHaveBeenCalledWith(
            '{{userName}}',
            expect.any(Object)
        );
    });

    test('insertVariable does nothing when no editor', async () => {
        controller.editor = null;
        expect(() => controller.insertVariable('test')).not.toThrow();
    });

    // renderPreview tests
    test('renderPreview highlights {{}} variables', async () => {
        await controller.connect();
        const html = controller.renderPreview('Hello {{userName}}!');
        expect(html).toContain('badge bg-primary');
        expect(html).toContain('{{userName}}');
    });

    test('renderPreview highlights ${} variables', async () => {
        await controller.connect();
        const html = controller.renderPreview('Hello ${email}!');
        expect(html).toContain('badge bg-success');
        expect(html).toContain('${email}');
    });

    // getValue / setValue
    test('getValue returns editor value', async () => {
        await controller.connect();
        controller.editor.value.mockReturnValue('Template content');
        expect(controller.getValue()).toBe('Template content');
    });

    test('getValue returns empty string when no editor', async () => {
        controller.editor = null;
        expect(controller.getValue()).toBe('');
    });

    test('setValue sets editor value', async () => {
        await controller.connect();
        controller.setValue('New template');
        expect(controller.editor.value).toHaveBeenCalledWith('New template');
    });

    // showVariableMenu tests
    test('showVariableMenu inserts variable on valid selection', async () => {
        await controller.connect();
        window.KMP_accessibility.prompt.mockResolvedValue('userName');
        await controller.showVariableMenu(controller.editor);
        expect(controller.editor.codemirror.replaceSelection).toHaveBeenCalledWith('{{userName}}');
    });

    test('showVariableMenu announces invalid variable', async () => {
        await controller.connect();
        window.KMP_accessibility.prompt.mockResolvedValue('invalidVar');
        await controller.showVariableMenu(controller.editor);
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Invalid variable name', { assertive: true });
    });

    test('showVariableMenu does nothing when cancelled', async () => {
        await controller.connect();
        window.KMP_accessibility.prompt.mockResolvedValue(null);
        await controller.showVariableMenu(controller.editor);
        expect(controller.editor.codemirror.replaceSelection).not.toHaveBeenCalled();
    });
});
