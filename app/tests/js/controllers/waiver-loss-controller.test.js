import '../../../plugins/Waivers/assets/js/controllers/waiver-loss-controller.js';

const WaiverLossController = window.Controllers['waiver-loss'];

describe('waiver loss grid action', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <button id="first" data-waiver-loss-type-id="1" data-waiver-loss-type-name="Adult Waiver">Report/Mark Lost</button>
            <button id="second" data-waiver-loss-type-id="2" data-waiver-loss-type-name="Minor Waiver">Report/Mark Lost</button>
            <input type="hidden" id="type"><strong id="type-name"></strong><textarea id="notes"></textarea>`;
        controller = new WaiverLossController();
        controller.typeTarget = document.getElementById('type');
        controller.typeNameTarget = document.getElementById('type-name');
        controller.notesTarget = document.getElementById('notes');
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('uses the clicked row and focuses notes, then returns focus to that row action', () => {
        const trigger = document.getElementById('second');
        controller.selectRow({ relatedTarget: trigger });
        expect(controller.typeTarget.value).toBe('2');
        expect(controller.typeNameTarget.textContent).toBe('Minor Waiver');

        controller.focusNotes();
        expect(document.activeElement).toBe(controller.notesTarget);
        controller.restoreFocus();
        expect(document.activeElement).toBe(trigger);
    });

    test('switching rows updates the waiver type and clears previous report notes', () => {
        controller.selectRow({ relatedTarget: document.getElementById('first') });
        controller.notesTarget.value = 'Notes for the adult waiver';
        const trigger = document.getElementById('second');
        trigger.dataset.waiverLossTypeName = '<script>Waiver</script>';
        controller.selectRow({ relatedTarget: trigger });
        expect(controller.typeTarget.value).toBe('2');
        expect(controller.notesTarget.value).toBe('');
        expect(controller.typeNameTarget.textContent).toBe('<script>Waiver</script>');
        expect(controller.typeNameTarget.querySelector('script')).toBeNull();
    });

    test('does not restore focus to a row that was removed', () => {
        const trigger = document.getElementById('first');
        controller.selectRow({ relatedTarget: trigger });
        trigger.remove();
        expect(() => controller.restoreFocus()).not.toThrow();
    });
});
