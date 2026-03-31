import '../../../assets/js/controllers/filter-grid-controller.js';
const FilterGridController = window.Controllers['filter-grid'];

describe('FilterGridController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="filter-grid" method="get" action="/members">
                <input type="text" name="search" data-action="input->filter-grid#submitForm">
                <select name="status" data-action="change->filter-grid#submitForm">
                    <option value="">All</option>
                    <option value="active">Active</option>
                </select>
            </form>
        `;

        controller = new FilterGridController();
        controller.element = document.querySelector('[data-controller="filter-grid"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('registers on window.Controllers', () => {
        expect(window.Controllers['filter-grid']).toBe(FilterGridController);
    });

    // --- submitForm ---

    test('submitForm calls _safeSubmit on the form element', () => {
        const safeSpy = jest.spyOn(controller, '_safeSubmit').mockImplementation(() => {});
        controller.submitForm({});
        expect(safeSpy).toHaveBeenCalledWith(controller.element);
    });

    // --- _safeSubmit ---

    test('_safeSubmit uses requestSubmit when available', () => {
        const form = controller.element;
        form.requestSubmit = jest.fn();

        controller._safeSubmit(form);

        expect(form.requestSubmit).toHaveBeenCalled();
    });

    test('_safeSubmit falls back to clicking existing submit button', () => {
        const form = controller.element;
        form.requestSubmit = undefined;

        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.click = jest.fn();
        form.appendChild(submitBtn);

        controller._safeSubmit(form);

        expect(submitBtn.click).toHaveBeenCalled();
    });

    test('_safeSubmit creates temp button when no submit button exists', () => {
        const form = controller.element;
        form.requestSubmit = undefined;

        // Ensure no submit button
        const btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        btns.forEach(b => b.remove());

        const appendSpy = jest.spyOn(form, 'appendChild');

        controller._safeSubmit(form);

        // A temporary button was created, clicked, and removed
        expect(appendSpy).toHaveBeenCalled();
        const tempBtn = appendSpy.mock.calls[0][0];
        expect(tempBtn.type).toBe('submit');
        expect(tempBtn.style.display).toBe('none');
    });

    test('_safeSubmit falls back when requestSubmit throws', () => {
        const form = controller.element;
        form.requestSubmit = jest.fn().mockImplementation(() => {
            throw new Error('Not supported');
        });

        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.click = jest.fn();
        form.appendChild(submitBtn);

        controller._safeSubmit(form);

        expect(submitBtn.click).toHaveBeenCalled();
    });
});
