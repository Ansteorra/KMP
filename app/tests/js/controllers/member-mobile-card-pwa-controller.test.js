import MobileControllerBase from '../../../assets/js/controllers/mobile-controller-base.js';
import '../../../assets/js/controllers/member-mobile-card-pwa-controller.js';

describe('MemberMobileCardPWA probeConnectivity', () => {
  let controller;
  let originalFetch;

  beforeEach(() => {
    const MemberMobileCardPWA = window.Controllers['member-mobile-card-pwa'];
    controller = new MemberMobileCardPWA();
    controller.updateStatusDisplay = jest.fn();
    controller.dispatchStatusEvent = jest.fn();
    originalFetch = global.fetch;
    MobileControllerBase.setOnlineState(true, false);
  });

  afterEach(() => {
    global.fetch = originalFetch;
  });

  test('does not dispatch duplicate status event when connectivity is unchanged', async () => {
    global.fetch = jest.fn().mockResolvedValue({ status: 200 });

    await controller.probeConnectivity();

    expect(global.fetch).toHaveBeenCalledWith('/health', expect.objectContaining({
      method: 'HEAD',
      cache: 'no-store',
    }));
    expect(controller.updateStatusDisplay).toHaveBeenCalledWith(true);
    expect(controller.dispatchStatusEvent).not.toHaveBeenCalled();
  });

  test('dispatches status event when connectivity changes', async () => {
    global.fetch = jest.fn().mockRejectedValue(new Error('offline'));

    await controller.probeConnectivity();

    expect(controller.updateStatusDisplay).toHaveBeenCalledWith(false);
    expect(controller.dispatchStatusEvent).toHaveBeenCalledWith('offline');
  });
});
