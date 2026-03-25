// Mock KMP_Timezone globally before import
global.KMP_Timezone = {
    detectTimezone: jest.fn(() => 'America/Chicago'),
    toLocalInput: jest.fn((utc, tz) => '2025-03-15T09:30'),
    toUTC: jest.fn((local, tz) => '2025-03-15T14:30:00Z'),
    getAbbreviation: jest.fn((tz) => 'CDT'),
};

// Polyfill CSS.escape for jsdom (not available in jsdom by default)
if (typeof CSS === 'undefined') {
    global.CSS = {};
}
if (!CSS.escape) {
    CSS.escape = function(value) {
        return String(value).replace(/([^\w-])/g, '\\$1');
    };
}

import '../../../assets/js/controllers/timezone-input-controller.js';

const TimezoneInputController = window.Controllers['timezone-input'];

describe('TimezoneInputController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="timezone-input"
                  data-timezone-input-timezone-value="America/Chicago"
                  data-timezone-input-show-notice-value="true">
                <input type="datetime-local"
                       name="start_date"
                       data-timezone-input-target="datetimeInput"
                       data-utc-value="2025-03-15T14:30:00Z"
                       value="">
                <input type="datetime-local"
                       name="end_date"
                       data-timezone-input-target="datetimeInput"
                       data-utc-value="2025-03-15T18:00:00Z"
                       value="">
                <div data-timezone-input-target="notice"></div>
            </form>
        `;

        controller = new TimezoneInputController();
        controller.element = document.querySelector('[data-controller="timezone-input"]');
        controller.datetimeInputTargets = Array.from(
            document.querySelectorAll('[data-timezone-input-target="datetimeInput"]')
        );
        controller.noticeTargets = Array.from(
            document.querySelectorAll('[data-timezone-input-target="notice"]')
        );
        controller.hasTimezoneValue = true;
        controller.timezoneValue = 'America/Chicago';
        controller.showNoticeValue = true;
        controller.hasNoticeTarget = true;

        jest.clearAllMocks();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['timezone-input']).toBe(TimezoneInputController);
    });

    test('has correct static targets', () => {
        expect(TimezoneInputController.targets).toEqual(
            expect.arrayContaining(['datetimeInput', 'notice'])
        );
    });

    test('has correct static values', () => {
        expect(TimezoneInputController.values).toHaveProperty('timezone', String);
        expect(TimezoneInputController.values).toHaveProperty('showNotice');
    });

    // connect tests
    test('connect uses provided timezone value', () => {
        controller.connect();
        expect(controller.timezone).toBe('America/Chicago');
        expect(KMP_Timezone.detectTimezone).not.toHaveBeenCalled();
    });

    test('connect detects timezone when none provided', () => {
        controller.hasTimezoneValue = false;
        controller.connect();
        expect(KMP_Timezone.detectTimezone).toHaveBeenCalled();
        expect(controller.timezone).toBe('America/Chicago');
    });

    test('connect converts UTC to local', () => {
        controller.connect();
        expect(KMP_Timezone.toLocalInput).toHaveBeenCalledTimes(2);
    });

    test('connect updates notice when showNotice is true', () => {
        controller.connect();
        expect(KMP_Timezone.getAbbreviation).toHaveBeenCalledWith('America/Chicago');
    });

    test('connect sets up submit and reset event listeners', () => {
        const addSpy = jest.spyOn(controller.element, 'addEventListener');
        controller.connect();
        expect(addSpy).toHaveBeenCalledWith('submit', expect.any(Function));
        expect(addSpy).toHaveBeenCalledWith('reset', expect.any(Function));
    });

    // convertUtcToLocal tests
    test('convertUtcToLocal sets input values and data attributes', () => {
        controller.timezone = 'America/Chicago';
        controller.convertUtcToLocal();

        controller.datetimeInputTargets.forEach(input => {
            expect(input.value).toBe('2025-03-15T09:30');
            expect(input.dataset.originalUtc).toBeDefined();
            expect(input.dataset.localValue).toBe('2025-03-15T09:30');
        });
    });

    test('convertUtcToLocal skips inputs without utc-value', () => {
        controller.datetimeInputTargets[0].removeAttribute('data-utc-value');
        delete controller.datetimeInputTargets[0].dataset.utcValue;
        controller.timezone = 'America/Chicago';
        KMP_Timezone.toLocalInput.mockClear();
        controller.convertUtcToLocal();
        // Only second input should be converted
        expect(KMP_Timezone.toLocalInput).toHaveBeenCalledTimes(1);
    });

    // updateNotice tests
    test('updateNotice displays timezone info', () => {
        controller.timezone = 'America/Chicago';
        controller.updateNotice();
        const notice = controller.noticeTargets[0];
        expect(notice.textContent).toContain('America/Chicago');
        expect(notice.textContent).toContain('CDT');
        expect(notice.querySelector('i.bi.bi-clock')).not.toBeNull();
    });

    // handleSubmit tests
    test('handleSubmit creates hidden inputs with UTC values', () => {
        controller.timezone = 'America/Chicago';
        controller.datetimeInputTargets.forEach(input => {
            input.value = '2025-03-15T09:30';
            input.name = input.getAttribute('name');
        });

        controller.handleSubmit(new Event('submit'));

        const hiddenInputs = controller.element.querySelectorAll('input[data-timezone-converted="true"]');
        expect(hiddenInputs.length).toBe(2);
        expect(hiddenInputs[0].value).toBe('2025-03-15T14:30:00Z');
    });

    test('handleSubmit disables original inputs', () => {
        controller.timezone = 'America/Chicago';
        controller.datetimeInputTargets.forEach(input => {
            input.value = '2025-03-15T09:30';
        });

        controller.handleSubmit(new Event('submit'));

        controller.datetimeInputTargets.forEach(input => {
            expect(input.disabled).toBe(true);
        });
    });

    test('handleSubmit skips empty inputs', () => {
        controller.timezone = 'America/Chicago';
        controller.datetimeInputTargets[0].value = '';
        controller.datetimeInputTargets[1].value = '2025-03-15T09:30';

        controller.handleSubmit(new Event('submit'));

        const hiddenInputs = controller.element.querySelectorAll('input[data-timezone-converted="true"]');
        expect(hiddenInputs.length).toBe(1);
    });

    test('handleSubmit marks failed conversion', () => {
        controller.timezone = 'America/Chicago';
        // Use a valid datetime-local format so input.value is truthy
        controller.datetimeInputTargets[0].value = '2025-03-15T09:30';
        controller.datetimeInputTargets[1].value = '';

        // Override toUTC to return null (simulating conversion failure)
        KMP_Timezone.toUTC.mockImplementation(() => null);

        controller.handleSubmit(new Event('submit'));

        expect(controller.datetimeInputTargets[0].dataset.timezoneConversionFailed).toBe('true');
        expect(controller.datetimeInputTargets[0].disabled).toBe(false);

        // Restore original implementation
        KMP_Timezone.toUTC.mockImplementation((local, tz) => '2025-03-15T14:30:00Z');
    });

    test('handleSubmit skips already-disabled inputs', () => {
        controller.timezone = 'America/Chicago';
        controller.datetimeInputTargets[0].value = '2025-03-15T09:30';
        controller.datetimeInputTargets[0].disabled = true;
        controller.datetimeInputTargets[1].value = '';

        controller.handleSubmit(new Event('submit'));

        const hiddenInputs = controller.element.querySelectorAll('input[data-timezone-converted="true"]');
        expect(hiddenInputs.length).toBe(0);
    });

    // handleReset tests
    test('handleReset removes hidden inputs and re-enables originals', () => {
        // First submit to create hidden inputs
        controller.timezone = 'America/Chicago';
        controller.datetimeInputTargets.forEach(input => {
            input.value = '2025-03-15T09:30';
            input.dataset.localValue = '2025-03-15T09:30';
        });
        controller.handleSubmit(new Event('submit'));

        // Then reset
        controller.handleReset(new Event('reset'));

        const hiddenInputs = controller.element.querySelectorAll('input[data-timezone-converted="true"]');
        expect(hiddenInputs.length).toBe(0);
        controller.datetimeInputTargets.forEach(input => {
            expect(input.disabled).toBe(false);
        });
    });

    // updateTimezone tests
    test('updateTimezone re-converts with new timezone', () => {
        controller.timezone = 'America/Chicago';
        KMP_Timezone.toLocalInput.mockClear();
        KMP_Timezone.getAbbreviation.mockClear();

        controller.updateTimezone('Europe/London');

        expect(controller.timezone).toBe('Europe/London');
        expect(KMP_Timezone.toLocalInput).toHaveBeenCalled();
        expect(KMP_Timezone.getAbbreviation).toHaveBeenCalledWith('Europe/London');
    });

    // getTimezone
    test('getTimezone returns current timezone', () => {
        controller.timezone = 'America/New_York';
        expect(controller.getTimezone()).toBe('America/New_York');
    });

    // disconnect tests
    test('disconnect removes event listeners', () => {
        controller.connect();
        const removeSpy = jest.spyOn(controller.element, 'removeEventListener');
        controller.disconnect();
        expect(removeSpy).toHaveBeenCalledWith('submit', expect.any(Function));
        expect(removeSpy).toHaveBeenCalledWith('reset', expect.any(Function));
        expect(controller._handleSubmit).toBeNull();
        expect(controller._handleReset).toBeNull();
    });
});
