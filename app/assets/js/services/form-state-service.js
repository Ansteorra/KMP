let initialValues = new WeakMap();
let touchedForms = new WeakSet();
const fingerprint = form => JSON.stringify([...new FormData(form)]
    .filter(([key]) => !key.startsWith('_') && key !== 'page_context_url')
    .map(([key, value]) => [key, value instanceof File ? `${value.name}:${value.size}` : value]));

export function rememberForms(root = document) {
    root.querySelectorAll('form').forEach(form => {
        if (form.dataset.kmpPreservedDraft === 'true' && !initialValues.has(form)) {
            initialValues.set(form, 'restored-draft'); touchedForms.add(form);
        }
        if (!touchedForms.has(form)) initialValues.set(form, fingerprint(form));
    });
}

export function isDirty(form) {
    return form instanceof HTMLFormElement && form.method.toLowerCase() !== 'get'
        && touchedForms.has(form) && initialValues.has(form) && initialValues.get(form) !== fingerprint(form);
}

export function dirtyWithin(region) {
    const forms = [...region.querySelectorAll('form')];
    if (region.matches('form')) forms.push(region);
    const owner = region.closest('form');
    if (owner) forms.push(owner);
    return forms.some(isDirty);
}

/** A confirmed save or explicit reset establishes the next draft baseline. */
export function markFormClean(form) {
    if (!(form instanceof HTMLFormElement)) return;
    initialValues.set(form, fingerprint(form));
    touchedForms.delete(form);
    delete form.dataset.kmpPreservedDraft;
    window.dispatchEvent(new Event('kmp:draft-settled'));
}

let installed = false;
export function installFormState() {
    if (installed) return;
    installed = true;
    let submitting = false;
    let confirmedDestination = null;
    window.addEventListener('kmp:session-invalidated', () => {
        initialValues = new WeakMap(); touchedForms = new WeakSet(); submitting = true;
    });
    rememberForms();
    document.addEventListener('turbo:before-cache', () => {
        document.querySelectorAll('form').forEach(form => {
            if (isDirty(form)) form.dataset.kmpPreservedDraft = 'true';
            else delete form.dataset.kmpPreservedDraft;
        });
    });
    document.addEventListener('input', event => {
        if (event.target.form && event.target.type !== 'hidden') touchedForms.add(event.target.form);
    });
    document.addEventListener('change', event => {
        if (event.isTrusted && event.target.form && event.target.type !== 'hidden') touchedForms.add(event.target.form);
    });
    ['turbo:load', 'turbo:frame-load', 'turbo:render'].forEach(name => document.addEventListener(name, () => rememberForms()));
    document.addEventListener('focusin', event => {
        if (event.target.form && !initialValues.has(event.target.form)) rememberForms(event.target.form.parentElement);
    });
    document.addEventListener('turbo:submit-end', event => {
        submitting = false;
        if (event.detail.success) markFormClean(event.target);
    });
    document.addEventListener('reset', event => {
        if (!(event.target instanceof HTMLFormElement)) return;
        queueMicrotask(() => {
            if (event.defaultPrevented) return;
            markFormClean(event.target);
        });
    });
    document.addEventListener('turbo:before-morph-element', event => {
        if (isDirty(event.target) || event.target.matches('.modal.show')) event.preventDefault();
    });
    document.addEventListener('turbo:submit-start', () => { submitting = true; });
    document.addEventListener('submit', event => {
        queueMicrotask(() => { if (!event.defaultPrevented) submitting = true; });
    });
    document.addEventListener('shown.bs.modal', event => {
        event.target.querySelectorAll('form').forEach(form => {
            if (!touchedForms.has(form)) initialValues.set(form, fingerprint(form));
        });
        const owner = event.target.closest('form');
        if (owner && !touchedForms.has(owner)) initialValues.set(owner, fingerprint(owner));
    });
    document.addEventListener('turbo-modal:saved', event => {
        markFormClean(event.target);
    });
    document.addEventListener('turbo:before-visit', async event => {
        if (submitting || event.detail.url === confirmedDestination || ![...document.forms].some(isDirty)) return;
        event.preventDefault();
        const confirmed = await window.KMP_accessibility.confirm('You have unsaved changes. Leave this page?', {
            title: 'Unsaved changes', confirmLabel: 'Leave page',
        });
        if (confirmed) {
            confirmedDestination = event.detail.url;
            const { visit } = await import('@hotwired/turbo');
            visit(event.detail.url);
            confirmedDestination = null;
        }
    });
    window.addEventListener('beforeunload', event => {
        if (!submitting && [...document.forms].some(isDirty)) { event.preventDefault(); event.returnValue = ''; }
    });
}
