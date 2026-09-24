import { tracePasskey, tracePasskeyError } from './passkey-debug-service.js';

const randomChallenge = () => crypto.getRandomValues(new Uint8Array(32));
export const encodePasskeyBytes = value => btoa(String.fromCharCode(...new Uint8Array(value)));
export const decodePasskeyBytes = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
const equal = (a, b) => a.length === b.length && a.every((value, i) => value === b[i]);

/** Serialize only WebAuthn proof; extension/PRF results never leave the browser. */
export function serializePasskey(credential) {
    return { credentialId: encodePasskeyBytes(credential.rawId),
        clientDataJSON: encodePasskeyBytes(credential.response.clientDataJSON),
        ...(credential.response.attestationObject ? { attestationObject: encodePasskeyBytes(credential.response.attestationObject) } : {}),
        ...(credential.response.authenticatorData ? { authenticatorData: encodePasskeyBytes(credential.response.authenticatorData),
            signature: encodePasskeyBytes(credential.response.signature) } : {}) };
}

export async function registerPasskey(credential, context) {
    const response = await fetch('/offline/register-passkey', { method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': context.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(serializePasskey(credential)), signal: AbortSignal.timeout?.(30000) });
    if (!response.ok || response.headers.get('X-KMP-Offline-Owner') !== context.owner || response.headers.get('X-KMP-Offline-Epoch') !== context.epoch) {
        throw new Error('Passkey registration failed. Choose Back, confirm your password, and try again.');
    }
    const result = await response.json();
    if (!result.success || !result.credential) throw new Error('Passkey registration was not completed.');
    return result.credential;
}

/** A fresh challenge is fetched ahead of the user's click so the prompt retains user activation. */
export async function preparePasskeyLogin() {
    const response = await fetch('/members/passkey-options', { cache: 'no-store', credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: AbortSignal.timeout?.(10000) });
    if (!response.ok) throw new Error('Connect to sign in with your passkey.');
    const options = await response.json();
    if (!options.challenge || !options.csrfToken) throw new Error('Sign-in preparation failed. Please try again.');
    return { ...options, until: Date.now() + 240000 };
}

export async function submitPasskeyLogin(credential, options) {
    const query = /^\/members\/login\/?$/i.test(location.pathname) ? location.search : '';
    const response = await fetch('/members/passkey-login' + query, { method: 'POST', cache: 'no-store', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': options.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(serializePasskey(credential)), signal: AbortSignal.timeout?.(15000) });
    if (!response.ok) throw new Error(response.status === 429 ? 'Please wait a few minutes before trying again.'
        : 'Passkey sign-in failed. Try again or sign in with your password.');
    const destination = new URL(response.url, location.origin);
    if (destination.origin !== location.origin || /\/members\/(?:passkey-login|login)\/?$/i.test(destination.pathname)) {
        throw new Error('Passkey sign-in was not completed. Sign in with your password.');
    }
    return destination.pathname + destination.search;
}

/** Only authenticated sessions can revoke this browser's server registration. Local removal is separate. */
export async function removePasskey(authentication, context) {
    const response = await fetch('/offline/remove-passkey', { method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': context.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ credentialId: authentication.credentialId }), signal: AbortSignal.timeout?.(10000) });
    if (!response.ok) throw new Error('Connect and sign in to remove this passkey.');
}

/** Validate a fresh local assertion, including its signature, without contacting the server. */
export async function verifyPasskeyAssertion(credential, authentication, challenge) {
    try {
        if (!credential || authentication.origin !== location.origin || authentication.rpId !== location.hostname
            || !equal(new Uint8Array(credential.rawId), decodePasskeyBytes(authentication.credentialId))) throw new Error();
        const response = credential.response;
        const client = JSON.parse(new TextDecoder().decode(response.clientDataJSON));
        if (client.type !== 'webauthn.get' || client.origin !== authentication.origin || (client.crossOrigin ?? false) !== false
            || client.topOrigin !== undefined || !equal(decodePasskeyBytes(client.challenge), new Uint8Array(challenge))) throw new Error();
        const auth = new Uint8Array(response.authenticatorData);
        const rpHash = new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(authentication.rpId)));
        if (auth.length < 37 || !equal(auth.subarray(0, 32), rpHash) || (auth[32] & 5) !== 5
            || ((auth[32] & 16) && !(auth[32] & 8))) throw new Error();
        const ecdsa = authentication.algorithm === -7;
        if (!ecdsa && authentication.algorithm !== -257) throw new Error();
        const algorithm = ecdsa ? { name: 'ECDSA', namedCurve: 'P-256', hash: 'SHA-256' }
            : { name: 'RSASSA-PKCS1-v1_5', hash: 'SHA-256' };
        const key = await crypto.subtle.importKey('spki', decodePasskeyBytes(authentication.publicKey), algorithm, false, ['verify']);
        const hash = new Uint8Array(await crypto.subtle.digest('SHA-256', response.clientDataJSON));
        const signed = new Uint8Array(auth.length + hash.length); signed.set(auth); signed.set(hash, auth.length);
        const signature = ecdsa ? ecdsaSignature(new Uint8Array(response.signature)) : response.signature;
        if (!await crypto.subtle.verify(algorithm, key, signature, signed)) throw new Error();
        tracePasskey('assertion-verified');
    } catch (error) {
        tracePasskeyError('assertion-rejected', error);
        throw new Error('The passkey could not be verified. Try again; your offline PIN cannot replace this check.');
    }
}

/** WebAuthn ES256 uses DER; WebCrypto expects the fixed-width r || s representation. */
function ecdsaSignature(der) {
    if (der[0] !== 0x30 || der[1] !== der.length - 2) throw new Error('Invalid signature');
    let offset = 2;
    const signature = new Uint8Array(64);
    for (let part = 0; part < 2; part++) {
        if (der[offset++] !== 2) throw new Error('Invalid signature');
        let length = der[offset++];
        if (length < 1 || length > 33 || offset + length > der.length || (der[offset] & 0x80)) throw new Error('Invalid signature');
        if (length > 1 && der[offset] === 0) {
            if (!(der[offset + 1] & 0x80)) throw new Error('Invalid signature');
            offset++; length--;
        }
        if (length > 32) throw new Error('Invalid signature');
        signature.set(der.subarray(offset, offset + length), part * 32 + 32 - length); offset += length;
    }
    if (offset !== der.length) throw new Error('Invalid signature');
    return signature;
}

export function passkeyRequest(authentication, challenge = randomChallenge(), input = null) {
    return { publicKey: { challenge, rpId: authentication.rpId, userVerification: 'required', timeout: 120000,
        allowCredentials: [{ type: 'public-key', id: decodePasskeyBytes(authentication.credentialId) }],
        ...(input ? { extensions: { prf: { eval: { first: input } } } } : {}) } };
}
