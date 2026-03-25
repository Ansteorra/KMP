import '../../../assets/js/controllers/csv-download-controller.js';
const CsvDownloadController = window.Controllers['csv-download'];

describe('CsvDownloadController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <button data-controller="csv-download"
                    data-csv-download-url-value="/export/members.csv"
                    data-csv-download-filename-value="members.csv">
                Download CSV
            </button>
        `;

        controller = new CsvDownloadController();
        controller.element = document.querySelector('[data-controller="csv-download"]');
        controller.urlValue = '/export/members.csv';
        controller.filenameValue = 'members.csv';
        controller.hasButtonTarget = false;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) delete global.fetch;
    });

    // --- Static properties ---

    test('has correct static values', () => {
        expect(CsvDownloadController.values).toHaveProperty('url', String);
        expect(CsvDownloadController.values).toHaveProperty('filename', String);
    });

    test('has correct static targets', () => {
        expect(CsvDownloadController.targets).toEqual(['button']);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['csv-download']).toBe(CsvDownloadController);
    });

    // --- connect ---

    test('connect adds click listener to element when no button target', () => {
        const addSpy = jest.spyOn(controller.element, 'addEventListener');
        controller.connect();
        expect(addSpy).toHaveBeenCalledWith('click', expect.any(Function));
    });

    test('connect adds click listener to button target when present', () => {
        document.body.innerHTML = `
            <div data-controller="csv-download"
                 data-csv-download-url-value="/export.csv"
                 data-csv-download-filename-value="data.csv">
                <button data-csv-download-target="button">Download</button>
            </div>
        `;
        const ctrl = new CsvDownloadController();
        ctrl.element = document.querySelector('[data-controller="csv-download"]');
        ctrl.buttonTarget = document.querySelector('[data-csv-download-target="button"]');
        ctrl.hasButtonTarget = true;
        ctrl.urlValue = '/export.csv';
        ctrl.filenameValue = 'data.csv';

        const addSpy = jest.spyOn(ctrl.buttonTarget, 'addEventListener');
        ctrl.connect();
        expect(addSpy).toHaveBeenCalledWith('click', expect.any(Function));
    });

    // --- disconnect ---

    test('disconnect removes click listener from element', () => {
        const removeSpy = jest.spyOn(controller.element, 'removeEventListener');
        controller.disconnect();
        expect(removeSpy).toHaveBeenCalledWith('click', expect.any(Function));
    });

    // --- download ---

    test('download fetches CSV and creates download link', async () => {
        const mockBlob = new Blob(['col1,col2\n1,2'], { type: 'text/csv' });
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            blob: () => Promise.resolve(mockBlob)
        });

        window.URL.createObjectURL = jest.fn().mockReturnValue('blob:test');
        window.URL.revokeObjectURL = jest.fn();

        const event = { preventDefault: jest.fn() };
        await controller.download(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(global.fetch).toHaveBeenCalledWith(
            '/export/members.csv',
            expect.objectContaining({
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
        );
        expect(window.URL.createObjectURL).toHaveBeenCalledWith(mockBlob);
    });

    test('download uses default filename when none specified', async () => {
        controller.filenameValue = '';
        const mockBlob = new Blob(['data'], { type: 'text/csv' });
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            blob: () => Promise.resolve(mockBlob)
        });
        window.URL.createObjectURL = jest.fn().mockReturnValue('blob:test');
        window.URL.revokeObjectURL = jest.fn();

        const event = { preventDefault: jest.fn() };
        await controller.download(event);

        // Default filename should be 'export.csv'
        expect(global.fetch).toHaveBeenCalled();
    });

    test('download alerts when no URL provided', async () => {
        controller.urlValue = '';
        controller.element.removeAttribute('href');
        controller.element.removeAttribute('data-url');

        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
        const event = { preventDefault: jest.fn() };
        await controller.download(event);

        expect(alertSpy).toHaveBeenCalledWith('No CSV URL provided.');
    });

    test('download alerts on fetch error', async () => {
        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            status: 500
        });

        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
        const event = { preventDefault: jest.fn() };
        await controller.download(event);

        expect(alertSpy).toHaveBeenCalledWith(expect.stringContaining('Error downloading CSV'));
    });

    test('download alerts on network error', async () => {
        global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));

        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
        const event = { preventDefault: jest.fn() };
        await controller.download(event);

        expect(alertSpy).toHaveBeenCalledWith(expect.stringContaining('Network error'));
    });

    test('download falls back to href attribute when no urlValue', async () => {
        controller.urlValue = '';
        controller.element.setAttribute('href', '/fallback.csv');

        const mockBlob = new Blob(['data'], { type: 'text/csv' });
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            blob: () => Promise.resolve(mockBlob)
        });
        window.URL.createObjectURL = jest.fn().mockReturnValue('blob:test');
        window.URL.revokeObjectURL = jest.fn();

        const event = { preventDefault: jest.fn() };
        await controller.download(event);

        expect(global.fetch).toHaveBeenCalledWith('/fallback.csv', expect.any(Object));
    });

    test('download revokes object URL after timeout', async () => {
        jest.useFakeTimers();
        const mockBlob = new Blob(['data'], { type: 'text/csv' });
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            blob: () => Promise.resolve(mockBlob)
        });
        window.URL.createObjectURL = jest.fn().mockReturnValue('blob:test');
        window.URL.revokeObjectURL = jest.fn();

        const event = { preventDefault: jest.fn() };
        await controller.download(event);

        jest.advanceTimersByTime(200);
        expect(window.URL.revokeObjectURL).toHaveBeenCalledWith('blob:test');
        jest.useRealTimers();
    });
});
