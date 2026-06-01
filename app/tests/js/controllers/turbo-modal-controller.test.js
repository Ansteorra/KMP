import '../../../assets/js/controllers/turbo-modal-controller.js';
const TurboModal = window.Controllers['turbo-modal'];

describe('TurboModalController', () => {
    let controller;
    let originalFetch;
    let originalIntersectionObserver;

    beforeEach(() => {
        originalFetch = global.fetch;
        originalIntersectionObserver = global.IntersectionObserver;
        document.body.innerHTML = `
            <div class="modal" id="testModal">
                <form data-controller="turbo-modal"
                      action="http://localhost/awards/recommendations/edit/594"
                      method="post"
                      data-action="submit->turbo-modal#submitAsTurboStream turbo:submit-start->turbo-modal#closeModalBeforeSubmit">
                    <input type="text" name="field" value="test">
                    <input type="hidden" name="page_context_url" value="/awards/recommendations/turbo-quick-edit-form/594">
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
            redirected: false,
            headers: {
                get: jest.fn(() => 'text/vnd.turbo-stream.html; charset=UTF-8')
            },
            text: jest.fn().mockResolvedValue('<turbo-stream action="remove" target="modal"></turbo-stream>')
        });
        const preventDefault = jest.fn();

        await controller.submitAsTurboStream({ preventDefault });

        expect(preventDefault).toHaveBeenCalled();
        expect(hideMock).toHaveBeenCalled();
        expect(controller.element.querySelector('[name="page_context_url"]').value)
            .toBe('/awards/recommendations?status=submitted');
        expect(global.fetch).toHaveBeenCalledWith(
            'http://localhost/awards/recommendations/edit/594',
            expect.objectContaining({
                method: 'POST',
                body: expect.any(FormData),
                headers: {
                    'Accept': 'text/vnd.turbo-stream.html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
        );
        expect(controller.renderTurboStream).toHaveBeenCalledWith(
            '<turbo-stream action="remove" target="modal"></turbo-stream>'
        );
        expect(hideMock).toHaveBeenCalledTimes(2);
    });

    test('submitAsTurboStream replaces containing frame for non-stream form responses', async () => {
        global.IntersectionObserver = jest.fn().mockImplementation(() => ({
            disconnect: jest.fn(),
            observe: jest.fn(),
            unobserve: jest.fn(),
        }));
        document.body.innerHTML = `
            <turbo-frame id="editRecommendationQuick">
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

        await controller.submitAsTurboStream({ preventDefault: jest.fn() });

        expect(controller.renderTurboStream).not.toHaveBeenCalled();
        expect(document.getElementById('editRecommendationQuick').innerHTML)
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
