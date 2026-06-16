import '../../../assets/js/controllers/workflow-designer-controller.js'

const WorkflowDesignerController = window.Controllers['workflow-designer']

function makeController() {
    const controller = new WorkflowDesignerController()
    const nodePaletteTarget = document.createElement('div')
    nodePaletteTarget.dataset.workflowDesignerTarget = 'nodePalette'
    document.body.appendChild(nodePaletteTarget)

    Object.defineProperty(controller, 'hasNodePaletteTarget', { value: true })
    Object.defineProperty(controller, 'nodePaletteTarget', { value: nodePaletteTarget })
    controller.registryData = {
        triggers: [
            {
                event: 'Awards.RecommendationFeedbackRequested',
                label: 'Recommendation Feedback Requested',
                source: 'Awards',
            },
        ],
        actions: [
            {
                action: 'Core.SendEmail',
                label: 'Send Email',
                source: 'Core',
            },
        ],
        conditions: [
            {
                condition: 'Members.HasPermission',
                label: 'Member Has Permission',
                source: 'Members',
            },
        ],
    }

    return { controller, nodePaletteTarget }
}

describe('WorkflowDesignerController palette filter', () => {
    afterEach(() => {
        document.body.innerHTML = ''
        jest.restoreAllMocks()
    })

    test('renders an accessible quick filter above the node catalog', () => {
        const { controller, nodePaletteTarget } = makeController()

        controller.populateNodePalette()

        const filter = nodePaletteTarget.querySelector('#workflow-palette-filter')
        const label = nodePaletteTarget.querySelector('label[for="workflow-palette-filter"]')
        const status = nodePaletteTarget.querySelector('[data-palette-filter-status]')

        expect(filter).not.toBeNull()
        expect(label).toHaveTextContent('Filter nodes')
        expect(filter).toHaveAttribute('type', 'search')
        expect(filter).toHaveAccessibleName('Filter nodes')
        expect(status).toHaveAttribute('role', 'status')
        expect(status).toHaveTextContent('11 nodes available.')
    })

    test('filters nodes by label and hides empty categories', () => {
        const { controller, nodePaletteTarget } = makeController()
        controller.populateNodePalette()
        const filter = nodePaletteTarget.querySelector('#workflow-palette-filter')

        filter.value = 'feedback'
        controller.filterNodePalette({ target: filter })

        const visibleNodes = Array.from(nodePaletteTarget.querySelectorAll('[data-palette-node]'))
            .filter(node => !node.hidden)
        const visibleCategories = Array.from(nodePaletteTarget.querySelectorAll('[data-palette-category]'))
            .filter(category => !category.hidden)

        expect(visibleNodes).toHaveLength(1)
        expect(visibleNodes[0]).toHaveTextContent('Recommendation Feedback Requested')
        expect(visibleCategories).toHaveLength(1)
        expect(visibleCategories[0]).toHaveTextContent('Triggers — Awards')
        expect(nodePaletteTarget.querySelector('[data-palette-filter-status]'))
            .toHaveTextContent('1 of 11 nodes match.')
    })

    test('shows an empty state when no nodes match', () => {
        const { controller, nodePaletteTarget } = makeController()
        controller.populateNodePalette()
        const filter = nodePaletteTarget.querySelector('#workflow-palette-filter')

        filter.value = 'not-a-real-node'
        controller.filterNodePalette({ target: filter })

        expect(nodePaletteTarget.querySelector('[data-palette-empty-message]')).not.toHaveAttribute('hidden')
        expect(nodePaletteTarget.querySelector('[data-palette-filter-status]'))
            .toHaveTextContent('0 of 11 nodes match.')
    })

    test('palette nodes are keyboard-operable buttons that add nodes to the canvas', () => {
        const { controller, nodePaletteTarget } = makeController()
        const status = document.createElement('div')
        status.id = 'workflow-palette-add-status'
        document.body.appendChild(status)
        const canvasTarget = document.createElement('div')
        Object.defineProperty(canvasTarget, 'getBoundingClientRect', {
            value: () => ({ width: 600, height: 400, left: 0, top: 0 })
        })
        Object.defineProperty(controller, 'hasCanvasTarget', { value: true })
        Object.defineProperty(controller, 'canvasTarget', { value: canvasTarget })
        controller.element = document.body
        controller.editor = { canvas_x: 0, canvas_y: 0 }
        controller._zoom = 1
        controller.addNode = jest.fn()

        controller.populateNodePalette()
        const node = nodePaletteTarget.querySelector('[data-node-type="approval"]')
        controller.onPaletteNodeKeydown({ key: 'Enter', preventDefault: jest.fn(), currentTarget: node })

        expect(node.tagName).toBe('BUTTON')
        expect(controller.addNode).toHaveBeenCalledWith('approval', 300, 200, { event: '', action: '' })
        expect(status).toHaveTextContent('Approval Gate node added to the canvas.')
    })

    test('waits for editor initialization before loading workflow data', async () => {
        const controller = new WorkflowDesignerController()
        const callOrder = []
        let resolveInit
        const initPromise = new Promise(resolve => {
            resolveInit = resolve
        })

        controller._showLoading = jest.fn()
        controller._showError = jest.fn()
        controller.initEditor = jest.fn(() => {
            callOrder.push('init-start')
            return initPromise.then(() => {
                controller.editor = { export: () => ({ drawflow: { Home: { data: {} } } }) }
                controller._nodeConfigHandler = { restoreConfigPanelWidth: jest.fn() }
                callOrder.push('init-done')
            })
        })
        controller.loadRegistry = jest.fn(async () => {
            callOrder.push('registry')
        })
        controller.loadWorkflow = jest.fn(async () => {
            callOrder.push('workflow')
        })
        Object.defineProperty(controller, 'hasWorkflowIdValue', { value: true })
        Object.defineProperty(controller, 'workflowIdValue', { value: 42 })

        const connectPromise = controller.connect()
        await Promise.resolve()

        expect(controller.loadRegistry).not.toHaveBeenCalled()

        resolveInit()
        await connectPromise

        expect(callOrder).toEqual(['init-start', 'init-done', 'registry', 'workflow'])
        expect(controller._showError).not.toHaveBeenCalled()
    })
})
