import WorkflowConfigPanel from '../../../assets/js/controllers/workflow-config-panel.js'
import WorkflowNodeConfigHandler from '../../../assets/js/controllers/workflow-node-config-handler.js'
import WorkflowValidationService from '../../../assets/js/controllers/workflow-validation-service.js'

describe('workflow hidden schema fields', () => {
    afterEach(() => {
        document.body.innerHTML = ''
    })

    test('config panel does not render action parameters marked hidden', () => {
        const panel = new WorkflowConfigPanel({
            triggers: [],
            actions: [{
                action: 'Awards.CreateFeedbackApproval',
                label: 'Create Feedback Approval',
                inputSchema: {
                    recipientId: { type: 'integer', label: 'Recipient Member ID' },
                    nodeId: { type: 'string', label: 'Action Node ID', hidden: true },
                    internalVisibleFalse: { type: 'string', label: 'Internal', visible: false },
                },
            }],
            conditions: [],
            resolvers: [],
        })

        document.body.innerHTML = panel.renderConfigHTML('7', {
            data: {
                type: 'action',
                config: {
                    action: 'Awards.CreateFeedbackApproval',
                },
            },
        })

        expect(document.body).toHaveTextContent('Recipient Member ID')
        expect(document.body).not.toHaveTextContent('Action Node ID')
        expect(document.querySelector('[name="params.nodeId"]')).toBeNull()
        expect(document.querySelector('[name="params.internalVisibleFalse"]')).toBeNull()
    })

    test('validation ignores required hidden action parameters', () => {
        const validator = new WorkflowValidationService(() => ({ inputs: 0, outputs: 0 }), {
            actions: [{
                action: 'Awards.CreateFeedbackApproval',
                inputSchema: {
                    nodeId: { type: 'string', label: 'Action Node ID', required: true, hidden: true },
                    recipientId: { type: 'integer', label: 'Recipient Member ID', required: true },
                },
            }],
            conditions: [],
        })

        const result = validator.validate({
            drawflow: {
                Home: {
                    data: {
                        1: {
                            name: 'Create Feedback Approval',
                            data: {
                                type: 'action',
                                config: {
                                    action: 'Awards.CreateFeedbackApproval',
                                    params: {},
                                },
                            },
                            inputs: {},
                            outputs: {},
                        },
                    },
                },
            },
        })

        expect(result.errors.some(error => error.includes('nodeId'))).toBe(false)
        expect(result.errors.some(error => error.includes('recipientId'))).toBe(true)
    })

    test('config panel renders option array fields as editable value and label rows', () => {
        const panel = new WorkflowConfigPanel({
            triggers: [],
            actions: [{
                action: 'Awards.CreateFeedbackApproval',
                label: 'Create Feedback Approval',
                inputSchema: {
                    decisionOptions: {
                        type: 'array',
                        label: 'Decision Options',
                        editor: 'options',
                        description: 'Choices shown to feedback recipients.',
                    },
                    decisionPromptLabel: {
                        type: 'string',
                        label: 'Decision Prompt Label',
                    },
                },
            }],
            conditions: [],
            resolvers: [],
        })

        document.body.innerHTML = panel.renderConfigHTML('7', {
            data: {
                type: 'action',
                config: {
                    action: 'Awards.CreateFeedbackApproval',
                    params: {
                        decisionOptions: [
                            { value: 'support', label: 'Support' },
                            { value: 'oppose', label: 'Oppose' },
                        ],
                    },
                },
            },
        })

        expect(document.body).toHaveTextContent('Decision Options')
        expect(document.body).toHaveTextContent('Decision Prompt Label')
        expect(document.body).toHaveTextContent('Choices shown to feedback recipients.')
        expect(document.body.textContent.match(/Choices shown to feedback recipients\./g)).toHaveLength(1)
        expect(document.querySelectorAll('.option-array-row')).toHaveLength(2)
        expect(document.querySelector('[name="params.decisionOptions__option_value__0"]').value).toBe('support')
        expect(document.querySelector('[name="params.decisionOptions__option_label__1"]').value).toBe('Oppose')
    })

    test('option array rows save as value and label objects', () => {
        const nodeData = {
            data: {
                type: 'action',
                config: {
                    action: 'Awards.CreateFeedbackApproval',
                    params: {},
                },
            },
        }
        const editor = {
            getNodeFromId: jest.fn(() => nodeData),
            updateNodeDataFromId: jest.fn(),
        }
        const designer = {
            editor,
            _configPanel: new WorkflowConfigPanel({ triggers: [], actions: [], conditions: [], resolvers: [] }),
            _variablePicker: null,
            nodeConfigTarget: document.createElement('div'),
            hasNodeConfigTarget: true,
            _updateDirtyState: jest.fn(),
        }
        const handler = new WorkflowNodeConfigHandler(designer)

        document.body.innerHTML = `
            <form data-node-id="7">
                <div class="option-array-editor">
                    <div data-array-rows="params.decisionOptions">
                        <div class="option-array-row">
                            <input name="params.decisionOptions__option_value__0" value="support">
                            <input name="params.decisionOptions__option_label__0" value="Support">
                        </div>
                        <div class="option-array-row">
                            <input name="params.decisionOptions__option_value__1" value="oppose">
                            <input name="params.decisionOptions__option_label__1" value="Oppose">
                        </div>
                    </div>
                </div>
            </form>`

        handler.updateNodeConfig({ target: document.querySelector('[name$="__option_label__1"]') })

        expect(nodeData.data.config.params.decisionOptions).toEqual([
            { value: 'support', label: 'Support' },
            { value: 'oppose', label: 'Oppose' },
        ])
        expect(editor.updateNodeDataFromId).toHaveBeenCalledWith('7', nodeData.data)
    })
})
