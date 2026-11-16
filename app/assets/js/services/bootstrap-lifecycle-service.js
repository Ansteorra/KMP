/** Bootstrap's instance registry must not retain nodes removed by Turbo. */
export function installBootstrapLifecycle(bootstrap) {
    const types = ['Tooltip', 'Popover', 'Dropdown', 'Tab', 'Collapse', 'Modal', 'Offcanvas', 'Toast'];
    function dispose(root) {
        const elements = [root, ...root.querySelectorAll(
            '[data-bs-toggle], [data-bs-original-title], .modal, .collapse, .offcanvas, .toast',
        )];
        for (const element of elements) {
            for (const type of types) bootstrap[type]?.getInstance(element)?.dispose();
            if (element.matches('.modal, .offcanvas')) {
                element.classList.remove('show', 'showing', 'hiding');
                element.setAttribute('aria-hidden', 'true');
                element.removeAttribute('aria-modal');
                element.style.removeProperty('display');
            }
        }
    }
    function initialize() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => {
            bootstrap.Tooltip.getOrCreateInstance(element);
        });
    }
    document.addEventListener('turbo:before-cache', () => {
        dispose(document.body);
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach(node => node.remove());
    });
    document.addEventListener('turbo:before-render', event => {
        if (event.detail?.renderMethod === 'morph') return;
        const render = event.detail?.render;
        if (typeof render === 'function') {
            event.detail.render = async (...args) => { dispose(document.body); return render(...args); };
        } else dispose(document.body);
    });
    document.addEventListener('turbo:before-frame-render', event => {
        const render = event.detail?.render;
        if (typeof render === 'function') {
            event.detail.render = (...args) => { dispose(event.target); return render(...args); };
        }
    });
    document.addEventListener('turbo:before-morph-element', event => {
        if (event.detail?.newElement) return;
        queueMicrotask(() => {
            if (!event.defaultPrevented && !event.target.isConnected) dispose(event.target);
        });
    });
    document.addEventListener('turbo:render', initialize);
    document.addEventListener('turbo:frame-load', initialize);
    document.addEventListener('turbo:morph-element', event => {
        if (!event.target.matches('[data-bs-toggle="tooltip"][title]')) return;
        bootstrap.Tooltip.getInstance(event.target)?.dispose();
        bootstrap.Tooltip.getOrCreateInstance(event.target);
    });
    initialize();
}
