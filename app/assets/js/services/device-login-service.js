import { shortSiteTitle } from './app-branding-service.js';
import vault from './offline-vault-service.js';
import { currentOfflineContext } from './offline-data-service.js';

let signingIn;
let lastFailure = 0;
let signingController, signingTimeout;
window.addEventListener('kmp:offline-revoked', () => signingController?.abort());

/** Return only verified account information; the password is never echoed by the server. */
export async function verifyDevicePassword(password) {
    const generation = vault.generation;
    const context = await currentOfflineContext();
    const response = await fetch('/offline/verify-login', { method: 'POST', cache: 'no-store', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': context.csrfToken },
        body: JSON.stringify({ password }), signal: AbortSignal.timeout?.(30000) });
    if (response.status === 429) throw new Error('Please wait a few minutes before trying your password again.');
    if (!response.ok) throw new Error(`Check your ${shortSiteTitle()} password and try again.`);
    if (generation !== vault.generation || response.headers.get('X-KMP-Offline-Owner') !== context.owner ||
        response.headers.get('X-KMP-Offline-Epoch') !== context.epoch) throw new Error('Your sign-in changed. Start device setup again.');
    const result = await response.json();
    if (!result.success || !result.email) throw new Error(`Check your ${shortSiteTitle()} password and try again.`);
    return { context, generation, login: { email: result.email, password } };
}

/** Use the normal password login, including fresh CSRF and form protection, after local unlock. */
export async function loginWithSavedPassword(force = false) {
    if (signingIn) return signingIn;
    signingIn = (async () => {
        if (!navigator.onLine || !await vault.openTrusted()) return null;
        if (!force && Date.now() - lastFailure < 60000) return null;
        const generation = vault.generation;
        const { login } = await vault.read(true);
        if (!login?.email || !login.password) return null;
        signingController = new AbortController();
        signingTimeout = setTimeout(() => signingController?.abort(), 10000);
        const options = { credentials: 'same-origin', cache: 'no-store', signal: signingController.signal };
        const loginUrl = /^\/members\/login\/?$/i.test(location.pathname) ? '/members/login' + location.search : '/members/login';
        const start = await fetch(loginUrl, options);
        if (!start.ok) throw new Error('Sign in is temporarily unavailable. Your saved information is still here.');
        const html = new DOMParser().parseFromString(await start.text(), 'text/html');
        const form = html.querySelector('[data-login-device-auth-target=passwordForm]');
        if (!form) { await currentOfflineContext(); return loginDestination(start.url); }
        if (generation !== vault.generation) throw new Error('Device was locked.');
        const body = new FormData(form);
        body.set('email_address', login.email); body.set('password', login.password);
        body.set('login_method', 'password'); body.delete('quick_login_enable');
        const response = await fetch(loginUrl, { ...options, method: 'POST', body });
        body.delete('password');
        if (generation !== vault.generation) throw new Error('Device was locked.');
        try { await currentOfflineContext(); }
        catch (error) {
            lastFailure = Date.now();
            throw error;
        }
        lastFailure = 0;
        return loginDestination(response.url);
    })().finally(() => { signingIn = null; signingController = null; clearTimeout(signingTimeout); });
    return signingIn;
}

/** Preserve the normal server redirect while excluding external or looping destinations. */
function loginDestination(url) {
    const destination = new URL(url || '/members/profile', location.origin);
    return destination.origin === location.origin && !/^\/members\/login\/?$/i.test(destination.pathname)
        ? destination.pathname + destination.search : '/members/profile';
}

/** Server authorization remains independent of the device PIN or passkey. */
export async function ensureDeviceSession() {
    try { return await currentOfflineContext(); }
    catch (error) {
        if (!error.message?.includes('Sign in')) throw error;
        if (!await loginWithSavedPassword()) throw error;
        return currentOfflineContext();
    }
}
