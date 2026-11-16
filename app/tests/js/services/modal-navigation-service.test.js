jest.mock('../../../assets/js/services/page-refresh-service.js', () => ({ hideModalAndWait: jest.fn() }));
import { hideModalAndWait } from '../../../assets/js/services/page-refresh-service.js';
import { installModalNavigation } from '../../../assets/js/services/modal-navigation-service.js';

describe('HTML modal navigation', () => {
    let dispose;
    beforeEach(() => {
        document.body.innerHTML = '<button id="edit">Edit</button><div class="modal show" id="dialog"><form method="post"><input name="reason" value="Keep my reason"></form></div>';
        dispose = installModalNavigation();
        const shown = new Event('show.bs.modal', { bubbles: true });
        Object.defineProperty(shown, 'relatedTarget', { value: document.getElementById('edit') });
        document.getElementById('dialog').dispatchEvent(shown);
    });
    afterEach(() => { dispose(); jest.clearAllMocks(); });
    function response(status) {
        document.forms[0].dispatchEvent(new CustomEvent('turbo:before-fetch-response', { bubbles: true, detail: {
            fetchResponse: { response: { ok: status === 200, status, redirected: status === 200,
                headers: new Headers({ 'Content-Type': 'text/html' }),
                clone: () => ({ text: async () => '' }) } },
        } }));
    }

    test('waits for dialog closure before the existing renderer without bypassing snapshot validation', async () => {
        let closed;
        hideModalAndWait.mockImplementation(() => new Promise(resolve => { closed = resolve; }));
        response(200);
        const render = jest.fn();
        const event = new CustomEvent('turbo:before-render', { cancelable: true, detail: { render } });
        document.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(false);
        const rendering = event.detail.render(document.body, document.body.cloneNode());
        expect(render).not.toHaveBeenCalled();
        closed(); await rendering;
        expect(render).toHaveBeenCalledTimes(1);
    });

    test('validation does not close the dialog or alter the draft', () => {
        response(422);
        const render = jest.fn();
        const event = new CustomEvent('turbo:before-render', { detail: { render } });
        document.dispatchEvent(event);
        expect(event.detail.render).toBe(render);
        expect(hideModalAndWait).not.toHaveBeenCalled();
        expect(document.querySelector('[name="reason"]')).toHaveValue('Keep my reason');
    });

    test('HTML validation keeps current inputs and associates fresh field errors inside the open dialog', async () => {
        const event = new CustomEvent('turbo:before-fetch-response', { bubbles: true, cancelable: true, detail: {
            fetchResponse: { response: { ok: false, status: 422,
                headers: new Headers({ 'Content-Type': 'text/html' }),
                clone: () => ({ text: async () => '<form><input name="reason" aria-invalid="true" aria-describedby="reason-error"><div id="reason-error" class="invalid-feedback">A longer reason is required.</div></form>' }),
            } },
        } });
        document.forms[0].dispatchEvent(event);
        expect(event.defaultPrevented).toBe(true);
        await Promise.resolve(); await Promise.resolve();
        const field = document.querySelector('[name="reason"]');
        expect(field).toHaveValue('Keep my reason');
        expect(field).toHaveAttribute('aria-invalid', 'true');
        expect(document.getElementById(field.getAttribute('aria-describedby'))).toHaveTextContent('A longer reason is required.');
        expect(document.getElementById('dialog')).toHaveClass('show');
        expect(field).toHaveFocus();
    });
});
