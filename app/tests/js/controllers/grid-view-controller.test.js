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

    test('createFilterPill generates removable filter button with accessible target size and name', () => {
        controller.state = {
            filters: {
                active: { branch_id: ['1'] },
                available: {
                    branch_id: {
                        label: 'Branch',
                        options: [{ value: '1', label: 'Aethelmearc' }]
                    }
                }
            }
        };

        const pill = controller.createFilterPill('branch_id', '1', false);
        const removeButton = pill.querySelector('button[data-action="click->grid-view#removeFilter"]');
        const icon = removeButton.querySelector('i');

        expect(removeButton).not.toBeNull();
        expect(removeButton.style.width).toBe('24px');
        expect(removeButton.style.height).toBe('24px');
        expect(removeButton.style.minWidth).toBe('24px');
        expect(removeButton.style.minHeight).toBe('24px');
        expect(removeButton.getAttribute('aria-label')).toBe('Remove filter Branch: Aethelmearc');
        expect(icon.getAttribute('aria-hidden')).toBe('true');
    });

    test('createSearchBadge generates remove button with accessible target size and decorative icon', () => {
        const badge = controller.createSearchBadge('smith');
        const removeButton = badge.querySelector('button[data-action="click->grid-view#clearSearch"]');
        const icon = removeButton.querySelector('i');

        expect(removeButton.style.width).toBe('24px');
        expect(removeButton.style.height).toBe('24px');
        expect(removeButton.getAttribute('aria-label')).toBe('Remove search');
        expect(icon.getAttribute('aria-hidden')).toBe('true');
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

    test('handlePopState refreshes table frame without pushing history', () => {
        const tableFrame = controller.element.querySelector('turbo-frame');
        tableFrame.setAttribute('src', '/members/grid-data');

        const pushStateSpy = jest.spyOn(window.history, 'pushState');
        const navigateSpy = jest.spyOn(controller, 'navigate');

        controller.handlePopState();

        expect(navigateSpy).toHaveBeenCalledWith(
            window.location.pathname + window.location.search,
            false,
            { updateHistory: false },
        );
        expect(pushStateSpy).not.toHaveBeenCalled();

        pushStateSpy.mockRestore();
        navigateSpy.mockRestore();
    });

    test('eligible-only bulk action is hidden when no rows match required field', () => {
        document.body.innerHTML = `
            <div data-controller="grid-view">
                <button data-grid-view-target="bulkActionBtn"
                    data-bulk-action-requires-selection-field="canWorkflowDecide"></button>
                <input type="checkbox" data-grid-view-target="rowCheckbox" value="1"
                    data-can-workflow-decide="false">
            </div>
        `;
        controller.element = document.querySelector('[data-controller="grid-view"]');
        controller.hasRowCheckboxTarget = true;
        controller.rowCheckboxTargets = [...document.querySelectorAll('[data-grid-view-target="rowCheckbox"]')];
        controller.hasSelectAllCheckboxTarget = false;
        controller.hasBulkActionBtnTarget = true;
        controller.bulkActionBtnTargets = [...document.querySelectorAll('[data-grid-view-target="bulkActionBtn"]')];
        controller.hasSelectionCountTarget = false;
        controller.selectedIds = [];

        controller.updateBulkSelectionUI();

        expect(controller.bulkActionBtnTargets[0].hidden).toBe(true);
        expect(controller.bulkActionBtnTargets[0].classList.contains('d-none')).toBe(true);
        expect(controller.bulkActionBtnTargets[0].disabled).toBe(true);
    });

    test('eligible-only bulk action disables mixed selections', () => {
        document.body.innerHTML = `
            <div data-controller="grid-view">
                <button data-grid-view-target="bulkActionBtn"
                    data-bulk-action-requires-selection-field="canWorkflowDecide"></button>
                <input type="checkbox" data-grid-view-target="rowCheckbox" value="1"
                    data-can-workflow-decide="true" checked>
                <input type="checkbox" data-grid-view-target="rowCheckbox" value="2"
                    data-can-workflow-decide="false" checked>
            </div>
        `;
        controller.element = document.querySelector('[data-controller="grid-view"]');
        controller.hasRowCheckboxTarget = true;
        controller.rowCheckboxTargets = [...document.querySelectorAll('[data-grid-view-target="rowCheckbox"]')];
        controller.hasSelectAllCheckboxTarget = false;
        controller.hasBulkActionBtnTarget = true;
        controller.bulkActionBtnTargets = [...document.querySelectorAll('[data-grid-view-target="bulkActionBtn"]')];
        controller.hasSelectionCountTarget = false;
        controller.selectedIds = ['1', '2'];

        controller.updateBulkSelectionUI();

        expect(controller.bulkActionBtnTargets[0].hidden).toBe(false);
        expect(controller.bulkActionBtnTargets[0].classList.contains('d-none')).toBe(false);
        expect(controller.bulkActionBtnTargets[0].disabled).toBe(true);
    });

    test('eligible-only bulk action enables when all selected rows match required field', () => {
        document.body.innerHTML = `
            <div data-controller="grid-view">
                <button data-grid-view-target="bulkActionBtn"
                    data-bulk-action-requires-selection-field="canWorkflowDecide"></button>
                <input type="checkbox" data-grid-view-target="rowCheckbox" value="1"
                    data-can-workflow-decide="true" checked>
                <input type="checkbox" data-grid-view-target="rowCheckbox" value="2"
                    data-can-workflow-decide="true" checked>
            </div>
        `;
        controller.element = document.querySelector('[data-controller="grid-view"]');
        controller.hasRowCheckboxTarget = true;
        controller.rowCheckboxTargets = [...document.querySelectorAll('[data-grid-view-target="rowCheckbox"]')];
        controller.hasSelectAllCheckboxTarget = false;
        controller.hasBulkActionBtnTarget = true;
        controller.bulkActionBtnTargets = [...document.querySelectorAll('[data-grid-view-target="bulkActionBtn"]')];
        controller.hasSelectionCountTarget = false;
        controller.selectedIds = ['1', '2'];

        controller.updateBulkSelectionUI();

        expect(controller.bulkActionBtnTargets[0].hidden).toBe(false);
        expect(controller.bulkActionBtnTargets[0].classList.contains('d-none')).toBe(false);
        expect(controller.bulkActionBtnTargets[0].disabled).toBe(false);
    });
});
