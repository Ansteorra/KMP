import '../../../plugins/Awards/Assets/js/controllers/bestowal-todos-controller.js';

const BestowalTodosController = window.Controllers['awards-bestowal-todos'];

describe('AwardsBestowalTodos', () => {
    let controller;
    let frame;

    beforeEach(() => {
        document.body.replaceChildren();
        const root = document.createElement('div');
        root.setAttribute('data-controller', 'awards-bestowal-todos');

        frame = document.createElement('turbo-frame');
        frame.id = 'bestowalTodosQuick';
        root.appendChild(frame);
        document.body.appendChild(root);

        controller = new BestowalTodosController();
        controller.element = root;
        controller.turboFrameTarget = frame;
        controller.hasTurboFrameTarget = true;
        controller.turboFrameUrlValue = '/awards/bestowals/bestowal-todos';
        controller.modalIdValue = 'bestowalTodosModal';
    });

    test('registers under its kebab-case identifier', () => {
        expect(typeof BestowalTodosController).toBe('function');
    });

    test('extractBestowalIdFromTrigger reads the outlet-btn data value', () => {
        const trigger = document.createElement('button');
        trigger.setAttribute('data-outlet-btn-btn-data-value', JSON.stringify({ id: 42 }));
        expect(controller.extractBestowalIdFromTrigger(trigger)).toBe(42);
    });

    test('extractBestowalIdFromTrigger falls back to the closest row id', () => {
        const row = document.createElement('tr');
        row.dataset.id = '7';
        const cell = document.createElement('td');
        const trigger = document.createElement('button');
        cell.appendChild(trigger);
        row.appendChild(cell);
        document.body.appendChild(row);

        expect(controller.extractBestowalIdFromTrigger(trigger)).toBe('7');
    });

    test('loadTodos points the turbo frame at the bestowal checklist url', () => {
        controller.loadTodos(13);
        expect(frame.src).toContain('/awards/bestowals/bestowal-todos/13');
        expect(frame.dataset.bestowalTodosLoadingId).toBe('13');
    });

    test('loadTodos is a no-op without a configured url', () => {
        controller.turboFrameUrlValue = '';
        controller.loadTodos(13);
        expect(frame.getAttribute('src')).toBeNull();
    });

    test('handleOutletClick ignores triggers outside the todos row action', () => {
        const wrapper = document.createElement('div');
        const trigger = document.createElement('button');
        wrapper.appendChild(trigger);
        document.body.appendChild(wrapper);

        controller.handleOutletClick({ target: trigger, detail: { id: 5 } });
        expect(frame.getAttribute('src')).toBeNull();
    });

    test('handleOutletClick loads the checklist for a todos trigger', () => {
        const wrapper = document.createElement('div');
        wrapper.className = 'todos-bestowal';
        const trigger = document.createElement('button');
        wrapper.appendChild(trigger);
        document.body.appendChild(wrapper);

        controller.handleOutletClick({ target: trigger, detail: { id: 9 } });
        expect(frame.src).toContain('/awards/bestowals/bestowal-todos/9');
    });

    test('include past toggle updates required gathering lookup and clears selection', () => {
        const section = document.createElement('div');
        section.setAttribute('data-bestowal-gathering-requirement', '');
        const includePast = document.createElement('input');
        includePast.type = 'checkbox';
        includePast.checked = true;
        const control = document.createElement('div');
        control.setAttribute('data-bestowal-gathering-control', 'true');
        control.dataset.baseUrl = '/awards/bestowals/gatherings-for-bestowal-auto-complete/4?selected_id=9';
        const input = document.createElement('input');
        input.setAttribute('data-ac-target', 'input');
        input.value = 'Future Court';
        input.disabled = true;
        const hidden = document.createElement('input');
        hidden.setAttribute('data-ac-target', 'hidden');
        hidden.value = '9';
        const hiddenText = document.createElement('input');
        hiddenText.setAttribute('data-ac-target', 'hiddenText');
        hiddenText.value = 'Future Court';
        const clearBtn = document.createElement('button');
        clearBtn.setAttribute('data-ac-target', 'clearBtn');
        clearBtn.disabled = false;

        control.append(input, hidden, hiddenText, clearBtn);
        section.append(includePast, control);
        controller.element.appendChild(section);

        controller.handleIncludePastChange({ target: includePast });

        expect(control.dataset.acUrlValue)
            .toBe('http://localhost/awards/bestowals/gatherings-for-bestowal-auto-complete/4?selected_id=9&include_past=1');
        expect(hidden.value).toBe('');
        expect(input.value).toBe('');
        expect(input.disabled).toBe(false);
        expect(hiddenText.value).toBe('');
        expect(clearBtn.disabled).toBe(true);
    });
});
