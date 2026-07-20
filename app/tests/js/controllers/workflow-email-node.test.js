/**
 * Tests for workflow email-node UX:
 *   - WorkflowNodeConfigHandler._renderTemplateAnalysis()
 *   - WorkflowNodeConfigHandler._loadEmailTemplateOptions()
 *   - WorkflowNodeConfigHandler._escHtml()
 *   - WorkflowConfigPanel._renderEmailTemplateSelect()
 */

import WorkflowNodeConfigHandler from '../../../assets/js/controllers/workflow-node-config-handler.js'
import WorkflowConfigPanel from '../../../assets/js/controllers/workflow-config-panel.js'

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeOption({ value = '1', slug = '', isWorkflowNative = false, subject = '', variablesSchema = [], parsedPlaceholders = [], availableVars = [] } = {}) {
    const el = document.createElement('option')
    el.value = value
    el.selected = true
    el.dataset.slug = slug
    el.dataset.isWorkflowNative = isWorkflowNative ? '1' : '0'
    el.dataset.subject = subject
    el.dataset.variablesSchema = JSON.stringify(variablesSchema)
    el.dataset.parsedPlaceholders = JSON.stringify(parsedPlaceholders)
    el.dataset.availableVars = JSON.stringify(availableVars)
    return el
}

function makeSelect(option) {
    const container = document.createElement('div')
    container.className = 'mb-3'
    const select = document.createElement('select')
    if (option) select.appendChild(option)
    const analysis = document.createElement('div')
    analysis.className = 'email-template-analysis'
    container.appendChild(select)
    container.appendChild(analysis)
    document.body.appendChild(container)
    return { select, analysis, container }
}

function makeHandler({ workflowVars = null, nodeConfig = {} } = {}) {
    // Minimal designer/editor stub
    const mockVariablePicker = workflowVars !== null
        ? { buildVariableList: jest.fn(() => workflowVars) }
        : null

    const mockEditor = {
        getNodeFromId: jest.fn(() => ({ data: { type: 'action', config: nodeConfig } }))
    }

    const mockNodeConfigTarget = document.createElement('div')
    document.body.appendChild(mockNodeConfigTarget)

    const mockDesigner = {
        editor: mockEditor,
        _configPanel: null,
        _variablePicker: mockVariablePicker,
        nodeConfigTarget: mockNodeConfigTarget,
        hasNodeConfigTarget: true,
        _selectedNodes: new Set(),
        canvasTarget: document.createElement('div'),
        _shiftHeld: false,
        clearMultiSelect: jest.fn(),
    }

    const handler = new WorkflowNodeConfigHandler(mockDesigner)
    return { handler, mockNodeConfigTarget, mockEditor, mockVariablePicker }
}

// ── WorkflowNodeConfigHandler._escHtml ───────────────────────────────────────

describe('WorkflowNodeConfigHandler._escHtml', () => {
    let handler

    beforeEach(() => {
        ({ handler } = makeHandler())
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('escapes HTML special characters', () => {
        expect(handler._escHtml('<b>"it\'s"&done</b>')).toBe('&lt;b&gt;&quot;it&#39;s&quot;&amp;done&lt;/b&gt;')
    })

    test('returns empty string unchanged', () => {
        expect(handler._escHtml('')).toBe('')
    })

    test('coerces non-string to string', () => {
        expect(handler._escHtml(42)).toBe('42')
    })
})

// ── WorkflowNodeConfigHandler._renderTemplateAnalysis ────────────────────────

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — no option selected', () => {
    let handler, select, analysis, container

    beforeEach(() => {
        ({ handler } = makeHandler())
        ;({ select, analysis, container } = makeSelect(null))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('clears analysis when no option is selected', () => {
        analysis.innerHTML = 'old content'
        handler._renderTemplateAnalysis(select, null)
        expect(analysis.innerHTML).toBe('')
    })
})

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — workflow-native slug badge', () => {
    let handler, select, analysis, container

    beforeEach(() => {
        ({ handler } = makeHandler())
        const opt = makeOption({ value: '5', slug: 'warrant-issued', isWorkflowNative: true, subject: 'Your warrant {{warrantName}}' })
        ;({ select, analysis, container } = makeSelect(opt))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('shows workflow-native badge with slug', () => {
        handler._renderTemplateAnalysis(select, null)
        expect(analysis.innerHTML).toContain('warrant-issued')
        expect(analysis.innerHTML).toContain('bg-primary')
    })

    test('shows subject preview', () => {
        handler._renderTemplateAnalysis(select, null)
        expect(analysis.innerHTML).toContain('Your warrant')
    })
})

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — legacy template', () => {
    let handler, select, analysis

    beforeEach(() => {
        ({ handler } = makeHandler())
        const opt = makeOption({ value: '3', slug: '', isWorkflowNative: false, subject: 'Old email' })
        ;({ select, analysis } = makeSelect(opt))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('shows legacy warning badge', () => {
        handler._renderTemplateAnalysis(select, null)
        expect(analysis.innerHTML).toContain('Legacy')
        expect(analysis.innerHTML).toContain('bg-warning')
    })
})

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — variables_schema required/optional', () => {
    const schema = [
        { name: 'memberName', type: 'string', required: true, description: 'Member display name' },
        { name: 'warrantTitle', type: 'string', required: true },
        { name: 'expiresDate', type: 'date', required: false, description: 'Expiry date' },
    ]

    let handler, select, analysis

    beforeEach(() => {
        ({ handler } = makeHandler())
        const opt = makeOption({ value: '7', slug: 'warrant-issued', isWorkflowNative: true, variablesSchema: schema })
        ;({ select, analysis } = makeSelect(opt))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('renders required label for required schema entries', () => {
        handler._renderTemplateAnalysis(select, null)
        const html = analysis.innerHTML
        expect(html).toContain('memberName')
        expect(html).toContain('required')
    })

    test('renders optional label for optional schema entries', () => {
        handler._renderTemplateAnalysis(select, null)
        expect(analysis.innerHTML).toContain('expiresDate')
        expect(analysis.innerHTML).toContain('optional')
    })

    test('renders type information', () => {
        handler._renderTemplateAnalysis(select, null)
        expect(analysis.innerHTML).toContain('date')
    })
})

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — missing required vars warning', () => {
    const schema = [
        { name: 'memberName', type: 'string', required: true },
        { name: 'warrantTitle', type: 'string', required: true },
    ]
    let handler, select, analysis

    beforeEach(() => {
        ({ handler } = makeHandler())
        const opt = makeOption({ value: '7', slug: 'warrant-issued', isWorkflowNative: true, variablesSchema: schema })
        ;({ select, analysis } = makeSelect(opt))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('shows danger alert when required vars are unmapped', () => {
        handler._renderTemplateAnalysis(select, 'node-1')
        expect(analysis.innerHTML).toContain('alert-danger')
        expect(analysis.innerHTML).toContain('not configured in this Send Email step')
    })

    test('shows x-circle icons for unmapped required vars', () => {
        handler._renderTemplateAnalysis(select, 'node-1')
        expect(analysis.innerHTML).toContain('bi-x-circle-fill text-danger')
    })
})

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — mapped required vars show success', () => {
    const schema = [
        { name: 'memberName', type: 'string', required: true },
    ]
    let handler, select, analysis

    beforeEach(() => {
        ({ handler } = makeHandler({
            nodeConfig: {
                params: {
                    vars: {
                        memberName: '$.nodes.action-1.result.memberName',
                    },
                },
            },
        }))
        const opt = makeOption({ value: '9', slug: 'welcome', isWorkflowNative: true, variablesSchema: schema })
        ;({ select, analysis } = makeSelect(opt))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('shows check icon when required var is mapped', () => {
        handler._renderTemplateAnalysis(select, 'node-2')
        expect(analysis.innerHTML).toContain('bi-check-circle-fill text-success')
    })

    test('does not show danger alert when all required vars are mapped', () => {
        handler._renderTemplateAnalysis(select, 'node-2')
        expect(analysis.innerHTML).not.toContain('alert-danger')
    })

    test('does not require the mapped value path to share the template variable name', () => {
        handler._renderTemplateAnalysis(select, 'node-2')
        expect(analysis.innerHTML).toContain('bi-check-circle-fill text-success')
    })
})

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — empty mapped vars are missing', () => {
    const schema = [
        { name: 'memberName', type: 'string', required: true },
    ]

    let handler, select, analysis

    beforeEach(() => {
        ({ handler } = makeHandler({
            nodeConfig: {
                params: {
                    vars: {
                        memberName: '',
                    },
                },
            },
        }))
        const opt = makeOption({ value: '10', slug: 'welcome', isWorkflowNative: true, variablesSchema: schema })
        ;({ select, analysis } = makeSelect(opt))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('shows danger alert when a required mapping has no value', () => {
        handler._renderTemplateAnalysis(select, 'node-3')
        expect(analysis.innerHTML).toContain('alert-danger')
        expect(analysis.innerHTML).toContain('bi-x-circle-fill text-danger')
    })
})

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — all configured email vars pass', () => {
    const schema = [
        { name: 'award', type: 'string', required: true },
        { name: 'recipient_name', type: 'string', required: true },
        { name: 'sca_name', type: 'string', required: true },
    ]

    let handler, select, analysis

    beforeEach(() => {
        ({ handler } = makeHandler({
            nodeConfig: {
                params: {
                    vars: {
                        award: '$.nodes.action-1780419627954.result.record.name',
                        recipient_name: '$.trigger.member.name',
                        sca_name: '$.trigger.member.sca_name',
                    },
                },
            },
        }))
        const opt = makeOption({ value: '11', slug: 'feedback-request', isWorkflowNative: true, variablesSchema: schema })
        ;({ select, analysis } = makeSelect(opt))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('does not warn when all required template variables have configured mappings', () => {
        handler._renderTemplateAnalysis(select, 'node-4')

        expect(analysis.innerHTML).not.toContain('alert-danger')
        expect(analysis.querySelectorAll('.bi-check-circle-fill.text-success')).toHaveLength(3)
    })
})

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — no schema, show subject placeholders', () => {
    let handler, select, analysis

    beforeEach(() => {
        ({ handler } = makeHandler())
        const opt = makeOption({ value: '2', slug: '', parsedPlaceholders: ['recipientName', 'dueDate'] })
        ;({ select, analysis } = makeSelect(opt))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('shows subject placeholders as fallback when no schema', () => {
        handler._renderTemplateAnalysis(select, null)
        expect(analysis.innerHTML).toContain('recipientName')
        expect(analysis.innerHTML).toContain('dueDate')
        expect(analysis.innerHTML).toContain('Subject placeholders')
    })
})

describe('WorkflowNodeConfigHandler._renderTemplateAnalysis — uncovered subject placeholders', () => {
    const schema = [
        { name: 'memberName', type: 'string', required: true },
    ]

    let handler, select, analysis

    beforeEach(() => {
        ({ handler } = makeHandler())
        const opt = makeOption({ value: '8', slug: 'test-tmpl', isWorkflowNative: true, variablesSchema: schema, parsedPlaceholders: ['memberName', 'extraToken'] })
        ;({ select, analysis } = makeSelect(opt))
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('surfaces subject placeholders not in schema as advisory note', () => {
        handler._renderTemplateAnalysis(select, null)
        expect(analysis.innerHTML).toContain('extraToken')
        expect(analysis.innerHTML).toContain('not in schema')
    })
})

// ── WorkflowNodeConfigHandler._loadEmailTemplateOptions ──────────────────────

describe('WorkflowNodeConfigHandler._loadEmailTemplateOptions', () => {
    let handler, mockNodeConfigTarget

    // Flush all pending microtasks/promises
    const flushPromises = () => new Promise(resolve => setTimeout(resolve, 0))

    const apiOptions = [
        {
            value: '1',
            label: 'Warrant Issued',
            slug: 'warrant-issued',
            isWorkflowNative: true,
            availableVars: ['memberName'],
            variablesSchema: [{ name: 'memberName', type: 'string', required: true }],
            parsedPlaceholders: ['memberName'],
            subjectPreview: 'Your warrant for {{memberName}}',
        },
        {
            value: '2',
            label: 'Notify Of Hire (KMPMailer)',
            slug: '',
            isWorkflowNative: false,
            availableVars: [],
            variablesSchema: [],
            parsedPlaceholders: [],
            subjectPreview: 'You have been hired',
        },
    ]

    beforeEach(() => {
        ({ handler, mockNodeConfigTarget } = makeHandler())

        // Add a select with email-template-select attribute
        const select = document.createElement('select')
        select.setAttribute('data-email-template-select', 'true')
        select.innerHTML = '<option value="">Loading…</option>'
        const analysis = document.createElement('div')
        analysis.className = 'email-template-analysis'
        const wrap = document.createElement('div')
        wrap.className = 'mb-3'
        wrap.appendChild(select)
        wrap.appendChild(analysis)
        mockNodeConfigTarget.appendChild(wrap)

        // Reset promise cache
        handler._emailTemplateOptionsPromise = null

        // Mock fetch
        global.fetch = jest.fn(() =>
            Promise.resolve({
                ok: true,
                json: () => Promise.resolve({ options: apiOptions }),
            })
        )
    })

    afterEach(() => {
        document.body.innerHTML = ''
        jest.restoreAllMocks()
    })

    test('populates dropdown with options from API', async () => {
        handler._loadEmailTemplateOptions({ params: {} }, null)
        await flushPromises()
        const select = mockNodeConfigTarget.querySelector('[data-email-template-select]')
        expect(select.options.length).toBeGreaterThan(1)
    })

    test('workflow-native option label includes slug in brackets', async () => {
        handler._loadEmailTemplateOptions({ params: {} }, null)
        await flushPromises()
        const select = mockNodeConfigTarget.querySelector('[data-email-template-select]')
        const options = Array.from(select.options)
        const wfOpt = options.find(o => o.value === '1')
        expect(wfOpt).not.toBeUndefined()
        expect(wfOpt.textContent).toContain('[warrant-issued]')
    })

    test('stores variablesSchema as data attribute on option', async () => {
        handler._loadEmailTemplateOptions({ params: {} }, null)
        await flushPromises()
        const select = mockNodeConfigTarget.querySelector('[data-email-template-select]')
        const opt = Array.from(select.options).find(o => o.value === '1')
        expect(opt).not.toBeUndefined()
        const schema = JSON.parse(opt.dataset.variablesSchema)
        expect(schema).toHaveLength(1)
        expect(schema[0].name).toBe('memberName')
    })

    test('stores parsedPlaceholders as data attribute on option', async () => {
        handler._loadEmailTemplateOptions({ params: {} }, null)
        await flushPromises()
        const select = mockNodeConfigTarget.querySelector('[data-email-template-select]')
        const opt = Array.from(select.options).find(o => o.value === '1')
        expect(opt).not.toBeUndefined()
        const placeholders = JSON.parse(opt.dataset.parsedPlaceholders)
        expect(placeholders).toContain('memberName')
    })

    test('pre-selects current template and triggers analysis', async () => {
        const spy = jest.spyOn(handler, '_renderTemplateAnalysis').mockImplementation(() => {})
        handler._loadEmailTemplateOptions({ params: { template: 1 } }, 'node-5')
        await flushPromises()
        expect(spy).toHaveBeenCalled()
    })
})

// ── WorkflowConfigPanel._renderEmailTemplateSelect ───────────────────────────

describe('WorkflowConfigPanel._renderEmailTemplateSelect', () => {
    let panel

    beforeEach(() => {
        panel = new WorkflowConfigPanel(
            { triggers: [], actions: [], conditions: [], resolvers: [] },
            []
        )
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('renders a select element with data-email-template-select attribute', () => {
        const html = panel._renderEmailTemplateSelect('params.template', { label: 'Email Template', required: true }, '')
        document.body.innerHTML = html
        const select = document.querySelector('[data-email-template-select]')
        expect(select).not.toBeNull()
    })

    test('marks field as required with danger asterisk', () => {
        const html = panel._renderEmailTemplateSelect('params.template', { label: 'Email Template', required: true }, '')
        expect(html).toContain('text-danger')
    })

    test('renders analysis container with data-template-hint attribute', () => {
        const html = panel._renderEmailTemplateSelect('params.template', { label: 'Template', required: false }, '')
        document.body.innerHTML = html
        const hint = document.querySelector('[data-template-hint]')
        expect(hint).not.toBeNull()
        expect(hint.classList.contains('email-template-analysis')).toBe(true)
    })

    test('pre-populates a placeholder selected option when currentValue given', () => {
        const html = panel._renderEmailTemplateSelect('params.template', { label: 'Template' }, '42')
        document.body.innerHTML = html
        const selected = document.querySelector('option[selected]')
        expect(selected).not.toBeNull()
        expect(selected.value).toBe('42')
    })

    test('change action points to onEmailTemplateChange', () => {
        const html = panel._renderEmailTemplateSelect('params.template', { label: 'Template' }, '')
        expect(html).toContain('onEmailTemplateChange')
    })
})

// ── WorkflowConfigPanel key-value template variables ─────────────────────────

describe('WorkflowConfigPanel._renderKvRow', () => {
    let panel

    beforeEach(() => {
        panel = new WorkflowConfigPanel(
            { triggers: [], actions: [], conditions: [], resolvers: [] },
            []
        )
    })

    afterEach(() => { document.body.innerHTML = '' })

    test('marks context values as variable-picker inputs', () => {
        document.body.innerHTML = panel._renderKvRow('params.vars', 0, 'award', '$.trigger.award')

        const valueInput = document.querySelector('[name="params.vars__val__0"]')
        expect(valueInput.getAttribute('data-variable-picker')).toBe('true')
        expect(valueInput.placeholder).toBe('Choose a workflow variable')
    })

    test('does not mark fixed values as variable-picker inputs', () => {
        document.body.innerHTML = panel._renderKvRow('params.vars', 0, 'award', 'Court Baronage')

        const valueInput = document.querySelector('[name="params.vars__val__0"]')
        expect(valueInput.hasAttribute('data-variable-picker')).toBe(false)
        expect(valueInput.placeholder).toBe('Value')
    })

    test('renders template variables as labeled mapping cards with an accessible remove action', () => {
        document.body.innerHTML = panel._renderKvRow('params.vars', 0, 'award', '$.trigger.award')

        const row = document.querySelector('.wf-template-variable-card')
        const keyInput = document.querySelector('[name="params.vars__key__0"]')
        const typeSelect = document.querySelector('[data-kv-vtype]')
        const valueInput = document.querySelector('[name="params.vars__val__0"]')
        const removeButton = document.querySelector('.wf-template-variable-remove')

        expect(row).not.toBeNull()
        expect(document.querySelector(`label[for="${keyInput.id}"]`)).toHaveTextContent('Template variable')
        expect(document.querySelector(`label[for="${typeSelect.id}"]`)).toHaveTextContent('Source')
        expect(document.querySelector(`label[for="${valueInput.id}"]`)).toHaveTextContent('Value')
        expect(removeButton.getAttribute('aria-label')).toBe('Remove template variable award')
    })
})

describe('WorkflowNodeConfigHandler key-value context mode', () => {
    afterEach(() => { document.body.innerHTML = '' })

    test('attaches variable picker when a template variable row switches to context path', () => {
        const attachPickers = jest.fn()
        const mockEditor = {
            getNodeFromId: jest.fn(() => ({ data: { config: { params: {} } } })),
            updateNodeDataFromId: jest.fn(),
        }
        const mockDesigner = {
            editor: mockEditor,
            _configPanel: new WorkflowConfigPanel({ triggers: [], actions: [], conditions: [], resolvers: [] }, []),
            _variablePicker: { attachPickers, removeSelectedVariableHint: jest.fn() },
            nodeConfigTarget: document.createElement('div'),
            hasNodeConfigTarget: true,
            _selectedNodes: new Set(),
            canvasTarget: document.createElement('div'),
            _shiftHeld: false,
            clearMultiSelect: jest.fn(),
        }
        const handler = new WorkflowNodeConfigHandler(mockDesigner)

        document.body.innerHTML = `
            <form data-node-id="node-1">
                <div class="kv-editor">
                    <div data-kv-rows="params.vars">
                        ${mockDesigner._configPanel._renderKvRow('params.vars', 0, 'award', 'Court Baronage')}
                    </div>
                </div>
            </form>
        `
        const select = document.querySelector('[data-kv-vtype]')
        select.value = 'context'

        handler.onKvValueTypeChange({ target: select })

        const valueInput = document.querySelector('[name="params.vars__val__0"]')
        expect(valueInput.getAttribute('data-variable-picker')).toBe('true')
        expect(valueInput.placeholder).toBe('Choose a workflow variable')
        expect(valueInput.value).toBe('')
        expect(attachPickers).toHaveBeenCalledWith(select.closest('.kv-row'), 'node-1', mockEditor)
    })

    test('refreshes template analysis when template variable mappings are saved', () => {
        const schema = [
            { name: 'award', type: 'string', required: true },
        ]
        const opt = makeOption({ value: '12', slug: 'feedback-request', isWorkflowNative: true, variablesSchema: schema })
        const nodeData = { data: { config: { params: {} } } }
        const mockEditor = {
            getNodeFromId: jest.fn(() => nodeData),
            updateNodeDataFromId: jest.fn((id, data) => { nodeData.data = data }),
        }
        const mockDesigner = {
            editor: mockEditor,
            _configPanel: new WorkflowConfigPanel({ triggers: [], actions: [], conditions: [], resolvers: [] }, []),
            _variablePicker: null,
            nodeConfigTarget: document.createElement('div'),
            hasNodeConfigTarget: true,
            _selectedNodes: new Set(),
            canvasTarget: document.createElement('div'),
            _shiftHeld: false,
            clearMultiSelect: jest.fn(),
        }
        const handler = new WorkflowNodeConfigHandler(mockDesigner)

        const selectMarkup = document.createElement('select')
        selectMarkup.dataset.emailTemplateSelect = 'true'
        selectMarkup.appendChild(opt)
        document.body.innerHTML = `
            <form data-node-id="node-1">
                <div class="mb-3">
                    ${selectMarkup.outerHTML}
                    <div class="email-template-analysis"></div>
                </div>
                <div class="kv-editor">
                    <div data-kv-rows="params.vars">
                        ${mockDesigner._configPanel._renderKvRow('params.vars', 0, 'award', '$.nodes.find-award.result.record.name')}
                    </div>
                </div>
            </form>
        `
        const form = document.querySelector('form')

        handler._renderTemplateAnalysis(document.querySelector('[data-email-template-select="true"]'), 'node-1')
        expect(document.querySelector('.email-template-analysis').innerHTML).toContain('alert-danger')

        handler._saveKvFieldsFromForm(form)

        expect(document.querySelector('.email-template-analysis').innerHTML).not.toContain('alert-danger')
        expect(document.querySelector('.email-template-analysis').innerHTML).toContain('bi-check-circle-fill text-success')
    })
})
