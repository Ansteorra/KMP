import '../../../assets/js/controllers/workflow-toolbar-controller.js'

const WorkflowToolbarController = window.Controllers['workflow-toolbar']

function makeController() {
    document.body.innerHTML = `
        <div class="workflow-toolbar">
            <h5>
                <span data-workflow-toolbar-target="workflowName">Original Workflow</span>
                <span class="badge bg-primary ms-2" data-workflow-toolbar-target="executionModeBadge">Durable</span>
            </h5>
            <div class="modal" id="workflow-metadata-modal">
                <form data-workflow-toolbar-target="metadataForm">
                    <div class="alert alert-danger d-none" data-workflow-toolbar-target="metadataStatus"></div>
                    <input name="name" value="Updated Workflow">
                    <input name="slug" value="updated-workflow">
                    <select name="execution_mode">
                        <option value="ephemeral" selected>Ephemeral</option>
                    </select>
                    <button type="submit" data-workflow-toolbar-target="metadataSaveBtn">Save Details</button>
                </form>
            </div>
        </div>
    `

    const controller = new WorkflowToolbarController()
    const toolbar = document.querySelector('.workflow-toolbar')
    controller.element = toolbar

    Object.defineProperty(controller, 'hasUpdateMetadataUrlValue', { value: true })
    Object.defineProperty(controller, 'updateMetadataUrlValue', { value: '/workflows/update-metadata/1' })
    Object.defineProperty(controller, 'csrfTokenValue', { value: 'csrf-token' })
    Object.defineProperty(controller, 'hasMetadataFormTarget', { value: true })
    Object.defineProperty(controller, 'metadataFormTarget', { value: toolbar.querySelector('[data-workflow-toolbar-target="metadataForm"]') })
    Object.defineProperty(controller, 'hasMetadataStatusTarget', { value: true })
    Object.defineProperty(controller, 'metadataStatusTarget', { value: toolbar.querySelector('[data-workflow-toolbar-target="metadataStatus"]') })
    Object.defineProperty(controller, 'hasMetadataSaveBtnTarget', { value: true })
    Object.defineProperty(controller, 'metadataSaveBtnTarget', { value: toolbar.querySelector('[data-workflow-toolbar-target="metadataSaveBtn"]') })
    Object.defineProperty(controller, 'hasWorkflowNameTarget', { value: true })
    Object.defineProperty(controller, 'workflowNameTarget', { value: toolbar.querySelector('[data-workflow-toolbar-target="workflowName"]') })
    Object.defineProperty(controller, 'hasExecutionModeBadgeTarget', { value: true })
    Object.defineProperty(controller, 'executionModeBadgeTarget', { value: toolbar.querySelector('[data-workflow-toolbar-target="executionModeBadge"]') })

    return { controller }
}

describe('WorkflowToolbarController metadata editor', () => {
    let hideMock

    beforeEach(() => {
        hideMock = jest.fn()
        window.bootstrap = {
            Modal: {
                getInstance: jest.fn(() => ({ hide: hideMock })),
            },
        }
        global.fetch = jest.fn()
    })

    afterEach(() => {
        document.body.innerHTML = ''
        jest.restoreAllMocks()
    })

    test('submits workflow metadata and updates the visible title and execution badge', async () => {
        const { controller } = makeController()
        fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                success: true,
                workflow: {
                    name: 'Updated Workflow',
                    executionMode: 'ephemeral',
                },
            }),
        })

        await controller.saveMetadata({ preventDefault: jest.fn() })

        expect(fetch).toHaveBeenCalledWith('/workflows/update-metadata/1', expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({
                'Content-Type': 'application/json',
                'X-CSRF-Token': 'csrf-token',
            }),
        }))
        expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual(expect.objectContaining({
            name: 'Updated Workflow',
            slug: 'updated-workflow',
            execution_mode: 'ephemeral',
        }))
        expect(controller.workflowNameTarget).toHaveTextContent('Updated Workflow')
        expect(controller.executionModeBadgeTarget).toHaveTextContent('Ephemeral')
        expect(controller.executionModeBadgeTarget).toHaveClass('bg-info')
        expect(hideMock).toHaveBeenCalled()
    })

    test('shows validation errors without closing the modal', async () => {
        const { controller } = makeController()
        fetch.mockResolvedValue({
            ok: false,
            json: () => Promise.resolve({
                success: false,
                reason: 'slug: Must be unique',
            }),
        })

        await controller.saveMetadata({ preventDefault: jest.fn() })

        expect(controller.metadataStatusTarget).toHaveTextContent('slug: Must be unique')
        expect(controller.metadataStatusTarget).not.toHaveClass('d-none')
        expect(hideMock).not.toHaveBeenCalled()
    })
})
