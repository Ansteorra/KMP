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

  // ---------------------------------------------------------------------------
  // New tests – additional coverage
  // ---------------------------------------------------------------------------

  describe('isQuickLoginConfiguredForCurrentMember', () => {
    test('returns false when getQuickConfig() returns null', () => {
      const controller = new MobilePinGateController();
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue(null);

      expect(controller.isQuickLoginConfiguredForCurrentMember()).toBe(false);
    });

    test('returns false when emailValue is missing', () => {
      const controller = new MobilePinGateController();
      controller.hasEmailValue = false;
      controller.emailValue = '';
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue({
        email: 'test@example.com',
        deviceId: 'dev-1',
        pinSalt: 'salt',
        pinHash: 'hash',
      });

      expect(controller.isQuickLoginConfiguredForCurrentMember()).toBe(false);
    });

    test('returns false when emailValue is empty string', () => {
      const controller = new MobilePinGateController();
      controller.hasEmailValue = true;
      controller.emailValue = '   ';
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue({
        email: 'test@example.com',
        deviceId: 'dev-1',
        pinSalt: 'salt',
        pinHash: 'hash',
      });

      expect(controller.isQuickLoginConfiguredForCurrentMember()).toBe(false);
    });

    test('returns false when deviceId does not match', () => {
      const controller = new MobilePinGateController();
      controller.hasEmailValue = true;
      controller.emailValue = 'test@example.com';
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue({
        email: 'test@example.com',
        deviceId: 'dev-1',
        pinSalt: 'salt',
        pinHash: 'hash',
      });
      jest.spyOn(QuickLoginService, 'getOrCreateDeviceId').mockReturnValue('dev-OTHER');

      expect(controller.isQuickLoginConfiguredForCurrentMember()).toBe(false);
    });

    test('returns false when email does not match', () => {
      const controller = new MobilePinGateController();
      controller.hasEmailValue = true;
      controller.emailValue = 'other@example.com';
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue({
        email: 'test@example.com',
        deviceId: 'dev-1',
        pinSalt: 'salt',
        pinHash: 'hash',
      });
      jest.spyOn(QuickLoginService, 'getOrCreateDeviceId').mockReturnValue('dev-1');

      expect(controller.isQuickLoginConfiguredForCurrentMember()).toBe(false);
    });

    test('returns true when both deviceId and email match (case-insensitive)', () => {
      const controller = new MobilePinGateController();
      controller.hasEmailValue = true;
      controller.emailValue = 'Test@Example.COM';
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue({
        email: 'test@example.com',
        deviceId: 'dev-1',
        pinSalt: 'salt',
        pinHash: 'hash',
      });
      jest.spyOn(QuickLoginService, 'getOrCreateDeviceId').mockReturnValue('dev-1');

      expect(controller.isQuickLoginConfiguredForCurrentMember()).toBe(true);
    });
  });

  describe('isUnlocked', () => {
    test('returns false when session storage has no key', () => {
      const controller = new MobilePinGateController();

      expect(controller.isUnlocked()).toBe(false);
    });

    test('returns true when session storage value is "1"', () => {
      sessionStorage.setItem(unlockKey, '1');
      const controller = new MobilePinGateController();

      expect(controller.isUnlocked()).toBe(true);
    });

    test('returns false when session storage value is something other than "1"', () => {
      sessionStorage.setItem(unlockKey, 'yes');
      const controller = new MobilePinGateController();

      expect(controller.isUnlocked()).toBe(false);
    });
  });

  describe('isTrustedReferrer', () => {
    test('returns false for empty string', () => {
      const controller = new MobilePinGateController();
      expect(controller.isTrustedReferrer('')).toBe(false);
    });

    test('returns false for null', () => {
      const controller = new MobilePinGateController();
      expect(controller.isTrustedReferrer(null)).toBe(false);
    });

    test('returns false for undefined', () => {
      const controller = new MobilePinGateController();
      expect(controller.isTrustedReferrer(undefined)).toBe(false);
    });

    test('returns false for whitespace-only string', () => {
      const controller = new MobilePinGateController();
      expect(controller.isTrustedReferrer('   ')).toBe(false);
    });

    test('returns true for same-origin URL', () => {
      const controller = new MobilePinGateController();
      expect(controller.isTrustedReferrer(`${window.location.origin}/some/page`)).toBe(true);
    });

    test('returns false for cross-origin URL', () => {
      const controller = new MobilePinGateController();
      expect(controller.isTrustedReferrer('https://evil.example.com/page')).toBe(false);
    });

    test('returns false for non-string values', () => {
      const controller = new MobilePinGateController();
      expect(controller.isTrustedReferrer(42)).toBe(false);
    });
  });

  describe('gateMessage', () => {
    test('returns offline message when reason is "offline"', () => {
      const controller = new MobilePinGateController();
      expect(controller.gateMessage('offline')).toBe(
        "You're offline. Enter your quick login PIN to unlock this device."
      );
    });

    test('returns default session message for other reasons', () => {
      const controller = new MobilePinGateController();
      expect(controller.gateMessage('fresh-entry')).toBe(
        'Enter your quick login PIN to unlock this session.'
      );
    });

    test('returns default session message when reason is undefined', () => {
      const controller = new MobilePinGateController();
      expect(controller.gateMessage(undefined)).toBe(
        'Enter your quick login PIN to unlock this session.'
      );
    });
  });

  describe('showGate', () => {
    afterEach(() => {
      // Clean up any overlays left in DOM
      document.querySelectorAll('.mobile-pin-gate-overlay').forEach((el) => el.remove());
      document.body.style.overflow = '';
    });

    test('creates overlay DOM element appended to body', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');

      expect(controller.overlay).not.toBeNull();
      expect(document.querySelector('.mobile-pin-gate-overlay')).not.toBeNull();
      expect(document.body.style.overflow).toBe('hidden');
    });

    test('adds dialog semantics and wires title and message descriptions', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');

      expect(controller.overlay.getAttribute('role')).toBe('dialog');
      expect(controller.overlay.getAttribute('aria-modal')).toBe('true');
      expect(controller.overlay.getAttribute('aria-labelledby')).toBe('mobile-pin-gate-title');
      expect(controller.overlay.getAttribute('aria-describedby')).toBe('mobile-pin-gate-message');
      expect(document.getElementById('mobile-pin-gate-title').textContent).toBe('PIN Required');
      expect(controller.messageNode.id).toBe('mobile-pin-gate-message');
    });

    test('adds assertive alert semantics to error text', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');

      expect(controller.errorNode.getAttribute('role')).toBe('alert');
      expect(controller.errorNode.getAttribute('aria-live')).toBe('assertive');
    });

    test('renders sign out link without hiding the PIN gate', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.logoutUrlValue = '/Members/logout';
      controller.showGate('fresh-entry');

      const signOutLink = controller.overlay.querySelector('[data-mobile-pin-gate-sign-out]');
      expect(signOutLink).not.toBeNull();
      expect(signOutLink.getAttribute('href')).toBe('/Members/logout');
      expect(controller.overlay.isConnected).toBe(true);
    });

    test('sets internal references for form, pinInput, errorNode, messageNode', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');

      expect(controller.form).not.toBeNull();
      expect(controller.pinInput).not.toBeNull();
      expect(controller.errorNode).not.toBeNull();
      expect(controller.messageNode).not.toBeNull();
      expect(controller.submitButton).not.toBeNull();
      expect(controller.signOutLink).not.toBeNull();
    });

    test('does not create duplicate overlay when called twice with same reason', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');
      const firstOverlay = controller.overlay;
      controller.showGate('fresh-entry');

      expect(controller.overlay).toBe(firstOverlay);
      expect(document.querySelectorAll('.mobile-pin-gate-overlay').length).toBe(1);
    });

    test('updates message when called again with different reason', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');
      expect(controller.currentGateReason).toBe('fresh-entry');

      controller.showGate('offline');
      expect(controller.currentGateReason).toBe('offline');
      expect(controller.messageNode.textContent).toBe(controller.gateMessage('offline'));
    });

    test('displays the correct message for the given reason', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('offline');

      expect(controller.messageNode.textContent).toBe(
        "You're offline. Enter your quick login PIN to unlock this device."
      );
    });
  });

  describe('hideGate', () => {
    test('removes overlay from DOM and restores body overflow', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');
      expect(document.querySelector('.mobile-pin-gate-overlay')).not.toBeNull();

      controller.hideGate();

      expect(controller.overlay).toBeNull();
      expect(controller.form).toBeNull();
      expect(controller.pinInput).toBeNull();
      expect(controller.errorNode).toBeNull();
      expect(controller.messageNode).toBeNull();
      expect(controller.submitButton).toBeNull();
      expect(controller.signOutLink).toBeNull();
      expect(controller.currentGateReason).toBeNull();
      expect(document.querySelector('.mobile-pin-gate-overlay')).toBeNull();
      expect(document.body.style.overflow).toBe('');
    });

    test('restores previous focus when the previously focused element still exists', () => {
      document.body.innerHTML = '<button id="before-gate">Before</button>';
      const beforeGate = document.getElementById('before-gate');
      beforeGate.focus();
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');

      expect(document.activeElement).toBe(controller.pinInput);

      controller.hideGate();

      expect(document.activeElement).toBe(beforeGate);
    });

    test('does nothing when no overlay exists', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      // Should not throw
      controller.hideGate();

      expect(controller.overlay).toBeNull();
    });
  });

  describe('handleUnlockSubmit – error paths', () => {
    test('returns early when pinInput is null', async () => {
      const controller = new MobilePinGateController();
      controller.pinInput = null;
      controller.errorNode = document.createElement('div');
      const verifySpy = jest.spyOn(QuickLoginService, 'verifyPin');

      await controller.handleUnlockSubmit({ preventDefault: jest.fn() });

      expect(verifySpy).not.toHaveBeenCalled();
    });

    test('returns early when errorNode is null', async () => {
      const controller = new MobilePinGateController();
      controller.pinInput = document.createElement('input');
      controller.pinInput.value = '1234';
      controller.errorNode = null;
      const verifySpy = jest.spyOn(QuickLoginService, 'verifyPin');

      await controller.handleUnlockSubmit({ preventDefault: jest.fn() });

      expect(verifySpy).not.toHaveBeenCalled();
    });

    test('shows error for non-digit PIN', async () => {
      const controller = new MobilePinGateController();
      controller.pinInput = document.createElement('input');
      controller.pinInput.value = 'abcd';
      controller.errorNode = document.createElement('div');
      controller.errorNode.classList.add('d-none');
      const verifySpy = jest.spyOn(QuickLoginService, 'verifyPin');

      await controller.handleUnlockSubmit({ preventDefault: jest.fn() });

      expect(controller.errorNode.textContent).toBe('PIN must be 4 to 10 digits.');
      expect(controller.errorNode.classList.contains('d-none')).toBe(false);
      expect(verifySpy).not.toHaveBeenCalled();
    });

    test('shows error for PIN shorter than 4 digits', async () => {
      const controller = new MobilePinGateController();
      controller.pinInput = document.createElement('input');
      controller.pinInput.value = '12';
      controller.errorNode = document.createElement('div');
      controller.errorNode.classList.add('d-none');

      await controller.handleUnlockSubmit({ preventDefault: jest.fn() });

      expect(controller.errorNode.textContent).toBe('PIN must be 4 to 10 digits.');
      expect(controller.errorNode.classList.contains('d-none')).toBe(false);
    });

    test('shows error for PIN longer than 10 digits', async () => {
      const controller = new MobilePinGateController();
      controller.pinInput = document.createElement('input');
      controller.pinInput.value = '12345678901';
      controller.errorNode = document.createElement('div');
      controller.errorNode.classList.add('d-none');

      await controller.handleUnlockSubmit({ preventDefault: jest.fn() });

      expect(controller.errorNode.textContent).toBe('PIN must be 4 to 10 digits.');
      expect(controller.errorNode.classList.contains('d-none')).toBe(false);
    });

    test('shows error and clears input for incorrect PIN', async () => {
      const controller = new MobilePinGateController();
      controller.pinInput = document.createElement('input');
      controller.pinInput.value = '9999';
      controller.pinInput.focus = jest.fn();
      controller.errorNode = document.createElement('div');
      controller.errorNode.classList.add('d-none');
      jest.spyOn(QuickLoginService, 'verifyPin').mockResolvedValue(false);

      await controller.handleUnlockSubmit({ preventDefault: jest.fn() });

      expect(controller.errorNode.textContent).toBe('Incorrect PIN.');
      expect(controller.errorNode.classList.contains('d-none')).toBe(false);
      expect(controller.pinInput.value).toBe('');
      expect(controller.pinInput.focus).toHaveBeenCalled();
    });

    test('sets form busy state and disables submit while verifying PIN', async () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');
      controller.pinInput.value = '1234';
      let resolveVerification;
      jest.spyOn(QuickLoginService, 'verifyPin').mockImplementation(() => new Promise((resolve) => {
        resolveVerification = resolve;
      }));

      const submitPromise = controller.handleUnlockSubmit({ preventDefault: jest.fn() });

      expect(controller.form.getAttribute('aria-busy')).toBe('true');
      expect(controller.submitButton.disabled).toBe(true);

      resolveVerification(false);
      await submitPromise;

      expect(controller.form.getAttribute('aria-busy')).toBe('false');
      expect(controller.submitButton.disabled).toBe(false);
      expect(controller.errorNode.textContent).toBe('Incorrect PIN.');
    });

    test('calls preventDefault on the event', async () => {
      const controller = new MobilePinGateController();
      controller.pinInput = null;
      controller.errorNode = null;
      const event = { preventDefault: jest.fn() };

      await controller.handleUnlockSubmit(event);

      expect(event.preventDefault).toHaveBeenCalled();
    });
  });

  describe('focus containment and escape behavior', () => {
    afterEach(() => {
      document.querySelectorAll('.mobile-pin-gate-overlay').forEach((el) => el.remove());
      document.body.style.overflow = '';
    });

    test('traps Tab focus from the last focusable control back to the PIN input', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');
      controller.signOutLink.focus();
      const event = { key: 'Tab', shiftKey: false, preventDefault: jest.fn() };

      controller.handleGateKeydown(event);

      expect(event.preventDefault).toHaveBeenCalled();
      expect(document.activeElement).toBe(controller.pinInput);
    });

    test('traps Shift+Tab focus from the PIN input to the sign out link', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');
      controller.pinInput.focus();
      const event = { key: 'Tab', shiftKey: true, preventDefault: jest.fn() };

      controller.handleGateKeydown(event);

      expect(event.preventDefault).toHaveBeenCalled();
      expect(document.activeElement).toBe(controller.signOutLink);
    });

    test('Escape focuses sign out link and does not bypass the gate', () => {
      const controller = new MobilePinGateController();
      controller.initialize();
      controller.showGate('fresh-entry');
      const event = { key: 'Escape', preventDefault: jest.fn() };

      controller.handleGateKeydown(event);

      expect(event.preventDefault).toHaveBeenCalled();
      expect(document.activeElement).toBe(controller.signOutLink);
      expect(controller.overlay).not.toBeNull();
      expect(sessionStorage.getItem(unlockKey)).toBeNull();
    });
  });

  describe('enforceGate – additional branches', () => {
    test('hides gate when already unlocked', () => {
      sessionStorage.setItem(unlockKey, '1');
      const controller = new MobilePinGateController();
      controller.isQuickLoginConfiguredForCurrentMember = jest.fn().mockReturnValue(true);
      controller.showGate = jest.fn();
      controller.hideGate = jest.fn();

      controller.enforceGate();

      expect(controller.hideGate).toHaveBeenCalled();
      expect(controller.showGate).not.toHaveBeenCalled();
    });

    test('hides gate when quick login is not configured', () => {
      const controller = new MobilePinGateController();
      controller.isQuickLoginConfiguredForCurrentMember = jest.fn().mockReturnValue(false);
      controller.showGate = jest.fn();
      controller.hideGate = jest.fn();

      controller.enforceGate();

      expect(controller.hideGate).toHaveBeenCalled();
      expect(controller.showGate).not.toHaveBeenCalled();
    });

    test('hides gate when online, configured, not unlocked, but no fresh-entry requirement', () => {
      const controller = new MobilePinGateController();
      controller.requireFreshEntryPinCheck = false;
      controller.isQuickLoginConfiguredForCurrentMember = jest.fn().mockReturnValue(true);
      controller.isUnlocked = jest.fn().mockReturnValue(false);
      controller.showGate = jest.fn();
      controller.hideGate = jest.fn();
      setOnlineState(true);

      controller.enforceGate();

      expect(controller.hideGate).toHaveBeenCalled();
      expect(controller.showGate).not.toHaveBeenCalled();
    });
  });

  describe('handleVisibilityChange', () => {
    let originalVisibilityState;

    beforeEach(() => {
      originalVisibilityState = Object.getOwnPropertyDescriptor(document, 'visibilityState');
    });

    afterEach(() => {
      if (originalVisibilityState) {
        Object.defineProperty(document, 'visibilityState', originalVisibilityState);
      } else {
        delete document.visibilityState;
      }
    });

    test('calls enforceGate when document becomes visible', () => {
      Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        value: 'visible',
      });
      const controller = new MobilePinGateController();
      controller.enforceGate = jest.fn();

      controller.handleVisibilityChange();

      expect(controller.enforceGate).toHaveBeenCalledTimes(1);
    });

    test('does not call enforceGate when document is hidden', () => {
      Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        value: 'hidden',
      });
      const controller = new MobilePinGateController();
      controller.enforceGate = jest.fn();

      controller.handleVisibilityChange();

      expect(controller.enforceGate).not.toHaveBeenCalled();
    });
  });

  describe('currentNavigationType', () => {
    let originalGetEntries;

    beforeEach(() => {
      originalGetEntries = window.performance.getEntriesByType;
    });

    afterEach(() => {
      window.performance.getEntriesByType = originalGetEntries;
    });

    test('returns navigation type from performance API', () => {
      const controller = new MobilePinGateController();
      window.performance.getEntriesByType = jest.fn().mockReturnValue([{ type: 'reload' }]);

      expect(controller.currentNavigationType()).toBe('reload');
    });

    test('returns empty string when no navigation entries exist', () => {
      const controller = new MobilePinGateController();
      window.performance.getEntriesByType = jest.fn().mockReturnValue([]);

      expect(controller.currentNavigationType()).toBe('');
    });

    test('returns empty string when getEntriesByType is unavailable', () => {
      const controller = new MobilePinGateController();
      window.performance.getEntriesByType = undefined;

      expect(controller.currentNavigationType()).toBe('');
    });
  });

  describe('shouldRequireFreshEntryPinCheck – additional branches', () => {
    test('returns false for reload navigation type', () => {
      const controller = new MobilePinGateController();
      expect(controller.shouldRequireFreshEntryPinCheck('', 'reload')).toBe(false);
    });

    test('returns false for back_forward navigation type', () => {
      const controller = new MobilePinGateController();
      expect(controller.shouldRequireFreshEntryPinCheck('', 'back_forward')).toBe(false);
    });

    test('returns true for navigate type with no referrer', () => {
      const controller = new MobilePinGateController();
      expect(controller.shouldRequireFreshEntryPinCheck('', 'navigate')).toBe(true);
    });

    test('returns false when referrer is same-origin regardless of nav type', () => {
      const controller = new MobilePinGateController();
      const sameOrigin = `${window.location.origin}/dashboard`;
      expect(controller.shouldRequireFreshEntryPinCheck(sameOrigin, 'navigate')).toBe(false);
    });
  });
});
