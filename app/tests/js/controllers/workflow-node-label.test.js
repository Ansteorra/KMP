import WorkflowConfigPanel from '../../../assets/js/controllers/workflow-config-panel.js'
import WorkflowNodeConfigHandler from '../../../assets/js/controllers/workflow-node-config-handler.js'
import WorkflowSerializer from '../../../assets/js/controllers/workflow-serializer.js'

describe('workflow node labels', () => {
    afterEach(() => {
        document.body.innerHTML = ''
    })

    test('config panel renders persisted node label metadata', () => {
        const panel = new WorkflowConfigPanel(
            { triggers: [], actions: [], conditions: [], resolvers: [] },
            []
        )

        document.body.innerHTML = panel.renderConfigHTML('12', {
            name: 'action-123',
            data: {
                type: 'action',
                label: 'Notify Crown',
                config: {},
            },
        }, null)

        const label = document.querySelector('label[for="workflow-node-label-12"]')
        const input = document.querySelector('#workflow-node-label-12')

        expect(label).toHaveTextContent('Label')
        expect(input.value).toBe('Notify Crown')
    })

    test('changing label saves node metadata and refreshes the canvas node title', () => {
        const serializer = new WorkflowSerializer({
            triggers: [],
            actions: [{ action: 'Core.SendEmail', label: 'Send Email' }],
            conditions: [],
            entities: [],
        })
        const nodeData = {
            name: 'action-123',
            data: {
                type: 'action',
                nodeKey: 'action-123',
                label: 'Send Email',
                config: { action: 'Core.SendEmail' },
            },
        }
        const editor = {
            getNodeFromId: jest.fn(() => nodeData),
            updateNodeDataFromId: jest.fn(),
        }
        const canvasTarget = document.createElement('div')
        canvasTarget.innerHTML = '<div id="node-7"><div class="drawflow_content_node"></div></div>'
        const nodeConfigTarget = document.createElement('div')
        const designer = {
            editor,
            _configPanel: null,
            _variablePicker: null,
            _serializer: serializer,
            nodeConfigTarget,
            hasNodeConfigTarget: true,
            canvasTarget,
            _selectedNodes: new Set(),
            _updateDirtyState: jest.fn(),
        }
        const handler = new WorkflowNodeConfigHandler(designer)

        document.body.innerHTML = '<form data-node-id="7"><input name="label" value="Notify Crown"></form>'
        const input = document.querySelector('[name="label"]')

        handler.updateNodeConfig({ target: input })

        expect(nodeData.data.label).toBe('Notify Crown')
        expect(nodeData.data.config._nodeLabel).toBe('Notify Crown')
        expect(editor.updateNodeDataFromId).toHaveBeenCalledWith('7', nodeData.data)
        expect(designer._updateDirtyState).toHaveBeenCalled()
        expect(canvasTarget.querySelector('.wf-node-title')).toHaveTextContent('Notify Crown')
    })

    test('imported workflow labels are available in node config metadata', () => {
        const serializer = new WorkflowSerializer({ triggers: [], actions: [], conditions: [], entities: [] })
        const addedNodes = []
        const editor = {
            clear: jest.fn(),
            addNode: jest.fn((name, inputs, outputs, x, y, className, data, html) => {
                addedNodes.push({ name, data, html })
                return addedNodes.length
            }),
        }

        serializer.importWorkflow(editor, {
            nodes: {
                lookupRecommendation: {
                    type: 'action',
                    label: 'Find recommendation',
                    config: { action: 'Core.GetObjectById' },
                    outputs: [],
                },
            },
        }, {})

        expect(addedNodes[0].data.label).toBe('Find recommendation')
        expect(addedNodes[0].data.config._nodeLabel).toBe('Find recommendation')
    })

    test('export uses runtime node label metadata without persisting it in config', () => {
        const serializer = new WorkflowSerializer({ triggers: [], actions: [], conditions: [], entities: [] })
        const editor = {
            export: () => ({
                drawflow: {
                    Home: {
                        data: {
                            1: {
                                name: 'action-123',
                                pos_x: 10,
                                pos_y: 20,
                                data: {
                                    type: 'action',
                                    config: {
                                        action: 'Core.GetObjectById',
                                        _nodeLabel: 'Find recommendation',
                                    },
                                },
                                outputs: {},
                            },
                        },
                    },
                },
            }),
        }

        const exported = serializer.exportWorkflow(editor)

        expect(exported.definition.nodes['action-123'].label).toBe('Find recommendation')
        expect(exported.definition.nodes['action-123'].config._nodeLabel).toBeUndefined()
    })

    test('node label HTML is escaped when rendered', () => {
        const serializer = new WorkflowSerializer({ triggers: [], actions: [], conditions: [], entities: [] })

        document.body.innerHTML = serializer.buildNodeHTML('action', 'action-1', {
            _nodeLabel: '<img src=x onerror=alert(1)>',
        })

        const title = document.querySelector('.wf-node-title')
        expect(title.textContent).toBe('<img src=x onerror=alert(1)>')
        expect(title.querySelector('img')).toBeNull()
    })
})
