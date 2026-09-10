import QuickLoginService from '../../../assets/js/services/quick-login-service.js';

beforeEach(() => localStorage.clear());
const legacy = { email: 'legacy@example.test', deviceId: 'legacy-device-id-1234', pinSalt: 'salt', pinHash: 'hash' };

test('retires old PIN credentials, preserves email, and keeps the reminder through password login', () => {
    localStorage.setItem(QuickLoginService.storageKeys.quickConfig, JSON.stringify(legacy));
    expect(QuickLoginService.beginPinMigration()).toBe(true);
    expect(QuickLoginService.getQuickConfig()).toBeNull();
    expect(QuickLoginService.getRememberedId()).toBe(legacy.email);
    QuickLoginService.clearLoginState();
    expect(QuickLoginService.beginPinMigration()).toBe(true);
    QuickLoginService.completePinMigration();
    expect(QuickLoginService.beginPinMigration()).toBe(false);
});

test('remembered email, device ID and malformed records do not identify old PIN users', () => {
    QuickLoginService.setRememberedId('remembered@example.test');
    QuickLoginService.getOrCreateDeviceId();
    expect(QuickLoginService.beginPinMigration()).toBe(false);
    localStorage.setItem(QuickLoginService.storageKeys.quickConfig, '{broken');
    expect(QuickLoginService.beginPinMigration()).toBe(false);
});

test('keeps the old configuration if the migration reminder cannot be stored', () => {
    localStorage.setItem(QuickLoginService.storageKeys.quickConfig, JSON.stringify(legacy));
    const spy = jest.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('Storage full'); });
    expect(QuickLoginService.beginPinMigration()).toBe(true);
    expect(QuickLoginService.getQuickConfig()).toEqual(legacy);
    spy.mockRestore();
});
