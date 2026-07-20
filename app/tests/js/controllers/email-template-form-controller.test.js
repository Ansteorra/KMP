import '../../../assets/js/controllers/email-template-form-controller.js';

const EmailTemplateFormController = window.Controllers['email-template-form'];

describe('EmailTemplateFormController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="email-template-form">
                <input type="text" data-email-template-form-target="nameField" value="">
                <input type="text" data-email-template-form-target="slugField" value="">
                <input type="text" data-email-template-form-target="availableVars" value="">
                <input type="text" data-email-template-form-target="subjectTemplate" value="">
                <textarea data-email-template-form-target="htmlTemplate"></textarea>
                <textarea data-email-template-form-target="textTemplate"></textarea>
                <div data-email-template-form-target="parsedVarsPanel" style="display:none;"></div>
                <div data-email-template-form-target="parsedVarsList"></div>
                <input type="hidden" data-email-template-form-target="variablesSchema" value="">
                <div data-email-template-form-target="variableRows"></div>
                <p data-email-template-form-target="emptyVariableMessage"></p>
                <template data-email-template-form-target="variableRowTemplate">
                    <div data-variable-contract-row>
                        <input type="text" data-email-template-form-target="variableName">
                        <input type="text" data-email-template-form-target="variableDescription">
                        <select data-email-template-form-target="variableType">
                            <option value="string">Text</option>
                            <option value="number">Number</option>
                            <option value="boolean">Yes/No</option>
                        </select>
                        <input type="checkbox" data-email-template-form-target="variableRequired">
                        <button type="button" data-action="email-template-form#removeVariable">Remove</button>
                    </div>
                </template>
            </div>
        `;

        controller = new EmailTemplateFormController();
        controller.element = document.querySelector('[data-controller="email-template-form"]');
        controller.availableVarsTarget = document.querySelector('[data-email-template-form-target="availableVars"]');
        controller.subjectTemplateTarget = document.querySelector('[data-email-template-form-target="subjectTemplate"]');
        controller.nameFieldTarget = document.querySelector('[data-email-template-form-target="nameField"]');
        controller.slugFieldTarget = document.querySelector('[data-email-template-form-target="slugField"]');
        controller.htmlTemplateTarget = document.querySelector('[data-email-template-form-target="htmlTemplate"]');
        controller.textTemplateTarget = document.querySelector('[data-email-template-form-target="textTemplate"]');
        controller.parsedVarsPanelTarget = document.querySelector('[data-email-template-form-target="parsedVarsPanel"]');
        controller.parsedVarsListTarget = document.querySelector('[data-email-template-form-target="parsedVarsList"]');
        controller.variablesSchemaTarget = document.querySelector('[data-email-template-form-target="variablesSchema"]');
        controller.variableRowsTarget = document.querySelector('[data-email-template-form-target="variableRows"]');
        controller.variableRowTemplateTarget = document.querySelector('[data-email-template-form-target="variableRowTemplate"]');
        controller.emptyVariableMessageTarget = document.querySelector('[data-email-template-form-target="emptyVariableMessage"]');
        controller.hasAvailableVarsTarget = true;
        controller.hasSubjectTemplateTarget = true;
        controller.hasNameFieldTarget = true;
        controller.hasSlugFieldTarget = true;
        controller.hasHtmlTemplateTarget = true;
        controller.hasTextTemplateTarget = true;
        controller.hasParsedVarsPanelTarget = true;
        controller.hasParsedVarsListTarget = true;
        controller.hasVariablesSchemaTarget = true;
        controller.hasVariableRowsTarget = true;
        controller.hasVariableRowTemplateTarget = true;
        controller.hasEmptyVariableMessageTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['email-template-form']).toBe(EmailTemplateFormController);
    });

    test('has correct static targets', () => {
        expect(EmailTemplateFormController.targets).toEqual(
            expect.arrayContaining([
                'availableVars',
                'subjectTemplate',
                'nameField',
                'slugField',
                'htmlTemplate',
                'textTemplate',
                'parsedVarsPanel',
                'parsedVarsList',
                'variablesSchema',
                'variableRows',
                'variableRowTemplate',
                'emptyVariableMessage',
                'variableName',
                'variableDescription',
                'variableType',
                'variableRequired',
            ]),
        );
    });

    test('nameChanged generates slug from name when slug is empty', () => {
        controller.nameFieldTarget.value = 'Warrant Issued';
        controller.slugFieldTarget.value = '';
        controller.nameChanged();

        expect(controller.slugFieldTarget.value).toBe('warrant-issued');
    });

    test('nameChanged does not overwrite a manually-entered slug', () => {
        controller.nameFieldTarget.value = 'Warrant Issued';
        controller.slugFieldTarget.value = 'my-custom-slug';
        controller.nameChanged();

        expect(controller.slugFieldTarget.value).toBe('my-custom-slug');
    });

    test('_slugify strips special characters', () => {
        expect(controller._slugify("Officer's Appointment & Role!")).toBe('officers-appointment-role');
    });

    test('_extractPlaceholders extracts unique variable names', () => {
        const vars = controller._extractPlaceholders('Hello {{name}}, your award is {{awardName}} and {{name}} again');
        expect(vars).toEqual(['awardName', 'name']);
    });

    test('_extractPlaceholders ignores control keywords', () => {
        const vars = controller._extractPlaceholders('{{#if condition}}Hello {{name}}{{/if}}{{else}}');
        expect(vars).toEqual(['condition', 'name']);
    });

    test('_extractPlaceholders includes comparison condition variables without quoted values', () => {
        const vars = controller._extractPlaceholders('{{#if status == "Approved" && awardReason}}{{name}}{{/if}}');
        expect(vars).toEqual(['awardReason', 'name', 'status']);
    });

    test('templateChanged shows parsed vars panel when placeholders found', () => {
        controller.htmlTemplateTarget.value = 'Hello {{recipientName}}, your warrant {{warrantTitle}} is ready.';
        controller.textTemplateTarget.value = '';
        controller.templateChanged();

        expect(controller.parsedVarsPanelTarget.style.display).not.toBe('none');
        const badges = controller.parsedVarsListTarget.querySelectorAll('button');
        expect(badges).toHaveLength(2);
        expect(badges[0].textContent).toContain('recipientName');
        expect(badges[1].textContent).toContain('warrantTitle');
    });

    test('templateChanged hides parsed vars panel when no placeholders found', () => {
        controller.parsedVarsPanelTarget.style.display = '';
        controller.htmlTemplateTarget.value = 'No variables here';
        controller.textTemplateTarget.value = '';
        controller.templateChanged();

        expect(controller.parsedVarsPanelTarget.style.display).toBe('none');
    });

    test('templateChanged combines html and text template placeholders', () => {
        controller.htmlTemplateTarget.value = '{{htmlVar}}';
        controller.textTemplateTarget.value = '{{textVar}}';
        controller.templateChanged();

        const names = Array.from(controller.parsedVarsListTarget.querySelectorAll('button')).map((badge) => badge.textContent);
        expect(names).toContain('{{htmlVar}}');
        expect(names).toContain('{{textVar}}');
    });

    test('connect renders existing variables schema rows without stealing focus', () => {
        const focusSpy = jest.spyOn(HTMLInputElement.prototype, 'focus');
        controller.variablesSchemaTarget.value = JSON.stringify([
            { name: 'recipientName', description: 'Recipient name', type: 'string', required: true },
        ]);

        controller.connect();

        expect(focusSpy).not.toHaveBeenCalled();
        expect(controller.variableRowsTarget.querySelectorAll('[data-variable-contract-row]')).toHaveLength(1);
        expect(controller.variableRowsTarget.querySelector('[data-email-template-form-target~="variableName"]').value)
            .toBe('recipientName');
        expect(controller.emptyVariableMessageTarget.classList.contains('d-none')).toBe(true);
    });

    test('addVariableFromPlaceholder adds a required text variable and serializes JSON', () => {
        controller.addVariableFromPlaceholder('awardReason');

        const rows = controller.variableRowsTarget.querySelectorAll('[data-variable-contract-row]');
        expect(rows).toHaveLength(1);

        const schema = JSON.parse(controller.variablesSchemaTarget.value);
        expect(schema).toEqual([
            {
                name: 'awardReason',
                description: 'Award Reason',
                type: 'string',
                required: true,
            },
        ]);
    });

    test('variableContractChanged serializes edited rows', () => {
        controller.addVariable();

        controller.variableRowsTarget.querySelector('[data-email-template-form-target~="variableName"]').value = 'count';
        controller.variableRowsTarget.querySelector('[data-email-template-form-target~="variableDescription"]').value = 'Number of items';
        controller.variableRowsTarget.querySelector('[data-email-template-form-target~="variableType"]').value = 'number';
        controller.variableRowsTarget.querySelector('[data-email-template-form-target~="variableRequired"]').checked = true;
        controller.variableContractChanged();

        expect(JSON.parse(controller.variablesSchemaTarget.value)).toEqual([
            {
                name: 'count',
                description: 'Number of items',
                type: 'number',
                required: true,
            },
        ]);
    });
});
