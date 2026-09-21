import { devicePromptDismissed, rememberDevicePromptChoice } from '../../../assets/js/services/device-prompt-preference-service.js';

const account = (owner, impersonating = false) => {
    document.head.innerHTML = '';
    const meta = document.createElement('meta');
    meta.name = 'kmp-offline-session';
    meta.content = JSON.stringify({ owner, impersonating });
    document.head.append(meta);
};
beforeEach(() => { localStorage.clear(); sessionStorage.clear(); account('member-a'); });
afterEach(() => { jest.restoreAllMocks(); document.head.innerHTML = ''; });

test('remembers across cleared tab sessions and keeps accounts separate', () => {
    expect(rememberDevicePromptChoice(true)).toBe(true);
    sessionStorage.clear();
    expect(devicePromptDismissed()).toBe(true);
    account('member-b');
    expect(devicePromptDismissed()).toBe(false);
    account('member-a');
    expect(devicePromptDismissed()).toBe(true);
    rememberDevicePromptChoice(false);
    expect(devicePromptDismissed()).toBe(false);
});

test('signed-out and impersonated pages cannot change another account preference', () => {
    account(null);
    expect(rememberDevicePromptChoice(true)).toBe(false);
    account('member-a', true);
    expect(rememberDevicePromptChoice(true)).toBe(false);
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
});

test('offline removal remembers the saved owner without a server session', () => {
    account(null);
    rememberDevicePromptChoice(true, 'member-a');
    expect(devicePromptDismissed()).toBe(false);
    account('member-a');
    expect(devicePromptDismissed()).toBe(true);
});

test('blocked persistent storage falls back to the tab without claiming persistence', () => {
    const original = Storage.prototype.setItem;
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(function (key, value) {
        if (this === localStorage) throw new DOMException('Blocked', 'SecurityError');
        return original.call(this, key, value);
    });
    expect(rememberDevicePromptChoice(true)).toBe(false);
    expect(devicePromptDismissed()).toBe(true);
    rememberDevicePromptChoice(false);
    expect(devicePromptDismissed()).toBe(false);
});

test('unavailable storage does not break presentation', () => {
    jest.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('Blocked'); });
    jest.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('Blocked'); });
    expect(rememberDevicePromptChoice(true)).toBe(false);
    expect(devicePromptDismissed()).toBe(false);
});
