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
});
