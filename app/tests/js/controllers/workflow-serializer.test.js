import WorkflowSerializer from '../../../assets/js/controllers/workflow-serializer.js'

function makeSerializer() {
    return new WorkflowSerializer({
        triggers: [{ event: 'Members.Registered', label: 'Member Registered' }],
        actions: [{ action: 'Core.SendEmail', label: 'Send Email' }],
        conditions: [],
        entities: [],
    })
}

describe('WorkflowSerializer', () => {
    test('returns expected ports for supported node types', () => {
        const serializer = makeSerializer()

        expect(serializer.getNodePorts('trigger')).toEqual({ inputs: 0, outputs: 1 })
        expect(serializer.getNodePorts('condition')).toEqual({ inputs: 1, outputs: 2 })
        expect(serializer.getNodePorts('approval')).toEqual({ inputs: 1, outputs: 3 })
        expect(serializer.getNodePorts('fork')).toEqual({ inputs: 1, outputs: 2 })
        expect(serializer.getNodePorts('forEach')).toEqual({ inputs: 1, outputs: 3 })
        expect(serializer.getNodePorts('end')).toEqual({ inputs: 1, outputs: 0 })
        expect(serializer.getNodePorts('unknown')).toEqual({ inputs: 1, outputs: 1 })
        expect(serializer.getNodePorts('action', [
            { port: 'next' },
            { port: 'error' },
        ])).toEqual({ inputs: 1, outputs: 2 })
    })

    test('returns semantic output port labels', () => {
        const serializer = makeSerializer()

        expect(serializer.getPortLabel('condition', 1)).toBe('true')
        expect(serializer.getPortLabel('condition', 2)).toBe('false')
        expect(serializer.getPortLabel('approval', 3)).toBe('on_each_approval')
        expect(serializer.getPortLabel('fork', 4)).toBe('path-4')
        expect(serializer.getPortLabel('action', 3)).toBe('output-3')
    })

    test('builds accessible node HTML using registry labels and escaped custom labels', () => {
        const serializer = makeSerializer()

        document.body.innerHTML = serializer.buildNodeHTML('trigger', 'trigger-1', {
            event: 'Members.Registered',
            _nodeLabel: '<strong>Custom Trigger</strong>',
        })

        expect(document.querySelector('.wf-node')).toHaveClass('wf-node-trigger')
        expect(document.querySelector('.wf-node-title')).toHaveTextContent('<strong>Custom Trigger</strong>')
        expect(document.querySelector('.wf-node-title strong')).toBeNull()
        expect(document.querySelector('.wf-node-type-label')).toHaveTextContent('Trigger')
    })

    test('exports drawflow data to workflow definition and layout', () => {
        const serializer = makeSerializer()
        const editor = {
            export: () => ({
                drawflow: {
                    Home: {
                        data: {
                            1: {
                                name: 'trigger-1',
                                pos_x: 10,
                                pos_y: 20,
                                data: {
                                    type: 'trigger',
                                    nodeKey: 'trigger',
                                    label: 'Start',
                                    config: { event: 'Members.Registered', _nodeLabel: 'Start' },
                                },
                                outputs: {
                                    output_1: { connections: [{ node: '2' }] },
                                },
                            },
                            2: {
                                name: 'end-1',
                                pos_x: 300,
                                pos_y: 20,
                                data: { type: 'end', nodeKey: 'end', config: {} },
                                outputs: {},
                            },
                        },
                    },
                },
            }),
        }

        const exported = serializer.exportWorkflow(editor)

        expect(exported.definition.nodes.trigger).toEqual({
            type: 'trigger',
            label: 'Start',
            config: { event: 'Members.Registered' },
            outputs: [{ port: 'default', target: 'end' }],
        })
        expect(exported.definition.$schema).toBe('./schema.json')
        expect(exported.definition.schemaVersion).toBe('1.0')
        expect(exported.canvasLayout.trigger).toEqual({ x: 10, y: 20, drawflowId: 1 })
    })

    test('preserves workflow definition metadata across import and export', () => {
        const serializer = makeSerializer()
        const editor = {
            clear: jest.fn(),
            addNode: jest.fn(),
            addConnection: jest.fn(),
        }

        serializer.importWorkflow(editor, {
            '$schema': '/custom/workflow-schema.json',
            schemaVersion: '1.0',
            startNode: 'trigger-feedback',
            nodes: {},
        }, {})

        const exported = serializer.exportWorkflow({
            export: () => ({ drawflow: { Home: { data: {} } } }),
        })

        expect(exported.definition).toEqual({
            '$schema': '/custom/workflow-schema.json',
            schemaVersion: '1.0',
            startNode: 'trigger-feedback',
            nodes: {},
        })
    })

    test('defaults missing legacy metadata without replacing explicit values', () => {
        const serializer = makeSerializer()
        const editor = {
            clear: jest.fn(),
            addNode: jest.fn(),
            addConnection: jest.fn(),
        }
        const emptyCanvas = {
            export: () => ({ drawflow: { Home: { data: {} } } }),
        }

        serializer.importWorkflow(editor, { nodes: {} }, {})
        expect(serializer.exportWorkflow(emptyCanvas).definition).toEqual({
            '$schema': './schema.json',
            schemaVersion: '1.0',
            nodes: {},
        })

        serializer.importWorkflow(editor, {
            '$schema': null,
            schemaVersion: '2.0',
            nodes: {},
        }, {})
        expect(serializer.exportWorkflow(emptyCanvas).definition).toEqual({
            '$schema': null,
            schemaVersion: '2.0',
            nodes: {},
        })
    })

    test('imports workflow definitions with layout and connections', () => {
        const serializer = makeSerializer()
        const added = []
        const editor = {
            clear: jest.fn(),
            addNode: jest.fn((name, inputs, outputs, x, y, className, data, html) => {
                added.push({ name, inputs, outputs, x, y, className, data, html })
                return added.length
            }),
            addConnection: jest.fn(),
        }

        serializer.importWorkflow(editor, {
            nodes: {
                trigger: {
                    type: 'trigger',
                    label: 'Start',
                    config: { event: 'Members.Registered' },
                    outputs: [{ target: 'end' }],
                },
                end: {
                    type: 'end',
                    label: 'Done',
                    config: {},
                    outputs: [],
                },
            },
        }, {
            trigger: { x: 25, y: 50 },
            end: { x: 325, y: 50 },
        })

        expect(editor.clear).toHaveBeenCalled()
        expect(added[0]).toEqual(expect.objectContaining({
            name: 'trigger',
            inputs: 0,
            outputs: 1,
            x: 25,
            y: 50,
        }))
        expect(added[0].data.config._nodeLabel).toBe('Start')
        expect(editor.addConnection).toHaveBeenCalledWith(1, 2, 'output_1', 'input_1')
    })

    test('round-trips action success and error routes by semantic port', () => {
        const serializer = makeSerializer()
        const moduleData = {}
        let nextId = 1
        const editor = {
            clear: jest.fn(),
            addNode: jest.fn((name, inputs, outputs, x, y, className, data, html) => {
                const id = nextId++
                const drawflowOutputs = {}
                for (let index = 1; index <= outputs; index++) {
                    drawflowOutputs[`output_${index}`] = { connections: [] }
                }
                moduleData[id] = { name, inputs, outputs: drawflowOutputs, pos_x: x, pos_y: y, data, html }

                return id
            }),
            addConnection: jest.fn((sourceId, targetId, outputKey) => {
                const sourceOutput = moduleData[sourceId].outputs[outputKey]
                if (!sourceOutput) throw new Error(`Missing ${outputKey}`)
                sourceOutput.connections.push({ node: String(targetId) })
            }),
            export: () => ({ drawflow: { Home: { data: moduleData } } }),
        }

        expect(() => serializer.importWorkflow(editor, {
            nodes: {
                action: {
                    type: 'action',
                    label: 'Send Email',
                    config: { action: 'Core.SendEmail' },
                    position: { x: 100, y: 200 },
                    outputs: [
                        { port: 'error', target: 'failed' },
                        { port: 'next', target: 'complete' },
                    ],
                },
                complete: { type: 'end', label: 'Complete', config: {}, outputs: [] },
                failed: { type: 'end', label: 'Failed', config: {}, outputs: [] },
            },
        }, {})).not.toThrow()

        expect(editor.addNode).toHaveBeenNthCalledWith(
            1,
            'action', 1, 2, 100, 200,
            expect.any(String),
            expect.objectContaining({ outputPortLabels: ['next', 'error'] }),
            expect.any(String)
        )
        expect(editor.addConnection).toHaveBeenCalledWith(1, 3, 'output_2', 'input_1')
        expect(editor.addConnection).toHaveBeenCalledWith(1, 2, 'output_1', 'input_1')

        document.body.innerHTML = moduleData[1].html
        const renderedPortLabels = Array.from(document.querySelectorAll('.wf-port-label'))
            .map(label => label.textContent)
        expect(renderedPortLabels).toEqual(['next', 'error'])

        const exported = serializer.exportWorkflow(editor)
        expect(exported.definition.nodes.action.outputs).toEqual([
            { port: 'next', target: 'complete' },
            { port: 'error', target: 'failed' },
        ])
    })

    test('escapes imported dynamic output labels in node markup', () => {
        const serializer = makeSerializer()
        const addedNodeHtml = []
        const editor = {
            clear: jest.fn(),
            addNode: jest.fn((name, inputs, outputs, x, y, className, data, html) => {
                addedNodeHtml.push(html)
                return addedNodeHtml.length
            }),
            addConnection: jest.fn(),
        }

        serializer.importWorkflow(editor, {
            nodes: {
                action: {
                    type: 'action',
                    label: 'Action',
                    config: {},
                    outputs: [
                        { port: 'next', target: 'complete' },
                        { port: '<img src=x onerror=alert(1)>', target: 'failed' },
                    ],
                },
                complete: { type: 'end', label: 'Complete', config: {}, outputs: [] },
                failed: { type: 'end', label: 'Failed', config: {}, outputs: [] },
            },
        }, {})

        document.body.innerHTML = addedNodeHtml[0]
        expect(Array.from(document.querySelectorAll('.wf-port-label'))
            .map(label => label.textContent)).toEqual([
                'next',
                '<img src=x onerror=alert(1)>',
            ])
        expect(document.querySelector('.wf-port-label img')).toBeNull()
    })

    test('computes deterministic auto-layout layers from output targets', () => {
        const serializer = makeSerializer()

        const positions = serializer.computeAutoLayout({
            nodes: {
                trigger: { outputs: [{ target: 'action' }] },
                action: { outputs: [{ target: 'end' }] },
                end: { outputs: [] },
            },
        })

        expect(positions.trigger.y).toBeLessThan(positions.action.y)
        expect(positions.action.y).toBeLessThan(positions.end.y)
        expect(positions.trigger.x).toBeGreaterThanOrEqual(80)
    })
})
