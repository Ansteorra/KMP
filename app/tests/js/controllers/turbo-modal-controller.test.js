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
                    <input type="text" name="field" value="test">
                    <input type="hidden" name="page_context_url" value="/awards/recommendations/turbo-edit-form/594">
                    <button type="submit">Submit</button>
                </form>
            </div>
        `;

        controller = new TurboModal();
        controller.element = document.querySelector('[data-controller="turbo-modal"]');
    });

    afterEach(() => {
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
        expect(window.KMP_accessibility.announce)
            .toHaveBeenCalledWith('Your attendance has been registered.');
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
