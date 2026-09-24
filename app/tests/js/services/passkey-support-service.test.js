import { passkeyDebug } from '../../../assets/js/services/passkey-debug-service.js';
import { checkPasskeySupport, unavailablePasskey, forgetPasskeyFailure } from '../../../assets/js/services/passkey-support-service.js';

let api;
beforeEach(() => {
    localStorage.clear();
    api = { getClientCapabilities: jest.fn().mockResolvedValue({ 'extension:prf': true }),
        isUserVerifyingPlatformAuthenticatorAvailable: jest.fn().mockResolvedValue(true) };
    Object.defineProperty(globalThis, 'PublicKeyCredential', { configurable: true, value: api });
    Object.defineProperty(navigator, 'credentials', { configurable: true, value: { create: jest.fn(), get: jest.fn() } });
    Object.defineProperty(globalThis, 'crypto', { configurable: true, value: { subtle: {} } });
});
afterEach(() => jest.useRealTimers());

test.each([{}, { 'extension:prf': false }])('allows authentication when browser capabilities omit PRF: %j', async capabilities => {
    api.getClientCapabilities.mockResolvedValue(capabilities);
    await expect(checkPasskeySupport()).resolves.toEqual({ available: true });
    expect(navigator.credentials.create).not.toHaveBeenCalled();
    expect(navigator.credentials.get).not.toHaveBeenCalled();
});
test('allows password managers and security keys without a built-in authenticator or prompt', async () => {
    api.isUserVerifyingPlatformAuthenticatorAvailable.mockResolvedValue(false);
    await expect(checkPasskeySupport()).resolves.toEqual({ available: true });
    expect(navigator.credentials.create).not.toHaveBeenCalled();
});
test('unknown older browsers can try a passkey; support still requires actual cryptographic verification', async () => {
    delete api.getClientCapabilities;
    await expect(checkPasskeySupport()).resolves.toEqual({ available: true });
});
test('an old PRF failure hint never disables authentication', async () => {
    const now = Date.now();
    expect(unavailablePasskey('Unavailable').code).toBe('PASSKEY_UNAVAILABLE');
    await expect(checkPasskeySupport()).resolves.toEqual({ available: true });
    forgetPasskeyFailure();
    await expect(checkPasskeySupport()).resolves.toEqual({ available: true });
    unavailablePasskey('Unavailable');
    jest.useFakeTimers().setSystemTime(now + 8 * 86400000);
    await expect(checkPasskeySupport()).resolves.toEqual({ available: true });
});
test('a hung capability probe never leaves setup waiting indefinitely', async () => {
    jest.useFakeTimers();
    api.getClientCapabilities.mockImplementation(() => new Promise(() => {}));
    const check = checkPasskeySupport();
    await jest.advanceTimersByTimeAsync(2000);
    await expect(check).resolves.toEqual({ available: true });
});


test('diagnostics identify a cached rejection and the capability result after recheck', async () => {
    const log = jest.spyOn(console, 'info').mockImplementation(() => {});
    passkeyDebug.start();
    try {
        unavailablePasskey('Not supported');
        await checkPasskeySupport();
        expect(JSON.parse(passkeyDebug.report()).events.some(event => event.stage === 'support-cached-failure')).toBe(true);
        forgetPasskeyFailure();
        await checkPasskeySupport();
        expect(JSON.parse(passkeyDebug.report()).events).toEqual(expect.arrayContaining([
            expect.objectContaining({ stage: 'support-hint-cleared' }),
            expect.objectContaining({ stage: 'support-prf', prfEnabled: true })
        ]));
    } finally { passkeyDebug.clear(); log.mockRestore(); }
});
