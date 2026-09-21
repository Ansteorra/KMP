/** Opt-in, tab-local diagnostics. Never retain WebAuthn objects, identifiers, or key material. */
const LIMIT = 200;
const stages = new Set([
    'debug-start', 'debug-stop', 'support-start', 'support-api-missing', 'support-cached-failure',
    'support-prf', 'support-available', 'support-timeout', 'support-error', 'support-hint-cleared',
    'assertion-verified', 'assertion-rejected', 'setup-needs-pin', 'setup-online-only', 'setup-click', 'setup-error', 'setup-wait-wrap', 'setup-wait-verify', 'setup-complete',
    'create-start', 'create-returned', 'create-error', 'create-prf', 'create-prf-disabled',
    'get-start', 'get-returned', 'get-error', 'get-prf', 'get-prf-rejected', 'prompt-aborted',
    'derive-start', 'derive-complete', 'derive-error', 'seal-start', 'seal-complete', 'seal-error',
    'verify-start', 'verify-key-opened', 'verify-payload-opened', 'verify-committed', 'verify-error'
]);
const booleanFields = ['prfEnabled', 'hasPrf', 'credentialReturned', 'credentialMatches',
    'pendingCredential', 'generationMatches', 'passkeyUnavailable', 'hasCapabilityApi'];
const errorNames = new Set(['Error', 'TypeError', 'NotAllowedError', 'NotSupportedError', 'SecurityError',
    'AbortError', 'InvalidStateError', 'ConstraintError', 'OperationError', 'DataError', 'TimeoutError', 'UnknownError']);
const transports = new Set(['usb', 'nfc', 'ble', 'internal', 'hybrid', 'smart-card']);
let enabled = false;
let startedAt = 0;
let environment = null;
let events = [];
let dropped = 0;

/** Explicit field and value allowlists prevent accidentally sharing sensitive API responses. */
export function tracePasskey(stage, details = {}) {
    if (!enabled || !stages.has(stage)) return;
    try {
        const safe = {};
        for (const field of booleanFields) {
            if (typeof details[field] === 'boolean' || details[field] === null) safe[field] = details[field];
        }
        if (Number.isSafeInteger(details.prfBytes) && details.prfBytes >= 0) safe.prfBytes = details.prfBytes;
        if (errorNames.has(details.error)) safe.error = details.error;
        if (['platform', 'cross-platform'].includes(details.attachment)) safe.attachment = details.attachment;
        if (Array.isArray(details.transports)) safe.transports = [...new Set(details.transports.filter(value => transports.has(value)))];
        const entry = { ms: Math.round(performance.now() - startedAt), stage,
            online: navigator.onLine, focused: document.hasFocus(),
            visible: document.visibilityState === 'visible', userActivation: navigator.userActivation?.isActive ?? null,
            ...safe };
        events.push(entry);
        if (events.length > LIMIT) { events.shift(); dropped++; }
        // Log a snapshot, never a live object which could later acquire secrets.
        console.info('[KMP passkey debug] ' + JSON.stringify(entry));
    } catch { /* Diagnostics must never interrupt authentication. */ }
}

export function tracePasskeyError(stage, error) {
    try {
        tracePasskey(stage, { error: errorNames.has(error?.name) ? error.name : 'UnknownError',
            passkeyUnavailable: error?.code === 'PASSKEY_UNAVAILABLE' });
    } catch { /* Do not inspect arbitrary error messages, stacks, or causes. */ }
}

export const passkeyDebug = Object.freeze({
    start() {
        events = []; dropped = 0; startedAt = performance.now(); enabled = true;
        environment = { browser: navigator.userAgent, secureContext: globalThis.isSecureContext === true,
            publicKeyCredential: !!globalThis.PublicKeyCredential, create: !!navigator.credentials?.create,
            get: !!navigator.credentials?.get, webCrypto: !!globalThis.crypto?.subtle,
            capabilityApi: typeof globalThis.PublicKeyCredential?.getClientCapabilities === 'function' };
        tracePasskey('debug-start');
        return 'Passkey debug recording started. Reproduce setup, then copy(window.KMP_passkeyDebug.report()).';
    },
    stop() { tracePasskey('debug-stop'); enabled = false; },
    clear() { enabled = false; events = []; environment = null; dropped = 0; },
    report() { return JSON.stringify({ schema: 'kmp-passkey-debug-v1', enabled, environment, dropped, events }, null, 2); }
});

window.KMP_passkeyDebug = passkeyDebug;
