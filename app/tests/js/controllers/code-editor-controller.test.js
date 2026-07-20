import '../../../assets/js/controllers/code-editor-controller.js';

const CodeEditorController = window.Controllers['code-editor'];

describe('CodeEditorController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="code-editor"
                 data-code-editor-language-value="yaml"
                 data-code-editor-validate-on-change-value="true">
                <div class="form-group">
                    <textarea data-code-editor-target="textarea">key: value</textarea>
                    <div data-code-editor-target="errorDisplay" class="d-none"></div>
                </div>
            </div>
        `;

        controller = new CodeEditorController();
        controller.element = document.querySelector('[data-controller="code-editor"]');
        controller.textareaTarget = document.querySelector('[data-code-editor-target="textarea"]');
        controller.errorDisplayTarget = document.querySelector('[data-code-editor-target="errorDisplay"]');
        controller.hasTextareaTarget = true;
        controller.hasErrorDisplayTarget = true;
        controller.languageValue = 'yaml';
        controller.validateOnChangeValue = true;
        controller.minHeightValue = '300px';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['code-editor']).toBe(CodeEditorController);
    });

    test('has correct static targets', () => {
        expect(CodeEditorController.targets).toEqual(
            expect.arrayContaining(['textarea', 'errorDisplay', 'lineNumbers'])
        );
    });

    test('has correct static values', () => {
        expect(CodeEditorController.values).toHaveProperty('language');
        expect(CodeEditorController.values).toHaveProperty('validateOnChange');
        expect(CodeEditorController.values).toHaveProperty('minHeight');
    });

    // setupEditor tests
    test('setupEditor creates wrapper and line numbers', () => {
        controller.setupEditor();
        const wrapper = controller.element.querySelector('.code-editor-wrapper');
        expect(wrapper).not.toBeNull();
        expect(wrapper.querySelector('.code-editor-line-numbers')).not.toBeNull();
    });

    test('setupEditor does not re-initialize if already set up', () => {
        controller.setupEditor();
        const firstWrapper = controller.element.querySelector('.code-editor-wrapper');
        controller.setupEditor();
        const wrappers = controller.element.querySelectorAll('.code-editor-wrapper');
        expect(wrappers.length).toBe(1);
    });

    test('setupEditor skips when no textarea target', () => {
        controller.hasTextareaTarget = false;
        expect(() => controller.setupEditor()).not.toThrow();
    });

    // updateLineNumbers tests
    test('updateLineNumbers shows correct count', () => {
        controller.setupEditor();
        controller.textareaTarget.value = 'line1\nline2\nline3';
        controller.updateLineNumbers();
        expect(controller.lineNumbersElement.innerHTML).toBe('1<br>2<br>3');
    });

    test('updateLineNumbers handles single line', () => {
        controller.setupEditor();
        controller.textareaTarget.value = 'single line';
        controller.updateLineNumbers();
        expect(controller.lineNumbersElement.innerHTML).toBe('1');
    });

    test('updateLineNumbers handles empty content', () => {
        controller.setupEditor();
        controller.textareaTarget.value = '';
        controller.updateLineNumbers();
        expect(controller.lineNumbersElement.innerHTML).toBe('1');
    });

    // validateJSON tests
    test('validateJSON returns null for valid JSON', () => {
        expect(controller.validateJSON('{"key": "value"}')).toBeNull();
    });

    test('validateJSON returns null for empty content', () => {
        expect(controller.validateJSON('')).toBeNull();
        expect(controller.validateJSON('   ')).toBeNull();
    });

    test('validateJSON returns error for invalid JSON', () => {
        const error = controller.validateJSON('{invalid}');
        expect(error).not.toBeNull();
        expect(error).toContain('JSON Error');
    });

    test('validateJSON includes line info in error when available', () => {
        const error = controller.validateJSON('{\n  "key": value\n}');
        expect(error).not.toBeNull();
    });

    // validateYAML tests
    test('validateYAML returns null for valid YAML', () => {
        expect(controller.validateYAML('key: value')).toBeNull();
    });

    test('validateYAML returns null for empty content', () => {
        expect(controller.validateYAML('')).toBeNull();
    });

    test('validateYAML detects tabs', () => {
        const error = controller.validateYAML("key:\n\tvalue");
        expect(error).toContain('Tabs are not allowed');
    });

    test('validateYAML allows comments', () => {
        expect(controller.validateYAML('# This is a comment\nkey: value')).toBeNull();
    });

    test('validateYAML skips template syntax', () => {
        expect(controller.validateYAML('key: {{template_var}}')).toBeNull();
    });

    // validateContent tests
    test('validateContent validates JSON when language is json', () => {
        controller.languageValue = 'json';
        controller.textareaTarget.value = '{"valid": true}';
        expect(controller.validateContent()).toBe(true);
    });

    test('validateContent validates YAML when language is yaml', () => {
        controller.languageValue = 'yaml';
        controller.textareaTarget.value = 'key: value';
        expect(controller.validateContent()).toBe(true);
    });

    test('validateContent returns false for invalid JSON', () => {
        controller.languageValue = 'json';
        controller.textareaTarget.value = '{invalid';
        expect(controller.validateContent()).toBe(false);
    });

    // displayError tests
    test('displayError shows error in errorDisplay target', () => {
        controller.displayError('Test error');
        expect(controller.errorDisplayTarget.classList.contains('d-none')).toBe(false);
        expect(controller.errorDisplayTarget.classList.contains('alert-danger')).toBe(true);
        expect(controller.errorDisplayTarget.innerHTML).toContain('Syntax Error');
    });

    test('displayError hides when no error', () => {
        controller.displayError(null);
        expect(controller.errorDisplayTarget.classList.contains('d-none')).toBe(true);
        expect(controller.errorDisplayTarget.innerHTML).toBe('');
    });

    // handleKeydown Tab tests
    test('handleKeydown Tab inserts spaces', () => {
        controller.setupEditor();
        controller.textareaTarget.value = 'key: value';
        controller.textareaTarget.selectionStart = 0;
        controller.textareaTarget.selectionEnd = 0;

        const event = { key: 'Tab', shiftKey: false, preventDefault: jest.fn() };
        controller.handleKeydown(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.textareaTarget.value).toBe('  key: value');
    });

    test('handleKeydown Shift+Tab removes indentation', () => {
        controller.setupEditor();
        controller.textareaTarget.value = '  key: value';
        controller.textareaTarget.selectionStart = 2;
        controller.textareaTarget.selectionEnd = 2;

        const event = { key: 'Tab', shiftKey: true, preventDefault: jest.fn() };
        controller.handleKeydown(event);

        expect(controller.textareaTarget.value).toBe('key: value');
    });

    // escapeHtml tests
    test('escapeHtml escapes HTML entities', () => {
        expect(controller.escapeHtml('<script>alert("xss")</script>')).toBe(
            '&lt;script&gt;alert("xss")&lt;/script&gt;'
        );
    });

    // validate action
    test('validate action triggers validation and returns result', () => {
        controller.textareaTarget.value = 'key: value';
        controller.languageValue = 'yaml';
        const result = controller.validate({ preventDefault: jest.fn() });
        expect(result).toBe(true);
    });

    // beforeSubmit
    test('beforeSubmit prevents submission on errors with confirm false', async () => {
        window.KMP_accessibility.confirm.mockResolvedValue(false);
        controller.languageValue = 'json';
        controller.textareaTarget.value = '{invalid';
        const event = { preventDefault: jest.fn() };
        await controller.beforeSubmit(event);
        expect(event.preventDefault).toHaveBeenCalled();
    });

    test('beforeSubmit allows submission when valid', () => {
        controller.languageValue = 'json';
        controller.textareaTarget.value = '{"valid": true}';
        const event = { preventDefault: jest.fn() };
        controller.beforeSubmit(event);
        expect(event.preventDefault).not.toHaveBeenCalled();
    });

    test('beforeSubmit resubmits on errors with confirm true', async () => {
        window.KMP_accessibility.confirm.mockResolvedValue(true);
        controller.languageValue = 'json';
        controller.textareaTarget.value = '{invalid';
        const form = document.createElement('form');
        form.requestSubmit = jest.fn();
        const event = { preventDefault: jest.fn(), target: form };
        await controller.beforeSubmit(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(form.requestSubmit).toHaveBeenCalled();
    });

    // disconnect
    test('disconnect removes event listeners', () => {
        controller.setupEditor();
        const removeSpy = jest.spyOn(controller.textareaTarget, 'removeEventListener');
        controller.disconnect();
        expect(removeSpy).toHaveBeenCalledTimes(3); // input, scroll, keydown
        expect(controller._onInput).toBeNull();
        expect(controller._onScroll).toBeNull();
        expect(controller._onKeydown).toBeNull();
    });
});
