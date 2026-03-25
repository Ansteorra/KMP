import '../../../assets/js/controllers/modal-opener-controller.js';
const ModalOpener = window.Controllers['modal-opener'];

describe('ModalOpenerController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="modal-opener"
                 data-modal-opener-modal-btn-value="">
            </div>
            <button id="confirmModalBtn">Trigger</button>
        `;

        controller = new ModalOpener();
        controller.element = document.querySelector('[data-controller="modal-opener"]');
        controller.modalBtnValue = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['modal-opener']).toBe(ModalOpener);
    });

    test('has correct static values', () => {
        expect(ModalOpener.values).toHaveProperty('modalBtn', String);
    });

    test('modalBtnValueChanged clicks the referenced button', () => {
        const btn = document.getElementById('confirmModalBtn');
        const clickSpy = jest.spyOn(btn, 'click');

        controller.modalBtnValue = 'confirmModalBtn';
        controller.modalBtnValueChanged();

        expect(clickSpy).toHaveBeenCalled();
    });

    test('modalBtnValueChanged finds button by id', () => {
        controller.modalBtnValue = 'confirmModalBtn';
        expect(() => controller.modalBtnValueChanged()).not.toThrow();
    });
});
