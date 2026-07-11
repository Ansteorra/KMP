import '../../../assets/js/controllers/guarded-action-modal-controller.js'

const GuardedActionModalController = window.Controllers['guarded-action-modal']
const modalInstance = {
    show: jest.fn(),
    hide: jest.fn(),
    dispose: jest.fn(),
}

describe('GuardedActionModalController', () => {
    let controller
    let trigger

    beforeEach(() => {
        jest.clearAllMocks()
        jest.useFakeTimers()
        window.bootstrap.Modal = jest.fn(() => modalInstance)
        document.body.innerHTML = `
            <section data-controller="guarded-action-modal">
                <button
                    type="button"
                    data-action="guarded-action-modal#open"
                    data-guarded-template-id="download-template"
                    data-guarded-modal-title="Download backup"
                >Download</button>
                <template id="download-template">
                    <form action="/platform-admin/backups/one/download"
                          data-expected-confirmation="DOWNLOAD platform">
                        <input name="confirmation" data-guarded-action-initial-focus>
                        <textarea name="reason"></textarea>
                        <input name="totp">
                        <button type="submit">Download backup</button>
                    </form>
                </template>
                <div class="modal"
                     data-guarded-action-modal-target="modal"
                     aria-labelledby="modal-title">
                    <h2 id="modal-title" data-guarded-action-modal-target="title"></h2>
                    <div data-guarded-action-modal-target="content"></div>
                </div>
            </section>
        `

        controller = new GuardedActionModalController()
        controller.element = document.querySelector('[data-controller="guarded-action-modal"]')
        controller.modalTarget = document.querySelector('[data-guarded-action-modal-target="modal"]')
        controller.contentTarget = document.querySelector('[data-guarded-action-modal-target="content"]')
        controller.titleTarget = document.querySelector('[data-guarded-action-modal-target="title"]')
        trigger = controller.element.querySelector('button')
        controller.initialize()
        controller.connect()
    })

    afterEach(() => {
        controller.disconnect()
        document.body.innerHTML = ''
        jest.useRealTimers()
        jest.restoreAllMocks()
    })

    test('registers the controller and expected targets', () => {
        expect(window.Controllers['guarded-action-modal']).toBe(GuardedActionModalController)
        expect(GuardedActionModalController.targets).toEqual(['modal', 'content', 'title'])
    })

    test('opens the selected server-rendered form in the shared modal', () => {
        controller.open({ preventDefault: jest.fn(), currentTarget: trigger })

        expect(controller.titleTarget).toHaveTextContent('Download backup')
        expect(controller.contentTarget.querySelector('form')).toHaveAttribute(
            'action',
            '/platform-admin/backups/one/download'
        )
        expect(modalInstance.show).toHaveBeenCalled()
    })

    test('moves focus into the form and returns it to the trigger', () => {
        controller.open({ preventDefault: jest.fn(), currentTarget: trigger })
        const confirmation = controller.contentTarget.querySelector('[name="confirmation"]')
        confirmation.focus = jest.fn()
        trigger.focus = jest.fn()

        controller.modalTarget.dispatchEvent(new Event('shown.bs.modal'))
        expect(confirmation.focus).toHaveBeenCalled()

        controller.modalTarget.dispatchEvent(new Event('hidden.bs.modal'))
        expect(trigger.focus).toHaveBeenCalledWith({ preventScroll: true })
        expect(controller.contentTarget).toBeEmptyDOMElement()
    })

    test('rejects an inexact typed confirmation and announces the error', () => {
        controller.open({ preventDefault: jest.fn(), currentTarget: trigger })
        const form = controller.contentTarget.querySelector('form')
        const confirmation = form.elements.namedItem('confirmation')
        confirmation.value = 'DOWNLOAD'
        confirmation.reportValidity = jest.fn()
        confirmation.focus = jest.fn()
        const event = new Event('submit', { bubbles: true, cancelable: true })

        form.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(true)
        expect(confirmation.reportValidity).toHaveBeenCalled()
        expect(confirmation.focus).toHaveBeenCalled()
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith(
            'Type "DOWNLOAD platform" exactly to continue.',
            { assertive: true }
        )
        expect(modalInstance.hide).not.toHaveBeenCalled()
    })

    test('hides the modal after an approved form is submitted', () => {
        controller.open({ preventDefault: jest.fn(), currentTarget: trigger })
        const form = controller.contentTarget.querySelector('form')
        form.elements.namedItem('confirmation').value = 'DOWNLOAD platform'
        const event = new Event('submit', { bubbles: true, cancelable: true })

        form.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(false)
        expect(modalInstance.hide).toHaveBeenCalled()

        controller.modalTarget.dispatchEvent(new Event('hidden.bs.modal'))
        jest.runOnlyPendingTimers()
        expect(controller.contentTarget).toBeEmptyDOMElement()
    })

    test('announces a missing or out-of-scope template', () => {
        trigger.dataset.guardedTemplateId = 'missing-template'

        controller.open({ preventDefault: jest.fn(), currentTarget: trigger })

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith(
            'The approval form could not be opened.',
            { assertive: true }
        )
        expect(modalInstance.show).not.toHaveBeenCalled()
    })

    test('disconnect removes listeners and disposes the modal', () => {
        const removeModalListener = jest.spyOn(controller.modalTarget, 'removeEventListener')
        const removeContentListener = jest.spyOn(controller.contentTarget, 'removeEventListener')

        controller.disconnect()

        expect(removeModalListener).toHaveBeenCalledWith('shown.bs.modal', controller.boundHandleShown)
        expect(removeModalListener).toHaveBeenCalledWith('hidden.bs.modal', controller.boundHandleHidden)
        expect(removeContentListener).toHaveBeenCalledWith('submit', controller.boundHandleSubmit)
        expect(modalInstance.dispose).toHaveBeenCalled()
        controller.modalInstance = null
    })
})
