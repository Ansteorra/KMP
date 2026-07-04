import WorkflowValidationService from '../../../assets/js/controllers/workflow-validation-service.js'
import WorkflowSerializer from '../../../assets/js/controllers/workflow-serializer.js'

function makeService(registryData = {}) {
    const serializer = new WorkflowSerializer(registryData)

    return new WorkflowValidationService(
        type => serializer.getNodePorts(type),
        registryData
    )
}

function drawflow(nodes) {
    return {
        drawflow: {
            Home: {
                data: nodes,
            },
        },
    }
}

function node(name, type, config = {}, outputs = {}, inputs = {}) {
    return {
        name,
        data: { type, config },
        outputs,
        inputs,
    }
}

const triggerOutput = target => ({
    output_1: { connections: [{ node: String(target) }] },
})

const inputFrom = source => ({
    input_1: { connections: [{ node: String(source) }] },
})

describe('WorkflowValidationService', () => {
    test('rejects an empty workflow', () => {
        const result = makeService().validate(drawflow({}))

        expect(result.valid).toBe(false)
        expect(result.errors).toContain('Workflow is empty — add at least one node.')
    })

    test('requires exactly one trigger and at least one end node', () => {
        const result = makeService().validate(drawflow({
            1: node('action-1', 'action'),
        }))

        expect(result.valid).toBe(false)
        expect(result.errors).toContain('A workflow must have exactly one trigger node.')
        expect(result.errors).toContain('At least one End node is required.')
    })

    test('rejects multiple trigger nodes', () => {
        const result = makeService().validate(drawflow({
            1: node('trigger-1', 'trigger', {}, triggerOutput(3)),
            2: node('trigger-2', 'trigger', {}, triggerOutput(3)),
            3: node('end-1', 'end', {}, {}, inputFrom(1)),
        }))

        expect(result.valid).toBe(false)
        expect(result.errors).toContain('Found 2 trigger nodes — only one is allowed.')
    })

    test('rejects loop and forEach nodes without required config', () => {
        const result = makeService().validate(drawflow({
            1: node('trigger', 'trigger', {}, triggerOutput(2)),
            2: node('loop', 'loop', {}, { output_2: { connections: [{ node: '3' }] } }, inputFrom(1)),
            3: node('for-each', 'forEach', {}, { output_2: { connections: [{ node: '4' }] } }, inputFrom(2)),
            4: node('end', 'end', {}, {}, inputFrom(3)),
        }))

        expect(result.valid).toBe(false)
        expect(result.errors).toContain('Loop node #2 must have maxIterations set to a positive number.')
        expect(result.errors).toContain('ForEach node #3 must have a collection path configured.')
    })

    test('reports nodes with missing incoming connections and unreachable subgraphs', () => {
        const result = makeService().validate(drawflow({
            1: node('trigger', 'trigger', {}, triggerOutput(2)),
            2: node('end', 'end', {}, {}, inputFrom(1)),
            3: node('orphan-action', 'action', {}, triggerOutput(4)),
            4: node('orphan-end', 'end', {}, {}, inputFrom(3)),
        }))

        expect(result.valid).toBe(false)
        expect(result.errors).toContain('Node "orphan-action" (#3) has no incoming connection.')
        expect(result.errors.join(' ')).toContain('not connected to the trigger')
    })

    test('warns when a node with outputs has no outgoing connection', () => {
        const result = makeService().validate(drawflow({
            1: node('trigger', 'trigger'),
            2: node('end', 'end'),
        }))

        expect(result.warnings).toContain('Node "trigger" (#1) has no outgoing connection.')
    })

    test('validates action and condition required registry parameters', () => {
        const service = makeService({
            actions: [{
                action: 'Core.SendEmail',
                inputSchema: {
                    recipient: { required: true },
                    hiddenToken: { required: true, hidden: true },
                },
            }],
            conditions: [{
                condition: 'Awards.IsEligible',
                inputSchema: {
                    memberId: { required: true },
                    hiddenField: { required: true, visible: false },
                },
            }],
        })

        const result = service.validate(drawflow({
            1: node('trigger', 'trigger', {}, triggerOutput(2)),
            2: node('email', 'action', { action: 'Core.SendEmail', params: {} }, triggerOutput(3), inputFrom(1)),
            3: node('eligible', 'condition', { condition: 'Awards.IsEligible', params: {} }, {
                output_1: { connections: [{ node: '4' }] },
                output_2: { connections: [{ node: '4' }] },
            }, inputFrom(2)),
            4: node('end', 'end', {}, {}, inputFrom(3)),
        }))

        expect(result.valid).toBe(false)
        expect(result.errors).toContain('Action node "email" (#2): required parameter \'recipient\' is not configured.')
        expect(result.errors).toContain('Condition node "eligible" (#3): required parameter \'memberId\' is not configured.')
        expect(result.errors.join(' ')).not.toContain('hiddenToken')
        expect(result.errors.join(' ')).not.toContain('hiddenField')
    })

    test('rejects unknown action and condition references', () => {
        const result = makeService({ actions: [], conditions: [] }).validate(drawflow({
            1: node('trigger', 'trigger', {}, triggerOutput(2)),
            2: node('action', 'action', { action: 'Missing.Action' }, triggerOutput(3), inputFrom(1)),
            3: node('condition', 'condition', { condition: 'Missing.Condition' }, {
                output_1: { connections: [{ node: '4' }] },
                output_2: { connections: [{ node: '4' }] },
            }, inputFrom(2)),
            4: node('end', 'end', {}, {}, inputFrom(3)),
        }))

        expect(result.valid).toBe(false)
        expect(result.errors).toContain("Action node \"action\" (#2): references unknown action 'Missing.Action'.")
        expect(result.errors).toContain("Condition node \"condition\" (#3): references unknown condition 'Missing.Condition'.")
    })

    test('accepts a valid connected workflow', () => {
        const result = makeService({
            actions: [{
                action: 'Core.SendEmail',
                inputSchema: { recipient: { required: true } },
            }],
        }).validate(drawflow({
            1: node('trigger', 'trigger', {}, triggerOutput(2)),
            2: node('email', 'action', {
                action: 'Core.SendEmail',
                params: { recipient: 'test@example.com' },
            }, triggerOutput(3), inputFrom(1)),
            3: node('end', 'end', {}, {}, inputFrom(2)),
        }))

        expect(result).toEqual({ valid: true, errors: [], warnings: [] })
    })
})
