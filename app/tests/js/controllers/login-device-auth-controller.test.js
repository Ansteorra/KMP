import LoginController from '../../../assets/js/controllers/login-device-auth-controller.js';

let controller;
beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = '<form><input type="email" required><div><input type="password" required></div><button>Continue</button><button>Change email</button><input type="checkbox"></form>';
    controller = new LoginController();
    controller.emailTarget = document.querySelector('[type=email]');
    controller.passwordTarget = document.querySelector('[type=password]');
    controller.passwordStepTarget = document.querySelector('div');
    controller.continueTarget = document.querySelector('button');
    controller.changeEmailTarget = document.querySelectorAll('button')[1];
    controller.rememberIdTarget = document.querySelector('[type=checkbox]');
    controller.passwordFormTarget = document.querySelector('form');
    controller.dispatch = jest.fn();
});

const submit = () => {
    const event = new Event('submit', { cancelable: true });
    controller.submit(event);
    return event;
};

test('email step validates locally, then focuses password; back clears password and restores email focus', () => {
    controller.connect();
    expect(controller.passwordStepTarget.hidden).toBe(true);
    expect(controller.passwordTarget.disabled).toBe(true);
    expect(submit().defaultPrevented).toBe(true);
    expect(controller.passwordVisible).toBe(false);
    controller.emailTarget.value = 'unknown@example.invalid';
    expect(submit().defaultPrevented).toBe(true);
    expect(controller.passwordStepTarget.hidden).toBe(false);
    expect(document.activeElement).toBe(controller.passwordTarget);
    expect(controller.emailTarget.readOnly).toBe(true);
    expect(controller.dispatch).not.toHaveBeenCalled();
    controller.passwordTarget.value = 'secret';
    controller.back();
    expect(controller.passwordTarget.value).toBe('');
    expect(document.activeElement).toBe(controller.emailTarget);
    expect(controller.emailTarget.readOnly).toBe(false);
});

test('removes legacy PIN verifiers and remembers only an explicitly selected identifier on password submission', () => {
    localStorage.setItem('kmp.quickLogin.config', 'old-verifier');
    localStorage.setItem('kmp.quickLogin.deviceId', 'old-device');
    controller.connect();
    expect(localStorage.getItem('kmp.quickLogin.config')).toBeNull();
    expect(localStorage.getItem('kmp.quickLogin.deviceId')).toBeNull();
    controller.emailTarget.value = 'test@example.invalid';
    submit();
    controller.rememberIdTarget.checked = true;
    expect(submit().defaultPrevented).toBe(false);
    expect(controller.dispatch).toHaveBeenCalledWith('submit');
    expect(localStorage.getItem('kmp.login.rememberedId')).toBe('test@example.invalid');
    controller.rememberIdTarget.checked = false;
    submit();
    expect(localStorage.getItem('kmp.login.rememberedId')).toBeNull();
    controller.disconnect();
});

test('remembered identifiers do not overwrite a server-rendered retry email', () => {
    localStorage.setItem('kmp.login.rememberedId', 'remembered@example.invalid');
    controller.emailTarget.value = 'retry@example.invalid';
    controller.connect();
    expect(controller.emailTarget.value).toBe('retry@example.invalid');
});

test('blocked browser storage does not prevent progressing to password login', () => {
    const storage = jest.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('Blocked'); });
    controller.connect();
    controller.emailTarget.value = 'test@example.invalid';
    submit();
    expect(controller.passwordVisible).toBe(true);
    storage.mockRestore();
});


test('a rejected password attempt returns to password entry with the submitted email', () => {
    controller.retryValue = true;
    controller.emailTarget.value = 'retry@example.invalid';
    controller.connect();
    expect(controller.passwordVisible).toBe(true);
    expect(document.activeElement).toBe(controller.passwordTarget);
    expect(controller.emailTarget.value).toBe('retry@example.invalid');
});
