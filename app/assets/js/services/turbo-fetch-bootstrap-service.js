import { installFrameRequestLifecycle } from './frame-request-lifecycle-service.js';

// Turbo defines frame custom elements during import. Install request ownership
// first, before already-present eager frames can begin their initial requests.
const installed = Symbol.for('kmp.frame-request-lifecycle');
if (typeof window.fetch === 'function' && !window[installed]) {
    window[installed] = installFrameRequestLifecycle();
}
