import { tracePasskey, tracePasskeyError } from './passkey-debug-service.js';

const FAILURE_KEY = 'kmp.offline.passkey-unavailable';
const RETRY_AFTER = 7 * 24 * 60 * 60 * 1000;

/** A capability hint only: never changes an existing vault or proves a passkey can decrypt it. */
export function forgetPasskeyFailure() {
    tracePasskey('support-hint-cleared');
    try { localStorage.removeItem(FAILURE_KEY); } catch { /* Storage is optional for this hint. */ }
}

export function unavailablePasskey(message) {
    try { localStorage.setItem(FAILURE_KEY, JSON.stringify({ browser: navigator.userAgent, until: Date.now() + RETRY_AFTER })); }
    catch { /* Still offer PIN for this setup when browser storage is unavailable. */ }
    return Object.assign(new Error(message), { code: 'PASSKEY_UNAVAILABLE' });
}

/** Check without creating a credential or opening a biometric/password-manager prompt. */
export async function checkPasskeySupport() {
    tracePasskey('support-start', { hasCapabilityApi: typeof globalThis.PublicKeyCredential?.getClientCapabilities === 'function' });
    if (!globalThis.PublicKeyCredential || !navigator.credentials?.create || !navigator.credentials?.get || !crypto.subtle) {
        tracePasskey('support-api-missing');
        return { available: false, reason: 'browser' };
    }
    try {
        const failure = JSON.parse(localStorage.getItem(FAILURE_KEY) || 'null');
        if (failure?.browser === navigator.userAgent && failure.until > Date.now()) tracePasskey('support-cached-failure');
    } catch { /* Invalid or inaccessible hints do not block a fresh check. */ }
    let timer;
    try {
        return await Promise.race([
            (async () => {
                const api = globalThis.PublicKeyCredential;
                if (api.getClientCapabilities) {
                    const capabilities = await api.getClientCapabilities();
                    tracePasskey('support-prf', { prfEnabled: capabilities['extension:prf'] ?? null });
                    // PRF is optional: a working authentication passkey can use an offline PIN.
                }
                // Built-in authenticator availability does not cover password managers or security keys.
                tracePasskey('support-available');
                return { available: true };
            })(),
            new Promise(resolve => { timer = setTimeout(() => { tracePasskey('support-timeout'); resolve({ available: true }); }, 2000); })
        ]);
    } catch (error) { tracePasskeyError('support-error', error); return { available: true }; }
    finally { clearTimeout(timer); }
}
