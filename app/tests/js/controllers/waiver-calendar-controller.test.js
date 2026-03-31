// Controller registers on window.Controllers (no default export)
import '../../../plugins/Waivers/assets/js/controllers/waiver-calendar-controller.js';
const WaiverCalendarController = window.Controllers['waiver-calendar'];

describe('WaiverCalendarController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="waiver-calendar"
                 data-waiver-calendar-url-value="/api/calendar">
                <div data-waiver-calendar-target="calendar"></div>
                <span data-waiver-calendar-target="monthLabel"></span>
                <button data-waiver-calendar-target="prevBtn">Prev</button>
                <button data-waiver-calendar-target="nextBtn">Next</button>
            </div>
        `;

        controller = new WaiverCalendarController();
        controller.element = document.querySelector('[data-controller="waiver-calendar"]');

        // Wire up targets
        controller.calendarTarget = document.querySelector('[data-waiver-calendar-target="calendar"]');
        controller.monthLabelTarget = document.querySelector('[data-waiver-calendar-target="monthLabel"]');
        controller.prevBtnTarget = document.querySelector('[data-waiver-calendar-target="prevBtn"]');
        controller.nextBtnTarget = document.querySelector('[data-waiver-calendar-target="nextBtn"]');

        // Wire up values
        controller.urlValue = '/api/calendar';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(WaiverCalendarController.targets).toEqual(
            expect.arrayContaining(['calendar', 'monthLabel', 'prevBtn', 'nextBtn'])
        );
    });

    test('has correct static values', () => {
        expect(WaiverCalendarController.values).toHaveProperty('url', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['waiver-calendar']).toBe(WaiverCalendarController);
    });

    // --- connect ---

    test('connect sets current month and year and calls loadMonth', () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ monthName: 'January', year: 2025, month: 1, events: [] })
        }));

        const now = new Date();
        controller.connect();

        expect(controller.year).toBe(now.getFullYear());
        expect(controller.month).toBe(now.getMonth() + 1);
        expect(global.fetch).toHaveBeenCalled();
    });

    // --- prevMonth / nextMonth ---

    test('prevMonth decrements month', () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ monthName: 'March', year: 2025, month: 3, events: [] })
        }));

        controller.year = 2025;
        controller.month = 3;
        controller.prevMonth();
        expect(controller.month).toBe(2);
        expect(controller.year).toBe(2025);
    });

    test('prevMonth wraps to December of previous year', () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ monthName: 'Dec', year: 2024, month: 12, events: [] })
        }));

        controller.year = 2025;
        controller.month = 1;
        controller.prevMonth();
        expect(controller.month).toBe(12);
        expect(controller.year).toBe(2024);
    });

    test('nextMonth increments month', () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ monthName: 'April', year: 2025, month: 4, events: [] })
        }));

        controller.year = 2025;
        controller.month = 3;
        controller.nextMonth();
        expect(controller.month).toBe(4);
        expect(controller.year).toBe(2025);
    });

    test('nextMonth wraps to January of next year', () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ monthName: 'Jan', year: 2026, month: 1, events: [] })
        }));

        controller.year = 2025;
        controller.month = 12;
        controller.nextMonth();
        expect(controller.month).toBe(1);
        expect(controller.year).toBe(2026);
    });

    // --- loadMonth ---

    test('loadMonth fetches data and renders calendar', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                monthName: 'January 2025',
                year: 2025,
                month: 1,
                events: []
            })
        }));

        controller.year = 2025;
        controller.month = 1;
        await controller.loadMonth();

        expect(global.fetch).toHaveBeenCalledWith(
            '/api/calendar?year=2025&month=1',
            expect.objectContaining({ headers: { 'Accept': 'application/json' } })
        );
        expect(controller.monthLabelTarget.textContent).toBe('January 2025');
        expect(controller.calendarTarget.innerHTML).toContain('waiver-calendar-grid');
    });

    test('loadMonth appends with & when url already has query params', async () => {
        controller.urlValue = '/api/calendar?branch=5';
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ monthName: 'Jan', year: 2025, month: 1, events: [] })
        }));

        controller.year = 2025;
        controller.month = 1;
        await controller.loadMonth();

        expect(global.fetch).toHaveBeenCalledWith(
            '/api/calendar?branch=5&year=2025&month=1',
            expect.any(Object)
        );
    });

    test('loadMonth shows error on fetch failure', async () => {
        global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));

        controller.year = 2025;
        controller.month = 1;
        await controller.loadMonth();

        expect(controller.calendarTarget.innerHTML).toContain('alert-danger');
        expect(controller.calendarTarget.innerHTML).toContain('Failed to load calendar data');
    });

    test('loadMonth shows error on non-ok response', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: false,
            status: 500
        }));

        controller.year = 2025;
        controller.month = 1;
        await controller.loadMonth();

        expect(controller.calendarTarget.innerHTML).toContain('alert-danger');
    });

    // --- renderCalendar ---

    test('renderCalendar renders day headers', () => {
        const data = { year: 2025, month: 1, events: [] };
        controller.renderCalendar(data);
        expect(controller.calendarTarget.innerHTML).toContain('Sun');
        expect(controller.calendarTarget.innerHTML).toContain('Mon');
        expect(controller.calendarTarget.innerHTML).toContain('Sat');
    });

    test('renderCalendar renders events on matching dates', () => {
        const data = {
            year: 2025, month: 1,
            events: [{
                start_date: '2025-01-15',
                name: 'Test Event',
                branch: 'Test Branch',
                status: 'complete',
                url: '/event/1',
                uploaded: 3,
                exempted: 0,
                pending: 0
            }]
        };
        controller.renderCalendar(data);
        expect(controller.calendarTarget.innerHTML).toContain('Test Event');
        expect(controller.calendarTarget.innerHTML).toContain('Test Branch');
    });

    // --- renderEvent ---

    test('renderEvent returns HTML with status color for missing', () => {
        const evt = {
            name: 'Event', branch: 'Branch', status: 'missing',
            url: '/e/1', uploaded: 0, exempted: 0, pending: 0
        };
        const html = controller.renderEvent(evt);
        expect(html).toContain('#dc3545'); // red
        expect(html).toContain('No Waivers');
    });

    test('renderEvent shows badges for uploaded/exempted/pending counts', () => {
        const evt = {
            name: 'Event', branch: 'Branch', status: 'partial',
            url: '/e/1', uploaded: 2, exempted: 1, pending: 3
        };
        const html = controller.renderEvent(evt);
        expect(html).toContain('2 Uploaded');
        expect(html).toContain('1 Exempted');
        expect(html).toContain('3 Pending');
    });

    test('renderEvent shows closed badge for closed status', () => {
        const evt = {
            name: 'Event', branch: 'Branch', status: 'closed',
            url: '/e/1', uploaded: 0, exempted: 0, pending: 0
        };
        const html = controller.renderEvent(evt);
        expect(html).toContain('Closed');
        expect(html).toContain('#0d6efd'); // blue
    });

    test('renderEvent shows ready-to-close badge', () => {
        const evt = {
            name: 'Event', branch: 'Branch', status: 'complete',
            url: '/e/1', uploaded: 1, exempted: 0, pending: 0, ready_to_close: true
        };
        const html = controller.renderEvent(evt);
        expect(html).toContain('Ready to Close');
    });

    test('renderEvent adds multi-day class when applicable', () => {
        const evt = {
            name: 'Event', branch: 'Branch', status: 'complete',
            url: '/e/1', uploaded: 1, exempted: 0, pending: 0, multi_day: true
        };
        const html = controller.renderEvent(evt);
        expect(html).toContain('multi-day');
    });

    // --- dateKey ---

    test('dateKey formats date as YYYY-MM-DD with padding', () => {
        const d = new Date(2025, 0, 5); // Jan 5
        expect(controller.dateKey(d)).toBe('2025-01-05');
    });

    test('dateKey handles double-digit months and days', () => {
        const d = new Date(2025, 11, 25); // Dec 25
        expect(controller.dateKey(d)).toBe('2025-12-25');
    });

    // --- escapeHtml ---

    test('escapeHtml escapes special characters', () => {
        expect(controller.escapeHtml('<b>bold</b>')).toContain('&lt;b&gt;');
    });
});
