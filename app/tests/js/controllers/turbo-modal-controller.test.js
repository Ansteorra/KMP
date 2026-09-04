import '../../../assets/js/controllers/turbo-modal-controller.js';
const TurboModal = window.Controllers['turbo-modal'];

describe('TurboModalController', () => {
    let controller;
    let originalFetch;
    let originalIntersectionObserver;
    let originalAccessibility;

    beforeEach(() => {
        originalFetch = global.fetch;
        originalIntersectionObserver = global.IntersectionObserver;
        originalAccessibility = window.KMP_accessibility;
        window.KMP_accessibility = {
            ...(window.KMP_accessibility || {}),
            announce: jest.fn(),
        };
        document.body.innerHTML = `
            <div class="modal" id="testModal">
                <form data-controller="turbo-modal"
                      data-turbo-modal-success-message-value="Your attendance has been registered."
                      action="http://localhost/awards/recommendations/edit/594"
                      method="post"
                      data-action="submit->turbo-modal#submitAsTurboStream turbo:submit-start->turbo-modal#closeModalBeforeSubmit">
                    <div class="d-none" data-turbo-modal-feedback></div>
                    <input type="text" name="field" value="test">
                    <input type="hidden" name="page_context_url" value="/awards/recommendations/turbo-edit-form/594">
                    <button type="submit">Submit</button>
                </form>
            </div>
        `;

        controller = new TurboModal();
        controller.initialize();
        controller.element = document.querySelector('[data-controller="turbo-modal"]');
    });

    afterEach(() => {
        jest.useRealTimers();
        document.body.innerHTML = '';
        global.fetch = originalFetch;
        global.IntersectionObserver = originalIntersectionObserver;
        window.KMP_accessibility = originalAccessibility;
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['turbo-modal']).toBe(TurboModal);
    });

    test('connect logs connection', () => {
        controller.connect();
        expect(console.log).toHaveBeenCalledWith('TurboModal controller connected');
    });

    test('closeModalBeforeSubmit hides modal when modal instance exists', () => {
        const hideMock = jest.fn();
        window.bootstrap.Modal = {
            getInstance: jest.fn(() => ({ hide: hideMock })),
            getOrCreateInstance: jest.fn(() => ({ hide: hideMock })),
        };

        controller.closeModalBeforeSubmit({ target: controller.element });

        expect(window.bootstrap.Modal.getInstance).toHaveBeenCalledWith(
            document.getElementById('testModal')
        );
        expect(hideMock).toHaveBeenCalled();
    });

    test('closeModalBeforeSubmit handles no modal instance', () => {
        window.bootstrap.Modal = {
            getInstance: jest.fn(() => null)
        };

        expect(() => {
            controller.closeModalBeforeSubmit({ target: controller.element });
        }).not.toThrow();
    });

    test('closeModal finds modal nested inside the form', () => {
        const hideMock = jest.fn();
        window.bootstrap.Modal = {
            getInstance: jest.fn(() => null),
            getOrCreateInstance: jest.fn(() => ({ hide: hideMock })),
        };
        const form = document.createElement('form');
        form.setAttribute('data-controller', 'turbo-modal');
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'editOfficerModal';
        form.appendChild(modal);
        document.body.appendChild(form);
        controller.element = form;

        controller.closeModal();

        expect(window.bootstrap.Modal.getOrCreateInstance).toHaveBeenCalledWith(modal);
        expect(hideMock).toHaveBeenCalled();
        document.body.removeChild(form);
    });

    test('closeModalAndWait uses a bounded fallback when hidden is not emitted', async () => {
        jest.useFakeTimers();
        const modal = document.getElementById('testModal');
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        const hideMock = jest.fn();
        window.bootstrap.Modal = {
            getInstance: jest.fn(() => ({ hide: hideMock })),
            getOrCreateInstance: jest.fn(() => ({ hide: hideMock })),
        };

        const waiting = controller.closeModalAndWait();
        expect(hideMock).toHaveBeenCalledTimes(1);

        jest.advanceTimersByTime(750);
        await waiting;

        expect(controller.modalHideCleanup).toBeNull();
    });

    test('closeModalBeforeSubmit handles no modal parent', () => {
        document.body.innerHTML = `
            <form data-controller="turbo-modal">
                <button type="submit">Submit</button>
            </form>
        `;
        controller.element = document.querySelector('[data-controller="turbo-modal"]');

        expect(() => {
            controller.closeModalBeforeSubmit({ target: controller.element });
        }).not.toThrow();
    });

    test('submitAsTurboStream posts as turbo stream and renders the response without navigation', async () => {
        window.history.pushState({}, '', '/awards/recommendations?status=submitted');
        const hideMock = jest.fn();
        window.bootstrap.Modal = {
            getInstance: jest.fn(() => ({ hide: hideMock })),
            getOrCreateInstance: jest.fn(() => ({ hide: hideMock })),
        };
        controller.renderTurboStream = jest.fn();
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            redirected: false,
            headers: {
                get: jest.fn(() => 'text/vnd.turbo-stream.html; charset=UTF-8')
            },
            text: jest.fn().mockResolvedValue('<turbo-stream action="remove" target="modal"></turbo-stream>')
        });
        const preventDefault = jest.fn();
        const stopImmediatePropagation = jest.fn();

        await controller.submitAsTurboStream({ preventDefault, stopImmediatePropagation });

        expect(preventDefault).toHaveBeenCalled();
        expect(stopImmediatePropagation).toHaveBeenCalled();
        expect(hideMock).toHaveBeenCalled();
        expect(controller.element.querySelector('[name="page_context_url"]').value)
            .toBe('/awards/recommendations?status=submitted');
        expect(global.fetch).toHaveBeenCalledWith(
            'http://localhost/awards/recommendations/edit/594',
            expect.objectContaining({
                method: 'POST',
                body: expect.any(FormData),
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/vnd.turbo-stream.html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
        );
        expect(controller.renderTurboStream).toHaveBeenCalledWith(
            '<turbo-stream action="remove" target="modal"></turbo-stream>'
        );
        expect(hideMock).toHaveBeenCalledTimes(1);
        expect(hideMock.mock.invocationCallOrder[0])
            .toBeLessThan(controller.renderTurboStream.mock.invocationCallOrder[0]);
        expect(window.KMP_accessibility.announce)
            .toHaveBeenCalledWith('Your attendance has been registered.');
    });

    test('successful stream feedback suppresses a competing generic success announcement', async () => {
        window.bootstrap.Modal = {
            getInstance: jest.fn(() => ({ hide: jest.fn() })),
            getOrCreateInstance: jest.fn(() => ({ hide: jest.fn() })),
        };
        controller.renderTurboStream = jest.fn();
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            redirected: false,
            headers: { get: jest.fn(() => 'text/vnd.turbo-stream.html') },
            text: jest.fn().mockResolvedValue(`
                <turbo-stream action="replace" target="flash-messages">
                    <template>
                        <div id="flash-messages">
                            <div class="alert alert-warning" role="alert">
                                Saved with warning. Complete the follow-up separately.
                            </div>
                        </div>
                    </template>
                </turbo-stream>
            `),
        });

        await controller.submitAsTurboStream({
            preventDefault: jest.fn(),
            stopImmediatePropagation: jest.fn(),
        });

        expect(controller.renderTurboStream).toHaveBeenCalled();
        expect(window.KMP_accessibility.announce).not.toHaveBeenCalled();
    });

    test('waits for the modal to hide and focuses an asynchronously replaced edit trigger', async () => {
        const modal = document.getElementById('testModal');
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.insertAdjacentHTML('afterbegin', `
            <table>
                <tbody>
                    <tr id="officers-grid-row-42">
                        <td><button type="button" class="edit-btn">Old edit</button></td>
                    </tr>
                </tbody>
            </table>
        `);
        const hideMock = jest.fn();
        window.bootstrap.Modal = {
            getInstance: jest.fn(() => ({ hide: hideMock })),
            getOrCreateInstance: jest.fn(() => ({ hide: hideMock })),
        };
        controller.renderTurboStream = jest.fn(() => {
            window.requestAnimationFrame(() => {
                document.getElementById('officers-grid-row-42').outerHTML = `
                    <tr id="officers-grid-row-42">
                        <td><button type="button" class="edit-btn">Replacement edit</button></td>
                    </tr>
                `;
            });
        });
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            redirected: false,
            headers: { get: jest.fn(() => 'text/vnd.turbo-stream.html') },
            text: jest.fn().mockResolvedValue(
                '<turbo-stream action="replace" target="officers-grid-row-42"></turbo-stream>',
            ),
        });

        const submission = controller.submitAsTurboStream({
            preventDefault: jest.fn(),
            stopImmediatePropagation: jest.fn(),
        });
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        expect(hideMock).toHaveBeenCalledTimes(1);
        expect(controller.renderTurboStream).not.toHaveBeenCalled();

        modal.dispatchEvent(new Event('hidden.bs.modal'));
        await submission;

        const replacementEdit = document.querySelector(
            '#officers-grid-row-42 .edit-btn',
        );
        expect(controller.renderTurboStream).toHaveBeenCalled();
        expect(replacementEdit).toHaveTextContent('Replacement edit');
        expect(document.activeElement).toBe(replacementEdit);
    });

    test('focuses an actionable descendant of the surviving fallback container', async () => {
        const container = document.createElement('section');
        const fallbackButton = document.createElement('button');
        fallbackButton.type = 'button';
        fallbackButton.textContent = 'Next office';
        container.appendChild(fallbackButton);
        document.body.appendChild(container);
        controller.waitForRenderFrame = jest.fn().mockResolvedValue();

        await controller.restoreFocusAfterStream({ container });

        expect(document.activeElement).toBe(fallbackButton);
        expect(container).not.toHaveAttribute('tabindex');
    });

    test('setSubmitting updates a submit button associated from outside the form', () => {
        controller.element.id = 'attendanceModalForm';
        const externalSubmit = document.createElement('button');
        externalSubmit.type = 'submit';
        externalSubmit.setAttribute('form', 'attendanceModalForm');
        document.body.appendChild(externalSubmit);

        controller.setSubmitting(true);

        expect(externalSubmit.disabled).toBe(true);
        expect(externalSubmit.getAttribute('aria-busy')).toBe('true');

        controller.setSubmitting(false);
        expect(externalSubmit.disabled).toBe(false);
        expect(externalSubmit.getAttribute('aria-busy')).toBe('false');
    });

    test('successful save still closes and shows feedback if stream rendering fails', async () => {
        const hideMock = jest.fn();
        window.bootstrap.Modal = {
            getInstance: jest.fn(() => ({ hide: hideMock })),
            getOrCreateInstance: jest.fn(() => ({ hide: hideMock })),
        };
        document.body.insertAdjacentHTML('afterbegin', '<div id="flash-messages"></div>');
        controller.renderTurboStream = jest.fn(() => {
            throw new Error('stream render failed');
        });
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            redirected: false,
            headers: { get: jest.fn(() => 'text/vnd.turbo-stream.html') },
            text: jest.fn().mockResolvedValue('<turbo-stream></turbo-stream>'),
        });

        await controller.submitAsTurboStream({
            preventDefault: jest.fn(),
            stopImmediatePropagation: jest.fn(),
        });

        expect(hideMock).toHaveBeenCalledTimes(1);
        expect(document.getElementById('flash-messages')).toHaveTextContent(
            'Your attendance has been registered.',
        );
        expect(window.KMP_accessibility.announce).not.toHaveBeenCalled();
    });

    test('failed turbo stream response renders errors and announces failure', async () => {
        controller.renderTurboStream = jest.fn();
        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            redirected: false,
            headers: { get: jest.fn(() => 'text/vnd.turbo-stream.html') },
            text: jest.fn().mockResolvedValue('<turbo-stream></turbo-stream>'),
        });

        await controller.submitAsTurboStream({
            preventDefault: jest.fn(),
            stopImmediatePropagation: jest.fn(),
        });

        expect(controller.renderTurboStream)
            .toHaveBeenCalledWith('<turbo-stream></turbo-stream>');
        expect(controller.element.querySelector('[data-turbo-modal-feedback]'))
            .not.toHaveClass('d-none');
        expect(controller.element.querySelector('[data-turbo-modal-feedback]'))
            .toHaveTextContent('Unable to save. Please try again.');
        expect(window.KMP_accessibility.announce).not.toHaveBeenCalled();
    });

    test('network failure stays visible inside the modal and is announced', async () => {
        global.fetch = jest.fn().mockRejectedValue(new Error('network unavailable'));

        await controller.submitAsTurboStream({
            preventDefault: jest.fn(),
            stopImmediatePropagation: jest.fn(),
        });

        const feedback = controller.element.querySelector('[data-turbo-modal-feedback]');
        expect(feedback).not.toHaveClass('d-none');
        expect(feedback.querySelector('[role="alert"]'))
            .toHaveTextContent('Unable to save. Please try again.');
        expect(window.KMP_accessibility.announce).not.toHaveBeenCalled();
        expect(controller.element.querySelector('button[type="submit"]').disabled).toBe(false);
    });

    test('clears and reuses fallback feedback inserted outside a nested form', () => {
        document.body.innerHTML = `
            <div class="modal" id="nestedFormModal">
                <div class="modal-body">
                    <form data-controller="turbo-modal">
                        <button type="submit">Submit</button>
                    </form>
                </div>
            </div>
        `;
        controller.element = document.querySelector('[data-controller="turbo-modal"]');

        expect(controller.showFallbackFailure()).toBe(true);
        const modal = document.getElementById('nestedFormModal');
        const feedback = modal.querySelector('[data-turbo-modal-feedback]');
        expect(feedback).not.toBeNull();
        expect(controller.element.contains(feedback)).toBe(false);

        controller.clearFailure();
        expect(feedback).toHaveClass('d-none');
        expect(feedback).toBeEmptyDOMElement();

        expect(controller.showFallbackFailure()).toBe(true);
        expect(modal.querySelectorAll('[data-turbo-modal-feedback]')).toHaveLength(1);
        expect(feedback.querySelectorAll('[role="alert"]')).toHaveLength(1);
    });

    test('submitAsTurboStream replaces containing frame for non-stream form responses', async () => {
        global.IntersectionObserver = jest.fn().mockImplementation(() => ({
            disconnect: jest.fn(),
            observe: jest.fn(),
            unobserve: jest.fn(),
        }));
        document.body.innerHTML = `
            <turbo-frame id="editRecommendation">
                <form data-controller="turbo-modal"
                      action="http://localhost/awards/recommendations/edit/594"
                      method="post">
                    <input type="hidden" name="page_context_url" value="">
                    <button type="submit">Submit</button>
                </form>
            </turbo-frame>
        `;
        controller.element = document.querySelector('[data-controller="turbo-modal"]');
        controller.renderTurboStream = jest.fn();
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            redirected: false,
            headers: {
                get: jest.fn(() => 'text/html; charset=UTF-8')
            },
            text: jest.fn().mockResolvedValue('<form id="replacement-form"></form>')
        });

        await controller.submitAsTurboStream({
            preventDefault: jest.fn(),
            stopImmediatePropagation: jest.fn(),
        });

        expect(controller.renderTurboStream).not.toHaveBeenCalled();
        expect(document.getElementById('editRecommendation').innerHTML)
            .toContain('replacement-form');
    });

    test('failed non-stream response replaces the frame and announces failure', async () => {
        global.IntersectionObserver = jest.fn().mockImplementation(() => ({
            disconnect: jest.fn(),
            observe: jest.fn(),
            unobserve: jest.fn(),
        }));
        document.body.innerHTML = `
            <turbo-frame id="editRecommendation">
                <form data-controller="turbo-modal"
                      action="http://localhost/awards/recommendations/edit/594"
                      method="post">
                    <button type="submit">Submit</button>
                </form>
            </turbo-frame>
        `;
        controller.element = document.querySelector('[data-controller="turbo-modal"]');
        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            redirected: false,
            headers: { get: jest.fn(() => 'text/html; charset=UTF-8') },
            text: jest.fn().mockResolvedValue('<form id="replacement-form"></form>'),
        });

        await controller.submitAsTurboStream({
            preventDefault: jest.fn(),
            stopImmediatePropagation: jest.fn(),
        });

        expect(document.getElementById('editRecommendation').innerHTML)
            .toContain('replacement-form');
        expect(window.KMP_accessibility.announce)
            .toHaveBeenCalledWith('Unable to save. Please try again.', { assertive: true });
    });

    test('renderTurboStream applies stream actions through Turbo', async () => {
        document.body.innerHTML = '<div id="stream-target">Before</div>';

        controller.renderTurboStream(`
            <turbo-stream action="replace" target="stream-target">
                <template><div id="stream-target">After</div></template>
            </turbo-stream>
        `);
        await new Promise((resolve) => window.requestAnimationFrame(resolve));

        expect(document.getElementById('stream-target').textContent).toBe('After');
    });
});
