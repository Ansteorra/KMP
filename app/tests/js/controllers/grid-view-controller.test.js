import '../../../assets/js/controllers/grid-view-controller.js';
const GridViewController = window.Controllers['grid-view'];

describe('GridViewController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="grid-view">
                <input data-grid-view-target="searchInput" type="text" value="">
                <span data-grid-view-target="searchStatusIndicator" class="d-none"></span>
                <turbo-frame id="members-table">
                    <script type="application/json">
                        {"filters":{"active":{},"available":{}},"sort":{},"pagination":{},"config":{}}
                    </script>
                    <table><tr><td>Row</td></tr></table>
                </turbo-frame>
            </div>
        `;

        controller = new GridViewController();
        controller.element = document.querySelector('[data-controller="grid-view"]');
        controller.hasSearchInputTarget = true;
        controller.searchInputTarget = document.querySelector('[data-grid-view-target="searchInput"]');
        controller.hasSearchStatusIndicatorTarget = true;
        controller.searchStatusIndicatorTarget = document.querySelector('[data-grid-view-target="searchStatusIndicator"]');
        controller.hasGridStateTarget = false;
        controller.hasRowCheckboxTarget = false;
        controller.hasSelectAllCheckboxTarget = false;
        controller.hasBulkActionBtnTarget = false;
        controller.hasSelectionCountTarget = false;
        controller.hasStickyQueryValue = false;
        controller.hasStickyDefaultValue = false;
        controller.stickyDefaultValue = null;
        controller.stickyQueryValue = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['grid-view']).toBe(GridViewController);
    });

    test('has correct static targets', () => {
        expect(GridViewController.targets).toEqual(
            expect.arrayContaining([
                'gridState', 'searchInput', 'searchStatusIndicator',
                'rowCheckbox', 'selectAllCheckbox', 'bulkActionBtn', 'selectionCount'
            ])
        );
    });

    test('has correct static values', () => {
        expect(GridViewController.values).toHaveProperty('stickyQuery', String);
        expect(GridViewController.values).toHaveProperty('stickyDefault', Object);
    });

    test('connect initializes state to null', () => {
        controller.connect();
        // State gets loaded by loadInlineState
        expect(controller.selectedIds).toEqual([]);
    });

    test('connect initializes search debounce timer', () => {
        controller.connect();
        expect(controller.searchDebounceTimer).toBeNull();
        expect(controller.searchDebounceMs).toBe(900);
    });

    test('connect adds turbo:frame-load event listener', () => {
        const addSpy = jest.spyOn(document, 'addEventListener');
        controller.connect();
        expect(addSpy).toHaveBeenCalledWith('turbo:frame-load', expect.any(Function));
    });

    test('disconnect removes turbo:frame-load event listener', () => {
        controller.connect();
        const removeSpy = jest.spyOn(document, 'removeEventListener');
        controller.disconnect();
        expect(removeSpy).toHaveBeenCalledWith('turbo:frame-load', expect.any(Function));
    });

    test('disconnect clears search debounce timer', () => {
        jest.useFakeTimers();
        controller.connect();
        controller.searchDebounceTimer = setTimeout(() => {}, 1000);
        controller.disconnect();
        expect(controller.searchDebounceTimer).toBeNull();
        jest.useRealTimers();
    });

    test('setSearchBusy shows indicator when busy', () => {
        controller.setSearchBusy(true);
        expect(controller.searchStatusIndicatorTarget.classList.contains('d-none')).toBe(false);
        expect(controller.searchInputTarget.getAttribute('aria-busy')).toBe('true');
    });

    test('setSearchBusy hides indicator when not busy', () => {
        controller.searchStatusIndicatorTarget.classList.remove('d-none');
        controller.setSearchBusy(false);
        expect(controller.searchStatusIndicatorTarget.classList.contains('d-none')).toBe(true);
        expect(controller.searchInputTarget.getAttribute('aria-busy')).toBe('false');
    });

    test('escapeHtml escapes special characters', () => {
        const result = controller.escapeHtml('<script>alert("xss")</script>');
        expect(result).toContain('&lt;');
        expect(result).not.toContain('<script>');
    });

    test('escapeHtml returns plain text unchanged', () => {
        expect(controller.escapeHtml('hello world')).toBe('hello world');
    });

    test('formatColumnName converts snake_case to Title Case', () => {
        expect(controller.formatColumnName('first_name')).toBe('First Name');
        expect(controller.formatColumnName('created_at')).toBe('Created At');
    });

    test('formatColumnName handles single word', () => {
        expect(controller.formatColumnName('name')).toBe('Name');
    });

    test('handleFrameLoad ignores events from outside controller', () => {
        controller.connect();
        const externalFrame = document.createElement('turbo-frame');
        externalFrame.id = 'external-table';
        document.body.appendChild(externalFrame);

        // Should not throw
        expect(() => {
            controller.handleFrameLoad({ target: externalFrame });
        }).not.toThrow();
    });

    test('loadInlineState parses JSON state from script tag', () => {
        controller.updateToolbar = jest.fn();
        controller.captureStickyParamsFromFrame = jest.fn();
        controller.loadInlineState();
        expect(controller.state).toEqual({
            filters: { active: {}, available: {} },
            sort: {},
            pagination: {},
            config: {},
        });
    });

    test('loadInlineState skips when frame has src attribute', () => {
        controller.state = null;
        const frame = controller.element.querySelector('turbo-frame');
        frame.setAttribute('src', '/some-url');
        controller.loadInlineState();
        expect(controller.state).toBeNull();
    });
});
