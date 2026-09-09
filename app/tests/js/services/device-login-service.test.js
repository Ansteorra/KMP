jest.mock('../../../assets/js/services/offline-vault-service.js', () => ({ __esModule: true, default: {
    openTrusted: jest.fn(), read: jest.fn(), generation: 0
} }));
jest.mock('../../../assets/js/services/offline-data-service.js', () => ({ currentOfflineContext: jest.fn() }));
let service, vault, context, login, posted;
beforeEach(() => {
    jest.resetModules();
    service = require('../../../assets/js/services/device-login-service.js');
    vault = require('../../../assets/js/services/offline-vault-service.js').default;
    context = require('../../../assets/js/services/offline-data-service.js').currentOfflineContext;
    context.mockResolvedValue({ owner: 'owner', epoch: 'epoch', csrfToken: 'fresh' });
    login = { email: 'synthetic@example.test', password: crypto.randomUUID() };
    vault.read.mockResolvedValue({ login }); vault.openTrusted.mockResolvedValue(true);
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
    posted = null;
    global.fetch = jest.fn().mockResolvedValueOnce({ ok: true, text: async () => `<form data-login-device-auth-target="passwordForm">
        <input name="_csrfToken" value="new-csrf"><input name="_Token[fields]" value="new-form-token">
        <input name="email_address"><input name="password"><input name="quick_login_enable" value="1"></form>` })
        .mockImplementationOnce(async (url, options) => { posted = Object.fromEntries(options.body); return { ok: true, url: 'http://localhost/members/view/1' }; });
});
test('online unlock uses the ordinary password login with fresh form tokens', async () => {
    expect(await service.loginWithSavedPassword()).toBe('/members/view/1');
    expect(posted).toMatchObject({ _csrfToken: 'new-csrf', '_Token[fields]': 'new-form-token',
        email_address: login.email, password: login.password, login_method: 'password' });
    expect(posted).not.toHaveProperty('quick_login_enable');
    expect(context).toHaveBeenCalled();
});
test.each([false, true])('locked or offline devices never send a saved password: online=%s', async online => {
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: online });
    vault.openTrusted.mockResolvedValue(false);
    expect(await service.loginWithSavedPassword()).toBeNull();
    expect(fetch).not.toHaveBeenCalled();
});
test('logout during preparation prevents the password POST', async () => {
    fetch.mockReset().mockImplementationOnce(async () => { vault.generation++; return { ok: true, text: async () => '<form data-login-device-auth-target="passwordForm"></form>' }; });
    await expect(service.loginWithSavedPassword()).rejects.toThrow('locked');
    expect(fetch).toHaveBeenCalledTimes(1);
});
test('a failed server login does not turn local unlock into server authorization', async () => {
    context.mockRejectedValue(new Error('Sign in again'));
    await expect(service.loginWithSavedPassword()).rejects.toThrow('Sign in');
    expect(await service.loginWithSavedPassword()).toBeNull();
    expect(fetch).toHaveBeenCalledTimes(2);
});
test('verification rejects a password response from a different session binding', async () => {
    fetch.mockReset().mockResolvedValue({ ok: true, headers: new Headers({ 'X-KMP-Offline-Owner': 'other', 'X-KMP-Offline-Epoch': 'epoch' }) });
    await expect(service.verifyDevicePassword(login.password)).rejects.toThrow('sign-in changed');
});


afterEach(() => history.replaceState({}, '', '/'));
test('quick login preserves a requested return URL for the normal server validation', async () => {
    history.replaceState({}, '', '/members/login?redirect=%2Fgatherings%2Fmobile-calendar');
    await service.loginWithSavedPassword();
    expect(fetch.mock.calls[0][0]).toBe('/members/login?redirect=%2Fgatherings%2Fmobile-calendar');
    expect(fetch.mock.calls[1][0]).toBe('/members/login?redirect=%2Fgatherings%2Fmobile-calendar');
});
