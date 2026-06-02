import WorkflowVariablePicker from '../../../assets/js/controllers/workflow-variable-picker.js'

describe('WorkflowVariablePicker object lookup variables', () => {
    test('expands Get Object by ID record fields from the selected entity schema', () => {
        const picker = new WorkflowVariablePicker({
            actions: [{
                action: 'Core.GetObjectById',
                label: 'Get Object by ID',
                outputSchema: {
                    found: { type: 'boolean', label: 'Record Found' },
                    record: { type: 'object', label: 'Record Fields' },
                },
            }],
            entities: [{
                entityType: 'Core.Members',
                label: 'Members',
                fields: {
                    id: { type: 'integer', label: 'ID' },
                    sca_name: { type: 'string', label: 'SCA Name' },
                },
            }],
        })

        const variables = picker.getNodeOutputSchema({
            name: 'lookupMember',
            data: {
                type: 'action',
                nodeKey: 'lookupMember',
                label: 'Find recipient',
                config: {
                    action: 'Core.GetObjectById',
                    params: {
                        entityType: 'Core.Members',
                    },
                },
            },
        })

        expect(variables).toEqual(expect.arrayContaining([
            expect.objectContaining({
                path: '$.nodes.lookupMember.result.record.id',
                label: 'Find recipient: record.ID',
                type: 'integer',
            }),
            expect.objectContaining({
                path: '$.nodes.lookupMember.result.record.sca_name',
                label: 'Find recipient: record.SCA Name',
                type: 'string',
            }),
        ]))
    })

    test('keeps Get Object by ID generic when entity selection is dynamic', () => {
        const picker = new WorkflowVariablePicker({
            actions: [{
                action: 'Core.GetObjectById',
                label: 'Get Object by ID',
                outputSchema: {
                    record: { type: 'object', label: 'Record Fields' },
                },
            }],
            entities: [{
                entityType: 'Core.Members',
                fields: {
                    sca_name: { type: 'string', label: 'SCA Name' },
                },
            }],
        })

        const variables = picker.getNodeOutputSchema({
            name: 'lookupDynamic',
            data: {
                type: 'action',
                nodeKey: 'lookupDynamic',
                config: {
                    action: 'Core.GetObjectById',
                    params: {
                        entityType: '$.trigger.entityType',
                    },
                },
            },
        })

        expect(variables).toContainEqual(expect.objectContaining({
            path: '$.nodes.lookupDynamic.result.record',
            type: 'object',
        }))
        expect(variables).not.toContainEqual(expect.objectContaining({
            path: '$.nodes.lookupDynamic.result.record.sca_name',
        }))
    })

    test('adds named Assign to Variable context output when variable name is fixed', () => {
        const picker = new WorkflowVariablePicker({
            actions: [{
                action: 'Core.SetVariable',
                label: 'Assign to Variable',
                outputSchema: {
                    value: { type: 'mixed', label: 'Assigned Value' },
                },
            }],
            entities: [],
        })

        const variables = picker.getNodeOutputSchema({
            name: 'assignMember',
            data: {
                type: 'action',
                nodeKey: 'assignMember',
                config: {
                    action: 'Core.SetVariable',
                    params: {
                        name: 'selectedMemberId',
                        value: 42,
                    },
                },
            },
        })

        expect(variables).toContainEqual({
            path: '$.variables.selectedMemberId',
            label: 'Variable: selectedMemberId',
            type: 'integer',
        })
    })

    test('renders variable dropdown as a stable inline listbox for context fields', () => {
        const picker = new WorkflowVariablePicker({
            actions: [],
            entities: [],
            builtinContext: [{
                path: '$.trigger.feedbackRequestId',
                label: 'Trigger: Feedback Request ID',
                type: 'integer',
            }],
        })

        const editor = {
            export: () => ({
                drawflow: {
                    Home: {
                        data: {},
                    },
                },
            }),
        }

        document.body.innerHTML = `
            <div class="workflow-config-panel">
                <div class="input-group value-picker">
                    <div class="wf-var-picker-wrapper">
                        <input data-variable-picker="true" value="$.tr">
                        <button type="button">Variables</button>
                    </div>
                </div>
            </div>
        `
        const input = document.querySelector('input')
        const button = document.querySelector('button')
        const wrapper = input.closest('.wf-var-picker-wrapper')
        wrapper.getBoundingClientRect = () => ({
            left: 160,
            top: 275,
            bottom: 306,
            width: 200,
        })
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 560,
        })
        Object.defineProperty(window, 'innerHeight', {
            configurable: true,
            value: 760,
        })
        picker.showDropdown(button, input, 'action-1', editor)

        const dropdown = document.querySelector('.wf-var-dropdown')
        expect(dropdown.parentElement).toBe(document.body)
        expect(dropdown.style.position).toBe('fixed')
        expect(dropdown.style.left).toBe('160px')
        expect(dropdown.style.top).toBe('310px')
        expect(dropdown.style.width).toBe('392px')
        expect(dropdown.getAttribute('role')).toBe('listbox')
        expect(dropdown.getAttribute('aria-label')).toBe('Workflow variables')
        expect(input.getAttribute('role')).toBe('combobox')
        expect(input.getAttribute('aria-expanded')).toBe('true')
        expect(input.getAttribute('aria-controls')).toBe(dropdown.id)
        expect(input.getAttribute('aria-activedescendant')).toBe(dropdown.querySelector('[role="option"]').id)
        expect(document.querySelector('.input-group').classList.contains('wf-var-picker-group-open')).toBe(true)

        document.dispatchEvent(new MouseEvent('click', { bubbles: true }))
        expect(document.querySelector('.wf-var-dropdown')).toBeNull()
        expect(input.getAttribute('aria-expanded')).toBe('false')
        expect(document.querySelector('.input-group').classList.contains('wf-var-picker-group-open')).toBe(false)
    })

    test('filters and displays node variables by config step label while inserting internal path', () => {
        const picker = new WorkflowVariablePicker({
            actions: [{
                action: 'Core.GetObjectById',
                label: 'Get Object by ID',
                outputSchema: {
                    record: { type: 'object', label: 'Record Fields' },
                },
            }],
            entities: [{
                entityType: 'Core.Members',
                fields: {
                    sca_name: { type: 'string', label: 'SCA Name' },
                },
            }],
        })
        const editor = {
            export: () => ({
                drawflow: {
                    Home: {
                        data: {
                            1: {
                                name: 'trigger-1',
                                data: {
                                    type: 'trigger',
                                    nodeKey: 'trigger-1',
                                    config: {},
                                },
                                inputs: {},
                                outputs: { output_1: { connections: [{ node: '2', input: 'input_1' }] } },
                            },
                            2: {
                                name: 'action-1780419674668',
                                data: {
                                    type: 'action',
                                    nodeKey: 'action-1780419674668',
                                    config: {
                                        action: 'Core.GetObjectById',
                                        _nodeLabel: 'Find recipient',
                                        params: { entityType: 'Core.Members' },
                                    },
                                },
                                inputs: { input_1: { connections: [{ node: '1', output: 'output_1' }] } },
                                outputs: { output_1: { connections: [{ node: '3', input: 'input_1' }] } },
                            },
                            3: {
                                name: 'action-3',
                                data: {
                                    type: 'action',
                                    nodeKey: 'action-3',
                                    config: {},
                                },
                                inputs: { input_1: { connections: [{ node: '2', output: 'output_1' }] } },
                                outputs: {},
                            },
                        },
                    },
                },
            }),
        }

        document.body.innerHTML = `
            <div class="value-picker">
                <input data-variable-picker="true" value="recipient">
            </div>
        `
        picker.attachPickers(document.querySelector('.value-picker'), '3', editor)
        const input = document.querySelector('input')
        const button = document.querySelector('.wf-var-picker-btn')

        picker.showDropdown(button, input, '3', editor)

        const options = Array.from(document.querySelectorAll('[role="option"]'))
        const genericOption = options.find(option => option.textContent.includes('Find recipient: Record Fields'))
        const fieldOption = options.find(option => option.textContent.includes('Find recipient: record.SCA Name'))
        expect(genericOption).toBeDefined()
        expect(genericOption.textContent).toContain('$.nodes.action-1780419674668.result.record')
        const option = fieldOption
        expect(option).toBeDefined()
        expect(option.textContent).toContain('Find recipient: record.SCA Name')
        expect(option.textContent).toContain('$.nodes.action-1780419674668.result.record.sca_name')

        option.click()

        expect(input.value).toBe('$.nodes.action-1780419674668.result.record.sca_name')
        const hint = document.querySelector('.wf-var-picker-hint')
        expect(hint.hidden).toBe(false)
        expect(hint).toHaveTextContent('Selected variable: Find recipient: record.SCA Name (string)')
        expect(input.getAttribute('aria-describedby')).toContain(hint.id)

        input.value = '$.nodes.action-1780419674668.result.record'
        input.dispatchEvent(new Event('input', { bubbles: true }))
        input.dispatchEvent(new Event('change', { bubbles: true }))
        expect(hint).toHaveTextContent('Selected variable: Find recipient: Record Fields (object)')

        input.value = '$.nodes.action-1780419674668.result.unknown'
        input.dispatchEvent(new Event('input', { bubbles: true }))
        expect(hint.hidden).toBe(true)
        input.dispatchEvent(new Event('change', { bubbles: true }))
        expect(input.value).toBe('$.nodes.action-1780419674668.result.record')
        expect(hint).toHaveTextContent('Selected variable: Find recipient: Record Fields (object)')
    })

    test('clears an unselected context filter instead of saving an invalid path', () => {
        const picker = new WorkflowVariablePicker({
            actions: [],
            entities: [],
            builtinContext: [{
                path: '$.trigger.feedbackRequestId',
                label: 'Trigger: Feedback Request ID',
                type: 'integer',
            }],
        })
        const editor = {
            export: () => ({
                drawflow: {
                    Home: {
                        data: {},
                    },
                },
            }),
        }

        document.body.innerHTML = `
            <div class="value-picker">
                <input data-variable-picker="true" value="">
            </div>
        `
        picker.attachPickers(document.querySelector('.value-picker'), 'action-1', editor)
        const input = document.querySelector('input')
        const hint = document.querySelector('.wf-var-picker-hint')

        input.value = 'Feedback Request'
        input.dispatchEvent(new Event('input', { bubbles: true }))
        input.dispatchEvent(new Event('change', { bubbles: true }))

        expect(input.value).toBe('')
        expect(hint.hidden).toBe(true)
    })

    test('uses above-anchor viewport space when there is not enough room below', () => {
        const picker = new WorkflowVariablePicker({ actions: [], entities: [] })
        const dropdown = document.createElement('ul')
        const wrapper = document.createElement('div')
        wrapper.getBoundingClientRect = () => ({
            left: 160,
            top: 500,
            bottom: 531,
            width: 200,
        })
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 560,
        })
        Object.defineProperty(window, 'innerHeight', {
            configurable: true,
            value: 600,
        })

        picker.positionDropdown(dropdown, wrapper)

        expect(dropdown.style.left).toBe('160px')
        expect(dropdown.style.top).toBe('176px')
        expect(dropdown.style.width).toBe('392px')
        expect(dropdown.style.maxHeight).toBe('320px')
    })

    test('supports keyboard selection and replaces the field with the selected variable', () => {
        const picker = new WorkflowVariablePicker({
            actions: [],
            entities: [],
            builtinContext: [
                {
                    path: '$.trigger.feedbackRequestId',
                    label: 'Trigger: Feedback Request ID',
                    type: 'integer',
                },
                {
                    path: '$.instance.id',
                    label: 'Instance ID',
                    type: 'integer',
                },
            ],
        })
        const editor = {
            export: () => ({
                drawflow: {
                    Home: {
                        data: {},
                    },
                },
            }),
        }

        document.body.innerHTML = `
            <div class="value-picker">
                <input data-variable-picker="true" value="before $.tr after">
                <button type="button">Variables</button>
            </div>
        `
        const input = document.querySelector('input')
        const button = document.querySelector('button')
        input.setSelectionRange(11, 11)
        picker.showDropdown(button, input, 'action-1', editor)

        picker.handleDropdownKeydown(new KeyboardEvent('keydown', { key: 'Enter' }), input)

        expect(document.querySelector('.wf-var-dropdown')).toBeNull()
        expect(input.value).toBe('$.trigger.feedbackRequestId')
    })

    test('arrow keys move the active variable option', () => {
        const picker = new WorkflowVariablePicker({
            actions: [],
            entities: [],
            builtinContext: [
                {
                    path: '$.trigger.feedbackRequestId',
                    label: 'Trigger: Feedback Request ID',
                    type: 'integer',
                },
                {
                    path: '$.instance.id',
                    label: 'Instance ID',
                    type: 'integer',
                },
            ],
        })
        const editor = {
            export: () => ({
                drawflow: {
                    Home: {
                        data: {},
                    },
                },
            }),
        }

        document.body.innerHTML = `
            <div class="value-picker">
                <input data-variable-picker="true" value="">
                <button type="button">Variables</button>
            </div>
        `
        const input = document.querySelector('input')
        const button = document.createElement('button')
        document.querySelector('.value-picker').appendChild(button)

        picker.showDropdown(button, input, 'action-1', editor, true)
        picker.handleDropdownKeydown(new KeyboardEvent('keydown', { key: 'ArrowDown' }), input)

        const options = Array.from(document.querySelectorAll('[role="option"]'))
        expect(options[0].getAttribute('aria-selected')).toBe('false')
        expect(options[1].getAttribute('aria-selected')).toBe('true')
        expect(input.getAttribute('aria-activedescendant')).toBe(options[1].id)
    })
})
