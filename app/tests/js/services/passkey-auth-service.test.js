import { webcrypto, generateKeyPairSync, sign } from 'crypto';
import { encodePasskeyBytes as b64, verifyPasskeyAssertion, serializePasskey } from '../../../assets/js/services/passkey-auth-service.js';

beforeEach(() => Object.defineProperty(global, 'crypto', { value: webcrypto, configurable: true }));

async function assertion(rsa = false) {
    const pair = rsa ? generateKeyPairSync('rsa', { modulusLength: 2048 }) : generateKeyPairSync('ec', { namedCurve: 'prime256v1' });
    const challenge = webcrypto.getRandomValues(new Uint8Array(32));
    const rawId = new Uint8Array([1, 2, 3]);
    const authentication = { origin: location.origin, rpId: location.hostname, algorithm: rsa ? -257 : -7,
        publicKey: b64(pair.publicKey.export({ type: 'spki', format: 'der' })), credentialId: b64(rawId) };
    const clientDataJSON = new TextEncoder().encode(JSON.stringify({ type: 'webauthn.get', origin: location.origin, challenge: b64(challenge) }));
    const authenticatorData = new Uint8Array(37);
    authenticatorData.set(new Uint8Array(await webcrypto.subtle.digest('SHA-256', new TextEncoder().encode(location.hostname))));
    authenticatorData[32] = 5;
    const signed = Buffer.concat([Buffer.from(authenticatorData), Buffer.from(await webcrypto.subtle.digest('SHA-256', clientDataJSON))]);
    const signature = new Uint8Array(sign('sha256', signed, pair.privateKey));
    return { challenge, authentication, credential: { rawId, response: { clientDataJSON, authenticatorData, signature },
        getClientExtensionResults: () => ({ prf: { results: { first: new Uint8Array(32) } } }) } };
}

test.each([false, true])('verifies a real signed offline assertion (RSA=%s)', async rsa => {
    const { credential, authentication, challenge } = await assertion(rsa);
    await expect(verifyPasskeyAssertion(credential, authentication, challenge)).resolves.toBeUndefined();
    expect(Object.keys(serializePasskey(credential)).sort()).toEqual(['authenticatorData', 'clientDataJSON', 'credentialId', 'signature']);
});

test.each(['signature', 'challenge', 'credential', 'origin', 'rpId', 'userVerification', 'userPresence', 'key', 'client', 'backup'])('rejects altered %s before offline PIN access', async field => {
    const { credential, authentication, challenge } = await assertion();
    if (field === 'signature') credential.response.signature[10] ^= 1;
    if (field === 'challenge') challenge[0] ^= 1;
    if (field === 'credential') credential.rawId[0] ^= 1;
    if (field === 'origin') authentication.origin = 'https://other.example';
    if (field === 'rpId') authentication.rpId = 'other.example';
    if (field === 'userVerification') credential.response.authenticatorData[32] = 1;
    if (field === 'userPresence') credential.response.authenticatorData[32] = 4;
    if (field === 'backup') credential.response.authenticatorData[32] = 21;
    if (field === 'key') authentication.publicKey = (await assertion()).authentication.publicKey;
    if (field === 'client') credential.response.clientDataJSON[5] ^= 1;
    await expect(verifyPasskeyAssertion(credential, authentication, challenge)).rejects.toThrow('PIN cannot replace');
});
