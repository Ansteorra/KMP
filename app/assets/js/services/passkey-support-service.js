const FAILURE_KEY = 'kmp.offline.passkey-unavailable';
const RETRY_AFTER = 7 * 24 * 60 * 60 * 1000;

/** A capability hint only: never changes an existing vault or proves a passkey can decrypt it. */
export function forgetPasskeyFailure() {
    try { localStorage.removeItem(FAILURE_KEY); } catch { /* Storage is optional for this hint. */ }
}

export function unavailablePasskey(message) {
    try { localStorage.setItem(FAILURE_KEY, JSON.stringify({ browser: navigator.userAgent, until: Date.now() + RETRY_AFTER })); }
    catch { /* Still offer PIN for this setup when browser storage is unavailable. */ }
    return Object.assign(new Error(message), { code: 'PASSKEY_UNAVAILABLE' });
}

/** Check without creating a credential or opening a biometric/password-manager prompt. */
export async function checkPasskeySupport() {
    if (!globalThis.PublicKeyCredential || !navigator.credentials?.create || !navigator.credentials?.get || !crypto.subtle) {
        return { available: false, reason: 'browser' };
    }
    try {
        const failure = JSON.parse(localStorage.getItem(FAILURE_KEY) || 'null');
        if (failure?.browser === navigator.userAgent && failure.until > Date.now()) return { available: false, reason: 'provider' };
    } catch { /* Invalid or inaccessible hints do not block a fresh check. */ }
    let timer;
    try {
        return await Promise.race([
            (async () => {
                const api = globalThis.PublicKeyCredential;
                if (api.getClientCapabilities) {
                    const capabilities = await api.getClientCapabilities();
                    if (capabilities['extension:prf'] !== true) return { available: false, reason: 'browser' };
                }
                if (api.isUserVerifyingPlatformAuthenticatorAvailable && !await api.isUserVerifyingPlatformAuthenticatorAvailable()) {
                    return { available: false, reason: 'device' };
                }
                return { available: true };
            })(),
            new Promise(resolve => { timer = setTimeout(() => resolve({ available: true }), 2000); })
        ]);
    } catch { return { available: true }; }
    finally { clearTimeout(timer); }
}
