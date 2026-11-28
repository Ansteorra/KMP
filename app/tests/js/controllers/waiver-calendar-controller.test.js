// Controller registers on window.Controllers (no default export)
import '../../../plugins/Waivers/assets/js/controllers/waiver-calendar-controller.js';
const WaiverCalendarController = window.Controllers['waiver-calendar'];

describe('WaiverCalendarController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="waiver-calendar">
                <turbo-frame data-waiver-calendar-target="frame">
                    <div data-waiver-calendar-target="calendar"></div>
                </turbo-frame>
                <div data-waiver-calendar-target="status" role="status"></div>
                <fieldset data-waiver-calendar-target="filterControls" disabled></fieldset>
                <span data-waiver-calendar-target="monthLabel"></span>
                <button data-waiver-calendar-target="prevBtn">Prev</button>
                <button data-waiver-calendar-target="nextBtn">Next</button>
            </div>
        `;

        controller = new WaiverCalendarController();
        controller.element = document.querySelector('[data-controller="waiver-calendar"]');

        for (const target of WaiverCalendarController.targets) {
            controller[`${target}Target`] = controller.element.querySelector(
                `[data-waiver-calendar-target="${target}"]`,
            );
        }
        controller.emptyMessageValue = 'No gatherings match this view.';
        controller.errorMessageValue = 'The calendar could not be loaded.';
        controller.hasGridViewOutlet = true;
        controller.gridViewOutlet = {
            buildUrl: jest.fn(() => '/dashboard?year=2025&month=1&filter%5Bstatus%5D%5B%5D=complete'),
            navigate: jest.fn(),
        };
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    function loadMonth(overrides = {}) {
        const month = { year: 2025, month: 1, monthName: 'January 2025', events: [], ...overrides };
        controller.monthDataTargetConnected({ textContent: JSON.stringify(month) });
    }

    test('loads filtered month data and enables the calendar filter and navigation controls', () => {
        loadMonth();

        expect(controller.monthLabelTarget).toHaveTextContent('January 2025');
        expect(controller.calendarTarget.querySelector('.waiver-calendar-grid')).toBeInTheDocument();
        expect(controller.filterControlsTarget).toBeEnabled();
        expect(controller.prevBtnTarget).toBeEnabled();
        expect(controller.nextBtnTarget).toBeEnabled();
        expect(controller.statusTarget).toHaveTextContent(controller.emptyMessageValue);
    });

    test('uses Dataverse navigation to move across a year boundary with active filters', () => {
        loadMonth({ year: 2024, month: 12, monthName: 'December 2024' });
        controller.nextMonth();

        expect(controller.gridViewOutlet.buildUrl).toHaveBeenCalledWith({ year: 2025, month: 1 });
        expect(controller.gridViewOutlet.navigate).toHaveBeenCalledWith(
            '/dashboard?year=2025&month=1&filter%5Bstatus%5D%5B%5D=complete',
        );
        expect(controller.monthLabelTarget).toHaveTextContent('December 2024');
    });

    test('disables navigation beyond supported years', () => {
        loadMonth({ year: 2000, month: 1 });
        expect(controller.prevBtnTarget).toBeDisabled();

        loadMonth({ year: 2100, month: 12 });
        expect(controller.nextBtnTarget).toBeDisabled();
    });

    test('announces a fetch failure while preserving the displayed calendar', () => {
        loadMonth();
        controller.loadFailed();

        expect(controller.statusTarget).toHaveTextContent(controller.errorMessageValue);
        expect(controller.calendarTarget.querySelector('.waiver-calendar-grid')).toBeInTheDocument();
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
