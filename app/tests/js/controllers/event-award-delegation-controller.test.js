import '../../../plugins/Awards/Assets/js/controllers/event-award-delegation-controller.js';

const EventAwardDelegation = window.Controllers['event-award-delegation'];

describe('EventAwardDelegation', () => {
    let controller;
    let frame;

    beforeEach(() => {
        document.body.innerHTML = '<div class="modal show"><turbo-frame><select aria-label="Group"><option>Group</option></select></turbo-frame></div>';
        controller = new EventAwardDelegation();
        controller.element = document.querySelector('.modal');
        frame = controller.element.querySelector('turbo-frame');
        controller.frameTarget = frame;
        controller.urlValue = '/awards/bestowal-delegations/gathering/123';
    });

    afterEach(() => document.body.replaceChildren());

    test('loads on opening and removes private content before the next opening', () => {
        controller.open();
        expect(frame.src).toBe(controller.urlValue);
        frame.setAttribute('src', controller.urlValue);
        controller.close();
        expect(frame.hasAttribute('src')).toBe(false);
        expect(frame.querySelector('select')).toBeNull();
        expect(frame.querySelector('[role="status"]')).not.toBeNull();
        controller.open();
        expect(frame.src).toBe(controller.urlValue);
    });

    test('focuses the first field after loading', () => {
        const status = document.createElement('div');
        status.setAttribute('role', 'status');
        status.className = 'visually-hidden';
        frame.prepend(status);
        frame.insertAdjacentHTML('afterbegin', '<h3>Event name</h3>');
        controller.loaded({ target: frame });
        expect(document.activeElement).toBe(frame.querySelector('select'));
    });

    test('focuses feedback after a form replaces its submit button', () => {
        frame.innerHTML = '<div class="alert" role="alert">Delegation revoked</div><select></select>';
        controller.loaded({ target: frame });
        expect(document.activeElement).toBe(frame.querySelector('.alert'));
    });

    test('does not steal focus when the user already started entering data', () => {
        const input = document.createElement('input');
        frame.appendChild(input);
        input.focus();
        controller.loaded({ target: frame });
        expect(document.activeElement).toBe(input);
    });

    test('does not move focus while closed or when another frame loads', () => {
        const outside = document.createElement('button');
        document.body.appendChild(outside);
        outside.focus();
        controller.loaded({ target: outside });
        controller.element.classList.remove('show');
        controller.loaded({ target: frame });
        expect(document.activeElement).toBe(outside);
    });

    test('handles denied frame responses as safe dialog feedback', () => {
        const event = { target: frame, preventDefault: jest.fn() };
        controller.unavailable(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(frame.querySelector('form')).toBeNull();
        expect(document.activeElement).toBe(frame.querySelector('[role="alert"]'));
        expect(frame.textContent).toContain('no longer available');
    });
});
