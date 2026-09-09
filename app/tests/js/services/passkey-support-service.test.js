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

test.each([{}, { 'extension:prf': false }])('hides the option when browser capabilities omit PRF: %j', async capabilities => {
    api.getClientCapabilities.mockResolvedValue(capabilities);
    await expect(checkPasskeySupport()).resolves.toEqual({ available: false, reason: 'browser' });
    expect(navigator.credentials.create).not.toHaveBeenCalled();
    expect(navigator.credentials.get).not.toHaveBeenCalled();
});
test('requires an available device authenticator without opening a prompt', async () => {
    api.isUserVerifyingPlatformAuthenticatorAvailable.mockResolvedValue(false);
    await expect(checkPasskeySupport()).resolves.toEqual({ available: false, reason: 'device' });
    expect(navigator.credentials.create).not.toHaveBeenCalled();
});
test('unknown older browsers can try a passkey; support still requires actual cryptographic verification', async () => {
    delete api.getClientCapabilities;
    await expect(checkPasskeySupport()).resolves.toEqual({ available: true });
});
test('remembers provider incompatibility until explicit recheck or expiry', async () => {
    const now = Date.now();
    expect(unavailablePasskey('Unavailable').code).toBe('PASSKEY_UNAVAILABLE');
    await expect(checkPasskeySupport()).resolves.toEqual({ available: false, reason: 'provider' });
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
