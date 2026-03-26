import LoginDeviceAuthController from '../../../assets/js/controllers/login-device-auth-controller.js';
import QuickLoginService from '../../../assets/js/services/quick-login-service.js';

describe('LoginDeviceAuthController', () => {
  afterEach(() => {
    jest.restoreAllMocks();
  });

  test('syncEmail preserves setup email when email input target is absent', () => {
    const controller = new LoginDeviceAuthController();
    controller.quickConfig = null;
    controller.hasQuickEmailTarget = false;
    controller.hasEmailTarget = false;
    controller.hasPinSetupEmailTarget = true;
    controller.pinSetupEmailTarget = { value: 'iris@ampdemo.com' };

    controller.syncEmail();

    expect(controller.pinSetupEmailTarget.value).toBe('iris@ampdemo.com');
  });

  test('handlePinSetupSubmit saves quick config and remembers setup email', async () => {
    const controller = new LoginDeviceAuthController();
    controller.isSubmittingPinSetup = false;
    controller.hasPinSetupPinTarget = true;
    controller.pinSetupPinTarget = { value: '1234' };
    controller.hasPinSetupConfirmTarget = true;
    controller.pinSetupConfirmTarget = { value: '1234' };
    controller.hasPinSetupEmailTarget = true;
    controller.pinSetupEmailTarget = { value: 'iris@ampdemo.com' };
    controller.hasPinSetupDeviceIdTarget = true;
    controller.pinSetupDeviceIdTarget = { value: 'device_id-1234567890' };
    controller.hasPinSetupFormTarget = true;
    controller.pinSetupFormTarget = { submit: jest.fn() };
    controller.deviceId = 'device_id-1234567890';

    const saveQuickConfigSpy = jest
      .spyOn(QuickLoginService, 'saveQuickConfig')
      .mockResolvedValue({
        email: 'iris@ampdemo.com',
        deviceId: 'device_id-1234567890',
        pinSalt: 'salt',
        pinHash: 'hash',
      });
    const rememberSpy = jest
      .spyOn(QuickLoginService, 'setRememberedId')
      .mockImplementation(() => {});
    const clearStateSpy = jest
      .spyOn(QuickLoginService, 'clearLoginState')
      .mockImplementation(() => {});
    const preventDefault = jest.fn();

    await controller.handlePinSetupSubmit({ preventDefault });

    expect(preventDefault).toHaveBeenCalled();
    expect(saveQuickConfigSpy).toHaveBeenCalledWith({
      email: 'iris@ampdemo.com',
      deviceId: 'device_id-1234567890',
      pin: '1234',
    });
    expect(rememberSpy).toHaveBeenCalledWith('iris@ampdemo.com');
    expect(clearStateSpy).not.toHaveBeenCalled();
    expect(controller.pinSetupFormTarget.submit).toHaveBeenCalled();
  });

  test('handlePinSetupSubmit stops when quick config cannot be saved', async () => {
    const controller = new LoginDeviceAuthController();
    controller.isSubmittingPinSetup = false;
    controller.hasPinSetupPinTarget = true;
    controller.pinSetupPinTarget = { value: '1234' };
    controller.hasPinSetupConfirmTarget = true;
    controller.pinSetupConfirmTarget = { value: '1234' };
    controller.hasPinSetupEmailTarget = true;
    controller.pinSetupEmailTarget = { value: 'iris@ampdemo.com' };
    controller.hasPinSetupDeviceIdTarget = true;
    controller.pinSetupDeviceIdTarget = { value: 'device_id-1234567890' };
    controller.hasPinSetupFormTarget = true;
    controller.pinSetupFormTarget = { submit: jest.fn() };
    controller.deviceId = 'device_id-1234567890';

    const saveQuickConfigSpy = jest
      .spyOn(QuickLoginService, 'saveQuickConfig')
      .mockResolvedValue(null);
    const preventDefault = jest.fn();

    await controller.handlePinSetupSubmit({ preventDefault });

    expect(preventDefault).toHaveBeenCalled();
    expect(saveQuickConfigSpy).toHaveBeenCalled();
    expect(controller.pinSetupFormTarget.submit).not.toHaveBeenCalled();
    expect(controller.isSubmittingPinSetup).toBe(false);
  });

  test('handleServerQuickLoginDisabled clears stale quick config and keeps email for password login', () => {
    const controller = new LoginDeviceAuthController();
    controller.quickConfig = {
      email: 'iris@ampdemo.com',
      deviceId: 'device_id-1234567890',
      pinSalt: 'salt',
      pinHash: 'hash',
    };
    controller.hasQuickDisabledTarget = true;
    controller.quickDisabledTarget = { value: '1' };
    controller.hasQuickDisabledEmailTarget = true;
    controller.quickDisabledEmailTarget = { value: 'iris@ampdemo.com' };

    const clearQuickConfigSpy = jest
      .spyOn(QuickLoginService, 'clearQuickConfig')
      .mockImplementation(() => {});
    const rememberSpy = jest
      .spyOn(QuickLoginService, 'setRememberedId')
      .mockImplementation(() => {});

    controller.handleServerQuickLoginDisabled();

    expect(clearQuickConfigSpy).toHaveBeenCalled();
    expect(rememberSpy).toHaveBeenCalledWith('iris@ampdemo.com');
    expect(controller.quickConfig).toBeNull();
    expect(controller.loginMode).toBe('password');
  });

  // ── applyDeviceId ──────────────────────────────────────────────────────

  describe('applyDeviceId', () => {
    test('sets deviceId on all three hidden fields when all targets exist', () => {
      const controller = new LoginDeviceAuthController();
      controller.deviceId = 'dev-abc';
      controller.hasPasswordDeviceIdTarget = true;
      controller.passwordDeviceIdTarget = { value: '' };
      controller.hasQuickDeviceIdTarget = true;
      controller.quickDeviceIdTarget = { value: '' };
      controller.hasPinSetupDeviceIdTarget = true;
      controller.pinSetupDeviceIdTarget = { value: '' };

      controller.applyDeviceId();

      expect(controller.passwordDeviceIdTarget.value).toBe('dev-abc');
      expect(controller.quickDeviceIdTarget.value).toBe('dev-abc');
      expect(controller.pinSetupDeviceIdTarget.value).toBe('dev-abc');
    });

    test('does nothing when no targets exist', () => {
      const controller = new LoginDeviceAuthController();
      controller.deviceId = 'dev-abc';
      controller.hasPasswordDeviceIdTarget = false;
      controller.hasQuickDeviceIdTarget = false;
      controller.hasPinSetupDeviceIdTarget = false;

      // should not throw
      controller.applyDeviceId();
    });

    test('sets only the targets that exist', () => {
      const controller = new LoginDeviceAuthController();
      controller.deviceId = 'dev-abc';
      controller.hasPasswordDeviceIdTarget = true;
      controller.passwordDeviceIdTarget = { value: '' };
      controller.hasQuickDeviceIdTarget = false;
      controller.hasPinSetupDeviceIdTarget = false;

      controller.applyDeviceId();

      expect(controller.passwordDeviceIdTarget.value).toBe('dev-abc');
    });

    test('writes empty string when deviceId is null (pinSetup keeps existing value)', () => {
      const controller = new LoginDeviceAuthController();
      controller.deviceId = null;
      controller.hasPasswordDeviceIdTarget = true;
      controller.passwordDeviceIdTarget = { value: 'old' };
      controller.hasQuickDeviceIdTarget = true;
      controller.quickDeviceIdTarget = { value: 'old' };
      controller.hasPinSetupDeviceIdTarget = true;
      controller.pinSetupDeviceIdTarget = { value: 'old' };

      controller.applyDeviceId();

      expect(controller.passwordDeviceIdTarget.value).toBe('');
      expect(controller.quickDeviceIdTarget.value).toBe('');
      // pinSetupDeviceId falls back to existing value via: this.deviceId || this.pinSetupDeviceIdTarget.value || ""
      expect(controller.pinSetupDeviceIdTarget.value).toBe('old');
    });
  });

  // ── getQuickConfigForDevice ────────────────────────────────────────────

  describe('getQuickConfigForDevice', () => {
    test('returns config when deviceId matches and email is valid', () => {
      const controller = new LoginDeviceAuthController();
      controller.deviceId = 'dev-abc';
      const cfg = { email: 'a@b.com', deviceId: 'dev-abc', pinSalt: 's', pinHash: 'h' };
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue(cfg);

      expect(controller.getQuickConfigForDevice()).toEqual(cfg);
    });

    test('returns null when service returns null', () => {
      const controller = new LoginDeviceAuthController();
      controller.deviceId = 'dev-abc';
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue(null);

      expect(controller.getQuickConfigForDevice()).toBeNull();
    });

    test('returns null when deviceId does not match', () => {
      const controller = new LoginDeviceAuthController();
      controller.deviceId = 'dev-abc';
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue({
        email: 'a@b.com', deviceId: 'dev-OTHER', pinSalt: 's', pinHash: 'h',
      });

      expect(controller.getQuickConfigForDevice()).toBeNull();
    });

    test('returns null when email is empty string', () => {
      const controller = new LoginDeviceAuthController();
      controller.deviceId = 'dev-abc';
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue({
        email: '   ', deviceId: 'dev-abc', pinSalt: 's', pinHash: 'h',
      });

      expect(controller.getQuickConfigForDevice()).toBeNull();
    });

    test('returns null when email is not a string', () => {
      const controller = new LoginDeviceAuthController();
      controller.deviceId = 'dev-abc';
      jest.spyOn(QuickLoginService, 'getQuickConfig').mockReturnValue({
        email: 123, deviceId: 'dev-abc', pinSalt: 's', pinHash: 'h',
      });

      expect(controller.getQuickConfigForDevice()).toBeNull();
    });
  });

  // ── currentEmail / setEmail ────────────────────────────────────────────

  describe('currentEmail', () => {
    test('returns emailTarget value when present', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: '  alice@test.com  ' };
      controller.hasPinSetupEmailTarget = false;

      expect(controller.currentEmail()).toBe('alice@test.com');
    });

    test('falls back to pinSetupEmailTarget when emailTarget absent', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = false;
      controller.hasPinSetupEmailTarget = true;
      controller.pinSetupEmailTarget = { value: 'bob@test.com' };

      expect(controller.currentEmail()).toBe('bob@test.com');
    });

    test('returns empty string when no email targets exist', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = false;
      controller.hasPinSetupEmailTarget = false;

      expect(controller.currentEmail()).toBe('');
    });
  });

  describe('setEmail', () => {
    test('sets emailTarget value when target exists', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: '' };

      controller.setEmail('alice@test.com');

      expect(controller.emailTarget.value).toBe('alice@test.com');
    });

    test('does nothing when emailTarget absent', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = false;

      // should not throw
      controller.setEmail('alice@test.com');
    });

    test('trims whitespace', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: '' };

      controller.setEmail('  spaced@test.com  ');

      expect(controller.emailTarget.value).toBe('spaced@test.com');
    });

    test('converts null to empty string', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: 'old' };

      controller.setEmail(null);

      expect(controller.emailTarget.value).toBe('');
    });
  });

  // ── initializeLoginMode ────────────────────────────────────────────────

  describe('initializeLoginMode', () => {
    function buildController(quickConfig, rememberedId) {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = quickConfig;
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: '' };
      controller.hasRememberIdTarget = true;
      controller.rememberIdTarget = { checked: false };
      // stub targets for applyMode
      controller.hasQuickExperienceTarget = false;
      controller.hasPasswordExperienceTarget = false;
      controller.hasModeTabsTarget = false;
      controller.hasQuickTabButtonTarget = false;
      controller.hasPasswordTabButtonTarget = false;
      controller.hasQuickLoginLabelTarget = false;
      controller.hasQuickEmailTarget = false;
      controller.hasPinSetupEmailTarget = false;

      jest.spyOn(QuickLoginService, 'getRememberedId').mockReturnValue(rememberedId);
      return controller;
    }

    test('sets quick mode when quickConfig exists', () => {
      const cfg = { email: 'a@b.com', deviceId: 'd', pinSalt: 's', pinHash: 'h' };
      const controller = buildController(cfg, '');

      controller.initializeLoginMode();

      expect(controller.loginMode).toBe('quick');
      expect(controller.emailTarget.value).toBe('a@b.com');
    });

    test('sets password mode when quickConfig is null', () => {
      const controller = buildController(null, 'remembered@test.com');

      controller.initializeLoginMode();

      expect(controller.loginMode).toBe('password');
      expect(controller.emailTarget.value).toBe('remembered@test.com');
    });

    test('checks rememberId when rememberedId is non-empty', () => {
      const controller = buildController(null, 'remembered@test.com');

      controller.initializeLoginMode();

      expect(controller.rememberIdTarget.checked).toBe(true);
    });

    test('unchecks rememberId when rememberedId is empty', () => {
      const controller = buildController(null, '');
      controller.rememberIdTarget.checked = true;

      controller.initializeLoginMode();

      expect(controller.rememberIdTarget.checked).toBe(false);
    });

    test('works when rememberIdTarget is absent', () => {
      const cfg = { email: 'a@b.com', deviceId: 'd', pinSalt: 's', pinHash: 'h' };
      const controller = buildController(cfg, '');
      controller.hasRememberIdTarget = false;

      // should not throw
      controller.initializeLoginMode();

      expect(controller.loginMode).toBe('quick');
    });
  });

  // ── applyMode ──────────────────────────────────────────────────────────

  describe('applyMode', () => {
    function buildControllerForApplyMode(quickConfig, loginMode) {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = quickConfig;
      controller.loginMode = loginMode;

      controller.hasQuickExperienceTarget = true;
      controller.quickExperienceTarget = { classList: { toggle: jest.fn() } };

      controller.hasPasswordExperienceTarget = true;
      controller.passwordExperienceTarget = { classList: { toggle: jest.fn() } };

      controller.hasModeTabsTarget = true;
      controller.modeTabsTarget = { classList: { toggle: jest.fn() } };

      controller.hasQuickTabButtonTarget = true;
      controller.quickTabButtonTarget = {
        disabled: false,
        classList: { toggle: jest.fn() },
        setAttribute: jest.fn(),
      };

      controller.hasPasswordTabButtonTarget = true;
      controller.passwordTabButtonTarget = {
        classList: { toggle: jest.fn() },
        setAttribute: jest.fn(),
      };

      controller.hasQuickLoginLabelTarget = true;
      controller.quickLoginLabelTarget = { textContent: '' };

      return controller;
    }

    test('shows quick experience when quickConfig exists and mode is quick', () => {
      const cfg = { email: 'a@b.com', deviceId: 'd', pinSalt: 's', pinHash: 'h' };
      const controller = buildControllerForApplyMode(cfg, 'quick');

      controller.applyMode();

      expect(controller.quickExperienceTarget.classList.toggle).toHaveBeenCalledWith('d-none', false);
      expect(controller.passwordExperienceTarget.classList.toggle).toHaveBeenCalledWith('d-none', true);
      expect(controller.modeTabsTarget.classList.toggle).toHaveBeenCalledWith('d-none', false);
      expect(controller.quickTabButtonTarget.disabled).toBe(false);
      expect(controller.quickTabButtonTarget.classList.toggle).toHaveBeenCalledWith('active', true);
      expect(controller.quickTabButtonTarget.setAttribute).toHaveBeenCalledWith('aria-selected', 'true');
      expect(controller.passwordTabButtonTarget.classList.toggle).toHaveBeenCalledWith('active', false);
      expect(controller.passwordTabButtonTarget.setAttribute).toHaveBeenCalledWith('aria-selected', 'false');
      expect(controller.quickLoginLabelTarget.textContent).toBe(
        'Enter your PIN to use quick login as a@b.com.'
      );
    });

    test('shows password experience when quickConfig exists but mode is password', () => {
      const cfg = { email: 'a@b.com', deviceId: 'd', pinSalt: 's', pinHash: 'h' };
      const controller = buildControllerForApplyMode(cfg, 'password');

      controller.applyMode();

      expect(controller.quickExperienceTarget.classList.toggle).toHaveBeenCalledWith('d-none', true);
      expect(controller.passwordExperienceTarget.classList.toggle).toHaveBeenCalledWith('d-none', false);
      // tabs still visible because quickConfig exists
      expect(controller.modeTabsTarget.classList.toggle).toHaveBeenCalledWith('d-none', false);
      expect(controller.quickTabButtonTarget.disabled).toBe(false);
    });

    test('hides tabs and quick when quickConfig is null', () => {
      const controller = buildControllerForApplyMode(null, 'password');

      controller.applyMode();

      expect(controller.quickExperienceTarget.classList.toggle).toHaveBeenCalledWith('d-none', true);
      expect(controller.passwordExperienceTarget.classList.toggle).toHaveBeenCalledWith('d-none', false);
      expect(controller.modeTabsTarget.classList.toggle).toHaveBeenCalledWith('d-none', true);
      expect(controller.quickTabButtonTarget.disabled).toBe(true);
      expect(controller.quickLoginLabelTarget.textContent).toBe(
        'Enter your PIN to use quick login on this device.'
      );
    });

    test('works when optional targets are missing', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = null;
      controller.loginMode = 'password';
      controller.hasQuickExperienceTarget = false;
      controller.hasPasswordExperienceTarget = false;
      controller.hasModeTabsTarget = false;
      controller.hasQuickTabButtonTarget = false;
      controller.hasPasswordTabButtonTarget = false;
      controller.hasQuickLoginLabelTarget = false;

      // should not throw
      controller.applyMode();
    });
  });

  // ── switchToPassword / switchToQuick ───────────────────────────────────

  describe('switchToPassword', () => {
    function buildSwitchController(quickConfig, rememberedId) {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = quickConfig;
      controller.loginMode = 'quick';
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: '' };
      controller.hasQuickExperienceTarget = false;
      controller.hasPasswordExperienceTarget = false;
      controller.hasModeTabsTarget = false;
      controller.hasQuickTabButtonTarget = false;
      controller.hasPasswordTabButtonTarget = false;
      controller.hasQuickLoginLabelTarget = false;
      controller.hasQuickEmailTarget = false;
      controller.hasPinSetupEmailTarget = false;

      jest.spyOn(QuickLoginService, 'getRememberedId').mockReturnValue(rememberedId);
      return controller;
    }

    test('switches to password mode using rememberedId', () => {
      const cfg = { email: 'quick@test.com', deviceId: 'd', pinSalt: 's', pinHash: 'h' };
      const controller = buildSwitchController(cfg, 'remembered@test.com');
      const event = { preventDefault: jest.fn() };

      controller.switchToPassword(event);

      expect(event.preventDefault).toHaveBeenCalled();
      expect(controller.loginMode).toBe('password');
      expect(controller.emailTarget.value).toBe('remembered@test.com');
    });

    test('falls back to quickConfig email when rememberedId is empty', () => {
      const cfg = { email: 'quick@test.com', deviceId: 'd', pinSalt: 's', pinHash: 'h' };
      const controller = buildSwitchController(cfg, '');
      const event = { preventDefault: jest.fn() };

      controller.switchToPassword(event);

      expect(controller.emailTarget.value).toBe('quick@test.com');
    });

    test('sets empty email when no quickConfig and no rememberedId', () => {
      const controller = buildSwitchController(null, '');
      const event = { preventDefault: jest.fn() };

      controller.switchToPassword(event);

      expect(controller.emailTarget.value).toBe('');
    });
  });

  describe('switchToQuick', () => {
    test('switches to quick mode when quickConfig exists', () => {
      const cfg = { email: 'quick@test.com', deviceId: 'd', pinSalt: 's', pinHash: 'h' };
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = cfg;
      controller.loginMode = 'password';
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: '' };
      controller.hasQuickExperienceTarget = false;
      controller.hasPasswordExperienceTarget = false;
      controller.hasModeTabsTarget = false;
      controller.hasQuickTabButtonTarget = false;
      controller.hasPasswordTabButtonTarget = false;
      controller.hasQuickLoginLabelTarget = false;
      controller.hasQuickEmailTarget = false;
      controller.hasPinSetupEmailTarget = false;
      const event = { preventDefault: jest.fn() };

      controller.switchToQuick(event);

      expect(event.preventDefault).toHaveBeenCalled();
      expect(controller.loginMode).toBe('quick');
      expect(controller.emailTarget.value).toBe('quick@test.com');
    });

    test('returns early when quickConfig is null', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = null;
      controller.loginMode = 'password';
      const event = { preventDefault: jest.fn() };

      controller.switchToQuick(event);

      expect(event.preventDefault).toHaveBeenCalled();
      expect(controller.loginMode).toBe('password');
    });
  });

  // ── syncQuickPreference ────────────────────────────────────────────────

  describe('syncQuickPreference', () => {
    test('checks and disables rememberId when quickEnable is checked', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasQuickEnableTarget = true;
      controller.quickEnableTarget = { checked: true };
      controller.hasRememberIdTarget = true;
      controller.rememberIdTarget = { checked: false, disabled: false };

      controller.syncQuickPreference();

      expect(controller.rememberIdTarget.checked).toBe(true);
      expect(controller.rememberIdTarget.disabled).toBe(true);
    });

    test('enables rememberId when quickEnable is unchecked', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasQuickEnableTarget = true;
      controller.quickEnableTarget = { checked: false };
      controller.hasRememberIdTarget = true;
      controller.rememberIdTarget = { checked: true, disabled: true };

      controller.syncQuickPreference();

      expect(controller.rememberIdTarget.disabled).toBe(false);
    });

    test('returns early when quickEnableTarget is missing', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasQuickEnableTarget = false;
      controller.hasRememberIdTarget = true;
      controller.rememberIdTarget = { checked: false, disabled: false };

      controller.syncQuickPreference();

      expect(controller.rememberIdTarget.disabled).toBe(false);
    });

    test('returns early when rememberIdTarget is missing', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasQuickEnableTarget = true;
      controller.quickEnableTarget = { checked: true };
      controller.hasRememberIdTarget = false;

      // should not throw
      controller.syncQuickPreference();
    });
  });

  // ── syncEmail ──────────────────────────────────────────────────────────

  describe('syncEmail', () => {
    test('sets quickEmailTarget from quickConfig email', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = { email: 'quick@test.com' };
      controller.hasQuickEmailTarget = true;
      controller.quickEmailTarget = { value: '' };
      controller.hasPinSetupEmailTarget = false;
      controller.hasEmailTarget = false;

      controller.syncEmail();

      expect(controller.quickEmailTarget.value).toBe('quick@test.com');
    });

    test('sets pinSetupEmail from currentEmail when both email and pinSetupEmail targets exist', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = null;
      controller.hasQuickEmailTarget = false;
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: 'current@test.com' };
      controller.hasPinSetupEmailTarget = true;
      controller.pinSetupEmailTarget = { value: '' };

      controller.syncEmail();

      expect(controller.pinSetupEmailTarget.value).toBe('current@test.com');
    });

    test('does not set pinSetupEmail when emailTarget is absent', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = null;
      controller.hasQuickEmailTarget = false;
      controller.hasEmailTarget = false;
      controller.hasPinSetupEmailTarget = true;
      controller.pinSetupEmailTarget = { value: 'untouched@test.com' };

      controller.syncEmail();

      expect(controller.pinSetupEmailTarget.value).toBe('untouched@test.com');
    });
  });

  // ── handlePasswordSubmit ───────────────────────────────────────────────

  describe('handlePasswordSubmit', () => {
    test('remembers email when rememberId is checked and email is non-empty', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: 'alice@test.com' };
      controller.hasQuickEnableTarget = false;
      controller.hasRememberIdTarget = true;
      controller.rememberIdTarget = { checked: true };

      const setRememberedSpy = jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});
      const clearRememberedSpy = jest.spyOn(QuickLoginService, 'clearRememberedId').mockImplementation(() => {});
      const clearStateSpy = jest.spyOn(QuickLoginService, 'clearLoginState').mockImplementation(() => {});

      controller.handlePasswordSubmit();

      expect(setRememberedSpy).toHaveBeenCalledWith('alice@test.com');
      expect(clearRememberedSpy).not.toHaveBeenCalled();
      expect(clearStateSpy).toHaveBeenCalled();
    });

    test('clears rememberedId when rememberId is unchecked', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: 'alice@test.com' };
      controller.hasQuickEnableTarget = false;
      controller.hasRememberIdTarget = true;
      controller.rememberIdTarget = { checked: false };

      const setRememberedSpy = jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});
      const clearRememberedSpy = jest.spyOn(QuickLoginService, 'clearRememberedId').mockImplementation(() => {});
      jest.spyOn(QuickLoginService, 'clearLoginState').mockImplementation(() => {});

      controller.handlePasswordSubmit();

      expect(clearRememberedSpy).toHaveBeenCalled();
      expect(setRememberedSpy).not.toHaveBeenCalled();
    });

    test('clears rememberedId when email is empty even with rememberId checked', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: '  ' };
      controller.hasQuickEnableTarget = false;
      controller.hasRememberIdTarget = true;
      controller.rememberIdTarget = { checked: true };

      const clearRememberedSpy = jest.spyOn(QuickLoginService, 'clearRememberedId').mockImplementation(() => {});
      jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});
      jest.spyOn(QuickLoginService, 'clearLoginState').mockImplementation(() => {});

      controller.handlePasswordSubmit();

      // email is empty after trim, so even though rememberId is checked
      // the condition (rememberSelected && email !== "") is false
      expect(clearRememberedSpy).toHaveBeenCalled();
    });

    test('calls clearLoginState and syncQuickPreference when quickEnable is checked', () => {
      const controller = new LoginDeviceAuthController();
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: 'alice@test.com' };
      controller.hasQuickEnableTarget = true;
      controller.quickEnableTarget = { checked: true };
      controller.hasRememberIdTarget = true;
      controller.rememberIdTarget = { checked: false, disabled: false };

      jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});
      jest.spyOn(QuickLoginService, 'clearRememberedId').mockImplementation(() => {});
      const clearStateSpy = jest.spyOn(QuickLoginService, 'clearLoginState').mockImplementation(() => {});

      controller.handlePasswordSubmit();

      expect(clearStateSpy).toHaveBeenCalled();
      // quickSelected forces rememberIdTarget.checked = true via syncQuickPreference
      expect(controller.rememberIdTarget.checked).toBe(true);
    });
  });

  // ── handleQuickSubmit ──────────────────────────────────────────────────

  describe('handleQuickSubmit', () => {
    test('sets quickEmail and remembers email from quickConfig', () => {
      const cfg = { email: 'quick@test.com', deviceId: 'd', pinSalt: 's', pinHash: 'h' };
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = cfg;
      controller.hasQuickEmailTarget = true;
      controller.quickEmailTarget = { value: '' };
      controller.hasEmailTarget = false;
      controller.hasPinSetupEmailTarget = false;

      const rememberSpy = jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});

      controller.handleQuickSubmit();

      expect(controller.quickEmailTarget.value).toBe('quick@test.com');
      expect(rememberSpy).toHaveBeenCalledWith('quick@test.com');
    });

    test('uses currentEmail when quickConfig is null', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = null;
      controller.hasQuickEmailTarget = true;
      controller.quickEmailTarget = { value: '' };
      controller.hasEmailTarget = true;
      controller.emailTarget = { value: 'typed@test.com' };
      controller.hasPinSetupEmailTarget = false;

      const rememberSpy = jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});

      controller.handleQuickSubmit();

      expect(controller.quickEmailTarget.value).toBe('typed@test.com');
      expect(rememberSpy).toHaveBeenCalledWith('typed@test.com');
    });

    test('does not remember when email is empty', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = null;
      controller.hasQuickEmailTarget = false;
      controller.hasEmailTarget = false;
      controller.hasPinSetupEmailTarget = false;

      const rememberSpy = jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});

      controller.handleQuickSubmit();

      expect(rememberSpy).not.toHaveBeenCalled();
    });
  });

  // ── handlePinSetupSubmit (additional cases) ────────────────────────────

  describe('handlePinSetupSubmit additional cases', () => {
    test('returns early when already submitting', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = true;
      controller.hasPinSetupPinTarget = true;
      controller.hasPinSetupConfirmTarget = true;
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };

      const preventDefault = jest.fn();
      await controller.handlePinSetupSubmit({ preventDefault });

      expect(preventDefault).not.toHaveBeenCalled();
      expect(controller.pinSetupFormTarget.submit).not.toHaveBeenCalled();
    });

    test('returns early when pinSetupPinTarget is missing', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = false;
      controller.hasPinSetupConfirmTarget = true;
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };

      const preventDefault = jest.fn();
      await controller.handlePinSetupSubmit({ preventDefault });

      expect(preventDefault).not.toHaveBeenCalled();
    });

    test('returns early when pinSetupConfirmTarget is missing', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.hasPinSetupConfirmTarget = false;
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };

      const preventDefault = jest.fn();
      await controller.handlePinSetupSubmit({ preventDefault });

      expect(preventDefault).not.toHaveBeenCalled();
    });

    test('returns early when pinSetupFormTarget is missing', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.hasPinSetupConfirmTarget = true;
      controller.hasPinSetupFormTarget = false;

      const preventDefault = jest.fn();
      await controller.handlePinSetupSubmit({ preventDefault });

      expect(preventDefault).not.toHaveBeenCalled();
    });

    test('rejects PIN with letters', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.pinSetupPinTarget = { value: 'abcd' };
      controller.hasPinSetupConfirmTarget = true;
      controller.pinSetupConfirmTarget = { value: 'abcd' };
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };

      const saveQuickConfigSpy = jest.spyOn(QuickLoginService, 'saveQuickConfig');
      const preventDefault = jest.fn();

      await controller.handlePinSetupSubmit({ preventDefault });

      expect(preventDefault).toHaveBeenCalled();
      expect(saveQuickConfigSpy).not.toHaveBeenCalled();
      expect(controller.pinSetupFormTarget.submit).not.toHaveBeenCalled();
    });

    test('rejects PIN shorter than 4 digits', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.pinSetupPinTarget = { value: '123' };
      controller.hasPinSetupConfirmTarget = true;
      controller.pinSetupConfirmTarget = { value: '123' };
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };

      const saveQuickConfigSpy = jest.spyOn(QuickLoginService, 'saveQuickConfig');
      const preventDefault = jest.fn();

      await controller.handlePinSetupSubmit({ preventDefault });

      expect(preventDefault).toHaveBeenCalled();
      expect(saveQuickConfigSpy).not.toHaveBeenCalled();
    });

    test('rejects PIN longer than 10 digits', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.pinSetupPinTarget = { value: '12345678901' };
      controller.hasPinSetupConfirmTarget = true;
      controller.pinSetupConfirmTarget = { value: '12345678901' };
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };

      const saveQuickConfigSpy = jest.spyOn(QuickLoginService, 'saveQuickConfig');
      const preventDefault = jest.fn();

      await controller.handlePinSetupSubmit({ preventDefault });

      expect(preventDefault).toHaveBeenCalled();
      expect(saveQuickConfigSpy).not.toHaveBeenCalled();
    });

    test('rejects when PIN and confirm do not match', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.pinSetupPinTarget = { value: '1234' };
      controller.hasPinSetupConfirmTarget = true;
      controller.pinSetupConfirmTarget = { value: '5678' };
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };

      const saveQuickConfigSpy = jest.spyOn(QuickLoginService, 'saveQuickConfig');
      const preventDefault = jest.fn();

      await controller.handlePinSetupSubmit({ preventDefault });

      expect(preventDefault).toHaveBeenCalled();
      expect(saveQuickConfigSpy).not.toHaveBeenCalled();
      expect(controller.pinSetupFormTarget.submit).not.toHaveBeenCalled();
    });

    test('accepts valid 10-digit PIN', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.pinSetupPinTarget = { value: '1234567890' };
      controller.hasPinSetupConfirmTarget = true;
      controller.pinSetupConfirmTarget = { value: '1234567890' };
      controller.hasPinSetupEmailTarget = true;
      controller.pinSetupEmailTarget = { value: 'a@b.com' };
      controller.hasPinSetupDeviceIdTarget = true;
      controller.pinSetupDeviceIdTarget = { value: 'dev-1' };
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };
      controller.deviceId = 'dev-1';

      jest.spyOn(QuickLoginService, 'saveQuickConfig').mockResolvedValue({ email: 'a@b.com' });
      jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});
      const preventDefault = jest.fn();

      await controller.handlePinSetupSubmit({ preventDefault });

      expect(controller.pinSetupFormTarget.submit).toHaveBeenCalled();
    });

    test('falls back to this.deviceId when pinSetupDeviceIdTarget is absent', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.pinSetupPinTarget = { value: '1234' };
      controller.hasPinSetupConfirmTarget = true;
      controller.pinSetupConfirmTarget = { value: '1234' };
      controller.hasPinSetupEmailTarget = true;
      controller.pinSetupEmailTarget = { value: 'a@b.com' };
      controller.hasPinSetupDeviceIdTarget = false;
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };
      controller.deviceId = 'fallback-dev-id';

      const saveSpy = jest.spyOn(QuickLoginService, 'saveQuickConfig').mockResolvedValue({ email: 'a@b.com' });
      jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});
      const preventDefault = jest.fn();

      await controller.handlePinSetupSubmit({ preventDefault });

      expect(saveSpy).toHaveBeenCalledWith({
        email: 'a@b.com',
        deviceId: 'fallback-dev-id',
        pin: '1234',
      });
    });

    test('does not remember email when pinSetupEmail is empty', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.pinSetupPinTarget = { value: '1234' };
      controller.hasPinSetupConfirmTarget = true;
      controller.pinSetupConfirmTarget = { value: '1234' };
      controller.hasPinSetupEmailTarget = true;
      controller.pinSetupEmailTarget = { value: '' };
      controller.hasPinSetupDeviceIdTarget = true;
      controller.pinSetupDeviceIdTarget = { value: 'dev-1' };
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };
      controller.deviceId = 'dev-1';

      jest.spyOn(QuickLoginService, 'saveQuickConfig').mockResolvedValue({ email: '' });
      const rememberSpy = jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});
      const preventDefault = jest.fn();

      await controller.handlePinSetupSubmit({ preventDefault });

      expect(rememberSpy).not.toHaveBeenCalled();
      expect(controller.pinSetupFormTarget.submit).toHaveBeenCalled();
    });

    test('resets isSubmittingPinSetup and logs error when saveQuickConfig throws', async () => {
      const controller = new LoginDeviceAuthController();
      controller.isSubmittingPinSetup = false;
      controller.hasPinSetupPinTarget = true;
      controller.pinSetupPinTarget = { value: '1234' };
      controller.hasPinSetupConfirmTarget = true;
      controller.pinSetupConfirmTarget = { value: '1234' };
      controller.hasPinSetupEmailTarget = true;
      controller.pinSetupEmailTarget = { value: 'a@b.com' };
      controller.hasPinSetupDeviceIdTarget = true;
      controller.pinSetupDeviceIdTarget = { value: 'dev-1' };
      controller.hasPinSetupFormTarget = true;
      controller.pinSetupFormTarget = { submit: jest.fn() };
      controller.deviceId = 'dev-1';

      jest.spyOn(QuickLoginService, 'saveQuickConfig').mockRejectedValue(new Error('network fail'));
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
      const preventDefault = jest.fn();

      await controller.handlePinSetupSubmit({ preventDefault });

      expect(controller.isSubmittingPinSetup).toBe(false);
      expect(controller.pinSetupFormTarget.submit).not.toHaveBeenCalled();
      expect(consoleSpy).toHaveBeenCalledWith(
        'Failed to save quick login configuration.',
        expect.any(Error)
      );
    });
  });

  // ── handleServerQuickLoginDisabled (additional cases) ──────────────────

  describe('handleServerQuickLoginDisabled additional cases', () => {
    test('does nothing when quickDisabledTarget is absent', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = { email: 'a@b.com' };
      controller.hasQuickDisabledTarget = false;

      const clearSpy = jest.spyOn(QuickLoginService, 'clearQuickConfig').mockImplementation(() => {});

      controller.handleServerQuickLoginDisabled();

      expect(clearSpy).not.toHaveBeenCalled();
      expect(controller.quickConfig).not.toBeNull();
    });

    test('does nothing when quickDisabledTarget value is not "1"', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = { email: 'a@b.com' };
      controller.hasQuickDisabledTarget = true;
      controller.quickDisabledTarget = { value: '0' };

      const clearSpy = jest.spyOn(QuickLoginService, 'clearQuickConfig').mockImplementation(() => {});

      controller.handleServerQuickLoginDisabled();

      expect(clearSpy).not.toHaveBeenCalled();
    });

    test('does not remember email when disabledEmail is empty', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = { email: 'a@b.com' };
      controller.hasQuickDisabledTarget = true;
      controller.quickDisabledTarget = { value: '1' };
      controller.hasQuickDisabledEmailTarget = true;
      controller.quickDisabledEmailTarget = { value: '  ' };

      jest.spyOn(QuickLoginService, 'clearQuickConfig').mockImplementation(() => {});
      const rememberSpy = jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});

      controller.handleServerQuickLoginDisabled();

      expect(rememberSpy).not.toHaveBeenCalled();
      expect(controller.quickConfig).toBeNull();
    });

    test('handles missing quickDisabledEmailTarget', () => {
      const controller = new LoginDeviceAuthController();
      controller.quickConfig = { email: 'a@b.com' };
      controller.hasQuickDisabledTarget = true;
      controller.quickDisabledTarget = { value: '1' };
      controller.hasQuickDisabledEmailTarget = false;

      jest.spyOn(QuickLoginService, 'clearQuickConfig').mockImplementation(() => {});
      const rememberSpy = jest.spyOn(QuickLoginService, 'setRememberedId').mockImplementation(() => {});

      controller.handleServerQuickLoginDisabled();

      expect(rememberSpy).not.toHaveBeenCalled();
      expect(controller.quickConfig).toBeNull();
    });
  });
});
