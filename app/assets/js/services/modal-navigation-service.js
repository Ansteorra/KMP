import { hideModalAndWait } from './page-refresh-service.js';

/** Coordinate ordinary HTML form redirects with Bootstrap before Turbo morphs. */
export function installModalNavigation() {
    let pending;
    let returnFocus;
    let epoch = 0;
    const triggers = new WeakMap();
    const shown = event => triggers.set(event.target, event.relatedTarget);
    const responded = async event => {
        if (!(event.target instanceof HTMLFormElement)) return;
        const modal = event.target.closest('.modal') || event.target.querySelector('.modal.show');
        const response = event.detail?.fetchResponse?.response;
        if (modal && response && (response.status === 422 || response.status >= 500)
            && /text\/html/i.test(response.headers.get('Content-Type') || '')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const form = event.target;
            const html = await response.clone().text().catch(() => '');
            const page = new DOMParser().parseFromString(html, 'text/html');
            if (!form.isConnected) return;
            form.querySelectorAll('[data-kmp-validation-feedback]').forEach(node => node.remove());
            form.querySelectorAll('[data-kmp-validation-invalid]').forEach(field => {
                field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid');
                field.removeAttribute('data-kmp-validation-invalid');
                const ids = (field.getAttribute('aria-describedby') || '').split(/\s+/)
                    .filter(id => document.getElementById(id));
                if (ids.length) field.setAttribute('aria-describedby', ids.join(' '));
                else field.removeAttribute('aria-describedby');
            });
            const invalid = [];
            if (response.status === 422) {
                for (const field of page.querySelectorAll('[name][aria-invalid="true"]')) {
                    const current = [...form.elements].find(control => control.name === field.name);
                    if (!current) continue;
                    const messages = (field.getAttribute('aria-describedby') || '').split(/\s+/)
                        .map(id => page.getElementById(id))
                        .filter(node => node?.matches('.invalid-feedback, .error-message'));
                    if (!messages.length) continue;
                    const feedback = document.createElement('div');
                    feedback.id = `kmp-field-error-${crypto.randomUUID()}`;
                    feedback.className = 'invalid-feedback'; feedback.dataset.kmpValidationFeedback = '';
                    feedback.textContent = messages.map(node => node.textContent.trim()).join(' ');
                    current.classList.add('is-invalid'); current.setAttribute('aria-invalid', 'true');
                    current.dataset.kmpValidationInvalid = '';
                    current.setAttribute('aria-describedby', [current.getAttribute('aria-describedby'), feedback.id].filter(Boolean).join(' '));
                    current.after(feedback); invalid.push(current);
                }
            }
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger'; alert.setAttribute('role', 'alert');
            alert.tabIndex = -1; alert.dataset.kmpValidationFeedback = '';
            alert.textContent = response.status === 422
                ? page.querySelector('.alert-danger')?.textContent.trim() || 'Please correct the form errors and try again.'
                : 'The request could not be completed. Your entries have been kept. Please try again.';
            (form.querySelector('.modal-body') || form).prepend(alert);
            (invalid[0] || alert).focus();
            return;
        }
        if (modal && response?.ok && response.redirected && /text\/html/i.test(response.headers.get('Content-Type') || '')) {
            pending = modal;
        }
    };
    const beforeRender = event => {
        const version = ++epoch;
        const modal = pending; pending = null;
        if (!modal?.isConnected) return;
        const render = event.detail?.render;
        if (typeof render !== 'function') return;
        returnFocus = { id: triggers.get(modal)?.id };
        event.detail.render = async (...args) => {
            await hideModalAndWait(modal);
            if (version === epoch) return render(...args);
        };
    };
    const rendered = () => {
        if (!returnFocus) return;
        const trigger = returnFocus.id ? document.getElementById(returnFocus.id) : null;
        const target = trigger?.getClientRects().length ? trigger
            : document.querySelector('[role="tab"][aria-selected="true"], [data-kmp-back]');
        target?.focus({ preventScroll: true });
        returnFocus = null;
    };
    document.addEventListener('show.bs.modal', shown);
    document.addEventListener('turbo:before-fetch-response', responded);
    document.addEventListener('turbo:before-render', beforeRender);
    document.addEventListener('turbo:render', rendered);
    return () => {
        epoch++; pending = null;
        document.removeEventListener('show.bs.modal', shown);
        document.removeEventListener('turbo:before-fetch-response', responded);
        document.removeEventListener('turbo:before-render', beforeRender);
        document.removeEventListener('turbo:render', rendered);
    };
}
