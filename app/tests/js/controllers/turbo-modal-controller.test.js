import '../../../assets/js/controllers/turbo-modal-controller.js';
const TurboModal = window.Controllers['turbo-modal'];

describe('TurboModalController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="modal" id="testModal">
                <form data-controller="turbo-modal"
                      data-action="turbo:submit-start->turbo-modal#closeModalBeforeSubmit">
                    <input type="text" name="field" value="test">
                    <button type="submit">Submit</button>
                </form>
            </div>
        `;

        controller = new TurboModal();
        controller.element = document.querySelector('[data-controller="turbo-modal"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
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
            getInstance: jest.fn(() => ({ hide: hideMock }))
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
});
