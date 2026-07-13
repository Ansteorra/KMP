
// export for others scripts to use
import { Application, Controller } from "@hotwired/stimulus";
import * as Turbo from "@hotwired/turbo";
import * as bootstrap from 'bootstrap';
import KMP_utils from './KMP_utils.js';
import KMP_accessibility from './KMP_accessibility.js';
import './timezone-utils.js';

// Import controllers
import './controllers/qrcode-controller.js';
import './controllers/timezone-input-controller.js';
import './controllers/security-debug-controller.js';
import './controllers/popover-controller.js';

// Disable Turbo Drive (automatic navigation) but keep Turbo Frames working
Turbo.session.drive = false;

//window.$ = $;
//window.jQuery = jQuery;
window.KMP_utils = KMP_utils;
window.bootstrap = bootstrap;
window.KMP_accessibility = KMP_accessibility;
window.KMP_accessibility.installCakeConfirmAdapter();
const stimulusApp = Application.start();
window.Stimulus = stimulusApp;

class StimulusReconnectController extends Controller {}

// load all the controllers that have registered in the window.Controllers object
for (const controller in window.Controllers) {
    stimulusApp.register(controller, window.Controllers[controller]);
}

stimulusApp.register('__stimulus-reconnect__', StimulusReconnectController);

/**
 * Modal markup is rendered late in the layout (or via turbo frames); Stimulus can
 * miss those scopes on the first pass. Touch the data-controller token so
 * ScopeObserver rebinds them.
 */
function reconnectMissedControllers(application) {
    document.querySelectorAll('[data-controller]').forEach((element) => {
        const token = (element.getAttribute('data-controller') || '')
            .trim()
            .split(/\s+/)
            .filter((identifier) => identifier && identifier !== '__stimulus-reconnect__');

        if (token.length === 0) {
            return;
        }

        const missing = token.some(
            (identifier) => !application.getControllerForElementAndIdentifier(element, identifier),
        );
        if (!missing) {
            return;
        }

        const restored = token.join(' ');
        element.setAttribute('data-controller', `${restored} __stimulus-reconnect__`);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                element.setAttribute('data-controller', restored);
            });
        });
    });
}

reconnectMissedControllers(stimulusApp);
document.addEventListener('turbo:render', () => reconnectMissedControllers(stimulusApp));
// Turbo frames (e.g. gathering tab cells) inject modal markup after the initial scan.
document.addEventListener('turbo:frame-load', () => reconnectMissedControllers(stimulusApp));

//activate boostrap tooltips
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

// Re-initialize tooltips after Turbo renders (for dynamically loaded content)
// Note: Popovers are handled by the popover Stimulus controller
document.addEventListener('turbo:render', () => {
    // Initialize new tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        if (!bootstrap.Tooltip.getInstance(el)) {
            new bootstrap.Tooltip(el);
        }
    });
});

/** Dismiss open Bootstrap modals before Turbo caches the page snapshot. */
document.addEventListener('turbo:before-cache', () => {
    document.querySelectorAll('.modal.show').forEach((modalEl) => {
        const instance = bootstrap.Modal.getInstance(modalEl);
        if (instance) {
            instance.hide();
        }
    });
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
});
