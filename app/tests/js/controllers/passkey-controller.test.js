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
    controller.post = jest.fn().mockResolvedValue({ publicKey: { challenge: 'AQID' } });
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
    controller.post = jest.fn().mockResolvedValue({ publicKey: { challenge: 'AQID' } });
    const pending = controller.login();
    await Promise.resolve(); await Promise.resolve();
    controller.disconnect();
    await pending;
    expect(controller.busy).toBe(false);
    expect(controller.abort.signal.aborted).toBe(true);
});
