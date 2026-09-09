import SecuritySettingsController from '../../../assets/js/controllers/security-settings-controller.js';

let controller;
beforeEach(() => {
    document.body.innerHTML = '<div class="modal"><turbo-frame><div id="settings"><section data-step="home"><h3 tabindex="-1">Security</h3></section><section data-step="password-form" hidden><h3 tabindex="-1">New password</h3><input type="password" id="password"><input type="password" id="confirm"></section><p id="error"></p></div></turbo-frame></div>';
    controller = new SecuritySettingsController();
    controller.element = document.querySelector('#settings');
    controller.panelTargets = [...document.querySelectorAll('section')];
    controller.passwordTarget = document.querySelector('#password');
    controller.confirmationTarget = document.querySelector('#confirm');
    controller.errorTarget = document.querySelector('#error');
    controller.connect();
});
afterEach(() => controller.disconnect());

test('only the chosen task is shown and its heading receives focus', () => {
    controller.open({ currentTarget: { dataset: { step: 'password-form' } } });
    expect(controller.panelTargets.filter(panel => !panel.hidden).map(panel => panel.dataset.step)).toEqual(['password-form']);
    expect(document.activeElement.textContent).toBe('New password');
});

test('password mismatch prevents submission and points to the correction', () => {
    controller.passwordTarget.value = 'FirstPassword';
    controller.confirmationTarget.value = 'OtherPassword';
    const event = { preventDefault: jest.fn() };
    controller.validatePassword(event);
    expect(event.preventDefault).toHaveBeenCalled();
    expect(controller.confirmationTarget.getAttribute('aria-invalid')).toBe('true');
    expect(document.activeElement).toBe(controller.confirmationTarget);
});

test('closing or leaving the password step clears both entries', () => {
    controller.passwordTarget.value = 'FirstPassword'; controller.confirmationTarget.value = 'FirstPassword';
    controller.show('home');
    expect(controller.passwordTarget.value).toBe('');
    controller.passwordTarget.value = 'SecondPassword';
    controller.modal.dispatchEvent(new Event('hide.bs.modal'));
    expect(controller.passwordTarget.value).toBe('');
    expect(controller.confirmationTarget.value).toBe('');
});


test('device setup shows one task and restores the Security choices afterward', () => {
    controller.overviewTargets = [document.createElement('button'), document.createElement('h3')];
    controller.deviceSetup({ detail: { active: true } });
    expect(controller.overviewTargets.every(element => element.hidden)).toBe(true);
    controller.deviceSetup({ detail: { active: false } });
    expect(controller.overviewTargets.every(element => !element.hidden)).toBe(true);
});

test('registers the Security settings controller', () => {
    expect(window.Controllers['security-settings']).toBe(SecuritySettingsController);
});
