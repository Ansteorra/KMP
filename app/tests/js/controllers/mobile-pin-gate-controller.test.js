import MobilePinGateController from '../../../assets/js/controllers/mobile-pin-gate-controller.js';
import QuickLoginService from '../../../assets/js/services/quick-login-service.js';

describe('MobilePinGateController', () => {
  const unlockKey = 'kmp.quickLogin.sessionUnlocked';
  let originalNavigatorOnline;

  beforeEach(() => {
    sessionStorage.clear();
    originalNavigatorOnline = Object.getOwnPropertyDescriptor(window.navigator, 'onLine');
  });

  afterEach(() => {
    if (originalNavigatorOnline) {
      Object.defineProperty(window.navigator, 'onLine', originalNavigatorOnline);
    }
    sessionStorage.clear();
    jest.restoreAllMocks();
  });

  const setOnlineState = (isOnline) => {
    Object.defineProperty(window.navigator, 'onLine', {
      configurable: true,
      value: isOnline,
    });
  };

  test('requires fresh-entry PIN check when no trusted referrer is present', () => {
    const controller = new MobilePinGateController();

    expect(controller.shouldRequireFreshEntryPinCheck('', 'navigate')).toBe(true);
  });

  test('skips fresh-entry PIN check when referrer is same-origin', () => {
    const controller = new MobilePinGateController();
    const sameOriginReferrer = `${window.location.origin}/members/profile`;

    expect(controller.shouldRequireFreshEntryPinCheck(sameOriginReferrer, 'navigate')).toBe(false);
  });

  test('shows gate online for fresh-entry sessions', () => {
    const controller = new MobilePinGateController();
    controller.requireFreshEntryPinCheck = true;
    controller.isQuickLoginConfiguredForCurrentMember = jest.fn().mockReturnValue(true);
    controller.isUnlocked = jest.fn().mockReturnValue(false);
    controller.showGate = jest.fn();
    controller.hideGate = jest.fn();
    setOnlineState(true);

    controller.enforceGate();

    expect(controller.showGate).toHaveBeenCalledWith('fresh-entry');
    expect(controller.hideGate).not.toHaveBeenCalled();
  });

  test('shows offline gate when device is offline', () => {
    const controller = new MobilePinGateController();
    controller.requireFreshEntryPinCheck = true;
    controller.isQuickLoginConfiguredForCurrentMember = jest.fn().mockReturnValue(true);
    controller.isUnlocked = jest.fn().mockReturnValue(false);
    controller.showGate = jest.fn();
    setOnlineState(false);

    controller.enforceGate();

    expect(controller.showGate).toHaveBeenCalledWith('offline');
  });

  test('successful unlock stores session unlock and clears fresh-entry requirement', async () => {
    const controller = new MobilePinGateController();
    controller.requireFreshEntryPinCheck = true;
    controller.pinInput = document.createElement('input');
    controller.pinInput.value = '1234';
    controller.errorNode = document.createElement('div');
    controller.errorNode.classList.add('d-none');
    controller.hideGate = jest.fn();
    jest.spyOn(QuickLoginService, 'verifyPin').mockResolvedValue(true);

    await controller.handleUnlockSubmit({ preventDefault: jest.fn() });

    expect(sessionStorage.getItem(unlockKey)).toBe('1');
    expect(controller.requireFreshEntryPinCheck).toBe(false);
    expect(controller.hideGate).toHaveBeenCalled();
  });
});
