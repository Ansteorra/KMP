// Controller registers on window.Controllers (no default export)
import '../../../plugins/Waivers/assets/js/controllers/exemption-reasons-controller.js';
const ExemptionReasonsController = window.Controllers['exemption-reasons'];

describe('ExemptionReasonsController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="exemption-reasons"
                 data-exemption-reasons-reasons-value="[]">
                <div data-exemption-reasons-target="container"></div>
                <template data-exemption-reasons-target="template">
                    <div class="exemption-reason-item">
                        <input type="text" data-exemption-reasons-target="reasonInput">
                        <button data-action="exemption-reasons#removeReason">Remove</button>
                    </div>
                </template>
                <input type="hidden" data-exemption-reasons-target="hiddenInput" value="">
            </div>
        `;

        controller = new ExemptionReasonsController();
        controller.element = document.querySelector('[data-controller="exemption-reasons"]');

        // Wire up targets
        controller.containerTarget = document.querySelector('[data-exemption-reasons-target="container"]');
        controller.templateTarget = document.querySelector('[data-exemption-reasons-target="template"]');
        controller.hiddenInputTarget = document.querySelector('[data-exemption-reasons-target="hiddenInput"]');

        // Dynamic targets - reasonInputTargets is a getter
        Object.defineProperty(controller, 'reasonInputTargets', {
            get: () => Array.from(controller.containerTarget.querySelectorAll('input[type="text"]')),
            configurable: true
        });

        // Wire up values
        controller.reasonsValue = [];
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(ExemptionReasonsController.targets).toEqual(
            expect.arrayContaining(['container', 'template', 'hiddenInput', 'reasonInput'])
        );
    });

    test('has correct static values', () => {
        expect(ExemptionReasonsController.values).toHaveProperty('reasons');
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['exemption-reasons']).toBe(ExemptionReasonsController);
    });

    // --- connect ---

    test('connect adds one empty field when no reasons exist', () => {
        controller.connect();
        const inputs = controller.containerTarget.querySelectorAll('input[type="text"]');
        expect(inputs.length).toBe(1);
        expect(inputs[0].value).toBe('');
    });

    test('connect populates fields from existing reasons', () => {
        controller.reasonsValue = ['Reason A', 'Reason B'];
        controller.connect();
        const inputs = controller.containerTarget.querySelectorAll('input[type="text"]');
        expect(inputs.length).toBe(2);
        expect(inputs[0].value).toBe('Reason A');
        expect(inputs[1].value).toBe('Reason B');
    });

    test('connect updates hidden input with reasons JSON', () => {
        controller.reasonsValue = ['Reason A'];
        controller.connect();
        expect(JSON.parse(controller.hiddenInputTarget.value)).toEqual(['Reason A']);
    });

    // --- addReason ---

    test('addReason appends a new input field', () => {
        controller.addReason('New Reason');
        const inputs = controller.containerTarget.querySelectorAll('input[type="text"]');
        expect(inputs.length).toBe(1);
        expect(inputs[0].value).toBe('New Reason');
    });

    test('addReason with empty value creates empty field', () => {
        controller.addReason('');
        const inputs = controller.containerTarget.querySelectorAll('input[type="text"]');
        expect(inputs.length).toBe(1);
        expect(inputs[0].value).toBe('');
    });

    // --- removeReason ---

    test('removeReason removes item when more than one exists', () => {
        controller.addReason('A');
        controller.addReason('B');
        expect(controller.containerTarget.children.length).toBe(2);

        const removeBtn = controller.containerTarget.querySelectorAll('button')[0];
        const event = { target: removeBtn };
        controller.removeReason(event);

        expect(controller.containerTarget.children.length).toBe(1);
    });

    test('removeReason clears last field instead of removing it', () => {
        controller.addReason('Only');
        expect(controller.containerTarget.children.length).toBe(1);

        const removeBtn = controller.containerTarget.querySelector('button');
        const event = { target: removeBtn };
        controller.removeReason(event);

        expect(controller.containerTarget.children.length).toBe(1);
        const input = controller.containerTarget.querySelector('input[type="text"]');
        expect(input.value).toBe('');
    });

    // --- updateHiddenInput ---

    test('updateHiddenInput serializes non-empty reason values to JSON', () => {
        controller.addReason('Reason 1');
        controller.addReason('');
        controller.addReason('Reason 3');
        controller.updateHiddenInput();

        const parsed = JSON.parse(controller.hiddenInputTarget.value);
        expect(parsed).toEqual(['Reason 1', 'Reason 3']);
    });

    test('updateHiddenInput trims whitespace from values', () => {
        controller.addReason('  padded  ');
        controller.updateHiddenInput();

        const parsed = JSON.parse(controller.hiddenInputTarget.value);
        expect(parsed).toEqual(['padded']);
    });

    // --- reasonChanged ---

    test('reasonChanged calls updateHiddenInput', () => {
        const spy = jest.spyOn(controller, 'updateHiddenInput');
        controller.reasonChanged({});
        expect(spy).toHaveBeenCalled();
    });

    // --- reasonBlurred ---

    test('reasonBlurred adds empty field when last input has a value', () => {
        controller.addReason('Last value');
        const inputs = controller.reasonInputTargets;
        const lastInput = inputs[inputs.length - 1];
        lastInput.value = 'Has value';

        controller.reasonBlurred({ target: lastInput });

        const newInputs = controller.containerTarget.querySelectorAll('input[type="text"]');
        expect(newInputs.length).toBe(2);
    });

    test('reasonBlurred does not add field when non-last input blurs', () => {
        controller.addReason('First');
        controller.addReason('Second');
        const inputs = controller.reasonInputTargets;

        controller.reasonBlurred({ target: inputs[0] });

        const allInputs = controller.containerTarget.querySelectorAll('input[type="text"]');
        expect(allInputs.length).toBe(2);
    });
});
