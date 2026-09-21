import GridSubscriptionsDialogController from '../../../assets/js/controllers/grid-subscriptions-dialog-controller.js';

let controller;
beforeEach(() => {
    document.body.innerHTML = '<button id="emailSubscriptionsButton">Email subscriptions</button><div class="modal show"><h2 tabindex="-1">Email subscriptions</h2><turbo-frame></turbo-frame></div>';
    controller = new GridSubscriptionsDialogController();
    controller.element = document.querySelector('.modal');
    controller.frameTarget = document.querySelector('turbo-frame');
    controller.headingTarget = document.querySelector('h2');
    controller.urlValue = '/grid-subscriptions';
});
afterEach(() => controller.disconnect());

test('loads fresh on open, focuses the heading, and returns focus after close', () => {
    controller.opened({ relatedTarget: document.querySelector('button') });
    expect(controller.frameTarget.src).toBe('/grid-subscriptions');
    expect(document.activeElement).toBe(controller.headingTarget);
    controller.frameTarget.innerHTML = '<p>Private subscription</p>';
    controller.closed();
    expect(controller.frameTarget.innerHTML).toBe('');
    expect(document.activeElement.id).toBe('emailSubscriptionsButton');
});

test('cancellation focuses its returned status instead of losing focus with the deleted row', () => {
    controller.frameTarget.innerHTML = '<div data-subscription-feedback tabindex="-1" role="status">Email subscription cancelled.</div>';
    controller.loaded();
    expect(document.activeElement.textContent).toBe('Email subscription cancelled.');
});

test('late responses cannot restore content in a closed dialog', () => {
    controller.element.classList.remove('show');
    controller.frameTarget.innerHTML = '<p>Private subscription</p>';
    controller.loaded();
    expect(controller.frameTarget.innerHTML).toBe('');
});

test('failed frame loads show focused, retryable feedback', () => {
    const event = { preventDefault: jest.fn() };
    controller.failed(event);
    expect(event.preventDefault).toHaveBeenCalled();
    expect(document.activeElement.getAttribute('role')).toBe('alert');
    expect(document.activeElement.textContent).toContain('Close this window and try again');
});

test('cancellation asks for confirmation in place and keeping it restores the action focus', () => {
    controller.frameTarget.innerHTML = '<form><button type="button" data-cancel-subscription>Cancel</button><div hidden data-cancel-confirmation><button type="button" data-keep-subscription>Keep subscription</button><button type="submit">Confirm cancellation</button></div></form>';
    const button = controller.frameTarget.querySelector('[data-cancel-subscription]');
    const keep = controller.frameTarget.querySelector('[data-keep-subscription]');
    controller.requestCancellation({ currentTarget: button });
    expect(button.hidden).toBe(true);
    expect(keep.parentElement.hidden).toBe(false);
    expect(document.activeElement).toBe(keep);
    controller.keepSubscription({ currentTarget: keep });
    expect(keep.parentElement.hidden).toBe(true);
    expect(button.hidden).toBe(false);
    expect(document.activeElement).toBe(button);
});
