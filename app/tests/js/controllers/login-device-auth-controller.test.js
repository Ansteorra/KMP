import LoginController from '../../../assets/js/controllers/login-device-auth-controller.js';

test('removes legacy fast PIN verifiers and remembers only an explicitly selected identifier', () => {
    localStorage.setItem('kmp.quickLogin.config', JSON.stringify({ pinHash: 'old-fast-hash', pinSalt: 'salt' }));
    localStorage.setItem('kmp.quickLogin.deviceId', 'old-device');
    const controller = new LoginController();
    controller.emailTarget = document.createElement('input');
    controller.rememberIdTarget = document.createElement('input');
    controller.passwordFormTarget = document.createElement('form');
    controller.connect();
    expect(localStorage.getItem('kmp.quickLogin.config')).toBeNull();
    expect(localStorage.getItem('kmp.quickLogin.deviceId')).toBeNull();
    controller.emailTarget.value = 'test@example.invalid';
    controller.rememberIdTarget.checked = true;
    controller.passwordFormTarget.dispatchEvent(new Event('submit'));
    expect(localStorage.getItem('kmp.login.rememberedId')).toBe('test@example.invalid');
    controller.rememberIdTarget.checked = false;
    controller.passwordFormTarget.dispatchEvent(new Event('submit'));
    expect(localStorage.getItem('kmp.login.rememberedId')).toBeNull();
    controller.disconnect();
});
