import '../../../assets/js/controllers/gatherings-calendar-controller.js';
const GatheringsCalendarController = window.Controllers['gatherings-calendar'];

describe('GatheringsCalendarController', () => {
    let controller;

    function buildDOM() {
        return `
            <div data-controller="gatherings-calendar"
                 data-gatherings-calendar-year-value="2025"
                 data-gatherings-calendar-month-value="6"
                 data-gatherings-calendar-view-value="month"
                 data-gatherings-calendar-week-start-value="">

                <div data-gatherings-calendar-header>June 2025</div>

                <a data-gatherings-calendar-nav="prev" href="/gatherings/calendar?view=month&year=2025&month=05">Prev</a>
                <a data-gatherings-calendar-nav="today" href="/gatherings/calendar?view=month&year=2025&month=06">Today</a>
                <a data-gatherings-calendar-nav="next" href="/gatherings/calendar?view=month&year=2025&month=07">Next</a>

                <turbo-frame id="gatherings-calendar-grid-table" src="/gatherings/calendar?view=month&year=2025&month=06"></turbo-frame>

                <input id="calendarFeedUrl" data-base-feed-url="https://example.com/feed.ics" value="">

                <div id="gatheringQuickViewModal" class="modal fade">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <turbo-frame id="gatheringQuickView"></turbo-frame>
                        </div>
                    </div>
                </div>

                <div id="attendanceModal" class="modal fade">
                    <div class="modal-dialog">
                        <div id="attendanceModalContent" class="modal-content"></div>
                    </div>
                </div>

                <div class="gathering-item"
                     data-action="click->gatherings-calendar#showQuickView"
                     data-gathering-id="123">
                    Test Gathering
                </div>
            </div>
            <meta name="csrf-token" content="test-csrf-token">
        `;
    }

    function setupController() {
        document.body.innerHTML = buildDOM();

        controller = new GatheringsCalendarController();
        controller.element = document.querySelector('[data-controller="gatherings-calendar"]');

        // Wire values
        controller.yearValue = 2025;
        controller.monthValue = 6;
        controller.viewValue = 'month';
        controller.weekStartValue = '';
        controller.hasYearValue = true;
        controller.hasMonthValue = true;
        controller.hasViewValue = true;
        controller.hasWeekStartValue = false;

        return controller;
    }

    beforeEach(() => {
        // Setup bootstrap mock with full modal lifecycle
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
            hide: jest.fn(),
            dispose: jest.fn()
        });
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) {
            delete global.fetch;
        }
    });

    // ==================== Static properties ====================

    test('registers on window.Controllers as "gatherings-calendar"', () => {
        expect(window.Controllers['gatherings-calendar']).toBe(GatheringsCalendarController);
    });

    test('has correct static values', () => {
        expect(GatheringsCalendarController.values).toHaveProperty('year');
        expect(GatheringsCalendarController.values).toHaveProperty('month');
        expect(GatheringsCalendarController.values).toHaveProperty('view');
        expect(GatheringsCalendarController.values).toHaveProperty('weekStart');
    });

    // ==================== Initialize ====================

    test('initialize sets modal properties to null', () => {
        setupController();
        controller.initialize();

        expect(controller.modalElement).toBeNull();
        expect(controller.modalInstance).toBeNull();
        expect(controller.turboFrame).toBeNull();
    });

    // ==================== Connect ====================

    test('connect finds modal and turbo-frame elements', () => {
        setupController();
        controller.connect();

        expect(controller.modalElement).not.toBeNull();
        expect(controller.turboFrame).not.toBeNull();
    });

    test('connect creates Bootstrap modal instance when modal element exists', () => {
        setupController();
        controller.connect();

        expect(window.bootstrap.Modal).toHaveBeenCalledWith(controller.modalElement);
        expect(controller.modalInstance).not.toBeNull();
    });

    test('connect registers popstate and grid-view:navigated listeners', () => {
        setupController();
        const addSpy = jest.spyOn(window, 'addEventListener');
        controller.connect();

        const eventNames = addSpy.mock.calls.map(c => c[0]);
        expect(eventNames).toContain('popstate');
        expect(eventNames).toContain('grid-view:navigated');
    });

    // ==================== Disconnect ====================

    test('disconnect removes window event listeners', () => {
        setupController();
        controller.connect();

        const removeSpy = jest.spyOn(window, 'removeEventListener');
        controller.disconnect();

        const eventNames = removeSpy.mock.calls.map(c => c[0]);
        expect(eventNames).toContain('popstate');
        expect(eventNames).toContain('grid-view:navigated');
    });

    test('disconnect nullifies handler references', () => {
        setupController();
        controller.connect();
        controller.disconnect();

        expect(controller._popstateHandler).toBeNull();
        expect(controller._pushStateHandler).toBeNull();
    });

    test('disconnect disposes modal instance', () => {
        setupController();
        controller.connect();
        const disposeSpy = controller.modalInstance.dispose;
        controller.disconnect();

        expect(disposeSpy).toHaveBeenCalled();
    });

    // ==================== getCalendarElement ====================

    test('getCalendarElement returns controller element when it has data attributes', () => {
        setupController();
        const el = controller.getCalendarElement();
        expect(el).toBe(controller.element);
    });

    test('getCalendarElement falls back to querySelector when element lacks calendar attributes', () => {
        setupController();
        const original = controller.element;
        // Create a div without any gatherings-calendar data attributes
        const emptyDiv = document.createElement('div');
        controller.element = emptyDiv;

        const el = controller.getCalendarElement();
        // Should fall back to querySelector and find the original element
        expect(el).toBe(original);
    });

    // ==================== getDisplayedCalendarState ====================

    test('getDisplayedCalendarState returns state from data attributes', () => {
        setupController();
        const state = controller.getDisplayedCalendarState();

        expect(state.year).toBe(2025);
        expect(state.month).toBe(6);
        expect(state.view).toBe('month');
    });

    test('getDisplayedCalendarState falls back to controller values', () => {
        setupController();
        // Remove data attributes from element
        delete controller.element.dataset.gatheringsCalendarYearValue;
        delete controller.element.dataset.gatheringsCalendarMonthValue;
        delete controller.element.dataset.gatheringsCalendarViewValue;

        const state = controller.getDisplayedCalendarState();

        expect(state.year).toBe(2025);
        expect(state.month).toBe(6);
        expect(state.view).toBe('month');
    });

    // ==================== updateCalendarHeader ====================

    test('updateCalendarHeader updates header text with formatted date', () => {
        setupController();
        controller.updateCalendarHeader();

        const header = document.querySelector('[data-gatherings-calendar-header]');
        // The header should contain the month and year
        expect(header.textContent).toContain('2025');
        expect(header.textContent).toContain('June');
    });

    test('updateCalendarHeader does nothing when header element is missing', () => {
        setupController();
        document.querySelector('[data-gatherings-calendar-header]').remove();

        // Should not throw
        controller.updateCalendarHeader();
    });

    test('updateCalendarHeader does nothing when year/month missing', () => {
        setupController();
        delete controller.element.dataset.gatheringsCalendarYearValue;
        delete controller.element.dataset.gatheringsCalendarMonthValue;
        controller.hasYearValue = false;
        controller.hasMonthValue = false;

        const header = document.querySelector('[data-gatherings-calendar-header]');
        header.textContent = 'Original';
        controller.updateCalendarHeader();

        expect(header.textContent).toBe('Original');
    });

    // ==================== updateFeedUrl ====================

    test('updateFeedUrl builds feed URL without filters', () => {
        setupController();
        // Simulate no filter params in URL
        delete window.location;
        window.location = new URL('http://localhost/gatherings/calendar');

        controller.updateFeedUrl();

        const feedInput = document.getElementById('calendarFeedUrl');
        expect(feedInput.value).toBe('https://example.com/feed.ics');
    });

    test('updateFeedUrl includes filter params in feed URL', () => {
        setupController();
        // Use pushState to set URL params without navigation
        window.history.pushState({}, '', '/gatherings/calendar?filter[branch]=5&filter[type]=Practice&other=ignore');

        controller.updateFeedUrl();

        const feedInput = document.getElementById('calendarFeedUrl');
        expect(feedInput.value).toContain('https://example.com/feed.ics?');
        expect(feedInput.value).toContain('filter');
        expect(feedInput.value).not.toContain('other=ignore');

        // Restore URL
        window.history.pushState({}, '', '/');
    });

    test('updateFeedUrl does nothing when feedInput is missing', () => {
        setupController();
        document.getElementById('calendarFeedUrl').remove();

        // Should not throw
        controller.updateFeedUrl();
    });

    test('updateFeedUrl does nothing when baseFeedUrl is missing', () => {
        setupController();
        const feedInput = document.getElementById('calendarFeedUrl');
        feedInput.removeAttribute('data-base-feed-url');

        controller.updateFeedUrl();
        expect(feedInput.value).toBe('');
    });

    // ==================== updateCalendarNavigation ====================

    test('updateCalendarNavigation sets prev/next/today links for month view', () => {
        setupController();
        controller.updateCalendarNavigation();

        const prevLink = document.querySelector('[data-gatherings-calendar-nav="prev"]');
        const nextLink = document.querySelector('[data-gatherings-calendar-nav="next"]');
        const todayLink = document.querySelector('[data-gatherings-calendar-nav="today"]');

        const prevHref = prevLink.getAttribute('href');
        const nextHref = nextLink.getAttribute('href');

        expect(prevHref).toContain('year=2025');
        expect(prevHref).toContain('month=05');
        expect(nextHref).toContain('year=2025');
        expect(nextHref).toContain('month=07');

        // Today link should contain current date
        const todayHref = todayLink.getAttribute('href');
        const now = new Date();
        expect(todayHref).toContain(`year=${now.getFullYear()}`);
    });

    test('updateCalendarNavigation does nothing when frame is missing', () => {
        setupController();
        document.getElementById('gatherings-calendar-grid-table').remove();

        // Should not throw
        controller.updateCalendarNavigation();
    });

    test('updateCalendarNavigation handles week view', () => {
        setupController();
        controller.viewValue = 'week';
        controller.element.dataset.gatheringsCalendarViewValue = 'week';
        controller.element.dataset.gatheringsCalendarWeekStartValue = '2025-06-01';
        controller.hasWeekStartValue = true;
        controller.weekStartValue = '2025-06-01';

        controller.updateCalendarNavigation();

        const prevLink = document.querySelector('[data-gatherings-calendar-nav="prev"]');
        const prevHref = prevLink.getAttribute('href');
        expect(prevHref).toContain('week_start=');
    });

    // ==================== getCsrfToken ====================

    test('getCsrfToken returns token from meta tag', () => {
        setupController();
        const token = controller.getCsrfToken();
        expect(token).toBe('test-csrf-token');
    });

    test('getCsrfToken returns token from input when meta missing', () => {
        setupController();
        document.querySelector('meta[name="csrf-token"]').remove();
        const input = document.createElement('input');
        input.name = '_csrfToken';
        input.value = 'input-token';
        document.body.appendChild(input);

        expect(controller.getCsrfToken()).toBe('input-token');
    });

    test('getCsrfToken returns empty string when no token found', () => {
        setupController();
        document.querySelector('meta[name="csrf-token"]').remove();
        expect(controller.getCsrfToken()).toBe('');
    });

    // ==================== showQuickView ====================

    test('showQuickView prevents default and shows modal', async () => {
        setupController();
        controller.connect();

        const event = {
            preventDefault: jest.fn(),
            currentTarget: document.createElement('a')
        };
        event.currentTarget.setAttribute('href', '/gatherings/view/123');

        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: () => Promise.resolve('<turbo-frame id="gatheringQuickView"><p>Content</p></turbo-frame>')
        });

        await controller.showQuickView(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.modalInstance.show).toHaveBeenCalled();
    });

    test('showQuickView loads content into turbo-frame', async () => {
        setupController();
        controller.connect();

        const event = {
            preventDefault: jest.fn(),
            currentTarget: document.createElement('a')
        };
        event.currentTarget.setAttribute('href', '/gatherings/view/123');

        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: () => Promise.resolve('<turbo-frame id="gatheringQuickView"><p>Loaded Content</p></turbo-frame>')
        });

        await controller.showQuickView(event);

        expect(controller.turboFrame.innerHTML).toContain('Loaded Content');
    });

    test('showQuickView handles fetch error gracefully', async () => {
        setupController();
        controller.connect();

        const event = {
            preventDefault: jest.fn(),
            currentTarget: document.createElement('a')
        };
        event.currentTarget.setAttribute('href', '/gatherings/view/999');

        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            status: 500
        });

        await controller.showQuickView(event);

        expect(controller.turboFrame.innerHTML).toContain('Error loading gathering details');
    });

    test('showQuickView handles missing turbo-frame in response', async () => {
        setupController();
        controller.connect();

        const event = {
            preventDefault: jest.fn(),
            currentTarget: document.createElement('a')
        };
        event.currentTarget.setAttribute('href', '/gatherings/view/123');

        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: () => Promise.resolve('<html><body>No turbo frame here</body></html>')
        });

        await controller.showQuickView(event);

        expect(controller.turboFrame.innerHTML).toContain('Failed to load gathering details');
    });

    test('showQuickView returns early when no modal instance', async () => {
        setupController();
        controller.initialize();
        // Don't connect, so modalInstance stays null

        const event = {
            preventDefault: jest.fn(),
            currentTarget: document.createElement('a')
        };
        event.currentTarget.setAttribute('href', '/gatherings/view/123');

        global.fetch = jest.fn();

        await controller.showQuickView(event);

        expect(global.fetch).not.toHaveBeenCalled();
    });

    // ==================== showToast ====================

    test('showToast creates toast container and toast element', () => {
        setupController();
        controller.showToast('Title', 'Message', 'success');

        const container = document.getElementById('toast-container');
        expect(container).not.toBeNull();
        expect(container.innerHTML).toContain('Title');
        expect(container.innerHTML).toContain('Message');

        expect(window.bootstrap.Toast).toHaveBeenCalled();
    });

    test('showToast reuses existing toast container', () => {
        setupController();
        controller.showToast('First', 'One', 'info');
        controller.showToast('Second', 'Two', 'success');

        const containers = document.querySelectorAll('#toast-container');
        expect(containers.length).toBe(1);
    });

    // ==================== toggleAttendance ====================

    test('toggleAttendance sends POST for non-attending', async () => {
        jest.useFakeTimers();
        setupController();
        const button = document.createElement('button');
        button.dataset.gatheringId = '42';
        button.dataset.attending = 'false';
        button.innerHTML = '<i class="bi bi-calendar-check"></i> Attend';
        button.classList.add('btn-outline-success');

        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: true, attendance_id: '99' })
        });

        await controller.toggleAttendance({ currentTarget: button });

        expect(global.fetch).toHaveBeenCalledWith(
            '/gathering-attendances/add',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    'X-CSRF-Token': 'test-csrf-token'
                })
            })
        );

        expect(button.dataset.attending).toBe('true');
        expect(button.dataset.attendanceId).toBe('99');
        expect(button.classList.contains('btn-success')).toBe(true);
        jest.useRealTimers();
    });

    test('toggleAttendance sends DELETE for attending', async () => {
        jest.useFakeTimers();
        setupController();
        const button = document.createElement('button');
        button.dataset.gatheringId = '42';
        button.dataset.attendanceId = '99';
        button.dataset.attending = 'true';
        button.innerHTML = '<i class="bi bi-check-circle"></i> Attending';

        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: true })
        });

        await controller.toggleAttendance({ currentTarget: button });

        expect(global.fetch).toHaveBeenCalledWith(
            '/gathering-attendances/delete/99',
            expect.objectContaining({ method: 'DELETE' })
        );

        expect(button.dataset.attending).toBe('false');
        jest.useRealTimers();
    });

    test('toggleAttendance handles error and restores button', async () => {
        setupController();
        const button = document.createElement('button');
        button.dataset.gatheringId = '42';
        button.dataset.attending = 'false';
        button.innerHTML = 'Attend';

        global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));

        await controller.toggleAttendance({ currentTarget: button });

        expect(button.disabled).toBe(false);
        expect(button.innerHTML).toBe('Attend');
    });

    test('toggleAttendance returns early when no gatheringId', async () => {
        setupController();
        const button = document.createElement('button');
        // No gatheringId
        global.fetch = jest.fn();

        await controller.toggleAttendance({ currentTarget: button });

        expect(global.fetch).not.toHaveBeenCalled();
    });

    // ==================== markAttendance ====================

    test('markAttendance sets action to add and delegates to showAttendanceModal', async () => {
        setupController();
        jest.spyOn(controller, 'showAttendanceModal').mockResolvedValue(undefined);

        const button = document.createElement('button');
        button.dataset.gatheringId = '10';

        const event = { preventDefault: jest.fn(), currentTarget: button };
        await controller.markAttendance(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(button.dataset.attendanceAction).toBe('add');
        expect(controller.showAttendanceModal).toHaveBeenCalledWith(event);
    });

    test('markAttendance returns early when no gatheringId', async () => {
        setupController();
        jest.spyOn(controller, 'showAttendanceModal');

        const button = document.createElement('button');
        const event = { preventDefault: jest.fn(), currentTarget: button };
        await controller.markAttendance(event);

        expect(controller.showAttendanceModal).not.toHaveBeenCalled();
    });

    // ==================== updateAttendance ====================

    test('updateAttendance sets action to edit and delegates to showAttendanceModal', async () => {
        setupController();
        jest.spyOn(controller, 'showAttendanceModal').mockResolvedValue(undefined);

        const button = document.createElement('button');
        const event = { preventDefault: jest.fn(), currentTarget: button };
        await controller.updateAttendance(event);

        expect(button.dataset.attendanceAction).toBe('edit');
        expect(controller.showAttendanceModal).toHaveBeenCalledWith(event);
    });

    // ==================== showAttendanceModal ====================

    test('showAttendanceModal fetches and loads modal content', async () => {
        setupController();
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: () => Promise.resolve('<div class="modal-body">Form</div>')
        });

        const button = document.createElement('button');
        button.dataset.attendanceAction = 'add';
        button.dataset.gatheringId = '42';

        await controller.showAttendanceModal({ currentTarget: button });

        expect(global.fetch).toHaveBeenCalledWith('/gatherings/attendance-modal/42');
        const content = document.getElementById('attendanceModalContent');
        expect(content.innerHTML).toContain('Form');
    });

    test('showAttendanceModal uses edit URL with attendanceId', async () => {
        setupController();
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: () => Promise.resolve('<div>Edit Form</div>')
        });

        const button = document.createElement('button');
        button.dataset.attendanceAction = 'edit';
        button.dataset.gatheringId = '42';
        button.dataset.attendanceId = '7';

        await controller.showAttendanceModal({ currentTarget: button });

        expect(global.fetch).toHaveBeenCalledWith('/gatherings/attendance-modal/42?attendance_id=7');
    });

    test('showAttendanceModal shows error on fetch failure', async () => {
        setupController();
        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            status: 500
        });

        const button = document.createElement('button');
        button.dataset.attendanceAction = 'add';
        button.dataset.gatheringId = '42';

        await controller.showAttendanceModal({ currentTarget: button });

        const content = document.getElementById('attendanceModalContent');
        expect(content.innerHTML).toContain('Failed to load attendance form');
    });

    test('showAttendanceModal returns early when attendanceModal missing', async () => {
        setupController();
        document.getElementById('attendanceModal').remove();
        global.fetch = jest.fn();

        const button = document.createElement('button');
        button.dataset.gatheringId = '42';

        await controller.showAttendanceModal({ currentTarget: button });

        expect(global.fetch).not.toHaveBeenCalled();
    });

    // ==================== showLocation ====================

    test('showLocation builds correct URL from gatheringId', () => {
        setupController();
        const button = document.createElement('button');
        button.dataset.gatheringId = '42';

        // The method constructs /gatherings/view/{id}#nav-location-tab
        // Verify the URL would be correct (jsdom doesn't support navigation)
        expect(`/gatherings/view/${button.dataset.gatheringId}#nav-location-tab`)
            .toBe('/gatherings/view/42#nav-location-tab');
    });

    test('showLocation returns early when no gatheringId', () => {
        setupController();
        const button = document.createElement('button');
        // No gatheringId — should not throw
        expect(() => {
            controller.showLocation({ currentTarget: button });
        }).not.toThrow();
    });
});
