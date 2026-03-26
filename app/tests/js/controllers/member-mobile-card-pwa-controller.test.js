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

/* ------------------------------------------------------------------ */
/*  NEW TESTS – added below to improve coverage                       */
/* ------------------------------------------------------------------ */

function createController() {
  const MemberMobileCardPWA = window.Controllers['member-mobile-card-pwa'];
  const ctrl = new MemberMobileCardPWA();
  ctrl._boundHandlers = new Map();
  ctrl.element = document.createElement('div');
  return ctrl;
}

describe('MemberMobileCardPWA updateStatusDisplay', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    MobileControllerBase.setOnlineState(true, false);
  });

  test('sets online styles when isOnline is true', () => {
    const status = document.createElement('div');
    status.classList.add('bg-danger');
    const refreshBtn = document.createElement('button');
    refreshBtn.hidden = true;

    controller.hasStatusTarget = true;
    controller.statusTarget = status;
    controller.hasRefreshBtnTarget = true;
    controller.refreshBtnTarget = refreshBtn;

    controller.updateStatusDisplay(true);

    expect(status.title).toBe('Online');
    expect(status.classList.contains('bg-success')).toBe(true);
    expect(status.classList.contains('bg-danger')).toBe(false);
    expect(refreshBtn.hidden).toBe(false);
  });

  test('sets offline styles when isOnline is false', () => {
    const status = document.createElement('div');
    status.classList.add('bg-success');
    const refreshBtn = document.createElement('button');
    refreshBtn.hidden = false;

    controller.hasStatusTarget = true;
    controller.statusTarget = status;
    controller.hasRefreshBtnTarget = true;
    controller.refreshBtnTarget = refreshBtn;

    controller.updateStatusDisplay(false);

    expect(status.title).toBe('Offline');
    expect(status.classList.contains('bg-danger')).toBe(true);
    expect(status.classList.contains('bg-success')).toBe(false);
    expect(refreshBtn.hidden).toBe(true);
  });

  test('does nothing when hasStatusTarget is false', () => {
    controller.hasStatusTarget = false;
    // Should not throw
    expect(() => controller.updateStatusDisplay(true)).not.toThrow();
  });

  test('works without refresh button target', () => {
    const status = document.createElement('div');
    controller.hasStatusTarget = true;
    controller.statusTarget = status;
    controller.hasRefreshBtnTarget = false;

    controller.updateStatusDisplay(true);
    expect(status.title).toBe('Online');

    controller.updateStatusDisplay(false);
    expect(status.title).toBe('Offline');
  });
});

describe('MemberMobileCardPWA onConnectionStateChanged', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    controller.updateStatusDisplay = jest.fn();
    controller.notifyServiceWorker = jest.fn();
    controller.dispatchStatusEvent = jest.fn();
    MobileControllerBase.setOnlineState(true, false);
  });

  test('calls updateStatusDisplay, notifyServiceWorker, and dispatchStatusEvent when online', () => {
    controller.hasRefreshBtnTarget = true;
    const refreshBtn = document.createElement('button');
    refreshBtn.click = jest.fn();
    controller.refreshBtnTarget = refreshBtn;

    controller.onConnectionStateChanged(true);

    expect(controller.updateStatusDisplay).toHaveBeenCalledWith(true);
    expect(controller.notifyServiceWorker).toHaveBeenCalledWith(true);
    expect(controller.dispatchStatusEvent).toHaveBeenCalledWith('online');
    expect(refreshBtn.click).toHaveBeenCalled();
  });

  test('dispatches offline status when offline', () => {
    controller.hasRefreshBtnTarget = false;

    controller.onConnectionStateChanged(false);

    expect(controller.updateStatusDisplay).toHaveBeenCalledWith(false);
    expect(controller.notifyServiceWorker).toHaveBeenCalledWith(false);
    expect(controller.dispatchStatusEvent).toHaveBeenCalledWith('offline');
  });

  test('does not click refresh button when hasRefreshBtnTarget is false', () => {
    controller.hasRefreshBtnTarget = false;

    controller.onConnectionStateChanged(true);

    expect(controller.dispatchStatusEvent).toHaveBeenCalledWith('online');
  });
});

describe('MemberMobileCardPWA notifyServiceWorker', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    MobileControllerBase.setOnlineState(true, false);
  });

  test('posts ONLINE message when isOnline is true', () => {
    const postMessage = jest.fn();
    controller.sw = { active: { postMessage } };

    controller.notifyServiceWorker(true);

    expect(postMessage).toHaveBeenCalledWith({ type: 'ONLINE' });
  });

  test('posts OFFLINE message when isOnline is false', () => {
    const postMessage = jest.fn();
    controller.sw = { active: { postMessage } };

    controller.notifyServiceWorker(false);

    expect(postMessage).toHaveBeenCalledWith({ type: 'OFFLINE' });
  });

  test('does nothing when sw is null', () => {
    controller.sw = null;
    expect(() => controller.notifyServiceWorker(true)).not.toThrow();
  });

  test('does nothing when sw.active is null', () => {
    controller.sw = { active: null };
    expect(() => controller.notifyServiceWorker(true)).not.toThrow();
  });
});

describe('MemberMobileCardPWA dispatchStatusEvent', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    MobileControllerBase.setOnlineState(true, false);
  });

  test('dispatches connection-status-changed event with online details', () => {
    controller.hasIsAuthCardValue = true;
    controller.isAuthCardValue = true;
    controller.hasAuthCardUrlValue = true;
    controller.authCardUrlValue = '/auth/card';

    const listener = jest.fn();
    controller.element.addEventListener('connection-status-changed', listener);

    controller.dispatchStatusEvent('online');

    expect(listener).toHaveBeenCalledTimes(1);
    const detail = listener.mock.calls[0][0].detail;
    expect(detail.status).toBe('online');
    expect(detail.isOnline).toBe(true);
    expect(detail.isAuthCard).toBe(true);
    expect(detail.authCardUrl).toBe('/auth/card');
  });

  test('dispatches event with offline status', () => {
    controller.hasIsAuthCardValue = false;
    controller.hasAuthCardUrlValue = false;

    const listener = jest.fn();
    controller.element.addEventListener('connection-status-changed', listener);

    controller.dispatchStatusEvent('offline');

    const detail = listener.mock.calls[0][0].detail;
    expect(detail.status).toBe('offline');
    expect(detail.isOnline).toBe(false);
    expect(detail.isAuthCard).toBe(false);
    expect(detail.authCardUrl).toBeNull();
  });

  test('event bubbles', () => {
    controller.hasIsAuthCardValue = false;
    controller.hasAuthCardUrlValue = false;

    const parent = document.createElement('div');
    parent.appendChild(controller.element);
    const parentListener = jest.fn();
    parent.addEventListener('connection-status-changed', parentListener);

    controller.dispatchStatusEvent('online');

    expect(parentListener).toHaveBeenCalledTimes(1);
  });
});

describe('MemberMobileCardPWA handleSwMessage', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    controller.showUpdateToast = jest.fn();
    controller.updateDismissed = false;
    MobileControllerBase.setOnlineState(true, false);
  });

  test('handles SW_UPDATED message and shows toast', () => {
    const event = { data: { type: 'SW_UPDATED', version: '2.0.0' } };

    controller.handleSwMessage(event);

    expect(controller.swVersion).toBe('2.0.0');
    expect(controller.showUpdateToast).toHaveBeenCalled();
  });

  test('handles SW_UPDATED but does not show toast when dismissed', () => {
    controller.updateDismissed = true;
    const event = { data: { type: 'SW_UPDATED', version: '2.0.0' } };

    controller.handleSwMessage(event);

    expect(controller.swVersion).toBe('2.0.0');
    expect(controller.showUpdateToast).not.toHaveBeenCalled();
  });

  test('ignores unknown event types', () => {
    const event = { data: { type: 'UNKNOWN_TYPE' } };

    controller.handleSwMessage(event);

    expect(controller.showUpdateToast).not.toHaveBeenCalled();
  });

  test('returns early when event.data is null', () => {
    const event = { data: null };

    expect(() => controller.handleSwMessage(event)).not.toThrow();
    expect(controller.showUpdateToast).not.toHaveBeenCalled();
  });
});

describe('MemberMobileCardPWA showUpdateToast', () => {
  let controller;

  beforeEach(() => {
    jest.useFakeTimers();
    controller = createController();
    controller.updateDismissed = false;
    MobileControllerBase.setOnlineState(true, false);
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('shows existing toast target and auto-hides after 10s', () => {
    const toast = document.createElement('div');
    toast.hidden = true;
    controller.hasUpdateToastTarget = true;
    controller.updateToastTarget = toast;

    controller.showUpdateToast();

    expect(toast.hidden).toBe(false);

    jest.advanceTimersByTime(10000);

    expect(toast.hidden).toBe(true);
    expect(controller.updateDismissed).toBe(true);
  });

  test('creates toast when target does not exist', () => {
    controller.hasUpdateToastTarget = false;
    controller.createUpdateToast = jest.fn(() => {
      // Simulate what createUpdateToast does: appends and sets target
      const toast = document.createElement('div');
      controller.element.appendChild(toast);
      controller.hasUpdateToastTarget = true;
      controller.updateToastTarget = toast;
    });

    controller.showUpdateToast();

    expect(controller.createUpdateToast).toHaveBeenCalled();
    expect(controller.updateToastTarget.hidden).toBe(false);
  });

  test('does not auto-hide if toast target disconnects before timeout', () => {
    const toast = document.createElement('div');
    toast.hidden = true;
    controller.hasUpdateToastTarget = true;
    controller.updateToastTarget = toast;

    controller.showUpdateToast();
    expect(toast.hidden).toBe(false);

    // Simulate target disconnecting
    controller.hasUpdateToastTarget = false;

    jest.advanceTimersByTime(10000);

    // toast.hidden was not changed back because hasUpdateToastTarget is false
    expect(controller.updateDismissed).toBe(false);
  });
});

describe('MemberMobileCardPWA createUpdateToast', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    MobileControllerBase.setOnlineState(true, false);
  });

  test('creates toast element and appends to controller element', () => {
    controller.createUpdateToast();

    const toast = controller.element.querySelector('.mobile-update-toast');
    expect(toast).not.toBeNull();
    expect(toast.className).toContain('alert-info');
    expect(toast.className).toContain('alert-dismissible');
    expect(toast.style.zIndex).toBe('9999');
    expect(toast.getAttribute('data-member-mobile-card-pwa-target')).toBe('updateToast');
    expect(toast.innerHTML).toContain('Update available');
    expect(toast.innerHTML).toContain('btn-close');
  });

  test('toast click handler calls applyUpdate for non-close-button clicks', () => {
    controller.applyUpdate = jest.fn();
    controller.createUpdateToast();

    const toast = controller.element.querySelector('.mobile-update-toast');
    toast.click();

    expect(controller.applyUpdate).toHaveBeenCalled();
  });

  test('toast click handler does not call applyUpdate when btn-close is clicked', () => {
    controller.applyUpdate = jest.fn();
    controller.createUpdateToast();

    const closeBtn = controller.element.querySelector('.btn-close');
    // Direct click on close button – event.target has btn-close class
    const event = new MouseEvent('click', { bubbles: true });
    Object.defineProperty(event, 'target', { value: closeBtn, writable: false });
    const toast = controller.element.querySelector('.mobile-update-toast');
    toast.dispatchEvent(event);

    expect(controller.applyUpdate).not.toHaveBeenCalled();
  });
});

describe('MemberMobileCardPWA dismissUpdate', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    controller.updateDismissed = false;
    MobileControllerBase.setOnlineState(true, false);
  });

  test('hides toast, sets updateDismissed, and stops propagation', () => {
    const toast = document.createElement('div');
    toast.hidden = false;
    controller.hasUpdateToastTarget = true;
    controller.updateToastTarget = toast;

    const event = { stopPropagation: jest.fn() };

    controller.dismissUpdate(event);

    expect(event.stopPropagation).toHaveBeenCalled();
    expect(toast.hidden).toBe(true);
    expect(controller.updateDismissed).toBe(true);
  });

  test('sets updateDismissed even without toast target', () => {
    controller.hasUpdateToastTarget = false;

    const event = { stopPropagation: jest.fn() };

    controller.dismissUpdate(event);

    expect(event.stopPropagation).toHaveBeenCalled();
    expect(controller.updateDismissed).toBe(true);
  });
});

describe('MemberMobileCardPWA registerServiceWorker', () => {
  let controller;
  let originalServiceWorker;

  beforeEach(() => {
    controller = createController();
    controller.swUrlValue = '/sw.js';
    controller.urlCacheValue = ['/page1', '/page2'];
    controller.bindHandler = jest.fn((name, fn) => fn.bind(controller));
    MobileControllerBase.setOnlineState(true, false);

    originalServiceWorker = navigator.serviceWorker;
  });

  afterEach(() => {
    // Restore navigator.serviceWorker
    if (originalServiceWorker !== undefined) {
      Object.defineProperty(navigator, 'serviceWorker', {
        configurable: true,
        value: originalServiceWorker,
      });
    }
  });

  test('registers SW, waits for active, sends cache URLs, dispatches pwa-ready', async () => {
    const postMessage = jest.fn();
    const mockRegistration = {
      active: { postMessage },
      installing: null,
      waiting: null,
    };

    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: {
        register: jest.fn().mockResolvedValue(mockRegistration),
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
      },
    });

    const readyListener = jest.fn();
    controller.element.addEventListener('pwa-ready', readyListener);

    await controller.registerServiceWorker();

    expect(navigator.serviceWorker.register).toHaveBeenCalledWith('/sw.js');
    expect(controller.sw).toBe(mockRegistration);
    expect(postMessage).toHaveBeenCalledWith({
      type: 'CACHE_URLS',
      payload: ['/page1', '/page2'],
    });
    expect(readyListener).toHaveBeenCalledTimes(1);
  });

  test('dispatches pwa-ready even when registration fails', async () => {
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: {
        register: jest.fn().mockRejectedValue(new Error('SW failed')),
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
      },
    });

    const readyListener = jest.fn();
    controller.element.addEventListener('pwa-ready', readyListener);

    await controller.registerServiceWorker();

    expect(readyListener).toHaveBeenCalledTimes(1);
  });

  test('does not send cache URLs when urlCacheValue is falsy', async () => {
    const postMessage = jest.fn();
    const mockRegistration = {
      active: { postMessage },
      installing: null,
      waiting: null,
    };

    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: {
        register: jest.fn().mockResolvedValue(mockRegistration),
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
      },
    });

    controller.urlCacheValue = null;

    await controller.registerServiceWorker();

    expect(postMessage).not.toHaveBeenCalled();
  });
});

describe('MemberMobileCardPWA waitForActive', () => {
  let controller;

  beforeEach(() => {
    jest.useFakeTimers();
    controller = createController();
    MobileControllerBase.setOnlineState(true, false);
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('resolves immediately when registration.active is already set', async () => {
    const registration = { active: { postMessage: jest.fn() }, installing: null, waiting: null };

    await controller.waitForActive(registration);
    // If we get here without hanging, the test passes
  });

  test('resolves when installing worker transitions to activated', async () => {
    let stateChangeHandler;
    const worker = {
      addEventListener: jest.fn((event, handler) => {
        stateChangeHandler = handler;
      }),
      removeEventListener: jest.fn(),
    };
    const registration = { active: null, installing: worker, waiting: null };

    const promise = controller.waitForActive(registration);

    // Simulate the worker activating
    stateChangeHandler({ target: { state: 'activated' } });

    await promise;

    expect(worker.removeEventListener).toHaveBeenCalledWith('statechange', stateChangeHandler);
  });

  test('resolves when waiting worker transitions to activated', async () => {
    let stateChangeHandler;
    const worker = {
      addEventListener: jest.fn((event, handler) => {
        stateChangeHandler = handler;
      }),
      removeEventListener: jest.fn(),
    };
    const registration = { active: null, installing: null, waiting: worker };

    const promise = controller.waitForActive(registration);

    stateChangeHandler({ target: { state: 'activated' } });

    await promise;

    expect(worker.removeEventListener).toHaveBeenCalledWith('statechange', stateChangeHandler);
  });

  test('does not resolve for non-activated state changes', async () => {
    let stateChangeHandler;
    const worker = {
      addEventListener: jest.fn((event, handler) => {
        stateChangeHandler = handler;
      }),
      removeEventListener: jest.fn(),
    };
    const registration = { active: null, installing: worker, waiting: null };

    let resolved = false;
    const promise = controller.waitForActive(registration).then(() => { resolved = true; });

    // Simulate a non-activated state change
    stateChangeHandler({ target: { state: 'installed' } });

    // Flush microtasks
    await Promise.resolve();

    expect(resolved).toBe(false);

    // Now activate
    stateChangeHandler({ target: { state: 'activated' } });
    await promise;
    expect(resolved).toBe(true);
  });

  test('falls back to timeout when no worker is available', async () => {
    const registration = { active: null, installing: null, waiting: null };

    const promise = controller.waitForActive(registration);

    jest.advanceTimersByTime(100);

    await promise;
    // Resolves without error after timeout
  });
});

describe('MemberMobileCardPWA onDisconnect', () => {
  let controller;
  let originalServiceWorker;

  beforeEach(() => {
    controller = createController();
    controller._boundHandlers = new Map();
    controller.refreshIntervalId = null;
    controller.connectivityProbeId = null;
    MobileControllerBase.setOnlineState(true, false);

    originalServiceWorker = navigator.serviceWorker;
  });

  afterEach(() => {
    if (originalServiceWorker !== undefined) {
      Object.defineProperty(navigator, 'serviceWorker', {
        configurable: true,
        value: originalServiceWorker,
      });
    }
  });

  test('clears intervals and removes visibility listener', () => {
    const clearIntervalSpy = jest.spyOn(global, 'clearInterval');
    controller.refreshIntervalId = 123;
    controller.connectivityProbeId = 456;

    const removeEventSpy = jest.spyOn(document, 'removeEventListener');

    // Mock navigator.serviceWorker for the getHandler path
    const removeSwListener = jest.fn();
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: { removeEventListener: removeSwListener },
    });

    const mockHandler = jest.fn();
    controller._boundHandlers.set('swMessage', mockHandler);
    controller._visibilityHandler = jest.fn();

    controller.onDisconnect();

    expect(clearIntervalSpy).toHaveBeenCalledWith(123);
    expect(clearIntervalSpy).toHaveBeenCalledWith(456);
    expect(controller.refreshIntervalId).toBeNull();
    expect(controller.connectivityProbeId).toBeNull();
    expect(removeEventSpy).toHaveBeenCalledWith('visibilitychange', controller._visibilityHandler);
    expect(removeSwListener).toHaveBeenCalledWith('message', mockHandler);

    clearIntervalSpy.mockRestore();
    removeEventSpy.mockRestore();
  });

  test('handles no intervals gracefully', () => {
    controller.refreshIntervalId = null;
    controller.connectivityProbeId = null;
    controller._visibilityHandler = jest.fn();

    // No navigator.serviceWorker
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: undefined,
    });

    expect(() => controller.onDisconnect()).not.toThrow();
  });
});

describe('MemberMobileCardPWA urlCacheTargetConnected', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    MobileControllerBase.setOnlineState(true, false);
  });

  test('parses JSON from urlCacheTarget textContent', () => {
    const target = document.createElement('script');
    target.textContent = JSON.stringify(['/url1', '/url2', '/url3']);
    controller.urlCacheTarget = target;

    controller.urlCacheTargetConnected();

    expect(controller.urlCacheValue).toEqual(['/url1', '/url2', '/url3']);
  });

  test('handles empty array', () => {
    const target = document.createElement('script');
    target.textContent = '[]';
    controller.urlCacheTarget = target;

    controller.urlCacheTargetConnected();

    expect(controller.urlCacheValue).toEqual([]);
  });
});

describe('MemberMobileCardPWA statusTargetConnected', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    controller.updateStatusDisplay = jest.fn();
    MobileControllerBase.setOnlineState(true, false);
  });

  test('calls updateStatusDisplay with current online state (online)', () => {
    MobileControllerBase.setOnlineState(true, false);

    controller.statusTargetConnected();

    expect(controller.updateStatusDisplay).toHaveBeenCalledWith(true);
  });

  test('calls updateStatusDisplay with current online state (offline)', () => {
    MobileControllerBase.setOnlineState(false, false);

    controller.statusTargetConnected();

    expect(controller.updateStatusDisplay).toHaveBeenCalledWith(false);
  });
});
