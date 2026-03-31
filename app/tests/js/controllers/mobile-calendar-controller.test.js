// Mock services before importing controller
jest.mock('../../../assets/js/services/offline-queue-service.js', () => ({
    __esModule: true,
    default: {
        init: jest.fn().mockResolvedValue(undefined),
        enqueue: jest.fn().mockResolvedValue(undefined)
    }
}));

jest.mock('../../../assets/js/services/rsvp-cache-service.js', () => ({
    __esModule: true,
    default: {
        init: jest.fn().mockResolvedValue(undefined),
        cacheUserRsvps: jest.fn().mockResolvedValue(undefined),
        getPendingCount: jest.fn().mockResolvedValue(0),
        syncPendingRsvps: jest.fn().mockResolvedValue({ success: 0, failed: 0 }),
        queueOfflineRsvp: jest.fn().mockResolvedValue(undefined),
        updateCachedRsvp: jest.fn().mockResolvedValue(undefined),
        removeCachedRsvp: jest.fn().mockResolvedValue(undefined)
    }
}));

// Mock the base class — provide a simple Controller-like base
jest.mock('../../../assets/js/controllers/mobile-controller-base.js', () => {
    const { Controller } = require('@hotwired/stimulus');

    class MockMobileControllerBase extends Controller {
        static isOnline = true;
        static connectionListeners = new Set();
        static initialized = false;

        initialize() {
            this._online = true;
            this._boundHandlers = new Map();
        }

        connect() {
            if (typeof this.onConnect === 'function') {
                this.onConnect();
            }
        }

        disconnect() {
            if (typeof this.onDisconnect === 'function') {
                this.onDisconnect();
            }
        }

        get online() {
            return this._online !== undefined ? this._online : true;
        }

        set online(val) {
            this._online = val;
        }

        bindHandler(name, fn) {
            const bound = fn.bind(this);
            this._boundHandlers.set(name, bound);
            return bound;
        }

        async fetchWithRetry(url) {
            return fetch(url);
        }

        showToast(message, type) {
            // stub
        }

        setupPullToRefresh() {
            this.pullStartY = 0;
            this.isPulling = false;
            this.pullThreshold = 80;
            this.pullIndicator = null;
        }
    }

    return {
        __esModule: true,
        default: MockMobileControllerBase
    };
});

import '../../../assets/js/controllers/mobile-calendar-controller.js';
import rsvpCacheService from '../../../assets/js/services/rsvp-cache-service.js';

const MobileCalendarController = window.Controllers['mobile-calendar'];

describe('MobileCalendarController', () => {
    let controller;

    function buildDOM() {
        return `
            <div data-controller="mobile-calendar"
                 data-mobile-calendar-year-value="2025"
                 data-mobile-calendar-month-value="6"
                 data-mobile-calendar-data-url-value="/api/calendar"
                 data-mobile-calendar-rsvp-url-value="/api/rsvp"
                 data-mobile-calendar-unrsvp-url-value="/api/unrsvp"
                 data-mobile-calendar-update-rsvp-url-value="/api/update-rsvp">

                <div data-mobile-calendar-target="loading" hidden>Loading...</div>
                <div data-mobile-calendar-target="error" hidden>
                    <span data-mobile-calendar-target="errorMessage"></span>
                </div>
                <div data-mobile-calendar-target="eventList" hidden></div>
                <div data-mobile-calendar-target="emptyState" hidden>
                    <span data-mobile-calendar-target="emptyMessage">No events</span>
                </div>
                <div data-mobile-calendar-target="resultsCount" hidden></div>

                <input data-mobile-calendar-target="searchInput" type="text" value="">
                <div data-mobile-calendar-target="filterPanel" hidden>
                    <button data-mobile-calendar-target="filterToggle"></button>
                    <select data-mobile-calendar-target="typeFilter"><option value="">All Types</option></select>
                    <select data-mobile-calendar-target="activityFilter"><option value="">All Activities</option></select>
                    <select data-mobile-calendar-target="branchFilter"><option value="">All Branches</option></select>
                    <input type="checkbox" data-mobile-calendar-target="rsvpFilter">
                </div>

                <select data-mobile-calendar-target="monthSelect">
                    <option value="1">Jan</option><option value="2">Feb</option>
                    <option value="3">Mar</option><option value="4">Apr</option>
                    <option value="5">May</option><option value="6">Jun</option>
                    <option value="7">Jul</option><option value="8">Aug</option>
                    <option value="9">Sep</option><option value="10">Oct</option>
                    <option value="11">Nov</option><option value="12">Dec</option>
                </select>
                <select data-mobile-calendar-target="yearSelect"></select>

                <div data-mobile-calendar-target="rsvpSheet" hidden></div>
                <div data-mobile-calendar-target="rsvpContent"></div>

                <div data-mobile-calendar-target="pendingBanner" hidden>
                    <span data-mobile-calendar-target="pendingCount">0</span>
                    <button data-mobile-calendar-target="syncBtn">
                        <i class="bi bi-arrow-repeat"></i> Sync
                    </button>
                </div>
            </div>
            <meta name="csrf-token" content="test-csrf-token">
        `;
    }

    function setupController() {
        document.body.innerHTML = buildDOM();

        controller = new MobileCalendarController();
        controller.element = document.querySelector('[data-controller="mobile-calendar"]');

        // Wire values
        controller.yearValue = 2025;
        controller.monthValue = 6;
        controller.dataUrlValue = '/api/calendar';
        controller.rsvpUrlValue = '/api/rsvp';
        controller.unrsvpUrlValue = '/api/unrsvp';
        controller.updateRsvpUrlValue = '/api/update-rsvp';

        // Wire targets
        const q = (sel) => controller.element.querySelector(`[data-mobile-calendar-target="${sel}"]`);

        controller.loadingTarget = q('loading');
        controller.errorTarget = q('error');
        controller.errorMessageTarget = q('errorMessage');
        controller.eventListTarget = q('eventList');
        controller.emptyStateTarget = q('emptyState');
        controller.emptyMessageTarget = q('emptyMessage');
        controller.resultsCountTarget = q('resultsCount');
        controller.searchInputTarget = q('searchInput');
        controller.filterPanelTarget = q('filterPanel');
        controller.filterToggleTarget = q('filterToggle');
        controller.typeFilterTarget = q('typeFilter');
        controller.activityFilterTarget = q('activityFilter');
        controller.branchFilterTarget = q('branchFilter');
        controller.rsvpFilterTarget = q('rsvpFilter');
        controller.monthSelectTarget = q('monthSelect');
        controller.yearSelectTarget = q('yearSelect');
        controller.rsvpSheetTarget = q('rsvpSheet');
        controller.rsvpContentTarget = q('rsvpContent');
        controller.pendingBannerTarget = q('pendingBanner');
        controller.pendingCountTarget = q('pendingCount');
        controller.syncBtnTarget = q('syncBtn');

        // Wire has* checks
        controller.hasLoadingTarget = true;
        controller.hasErrorTarget = true;
        controller.hasErrorMessageTarget = true;
        controller.hasEventListTarget = true;
        controller.hasEmptyStateTarget = true;
        controller.hasEmptyMessageTarget = true;
        controller.hasResultsCountTarget = true;
        controller.hasSearchInputTarget = true;
        controller.hasFilterPanelTarget = true;
        controller.hasFilterToggleTarget = true;
        controller.hasTypeFilterTarget = true;
        controller.hasActivityFilterTarget = true;
        controller.hasBranchFilterTarget = true;
        controller.hasRsvpFilterTarget = true;
        controller.hasMonthSelectTarget = true;
        controller.hasYearSelectTarget = true;
        controller.hasRsvpSheetTarget = true;
        controller.hasRsvpContentTarget = true;
        controller.hasPendingBannerTarget = true;
        controller.hasPendingCountTarget = true;
        controller.hasSyncBtnTarget = true;

        // Initialize state
        controller.initialize();

        return controller;
    }

    beforeEach(() => {
        window.bootstrap = {
            Modal: jest.fn().mockImplementation(() => ({
                show: jest.fn(),
                hide: jest.fn(),
                dispose: jest.fn()
            })),
            Toast: jest.fn().mockImplementation(() => ({
                show: jest.fn()
            }))
        };
        window.bootstrap.Modal.getInstance = jest.fn().mockReturnValue({
            hide: jest.fn()
        });

        jest.clearAllMocks();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) {
            delete global.fetch;
        }
    });

    // ==================== Static properties ====================

    test('registers on window.Controllers as "mobile-calendar"', () => {
        expect(window.Controllers['mobile-calendar']).toBe(MobileCalendarController);
    });

    test('has correct static targets', () => {
        expect(MobileCalendarController.targets).toEqual(
            expect.arrayContaining([
                'loading', 'error', 'errorMessage',
                'eventList', 'emptyState', 'emptyMessage', 'resultsCount',
                'searchInput', 'filterPanel', 'filterToggle',
                'typeFilter', 'activityFilter', 'branchFilter', 'rsvpFilter',
                'monthSelect', 'yearSelect',
                'rsvpSheet', 'rsvpContent',
                'pendingBanner', 'pendingCount', 'syncBtn'
            ])
        );
    });

    test('has correct static values', () => {
        expect(MobileCalendarController.values).toHaveProperty('year');
        expect(MobileCalendarController.values).toHaveProperty('month');
        expect(MobileCalendarController.values).toHaveProperty('dataUrl');
        expect(MobileCalendarController.values).toHaveProperty('rsvpUrl');
        expect(MobileCalendarController.values).toHaveProperty('unrsvpUrl');
        expect(MobileCalendarController.values).toHaveProperty('updateRsvpUrl');
    });

    // ==================== Initialize ====================

    test('initialize sets default state', () => {
        setupController();

        expect(controller.calendarData).toBeNull();
        expect(controller.filteredEvents).toEqual([]);
        expect(controller.searchDebounce).toBeNull();
        expect(controller.filters).toEqual({
            search: '', type: '', activity: '', branch: '', rsvpOnly: false
        });
    });

    // ==================== Display states ====================

    test('showLoading shows loading target and hides others', () => {
        setupController();
        controller.showLoading();

        expect(controller.loadingTarget.hidden).toBe(false);
        expect(controller.errorTarget.hidden).toBe(true);
        expect(controller.eventListTarget.hidden).toBe(true);
        expect(controller.emptyStateTarget.hidden).toBe(true);
        expect(controller.resultsCountTarget.hidden).toBe(true);
    });

    test('showError shows error target with message', () => {
        setupController();
        controller.showError('Something went wrong');

        expect(controller.loadingTarget.hidden).toBe(true);
        expect(controller.errorTarget.hidden).toBe(false);
        expect(controller.eventListTarget.hidden).toBe(true);
        expect(controller.errorMessageTarget.textContent).toBe('Something went wrong');
    });

    test('showEventList shows eventList when events exist', () => {
        setupController();
        controller.filteredEvents = [
            { id: 1, name: 'Event 1', start_date: '2025-06-15', type: { name: 'Practice' } }
        ];
        jest.spyOn(controller, 'renderEventList').mockImplementation(() => {});

        controller.showEventList();

        expect(controller.loadingTarget.hidden).toBe(true);
        expect(controller.errorTarget.hidden).toBe(true);
        expect(controller.eventListTarget.hidden).toBe(false);
        expect(controller.emptyStateTarget.hidden).toBe(true);
    });

    test('showEventList shows emptyState when no events', () => {
        setupController();
        controller.filteredEvents = [];

        controller.showEventList();

        expect(controller.eventListTarget.hidden).toBe(true);
        expect(controller.emptyStateTarget.hidden).toBe(false);
        expect(controller.resultsCountTarget.hidden).toBe(true);
    });

    // ==================== Navigation ====================

    test('previousMonth decrements month', () => {
        setupController();
        controller.monthValue = 6;
        controller.yearValue = 2025;
        jest.spyOn(controller, 'loadCalendarData').mockResolvedValue(undefined);
        jest.spyOn(controller, 'updateNavigationSelectors').mockImplementation(() => {});

        controller.previousMonth();

        expect(controller.monthValue).toBe(5);
        expect(controller.yearValue).toBe(2025);
    });

    test('previousMonth wraps to December of previous year', () => {
        setupController();
        controller.monthValue = 1;
        controller.yearValue = 2025;
        jest.spyOn(controller, 'loadCalendarData').mockResolvedValue(undefined);
        jest.spyOn(controller, 'updateNavigationSelectors').mockImplementation(() => {});

        controller.previousMonth();

        expect(controller.monthValue).toBe(12);
        expect(controller.yearValue).toBe(2024);
    });

    test('nextMonth increments month', () => {
        setupController();
        controller.monthValue = 6;
        controller.yearValue = 2025;
        jest.spyOn(controller, 'loadCalendarData').mockResolvedValue(undefined);
        jest.spyOn(controller, 'updateNavigationSelectors').mockImplementation(() => {});

        controller.nextMonth();

        expect(controller.monthValue).toBe(7);
        expect(controller.yearValue).toBe(2025);
    });

    test('nextMonth wraps to January of next year', () => {
        setupController();
        controller.monthValue = 12;
        controller.yearValue = 2025;
        jest.spyOn(controller, 'loadCalendarData').mockResolvedValue(undefined);
        jest.spyOn(controller, 'updateNavigationSelectors').mockImplementation(() => {});

        controller.nextMonth();

        expect(controller.monthValue).toBe(1);
        expect(controller.yearValue).toBe(2026);
    });

    test('goToToday sets month and year to current date', () => {
        setupController();
        jest.spyOn(controller, 'loadCalendarData').mockResolvedValue(undefined);
        jest.spyOn(controller, 'updateNavigationSelectors').mockImplementation(() => {});

        controller.goToToday();

        const now = new Date();
        expect(controller.yearValue).toBe(now.getFullYear());
        expect(controller.monthValue).toBe(now.getMonth() + 1);
    });

    test('jumpToMonth reads from month/year selectors', () => {
        setupController();
        controller.monthSelectTarget.value = '9';
        controller.yearSelectTarget.innerHTML = '<option value="2026">2026</option>';
        controller.yearSelectTarget.value = '2026';
        jest.spyOn(controller, 'loadCalendarData').mockResolvedValue(undefined);

        controller.jumpToMonth();

        expect(controller.monthValue).toBe(9);
        expect(controller.yearValue).toBe(2026);
    });

    // ==================== Year selector ====================

    test('initYearSelector populates year options', () => {
        setupController();
        controller.initYearSelector();

        const options = controller.yearSelectTarget.querySelectorAll('option');
        expect(options.length).toBe(4); // currentYear-1 to currentYear+2

        const currentYear = new Date().getFullYear();
        expect(options[0].value).toBe(String(currentYear - 1));
        expect(options[3].value).toBe(String(currentYear + 2));
    });

    test('initYearSelector does nothing without target', () => {
        setupController();
        controller.hasYearSelectTarget = false;
        // Should not throw
        controller.initYearSelector();
    });

    // ==================== Navigation selectors ====================

    test('updateNavigationSelectors sets month and year select values', () => {
        setupController();
        controller.monthValue = 8;
        controller.yearValue = 2025;

        controller.initYearSelector();
        controller.updateNavigationSelectors();

        expect(controller.monthSelectTarget.value).toBe('8');
        expect(controller.yearSelectTarget.value).toBe('2025');
    });

    // ==================== Filtering ====================

    test('populateFilters populates type, activity, and branch dropdowns', () => {
        setupController();
        controller.calendarData = {
            events: [
                { type: { name: 'Practice' }, branch: 'Shire A', activities: [{ name: 'Fencing' }] },
                { type: { name: 'Event' }, branch: 'Barony B', activities: [{ name: 'Archery' }] },
                { type: { name: 'Practice' }, branch: 'Shire A', activities: [{ name: 'Fencing' }, { name: 'Heavy' }] },
            ]
        };

        controller.populateFilters();

        const typeOptions = controller.typeFilterTarget.querySelectorAll('option');
        expect(typeOptions.length).toBe(3); // "All Types" + 2 unique

        const activityOptions = controller.activityFilterTarget.querySelectorAll('option');
        expect(activityOptions.length).toBe(4); // "All Activities" + 3 unique

        const branchOptions = controller.branchFilterTarget.querySelectorAll('option');
        expect(branchOptions.length).toBe(3); // "All Branches" + 2 unique
    });

    test('populateFilters does nothing without calendar data', () => {
        setupController();
        controller.calendarData = null;
        // Should not throw
        controller.populateFilters();
    });

    test('applyFilters filters events by search text', () => {
        setupController();
        controller.calendarData = {
            events: [
                { name: 'Sword Practice', location: 'Park', branch: 'Shire A', type: { name: 'Practice' } },
                { name: 'Archery Event', location: 'Field', branch: 'Barony B', type: { name: 'Event' } },
            ]
        };
        controller.searchInputTarget.value = 'sword';
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});

        controller.applyFilters();

        expect(controller.filteredEvents.length).toBe(1);
        expect(controller.filteredEvents[0].name).toBe('Sword Practice');
    });

    test('applyFilters filters events by type', () => {
        setupController();
        controller.calendarData = {
            events: [
                { name: 'A', type: { name: 'Practice' } },
                { name: 'B', type: { name: 'Event' } },
                { name: 'C', type: { name: 'Practice' } },
            ]
        };
        controller.typeFilterTarget.innerHTML = '<option value="Practice">Practice</option>';
        controller.typeFilterTarget.value = 'Practice';
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});

        controller.applyFilters();

        expect(controller.filteredEvents.length).toBe(2);
    });

    test('applyFilters filters events by activity', () => {
        setupController();
        controller.calendarData = {
            events: [
                { name: 'A', type: { name: 'Practice' }, activities: [{ name: 'Fencing' }] },
                { name: 'B', type: { name: 'Event' }, activities: [{ name: 'Archery' }] },
            ]
        };
        controller.activityFilterTarget.innerHTML = '<option value="Fencing">Fencing</option>';
        controller.activityFilterTarget.value = 'Fencing';
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});

        controller.applyFilters();

        expect(controller.filteredEvents.length).toBe(1);
        expect(controller.filteredEvents[0].name).toBe('A');
    });

    test('applyFilters filters events by branch', () => {
        setupController();
        controller.calendarData = {
            events: [
                { name: 'A', type: { name: 'Practice' }, branch: 'Shire A' },
                { name: 'B', type: { name: 'Event' }, branch: 'Barony B' },
            ]
        };
        controller.branchFilterTarget.innerHTML = '<option value="Barony B">Barony B</option>';
        controller.branchFilterTarget.value = 'Barony B';
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});

        controller.applyFilters();

        expect(controller.filteredEvents.length).toBe(1);
        expect(controller.filteredEvents[0].name).toBe('B');
    });

    test('applyFilters filters events by RSVP status', () => {
        setupController();
        controller.calendarData = {
            events: [
                { name: 'A', type: { name: 'Practice' }, user_attending: true },
                { name: 'B', type: { name: 'Event' }, user_attending: false },
            ]
        };
        controller.rsvpFilterTarget.checked = true;
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});

        controller.applyFilters();

        expect(controller.filteredEvents.length).toBe(1);
        expect(controller.filteredEvents[0].name).toBe('A');
    });

    test('applyFilters with no events sets filteredEvents to empty', () => {
        setupController();
        controller.calendarData = null;
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});

        controller.applyFilters();

        expect(controller.filteredEvents).toEqual([]);
    });

    test('applyFilters updates filter toggle indicator', () => {
        setupController();
        controller.calendarData = {
            events: [{ name: 'A', type: { name: 'Practice' } }]
        };
        controller.typeFilterTarget.innerHTML = '<option value="Practice">Practice</option>';
        controller.typeFilterTarget.value = 'Practice';
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});

        controller.applyFilters();

        expect(controller.filterToggleTarget.classList.contains('filter-active')).toBe(true);
    });

    // ==================== clearFilters ====================

    test('clearFilters resets all filter inputs and state', () => {
        setupController();
        controller.calendarData = { events: [{ name: 'A', type: { name: 'P' } }] };
        controller.searchInputTarget.value = 'test';
        controller.typeFilterTarget.value = 'Practice';
        controller.rsvpFilterTarget.checked = true;
        controller.filters.search = 'test';
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});

        controller.clearFilters();

        expect(controller.searchInputTarget.value).toBe('');
        expect(controller.typeFilterTarget.value).toBe('');
        expect(controller.rsvpFilterTarget.checked).toBe(false);
        expect(controller.filters).toEqual({
            search: '', type: '', activity: '', branch: '', rsvpOnly: false
        });
    });

    // ==================== handleSearch (debounce) ====================

    test('handleSearch debounces applyFilters', () => {
        jest.useFakeTimers();
        setupController();
        jest.spyOn(controller, 'applyFilters').mockImplementation(() => {});

        controller.handleSearch();
        controller.handleSearch();
        controller.handleSearch();

        expect(controller.applyFilters).not.toHaveBeenCalled();

        jest.advanceTimersByTime(300);

        expect(controller.applyFilters).toHaveBeenCalledTimes(1);
        jest.useRealTimers();
    });

    // ==================== toggleFilters ====================

    test('toggleFilters toggles filter panel visibility', () => {
        setupController();
        controller.filterPanelTarget.hidden = true;

        controller.toggleFilters();
        expect(controller.filterPanelTarget.hidden).toBe(false);

        controller.toggleFilters();
        expect(controller.filterPanelTarget.hidden).toBe(true);
    });

    // ==================== updateResultsCount ====================

    test('updateResultsCount shows filtered count when different from total', () => {
        setupController();
        controller.calendarData = { events: [1, 2, 3] };
        controller.filteredEvents = [1, 2];

        controller.updateResultsCount();

        expect(controller.resultsCountTarget.hidden).toBe(false);
        expect(controller.resultsCountTarget.innerHTML).toContain('Showing 2 of 3');
    });

    test('updateResultsCount shows total when not filtered', () => {
        setupController();
        controller.calendarData = { events: [1, 2, 3] };
        controller.filteredEvents = [1, 2, 3];

        controller.updateResultsCount();

        expect(controller.resultsCountTarget.hidden).toBe(false);
        expect(controller.resultsCountTarget.innerHTML).toContain('3 events');
    });

    test('updateResultsCount does nothing without target', () => {
        setupController();
        controller.hasResultsCountTarget = false;
        // Should not throw
        controller.updateResultsCount();
    });

    // ==================== loadCalendarData ====================

    test('loadCalendarData fetches data and updates state on success', async () => {
        setupController();
        const mockData = {
            success: true,
            data: {
                events: [
                    { id: 1, name: 'Test Event', start_date: '2025-06-15', type: { name: 'Practice' } }
                ]
            }
        };

        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockData)
        });
        jest.spyOn(controller, 'renderEventList').mockImplementation(() => {});

        await controller.loadCalendarData();

        expect(global.fetch).toHaveBeenCalledWith('/api/calendar?year=2025&month=6');
        expect(controller.calendarData).toBe(mockData.data);
        expect(controller.loadingTarget.hidden).toBe(true);
    });

    test('loadCalendarData shows error on failed response data', async () => {
        setupController();
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: false })
        });

        await controller.loadCalendarData();

        expect(controller.errorTarget.hidden).toBe(false);
        expect(controller.errorMessageTarget.textContent).toBe('Failed to load events');
    });

    test('loadCalendarData shows error on fetch failure', async () => {
        setupController();
        global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));

        await controller.loadCalendarData();

        expect(controller.errorTarget.hidden).toBe(false);
    });

    // ==================== updatePendingBanner ====================

    test('updatePendingBanner shows banner when pending RSVPs exist', async () => {
        setupController();
        rsvpCacheService.getPendingCount.mockResolvedValue(3);

        await controller.updatePendingBanner();

        expect(controller.pendingBannerTarget.hidden).toBe(false);
        expect(controller.pendingCountTarget.textContent).toBe('3');
    });

    test('updatePendingBanner hides banner when no pending RSVPs', async () => {
        setupController();
        rsvpCacheService.getPendingCount.mockResolvedValue(0);

        await controller.updatePendingBanner();

        expect(controller.pendingBannerTarget.hidden).toBe(true);
    });

    test('updatePendingBanner hides banner on error', async () => {
        setupController();
        rsvpCacheService.getPendingCount.mockRejectedValue(new Error('fail'));

        await controller.updatePendingBanner();

        expect(controller.pendingBannerTarget.hidden).toBe(true);
    });

    test('updatePendingBanner does nothing without target', async () => {
        setupController();
        controller.hasPendingBannerTarget = false;

        // Should not throw
        await controller.updatePendingBanner();
    });

    // ==================== syncPendingRsvps ====================

    test('syncPendingRsvps syncs and reloads data', async () => {
        setupController();
        Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
        rsvpCacheService.syncPendingRsvps.mockResolvedValue({ success: 2, failed: 0 });
        jest.spyOn(controller, 'loadCalendarData').mockResolvedValue(undefined);
        jest.spyOn(controller, 'showToast').mockImplementation(() => {});
        jest.spyOn(controller, 'updatePendingBanner').mockResolvedValue(undefined);

        await controller.syncPendingRsvps();

        expect(rsvpCacheService.syncPendingRsvps).toHaveBeenCalled();
        expect(controller.loadCalendarData).toHaveBeenCalled();
        expect(controller.showToast).toHaveBeenCalledWith('Synced 2 RSVP(s)!', 'success');
    });

    test('syncPendingRsvps warns when offline', async () => {
        setupController();
        Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
        jest.spyOn(controller, 'showToast').mockImplementation(() => {});

        await controller.syncPendingRsvps();

        expect(controller.showToast).toHaveBeenCalledWith('Cannot sync while offline', 'warning');
        expect(rsvpCacheService.syncPendingRsvps).not.toHaveBeenCalled();
    });

    test('syncPendingRsvps re-enables button on error', async () => {
        setupController();
        Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
        rsvpCacheService.syncPendingRsvps.mockRejectedValue(new Error('fail'));
        jest.spyOn(controller, 'showToast').mockImplementation(() => {});
        jest.spyOn(controller, 'updatePendingBanner').mockResolvedValue(undefined);

        await controller.syncPendingRsvps();

        expect(controller.syncBtnTarget.disabled).toBe(false);
    });

    // ==================== Rendering ====================

    test('groupEventsByWeek groups events by week', () => {
        setupController();
        const events = [
            { id: 1, name: 'A', start_date: '2025-06-02' },
            { id: 2, name: 'B', start_date: '2025-06-04' },
            { id: 3, name: 'C', start_date: '2025-06-15' },
        ];

        const weeks = controller.groupEventsByWeek(events);
        expect(weeks.size).toBeGreaterThanOrEqual(2);

        // First two events should be in the same week
        let totalEvents = 0;
        weeks.forEach(weekEvents => {
            totalEvents += weekEvents.length;
        });
        expect(totalEvents).toBe(3);
    });

    test('getWeekStart returns Sunday of the week', () => {
        setupController();
        // Wednesday June 4, 2025
        const date = new Date(2025, 5, 4);
        const weekStart = controller.getWeekStart(date);

        expect(weekStart.getDay()).toBe(0); // Sunday
        expect(weekStart.getDate()).toBe(1); // June 1
    });

    test('renderActivities returns empty string for no activities', () => {
        setupController();
        expect(controller.renderActivities(null)).toBe('');
        expect(controller.renderActivities([])).toBe('');
    });

    test('renderActivities renders activity names', () => {
        setupController();
        const html = controller.renderActivities([
            { name: 'Fencing' },
            { name: 'Archery' }
        ]);

        expect(html).toContain('Fencing');
        expect(html).toContain('Archery');
    });

    // ==================== formatTime ====================

    test('formatTime converts 24h to 12h format', () => {
        setupController();
        expect(controller.formatTime('14:30')).toBe('2:30 PM');
        expect(controller.formatTime('09:15')).toBe('9:15 AM');
        expect(controller.formatTime('00:00')).toBe('12:00 AM');
        expect(controller.formatTime('12:00')).toBe('12:00 PM');
    });

    test('formatTime returns empty for null/undefined', () => {
        setupController();
        expect(controller.formatTime(null)).toBe('');
        expect(controller.formatTime(undefined)).toBe('');
    });

    // ==================== escapeHtml ====================

    test('escapeHtml escapes HTML entities', () => {
        setupController();
        expect(controller.escapeHtml('<script>alert("xss")</script>')).toBe(
            '&lt;script&gt;alert("xss")&lt;/script&gt;'
        );
    });

    test('escapeHtml returns empty string for null/falsy', () => {
        setupController();
        expect(controller.escapeHtml(null)).toBe('');
        expect(controller.escapeHtml('')).toBe('');
        expect(controller.escapeHtml(undefined)).toBe('');
    });

    // ==================== getCsrfToken ====================

    test('getCsrfToken returns token from meta tag', () => {
        setupController();
        expect(controller.getCsrfToken()).toBe('test-csrf-token');
    });

    test('getCsrfToken returns empty when no meta tag', () => {
        setupController();
        document.querySelector('meta[name="csrf-token"]').remove();
        expect(controller.getCsrfToken()).toBe('');
    });

    // ==================== Touch handlers ====================

    test('handleTouchStart records touch position', () => {
        setupController();
        const event = {
            touches: [{ clientX: 100, clientY: 200 }]
        };

        controller.handleTouchStart(event);

        expect(controller.touchStartX).toBe(100);
        expect(controller.touchStartY).toBe(200);
    });

    test('handleTouchEnd triggers nextMonth on left swipe', () => {
        setupController();
        jest.spyOn(controller, 'nextMonth').mockImplementation(() => {});
        controller.touchStartX = 200;
        controller.touchStartY = 100;
        controller.isPulling = false;

        const event = {
            changedTouches: [{ clientX: 100, clientY: 100 }]
        };

        controller.handleTouchEnd(event);

        expect(controller.nextMonth).toHaveBeenCalled();
    });

    test('handleTouchEnd triggers previousMonth on right swipe', () => {
        setupController();
        jest.spyOn(controller, 'previousMonth').mockImplementation(() => {});
        controller.touchStartX = 100;
        controller.touchStartY = 100;
        controller.isPulling = false;

        const event = {
            changedTouches: [{ clientX: 200, clientY: 100 }]
        };

        controller.handleTouchEnd(event);

        expect(controller.previousMonth).toHaveBeenCalled();
    });

    test('handleTouchEnd does nothing on small swipe', () => {
        setupController();
        jest.spyOn(controller, 'nextMonth').mockImplementation(() => {});
        jest.spyOn(controller, 'previousMonth').mockImplementation(() => {});
        controller.touchStartX = 100;
        controller.touchStartY = 100;
        controller.isPulling = false;

        const event = {
            changedTouches: [{ clientX: 120, clientY: 100 }]
        };

        controller.handleTouchEnd(event);

        expect(controller.nextMonth).not.toHaveBeenCalled();
        expect(controller.previousMonth).not.toHaveBeenCalled();
    });

    // ==================== createBottomSheet ====================

    test('createBottomSheet creates modal element', () => {
        setupController();
        // Remove any existing
        const existing = document.getElementById('mobileRsvpModal');
        if (existing) existing.remove();

        controller.createBottomSheet();

        const modal = document.getElementById('mobileRsvpModal');
        expect(modal).not.toBeNull();
        expect(modal.classList.contains('modal')).toBe(true);
    });

    test('createBottomSheet does not duplicate modal', () => {
        setupController();
        controller.createBottomSheet();
        controller.createBottomSheet();

        const modals = document.querySelectorAll('#mobileRsvpModal');
        expect(modals.length).toBe(1);
    });

    // ==================== handleRsvpClick ====================

    test('handleRsvpClick shows modal when online', async () => {
        setupController();
        Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
        jest.spyOn(controller, 'showRsvpSheet').mockResolvedValue(undefined);

        controller.filteredEvents = [{ id: 42, name: 'Event' }];

        const button = document.createElement('button');
        button.dataset.eventId = '42';
        const event = {
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
            currentTarget: button
        };

        await controller.handleRsvpClick(event);

        expect(controller.showRsvpSheet).toHaveBeenCalled();
    });

    test('handleRsvpClick queues offline RSVP when offline', async () => {
        setupController();
        Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
        jest.spyOn(controller, 'queueOfflineRsvp').mockResolvedValue(undefined);

        controller.filteredEvents = [{ id: 42, name: 'Test Event' }];

        const button = document.createElement('button');
        button.dataset.eventId = '42';
        const event = {
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
            currentTarget: button
        };

        await controller.handleRsvpClick(event);

        expect(controller.queueOfflineRsvp).toHaveBeenCalledWith(
            expect.objectContaining({ id: 42 }),
            button
        );
    });

    test('handleRsvpClick returns early when event not found', async () => {
        setupController();
        controller.filteredEvents = [];
        controller.calendarData = { events: [] };
        jest.spyOn(controller, 'showRsvpSheet').mockResolvedValue(undefined);

        const button = document.createElement('button');
        button.dataset.eventId = '999';
        const event = {
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
            currentTarget: button
        };

        await controller.handleRsvpClick(event);

        expect(controller.showRsvpSheet).not.toHaveBeenCalled();
    });

    // ==================== queueOfflineRsvp ====================

    test('queueOfflineRsvp queues RSVP and updates event state', async () => {
        jest.useFakeTimers();
        setupController();
        jest.spyOn(controller, 'showToast').mockImplementation(() => {});
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});

        const eventData = { id: 1, name: 'Event', user_attending: false };
        const button = document.createElement('button');
        button.innerHTML = 'RSVP';

        await controller.queueOfflineRsvp(eventData, button);

        expect(rsvpCacheService.queueOfflineRsvp).toHaveBeenCalledWith(
            expect.objectContaining({ gathering_id: 1 })
        );
        expect(eventData.user_attending).toBe(true);
        expect(button.innerHTML).toContain('Queued');
        expect(controller.showToast).toHaveBeenCalledWith('RSVP queued - will sync when online', 'info');

        jest.useRealTimers();
    });

    test('queueOfflineRsvp handles error and re-enables button', async () => {
        setupController();
        rsvpCacheService.queueOfflineRsvp.mockRejectedValue(new Error('fail'));
        jest.spyOn(controller, 'showToast').mockImplementation(() => {});

        const button = document.createElement('button');
        button.innerHTML = 'RSVP';

        await controller.queueOfflineRsvp({ id: 1, name: 'Event' }, button);

        expect(button.disabled).toBe(false);
        expect(controller.showToast).toHaveBeenCalledWith('Failed to queue RSVP', 'danger');
    });

    // ==================== onConnectionStateChanged ====================

    test('onConnectionStateChanged reloads data when coming online', () => {
        setupController();
        controller.calendarData = null;
        jest.spyOn(controller, 'loadCalendarData').mockResolvedValue(undefined);
        jest.spyOn(controller, 'updatePendingBanner').mockResolvedValue(undefined);

        controller.onConnectionStateChanged(true);

        expect(controller.loadCalendarData).toHaveBeenCalled();
        expect(controller.updatePendingBanner).toHaveBeenCalled();
    });

    test('onConnectionStateChanged re-renders when going offline with data', () => {
        setupController();
        controller.calendarData = { events: [] };
        jest.spyOn(controller, 'showEventList').mockImplementation(() => {});
        jest.spyOn(controller, 'updatePendingBanner').mockResolvedValue(undefined);

        controller.onConnectionStateChanged(false);

        expect(controller.showEventList).toHaveBeenCalled();
    });

    // ==================== restoreFromUrlParams ====================

    test('restoreFromUrlParams sets month/year from URL params', () => {
        setupController();
        window.history.pushState({}, '', '/calendar?month=9&year=2026');
        window.history.replaceState = jest.fn();

        controller.restoreFromUrlParams();

        expect(controller.monthValue).toBe(9);
        expect(controller.yearValue).toBe(2026);

        // Restore URL
        window.history.pushState({}, '', '/');
    });

    test('restoreFromUrlParams ignores invalid values', () => {
        setupController();
        window.history.pushState({}, '', '/calendar?month=13&year=2025');

        const originalMonth = controller.monthValue;
        controller.restoreFromUrlParams();

        expect(controller.monthValue).toBe(originalMonth);

        // Restore URL
        window.history.pushState({}, '', '/');
    });

    test('restoreFromUrlParams does nothing without params', () => {
        setupController();
        window.history.pushState({}, '', '/calendar');

        const originalMonth = controller.monthValue;
        const originalYear = controller.yearValue;
        controller.restoreFromUrlParams();

        expect(controller.monthValue).toBe(originalMonth);
        expect(controller.yearValue).toBe(originalYear);

        // Restore URL
        window.history.pushState({}, '', '/');
    });

    // ==================== reload ====================

    test('reload calls loadCalendarData', () => {
        setupController();
        jest.spyOn(controller, 'loadCalendarData').mockResolvedValue(undefined);

        controller.reload();

        expect(controller.loadCalendarData).toHaveBeenCalled();
    });

    // ==================== renderEventList ====================

    test('renderEventList renders grouped events into eventListTarget', () => {
        setupController();
        controller.filteredEvents = [
            {
                id: 1, name: 'Event A', start_date: '2025-06-15',
                type: { name: 'Practice', color: '#ff0000' },
                branch: 'Shire A', user_attending: false,
                is_cancelled: false, public_page_enabled: true,
                public_id: 'abc123'
            }
        ];

        controller.renderEventList();

        expect(controller.eventListTarget.innerHTML).toContain('Event A');
        expect(controller.eventListTarget.innerHTML).toContain('Shire A');
    });

    test('renderEventList does nothing when no events', () => {
        setupController();
        controller.filteredEvents = [];
        controller.eventListTarget.innerHTML = 'original';

        controller.renderEventList();

        expect(controller.eventListTarget.innerHTML).toBe('original');
    });
});
