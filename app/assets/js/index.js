import './services/turbo-fetch-bootstrap-service.js';
import './services/icon-service.js';
import { installOfflineFetchCapture } from './services/offline-fetch-service.js';
import { reportOfflineSaveFailure } from './services/offline-runtime-service.js';
import { startOfflineSessionObserver } from './services/offline-session-service.js';

// export for others scripts to use
import { Application, Controller } from "@hotwired/stimulus";
import * as Turbo from "@hotwired/turbo";
import * as bootstrap from 'bootstrap';
import KMP_utils from './KMP_utils.js';
import KMP_accessibility from './KMP_accessibility.js';
import './timezone-utils.js';
import { installUiNavigation } from './services/ui-navigation-service.js';
import { installFormState } from './services/form-state-service.js';
import { installLiveUpdates } from './services/live-update-service.js';
import { installMutationRequestMetadata } from './services/mutation-request-service.js';
import { installBootstrapLifecycle } from './services/bootstrap-lifecycle-service.js';
import { installModalNavigation } from './services/modal-navigation-service.js';

// Import controllers
import './controllers/qrcode-controller.js';
import './controllers/timezone-input-controller.js';
import './controllers/security-debug-controller.js';
import './controllers/popover-controller.js';

// One navigation owner supports Drive and the frame/stream fallback.
installLiveUpdates(Turbo);
installUiNavigation(Turbo);
if (Turbo.session.drive) installFormState();

//window.$ = $;
//window.jQuery = jQuery;
window.KMP_utils = KMP_utils;
window.bootstrap = bootstrap;
window.KMP_accessibility = KMP_accessibility;
window.KMP_accessibility.installCakeConfirmAdapter();
installOfflineFetchCapture(window, reportOfflineSaveFailure);
installMutationRequestMetadata();
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

installBootstrapLifecycle(bootstrap);
installModalNavigation();

startOfflineSessionObserver();
