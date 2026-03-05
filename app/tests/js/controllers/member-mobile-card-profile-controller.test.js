import '../../../assets/js/controllers/member-mobile-card-profile-controller.js';

describe('MemberMobileCardProfile photo actions', () => {
  let ControllerClass;
  let controller;
  let originalBootstrap;

  beforeEach(() => {
    ControllerClass = window.Controllers['member-mobile-card-profile'];
    controller = new ControllerClass();
    originalBootstrap = window.bootstrap;
  });

  afterEach(() => {
    window.bootstrap = originalBootstrap;
    jest.restoreAllMocks();
  });

  test('hides profile photo manage button when offline', () => {
    const hideMock = jest.fn();
    const modalElement = document.createElement('div');

    controller.hasPhotoManageButtonTarget = true;
    controller.photoManageButtonTarget = document.createElement('button');
    controller.hasPhotoUploadModalTarget = true;
    controller.photoUploadModalTarget = modalElement;

    window.bootstrap = {
      ...originalBootstrap,
      Modal: {
        getInstance: jest.fn().mockReturnValue({ hide: hideMock }),
        getOrCreateInstance: jest.fn(),
      },
    };

    controller.updatePhotoActionsForConnection(false);

    expect(controller.photoManageButtonTarget.hidden).toBe(true);
    expect(window.bootstrap.Modal.getInstance).toHaveBeenCalledWith(modalElement);
    expect(hideMock).toHaveBeenCalled();
  });

  test('shows profile photo manage button when online', () => {
    controller.hasPhotoManageButtonTarget = true;
    controller.photoManageButtonTarget = document.createElement('button');
    controller.photoManageButtonTarget.hidden = true;
    controller.hasPhotoUploadModalTarget = false;

    controller.updatePhotoActionsForConnection(true);

    expect(controller.photoManageButtonTarget.hidden).toBe(false);
  });
});
