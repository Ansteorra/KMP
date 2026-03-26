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

// ---- New comprehensive tests below ----

function createController() {
  const ControllerClass = window.Controllers['member-mobile-card-profile'];
  const ctrl = new ControllerClass();

  // Wire DOM targets used by most methods
  ctrl.nameTarget = document.createElement('div');
  ctrl.scaNameTarget = document.createElement('div');
  ctrl.branchNameTarget = document.createElement('div');
  ctrl.membershipInfoTarget = document.createElement('div');
  ctrl.backgroundCheckTarget = document.createElement('div');
  ctrl.lastUpdateTarget = document.createElement('div');
  ctrl.cardSetTarget = document.createElement('div');
  ctrl.loadingTarget = document.createElement('div');
  ctrl.memberDetailsTarget = document.createElement('div');

  // Profile photo targets
  ctrl.hasProfilePhotoContainerTarget = true;
  ctrl.profilePhotoContainerTarget = document.createElement('div');
  ctrl.hasProfilePhotoTarget = true;
  ctrl.profilePhotoTarget = document.createElement('img');
  ctrl.hasZoomPhotoTarget = true;
  ctrl.zoomPhotoTarget = document.createElement('img');

  // Photo manage / modal targets (default: absent)
  ctrl.hasPhotoManageButtonTarget = false;
  ctrl.hasPhotoUploadModalTarget = false;

  // Initialize internal state that initialize() would set
  ctrl.currentCard = null;
  ctrl.cardCount = 0;

  return ctrl;
}

function makeMemberData(overrides = {}) {
  const base = {
    first_name: 'John',
    last_name: 'Doe',
    sca_name: 'Lord John',
    branch: { name: 'Shire' },
    membership_number: '12345',
    membership_expires_on: '2099-01-01',
    background_check_expires_on: '2099-06-01',
    profile_photo_url: '/photos/john.jpg',
  };
  return { member: { ...base, ...overrides } };
}

// ---------- renderMemberData ----------

describe('renderMemberData', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    // Stub cacheProfilePhotoForOffline to avoid SW calls
    controller.cacheProfilePhotoForOffline = jest.fn();
  });

  afterEach(() => jest.restoreAllMocks());

  test('populates name, scaName, and branchName from member data', () => {
    controller.renderMemberData(makeMemberData());

    expect(controller.nameTarget.textContent).toBe('John Doe');
    expect(controller.scaNameTarget.textContent).toBe('Lord John');
    expect(controller.branchNameTarget.textContent).toBe('Shire');
  });

  test('shows membership number with future expiry date', () => {
    controller.renderMemberData(makeMemberData({ membership_expires_on: '2099-01-01' }));

    expect(controller.membershipInfoTarget.textContent).toContain('12345');
    expect(controller.membershipInfoTarget.textContent).not.toContain('Expired');
  });

  test('shows "Expired" when membership is in the past', () => {
    controller.renderMemberData(makeMemberData({ membership_expires_on: '2000-01-01' }));

    expect(controller.membershipInfoTarget.textContent).toContain('12345');
    expect(controller.membershipInfoTarget.textContent).toContain('Expired');
  });

  test('shows "No Membership Info" when membership_number is empty', () => {
    controller.renderMemberData(makeMemberData({ membership_number: '' }));

    expect(controller.membershipInfoTarget.textContent).toBe('No Membership Info');
  });

  test('shows "Not on file" when background_check_expires_on is null', () => {
    controller.renderMemberData(makeMemberData({ background_check_expires_on: null }));

    expect(controller.backgroundCheckTarget.textContent).toBe('Not on file');
  });

  test('shows "Current" with date for future background check', () => {
    controller.renderMemberData(makeMemberData({ background_check_expires_on: '2099-06-01' }));

    expect(controller.backgroundCheckTarget.textContent).toContain('Current');
  });

  test('shows "Expired" for past background check', () => {
    controller.renderMemberData(makeMemberData({ background_check_expires_on: '2000-01-01' }));

    expect(controller.backgroundCheckTarget.textContent).toBe('Expired');
  });

  test('sets lastUpdate to current locale string', () => {
    controller.renderMemberData(makeMemberData());

    // lastUpdate should be a non-empty date string
    expect(controller.lastUpdateTarget.textContent.length).toBeGreaterThan(0);
  });

  test('handles null profile_photo_url by passing null to renderProfilePhoto', () => {
    const spy = jest.spyOn(controller, 'renderProfilePhoto');
    controller.renderMemberData(makeMemberData({ profile_photo_url: null }));

    expect(spy).toHaveBeenCalledWith(null);
  });

  test('calls renderPluginSections with the full data object', () => {
    const spy = jest.spyOn(controller, 'renderPluginSections').mockImplementation(() => {});
    const data = makeMemberData();
    controller.renderMemberData(data);

    expect(spy).toHaveBeenCalledWith(data);
  });
});

// ---------- renderProfilePhoto ----------

describe('renderProfilePhoto', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    controller.cacheProfilePhotoForOffline = jest.fn();
  });

  afterEach(() => jest.restoreAllMocks());

  test('shows container and sets src when given a valid URL', () => {
    controller.profilePhotoContainerTarget.hidden = true;
    controller.renderProfilePhoto('/photos/test.jpg');

    expect(controller.profilePhotoContainerTarget.hidden).toBe(false);
    expect(controller.profilePhotoTarget.src).toContain('/photos/test.jpg');
    expect(controller.zoomPhotoTarget.src).toContain('/photos/test.jpg');
    expect(controller.cacheProfilePhotoForOffline).toHaveBeenCalledWith('/photos/test.jpg');
  });

  test('hides container when photoUrl is null', () => {
    controller.profilePhotoContainerTarget.hidden = false;
    controller.renderProfilePhoto(null);

    expect(controller.profilePhotoContainerTarget.hidden).toBe(true);
    expect(controller.cacheProfilePhotoForOffline).not.toHaveBeenCalled();
  });

  test('hides container when photoUrl is empty string', () => {
    controller.renderProfilePhoto('');

    expect(controller.profilePhotoContainerTarget.hidden).toBe(true);
    expect(controller.cacheProfilePhotoForOffline).not.toHaveBeenCalled();
  });

  test('works when profilePhotoContainer target is absent', () => {
    controller.hasProfilePhotoContainerTarget = false;
    // Should not throw
    expect(() => controller.renderProfilePhoto('/photos/ok.jpg')).not.toThrow();
    expect(controller.cacheProfilePhotoForOffline).toHaveBeenCalledWith('/photos/ok.jpg');
  });

  test('skips setting src when profilePhoto target is absent', () => {
    controller.hasProfilePhotoTarget = false;
    controller.renderProfilePhoto('/photos/ok.jpg');
    // zoomPhoto should still be set
    expect(controller.zoomPhotoTarget.src).toContain('/photos/ok.jpg');
  });

  test('skips setting src when zoomPhoto target is absent', () => {
    controller.hasZoomPhotoTarget = false;
    controller.renderProfilePhoto('/photos/ok.jpg');

    expect(controller.profilePhotoTarget.src).toContain('/photos/ok.jpg');
  });
});

// ---------- renderPluginSections ----------

describe('renderPluginSections', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
  });

  test('creates cards for plugin sections with items', () => {
    const data = {
      member: { first_name: 'X' },
      awards: {
        'Awards': {
          'Court Awards': ['Award of Arms', 'Golden Rose'],
        },
      },
    };

    controller.renderPluginSections(data);

    // Should have created one card with title "Awards"
    const cards = controller.cardSetTarget.querySelectorAll('.card');
    expect(cards.length).toBe(1);

    const title = cards[0].querySelector('h3');
    expect(title.textContent).toBe('Awards');

    // Table with group header "Court Awards"
    const ths = cards[0].querySelectorAll('th');
    expect(ths.length).toBe(1);
    expect(ths[0].textContent).toBe('Court Awards');
  });

  test('handles key:value format (colon after position 2)', () => {
    const data = {
      member: {},
      plugin: {
        'Section': {
          'Group': ['OGR:Order of the Golden Rose'],
        },
      },
    };

    controller.renderPluginSections(data);

    const tds = controller.cardSetTarget.querySelectorAll('td');
    // key:value produces two td cells
    expect(tds.length).toBe(2);
    expect(tds[0].textContent).toBe('OGR');
    expect(tds[1].textContent).toBe('Order of the Golden Rose');
  });

  test('treats colon at position <= 2 as a regular item', () => {
    const data = {
      member: {},
      plugin: {
        'Section': {
          'Group': ['A:B'],
        },
      },
    };

    controller.renderPluginSections(data);

    // indexOf(":") for "A:B" is 1, which is <= 2, so it's treated as regular
    const tds = controller.cardSetTarget.querySelectorAll('td');
    expect(tds.length).toBe(1);
    expect(tds[0].textContent).toBe('A:B');
  });

  test('skips empty sections', () => {
    const data = {
      member: {},
      plugin: {
        'Empty Section': {},
        'Non-Empty': {
          'Group': ['Item1'],
        },
      },
    };

    controller.renderPluginSections(data);

    const cards = controller.cardSetTarget.querySelectorAll('.card');
    expect(cards.length).toBe(1);
    expect(cards[0].querySelector('h3').textContent).toBe('Non-Empty');
  });

  test('skips empty groups', () => {
    const data = {
      member: {},
      plugin: {
        'Section': {
          'EmptyGroup': [],
          'FilledGroup': ['Item'],
        },
      },
    };

    controller.renderPluginSections(data);

    const ths = controller.cardSetTarget.querySelectorAll('th');
    // Only "FilledGroup" header should appear
    expect(ths.length).toBe(1);
    expect(ths[0].textContent).toBe('FilledGroup');
  });

  test('applies colspan 2 to last item when it is the only item in a row', () => {
    const data = {
      member: {},
      plugin: {
        'Section': {
          'Group': ['ItemA', 'ItemB', 'ItemC'],
        },
      },
    };

    controller.renderPluginSections(data);

    const tds = controller.cardSetTarget.querySelectorAll('td');
    // Items: A(col0), B(col1) -> new row, C(col0, last, alone) -> colspan 2
    expect(tds.length).toBe(3);
    expect(tds[2].colSpan).toBe(2);
    expect(tds[2].textContent).toBe('ItemC');
  });

  test('does not apply colspan 2 when last item fills the second column', () => {
    const data = {
      member: {},
      plugin: {
        'Section': {
          'Group': ['ItemA', 'ItemB'],
        },
      },
    };

    controller.renderPluginSections(data);

    const tds = controller.cardSetTarget.querySelectorAll('td');
    expect(tds.length).toBe(2);
    expect(tds[0].colSpan).toBe(1);
    expect(tds[1].colSpan).toBe(1);
  });

  test('creates multiple cards for multiple plugin keys', () => {
    const data = {
      member: {},
      awards: {
        'Awards': { 'Group1': ['A'] },
      },
      activities: {
        'Activities': { 'Group2': ['B'] },
      },
    };

    controller.renderPluginSections(data);

    const cards = controller.cardSetTarget.querySelectorAll('.card');
    expect(cards.length).toBe(2);
  });

  test('skips the "member" key entirely', () => {
    const data = {
      member: { first_name: 'Skip me' },
    };

    controller.renderPluginSections(data);

    const cards = controller.cardSetTarget.querySelectorAll('.card');
    expect(cards.length).toBe(0);
  });
});

// ---------- startCard ----------

describe('startCard', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
  });

  test('creates a card with correct classes and title', () => {
    controller.startCard('Test Title');

    const card = controller.cardSetTarget.querySelector('.card');
    expect(card).not.toBeNull();
    expect(card.classList.contains('cardbox')).toBe(true);
    expect(card.classList.contains('m-3')).toBe(true);

    const title = card.querySelector('h3');
    expect(title.textContent).toBe('Test Title');
    expect(title.classList.contains('card-title')).toBe(true);
  });

  test('increments cardCount and sets correct IDs', () => {
    expect(controller.cardCount).toBe(0);

    controller.startCard('First');
    expect(controller.cardCount).toBe(1);
    expect(controller.cardSetTarget.querySelector('#card_1')).not.toBeNull();
    expect(controller.cardSetTarget.querySelector('#cardDetails_1')).not.toBeNull();

    controller.startCard('Second');
    expect(controller.cardCount).toBe(2);
    expect(controller.cardSetTarget.querySelector('#card_2')).not.toBeNull();
  });

  test('sets currentCard to the card-body element', () => {
    controller.startCard('My Card');

    expect(controller.currentCard).not.toBeNull();
    expect(controller.currentCard.classList.contains('card-body')).toBe(true);
  });

  test('sets data-section attribute on the card', () => {
    controller.startCard('Auth');

    const card = controller.cardSetTarget.querySelector('.card');
    expect(card.dataset.section).toBe('auth-card');
  });
});

// ---------- showOfflineMessage ----------

describe('showOfflineMessage', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
  });

  test('appends an alert div with warning class', () => {
    controller.showOfflineMessage();

    const alert = controller.cardSetTarget.querySelector('.alert-warning');
    expect(alert).not.toBeNull();
  });

  test('includes a retry button with correct data-action', () => {
    controller.showOfflineMessage();

    const btn = controller.cardSetTarget.querySelector('button');
    expect(btn).not.toBeNull();
    expect(btn.dataset.action).toBe('click->member-mobile-card-profile#retryLoad');
  });

  test('contains offline text', () => {
    controller.showOfflineMessage();

    const alert = controller.cardSetTarget.querySelector('.alert-warning');
    expect(alert.textContent).toContain('offline');
  });
});

// ---------- loadCard ----------

describe('loadCard', () => {
  let controller;
  let originalConsoleError;

  beforeEach(() => {
    controller = createController();
    controller.pwaReadyValue = true;
    controller.urlValue = '/api/member/1';
    controller.cacheProfilePhotoForOffline = jest.fn();
    originalConsoleError = console.error;
    console.error = jest.fn();
  });

  afterEach(() => {
    console.error = originalConsoleError;
    jest.restoreAllMocks();
  });

  test('does nothing when pwaReadyValue is false', async () => {
    controller.pwaReadyValue = false;
    controller.fetchWithRetry = jest.fn();

    await controller.loadCard();

    expect(controller.fetchWithRetry).not.toHaveBeenCalled();
  });

  test('shows loading state, then renders member data on success', async () => {
    const data = makeMemberData();
    controller.fetchWithRetry = jest.fn().mockResolvedValue({
      json: () => Promise.resolve(data),
    });

    await controller.loadCard();

    expect(controller.loadingTarget.hidden).toBe(true);
    expect(controller.memberDetailsTarget.hidden).toBe(false);
    expect(controller.nameTarget.textContent).toBe('John Doe');
  });

  test('clears cardSet innerHTML before loading', async () => {
    controller.cardSetTarget.innerHTML = '<div>Old content</div>';
    controller.fetchWithRetry = jest.fn().mockResolvedValue({
      json: () => Promise.resolve(makeMemberData()),
    });

    await controller.loadCard();

    // Old content was cleared; new content rendered by renderPluginSections
    expect(controller.cardSetTarget.innerHTML).not.toContain('Old content');
  });

  test('shows error message on fetch failure', async () => {
    // Simulate online so offline message is NOT shown
    const MobileControllerBase = Object.getPrototypeOf(Object.getPrototypeOf(controller)).constructor;
    const origOnline = MobileControllerBase.isOnline;
    MobileControllerBase.isOnline = true;

    controller.fetchWithRetry = jest.fn().mockRejectedValue(new Error('Network error'));

    await controller.loadCard();

    expect(controller.nameTarget.textContent).toBe('Error loading card data');
    expect(controller.loadingTarget.hidden).toBe(true);
    expect(controller.memberDetailsTarget.hidden).toBe(false);

    MobileControllerBase.isOnline = origOnline;
  });

  test('shows offline message with retry button when fetch fails and offline', async () => {
    const MobileControllerBase = Object.getPrototypeOf(Object.getPrototypeOf(controller)).constructor;
    const origOnline = MobileControllerBase.isOnline;
    MobileControllerBase.isOnline = false;

    controller.fetchWithRetry = jest.fn().mockRejectedValue(new Error('Offline'));

    await controller.loadCard();

    expect(controller.nameTarget.textContent).toBe('Error loading card data');
    const retryBtn = controller.cardSetTarget.querySelector('button');
    expect(retryBtn).not.toBeNull();

    MobileControllerBase.isOnline = origOnline;
  });

  test('hides profile photo container while loading', async () => {
    controller.profilePhotoContainerTarget.hidden = false;
    controller.fetchWithRetry = jest.fn().mockResolvedValue({
      json: () => Promise.resolve(makeMemberData()),
    });

    // The container should be hidden during loading (before await resolves)
    const origFetch = controller.fetchWithRetry;
    let containerHiddenDuringLoad = false;
    controller.fetchWithRetry = jest.fn().mockImplementation(async (...args) => {
      containerHiddenDuringLoad = controller.profilePhotoContainerTarget.hidden;
      return origFetch(...args);
    });

    await controller.loadCard();

    expect(containerHiddenDuringLoad).toBe(true);
  });
});

// ---------- cacheProfilePhotoForOffline ----------

describe('cacheProfilePhotoForOffline', () => {
  let controller;
  let originalNavigator;
  let originalConsoleWarn;

  beforeEach(() => {
    controller = createController();
    originalNavigator = navigator;
    originalConsoleWarn = console.warn;
    console.warn = jest.fn();
  });

  afterEach(() => {
    console.warn = originalConsoleWarn;
    jest.restoreAllMocks();
  });

  test('posts message to service worker when available', async () => {
    const postMessageMock = jest.fn();
    Object.defineProperty(navigator, 'serviceWorker', {
      value: {
        ready: Promise.resolve({
          active: { postMessage: postMessageMock },
        }),
      },
      configurable: true,
    });

    await controller.cacheProfilePhotoForOffline('/photos/test.jpg');

    expect(postMessageMock).toHaveBeenCalledWith({
      type: 'CACHE_URLS',
      payload: ['/photos/test.jpg'],
    });

    // Clean up
    Object.defineProperty(navigator, 'serviceWorker', {
      value: undefined,
      configurable: true,
    });
  });

  test('does nothing when serviceWorker is not available', async () => {
    // In jsdom, navigator.serviceWorker may not exist
    const orig = navigator.serviceWorker;
    Object.defineProperty(navigator, 'serviceWorker', {
      value: undefined,
      configurable: true,
    });

    // Should not throw
    await expect(controller.cacheProfilePhotoForOffline('/photos/test.jpg')).resolves.toBeUndefined();

    Object.defineProperty(navigator, 'serviceWorker', {
      value: orig,
      configurable: true,
    });
  });

  test('does nothing for empty URL', async () => {
    await expect(controller.cacheProfilePhotoForOffline('')).resolves.toBeUndefined();
  });

  test('does nothing for non-string URL', async () => {
    await expect(controller.cacheProfilePhotoForOffline(null)).resolves.toBeUndefined();
  });

  test('logs warning when service worker ready rejects', async () => {
    Object.defineProperty(navigator, 'serviceWorker', {
      value: {
        ready: Promise.reject(new Error('SW not ready')),
      },
      configurable: true,
    });

    await controller.cacheProfilePhotoForOffline('/photos/test.jpg');

    expect(console.warn).toHaveBeenCalled();

    Object.defineProperty(navigator, 'serviceWorker', {
      value: undefined,
      configurable: true,
    });
  });
});

// ---------- hidePhotoUploadModal ----------

describe('hidePhotoUploadModal', () => {
  let controller;
  let originalBootstrap;

  beforeEach(() => {
    controller = createController();
    originalBootstrap = window.bootstrap;
  });

  afterEach(() => {
    window.bootstrap = originalBootstrap;
    jest.restoreAllMocks();
  });

  test('does nothing when photoUploadModal target is absent', () => {
    controller.hasPhotoUploadModalTarget = false;
    // Should not throw
    expect(() => controller.hidePhotoUploadModal()).not.toThrow();
  });

  test('does nothing when bootstrap.Modal is undefined', () => {
    controller.hasPhotoUploadModalTarget = true;
    controller.photoUploadModalTarget = document.createElement('div');
    window.bootstrap = undefined;

    expect(() => controller.hidePhotoUploadModal()).not.toThrow();
  });

  test('calls hide on existing modal instance', () => {
    const hideMock = jest.fn();
    const modalEl = document.createElement('div');
    controller.hasPhotoUploadModalTarget = true;
    controller.photoUploadModalTarget = modalEl;

    window.bootstrap = {
      Modal: {
        getInstance: jest.fn().mockReturnValue({ hide: hideMock }),
        getOrCreateInstance: jest.fn(),
      },
    };

    controller.hidePhotoUploadModal();

    expect(window.bootstrap.Modal.getInstance).toHaveBeenCalledWith(modalEl);
    expect(hideMock).toHaveBeenCalled();
  });

  test('falls back to getOrCreateInstance when getInstance returns null', () => {
    const hideMock = jest.fn();
    const modalEl = document.createElement('div');
    controller.hasPhotoUploadModalTarget = true;
    controller.photoUploadModalTarget = modalEl;

    window.bootstrap = {
      Modal: {
        getInstance: jest.fn().mockReturnValue(null),
        getOrCreateInstance: jest.fn().mockReturnValue({ hide: hideMock }),
      },
    };

    controller.hidePhotoUploadModal();

    expect(window.bootstrap.Modal.getOrCreateInstance).toHaveBeenCalledWith(modalEl);
    expect(hideMock).toHaveBeenCalled();
  });
});

// ---------- pwaReadyValueChanged ----------

describe('pwaReadyValueChanged', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    controller.loadCard = jest.fn();
  });

  afterEach(() => jest.restoreAllMocks());

  test('calls loadCard when pwaReadyValue is true', () => {
    controller.pwaReadyValue = true;
    controller.pwaReadyValueChanged();

    expect(controller.loadCard).toHaveBeenCalled();
  });

  test('does not call loadCard when pwaReadyValue is false', () => {
    controller.pwaReadyValue = false;
    controller.pwaReadyValueChanged();

    expect(controller.loadCard).not.toHaveBeenCalled();
  });
});

// ---------- onConnectionStateChanged ----------

describe('onConnectionStateChanged', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    controller.hasPhotoManageButtonTarget = true;
    controller.photoManageButtonTarget = document.createElement('button');
    controller.hasPhotoUploadModalTarget = false;
  });

  afterEach(() => jest.restoreAllMocks());

  test('delegates to updatePhotoActionsForConnection with isOnline=true', () => {
    const spy = jest.spyOn(controller, 'updatePhotoActionsForConnection');
    controller.onConnectionStateChanged(true);

    expect(spy).toHaveBeenCalledWith(true);
  });

  test('delegates to updatePhotoActionsForConnection with isOnline=false', () => {
    const spy = jest.spyOn(controller, 'updatePhotoActionsForConnection');
    controller.onConnectionStateChanged(false);

    expect(spy).toHaveBeenCalledWith(false);
  });
});

// ---------- retryLoad ----------

describe('retryLoad', () => {
  let controller;

  beforeEach(() => {
    controller = createController();
    controller.loadCard = jest.fn();
  });

  test('calls loadCard', () => {
    controller.retryLoad();
    expect(controller.loadCard).toHaveBeenCalled();
  });
});
