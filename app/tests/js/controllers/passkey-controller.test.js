import PasskeyController from '../../../assets/js/controllers/passkey-controller.js';

let controller;
beforeEach(() => {
    controller = new PasskeyController();
    controller.statusTarget = document.createElement('p');
    controller.connected = true;
    controller.hasPasswordTarget = true;
    controller.passwordTarget = document.createElement('input');
    controller.passwordTarget.value = 'synthetic-password';
    Object.defineProperty(navigator, 'credentials', { configurable: true, value: { get: jest.fn(() => new Promise(() => {})) } });
});
afterEach(() => { jest.useRealTimers(); controller.disconnect(); });

test('an OS prompt that ignores abort still releases busy state and never submits a late assertion', async () => {
    jest.useFakeTimers();
    let finish;
    navigator.credentials.get.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    controller.post = jest.fn().mockImplementation(async () => ({ publicKey: { challenge: 'AQID' } }));
    const pending = controller.login();
    await jest.advanceTimersByTimeAsync(60000);
    await pending;
    expect(controller.busy).toBe(false);
    expect(controller.statusTarget.textContent).toContain('timed out');
    expect(controller.passwordTarget.value).toBe('');
    finish({ response: {} });
    await Promise.resolve(); await Promise.resolve();
    expect(controller.post).toHaveBeenCalledTimes(1);
});

test('leaving the page aborts the current authentication operation', async () => {
    controller.post = jest.fn().mockImplementation(async () => ({ publicKey: { challenge: 'AQID' } }));
    const pending = controller.login();
    await Promise.resolve(); await Promise.resolve();
    controller.disconnect();
    await pending;
    expect(controller.busy).toBe(false);
    expect(controller.abort.signal.aborted).toBe(true);
});

function wizard() {
    controller.headingTarget = document.createElement('h3');
    controller.headingTarget.tabIndex = -1;
    document.body.append(controller.headingTarget);
    controller.progressTarget = document.createElement('p');
    controller.panelTargets = ['intro', 'password', 'device', 'success'].map(step => {
        const panel = document.createElement('div'); panel.dataset.step = step; return panel;
    });
}

test('wizard presents one step, announces progress and focuses its heading', () => {
    wizard();
    controller.show('password');
    expect(controller.panelTargets.filter(panel => !panel.hidden).map(panel => panel.dataset.step)).toEqual(['password']);
    expect(controller.progressTarget.textContent).toBe('Step 2 of 3');
    expect(document.activeElement).toBe(controller.headingTarget);
});

test('going back discards password and pending enrollment options', () => {
    wizard();
    controller.abort = new AbortController(); controller.options = { secret: 'challenge' };
    controller.start();
    expect(controller.abort.signal.aborted).toBe(true);
    expect(controller.options).toBeNull();
    expect(controller.passwordTarget.value).toBe('');
    expect(controller.step).toBe('intro');
});

test('closing a modal aborts a native prompt and clears password and options', () => {
    controller.element = document.createElement('div');
    const modal = document.createElement('div'); modal.className = 'modal'; modal.append(controller.element);
    controller.connect();
    controller.abort = new AbortController(); controller.options = {};
    modal.dispatchEvent(new Event('hide.bs.modal'));
    expect(controller.abort.signal.aborted).toBe(true);
    expect(controller.connected).toBe(false);
    expect(controller.options).toBeNull();
    expect(controller.passwordTarget.value).toBe('');
});

function enableAutofill() {
    controller.autofillValue = true;
    Object.defineProperty(window, 'PublicKeyCredential', { configurable: true, value: {
        isConditionalMediationAvailable: jest.fn().mockResolvedValue(true)
    } });
    controller.post = jest.fn().mockImplementation(async () => ({ publicKey: { challenge: 'AQID' } }));
}

test('conditional autofill is account-independent, quiet, and abortable without blocking password entry', async () => {
    enableAutofill();
    const pending = controller.autofill();
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
    expect(controller.post).toHaveBeenCalledWith('login-options', {}, expect.any(AbortSignal));
    expect(navigator.credentials.get).toHaveBeenCalledWith(expect.objectContaining({ mediation: 'conditional' }));
    expect(controller.busy).toBeFalsy();
    expect(controller.statusTarget.textContent).toBe('');
    controller.cancelAutofill();
    await pending;
    expect(controller.autofillPending).toBeNull();
});

test('unsupported conditional UI does not request options or show an error', async () => {
    enableAutofill();
    window.PublicKeyCredential.isConditionalMediationAvailable.mockResolvedValue(false);
    await controller.autofill();
    expect(controller.post).not.toHaveBeenCalled();
    expect(controller.statusTarget.textContent).toBe('');
});

test('a stalled conditional OS request expires and a late credential is never submitted', async () => {
    jest.useFakeTimers();
    enableAutofill();
    let finish;
    navigator.credentials.get.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    const pending = controller.autofill();
    await jest.advanceTimersByTimeAsync(90000);
    await pending;
    expect(controller.autofillPending).toBeNull();
    expect(controller.statusTarget.textContent).toBe('');
    finish({ response: {} });
    await Promise.resolve(); await Promise.resolve();
    expect(controller.post).toHaveBeenCalledTimes(1);
});

test('explicit login replaces an ignored conditional prompt and disconnect cancels both', async () => {
    enableAutofill();
    const conditional = controller.autofill();
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
    const explicit = controller.login();
    await conditional;
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
    expect(controller.autofillAbort.signal.aborted).toBe(true);
    expect(navigator.credentials.get).toHaveBeenLastCalledWith(expect.not.objectContaining({ mediation: 'conditional' }));
    controller.disconnect();
    await explicit;
    expect(controller.post.mock.calls.map(call => call[0])).toEqual(['login-options', 'login-options']);
});


test('password submission cancels explicit authentication as well as conditional autofill', () => {
    controller.abort = new AbortController();
    controller.autofillAbort = new AbortController();
    controller.cancelLogin();
    expect(controller.abort.signal.aborted).toBe(true);
    expect(controller.autofillAbort.signal.aborted).toBe(true);
});

test('leaving during capability detection never issues a login challenge', async () => {
    enableAutofill();
    let finish;
    window.PublicKeyCredential.isConditionalMediationAvailable.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    const pending = controller.autofill();
    controller.disconnect();
    await pending;
    finish(true);
    await Promise.resolve(); await Promise.resolve();
    expect(controller.post).not.toHaveBeenCalled();
});
