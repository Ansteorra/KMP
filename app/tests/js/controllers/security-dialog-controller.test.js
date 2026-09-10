import SecurityDialogController from '../../../assets/js/controllers/security-dialog-controller.js';

let controller;
beforeEach(() => {
    document.body.innerHTML = '<button id="open">Settings</button><div class="modal show"><turbo-frame></turbo-frame></div>';
    controller = new SecurityDialogController();
    controller.element = document.querySelector('.modal');
    controller.frameTarget = document.querySelector('turbo-frame');
    controller.urlValue = '/members/security';
    controller.connect();
});
afterEach(() => controller.disconnect());

test('dialog loads on demand and clears private content with focus return on close', () => {
    expect(controller.frameTarget.src).toBeUndefined();
    const event = new Event('shown.bs.modal');
    event.relatedTarget = document.querySelector('#open');
    controller.element.dispatchEvent(event);
    expect(controller.frameTarget.src).toBe('/members/security');
    controller.frameTarget.innerHTML = '<input type="password" value="private">';
    controller.element.dispatchEvent(new Event('hidden.bs.modal'));
    expect(controller.frameTarget.innerHTML).toBe('');
    expect(document.activeElement.id).toBe('open');
});

test('a late frame response after close cannot restore private content', () => {
    controller.element.classList.remove('show');
    controller.frameTarget.innerHTML = '<p>Private credential names</p>';
    controller.loaded();
    expect(controller.frameTarget.innerHTML).toBe('');
});

test('frame failure is recoverable and announced', () => {
    const event = { preventDefault: jest.fn() };
    controller.failed(event);
    expect(event.preventDefault).toHaveBeenCalled();
    expect(controller.frameTarget.querySelector('[role="alert"]').textContent).toContain('Close this window and try again');
});
