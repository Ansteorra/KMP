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
        window.history.replaceState({}, '', '/');
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

    test('mergeStateWithPrevious preserves source-derived filter options on table-only refresh', () => {
        controller.state = {
            filters: {
                active: { todos_summary: ['open:has_scroll'] },
                available: {
                    lifecycle_status: { label: 'Lifecycle', options: [{ value: 'open', label: 'Open' }] },
                    todos_summary: {
                        label: 'To-Dos',
                        options: [
                            { value: '__remaining', label: 'Has remaining required checks' },
                            { value: 'open:has_scroll', label: 'Open: Has scroll' },
                        ],
                    },
                },
            },
        };

        // A table-only refresh re-sends inline-option dropdowns and fresh active
        // values but omits filterOptionsSource-derived dropdowns (todos_summary).
        const nextState = {
            filters: {
                active: { todos_summary: ['open:has_scroll'] },
                available: {
                    lifecycle_status: { label: 'Lifecycle', options: [{ value: 'open', label: 'Open' }] },
                },
            },
        };

        const merged = controller.mergeStateWithPrevious(nextState);

        expect(merged.filters.available.todos_summary).toBeDefined();
        expect(merged.filters.available.todos_summary.options).toHaveLength(2);
        expect(merged.filters.available.lifecycle_status).toBeDefined();
        expect(merged.filters.active).toEqual({ todos_summary: ['open:has_scroll'] });
    });

    test('mergeStateWithPrevious preserves visible columns when table refresh omits them', () => {
        controller.state = {
            columns: {
                visible: ['name', 'branch'],
                all: {
                    name: { label: 'Name' },
                    branch: { label: 'Branch' },
                },
            },
        };

        const merged = controller.mergeStateWithPrevious({
            columns: {
                visible: [],
                all: [],
            },
        });

        expect(merged.columns.visible).toEqual(['name', 'branch']);
        expect(merged.columns.all).toEqual({
            name: { label: 'Name' },
            branch: { label: 'Branch' },
        });
    });

    test('mergeStateWithPrevious lets a full refresh override stale filter options', () => {
        controller.state = {
            filters: {
                active: {},
                available: {
                    todos_summary: { label: 'To-Dos', options: [{ value: '__remaining', label: 'Old' }] },
                },
            },
        };

        const nextState = {
            filters: {
                active: {},
                available: {
                    todos_summary: { label: 'To-Dos', options: [{ value: '__complete', label: 'New' }] },
                },
            },
        };

        const merged = controller.mergeStateWithPrevious(nextState);

        expect(merged.filters.available.todos_summary.options).toEqual([{ value: '__complete', label: 'New' }]);
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

    test('buildUrl preserves bracketed dirty keys and removes orphan bracket params', () => {
        window.history.replaceState({}, '', '/gatherings?%5Bfilters%5D=1&filter%5Bgathering_type_id%5D%5B%5D=1');

        const url = controller.buildUrl({
            start_date_start: '2026-07-01',
            'dirty[filters]': '1',
        });
        const params = new URL(url, window.location.origin).searchParams;

        expect(params.get('dirty[filters]')).toBe('1');
        expect(params.getAll('filter[gathering_type_id][]')).toEqual(['1']);
        expect(params.get('start_date_start')).toBe('2026-07-01');
        expect(params.has('[filters]')).toBe(false);
    });

    test('buildUrlWithFilters removes malformed dirty params from filter URLs', () => {
        window.history.replaceState({}, '', '/gatherings?%5Bfilters%5D=1&start_date_start=2026-07-01');
        controller.state = {
            view: { currentId: null },
            filters: { active: {} },
            search: '',
        };

        const url = controller.buildUrlWithFilters({
            gathering_type_id: ['1'],
            start_date_start: '2026-07-01',
        });
        const params = new URL(url, window.location.origin).searchParams;

        expect(params.getAll('filter[gathering_type_id][]')).toEqual(['1']);
        expect(params.get('start_date_start')).toBe('2026-07-01');
        expect(params.has('[filters]')).toBe(false);
    });

    test('removeFilter removes numeric state values using the string value from the filter pill', () => {
        window.history.replaceState({}, '', '/awards/bestowals?filter%5Bawards%5D%5B%5D=10');
        controller.state = {
            view: { currentId: 'sys-bestowals-active' },
            filters: { active: { awards: [10] } },
            config: { lockedFilters: [] },
            search: '',
        };
        controller.navigate = jest.fn();

        controller.removeFilter({
            currentTarget: {
                dataset: { filterColumn: 'awards', filterValue: '10' },
            },
        });

        const navigatedUrl = controller.navigate.mock.calls[0][0];
        const params = new URL(navigatedUrl, window.location.origin).searchParams;
        expect(params.has('filter[awards][]')).toBe(false);
        expect(params.get('dirty[filters]')).toBe('1');
    });

    test('toggleFilter removes numeric state values using the checkbox string value', () => {
        window.history.replaceState({}, '', '/awards/bestowals?filter%5Bawards%5D%5B%5D=10');
        controller.state = {
            view: { currentId: 'sys-bestowals-active' },
            filters: { active: { awards: [10] } },
            config: { lockedFilters: [] },
            search: '',
        };
        controller.navigate = jest.fn();

        controller.toggleFilter({
            currentTarget: {
                checked: false,
                value: '10',
                dataset: { filterColumn: 'awards' },
            },
        });

        const navigatedUrl = controller.navigate.mock.calls[0][0];
        const params = new URL(navigatedUrl, window.location.origin).searchParams;
        expect(params.has('filter[awards][]')).toBe(false);
        expect(params.get('dirty[filters]')).toBe('1');
    });

    test('clearAllFilters removes the final filter before reloading the table frame', () => {
        window.history.replaceState(
            {},
            '',
            '/awards/bestowals?filter%5Bawards%5D%5B%5D=10&search=test',
        );
        controller.state = {
            view: { currentId: 'sys-bestowals-active' },
            filters: { active: { awards: ['10'] } },
            config: { lockedFilters: [] },
            search: 'test',
        };
        controller.navigate = jest.fn();

        controller.clearAllFilters();

        const navigatedUrl = controller.navigate.mock.calls[0][0];
        const params = new URL(navigatedUrl, window.location.origin).searchParams;
        expect(params.has('filter[awards][]')).toBe(false);
        expect(params.has('search')).toBe(false);
        expect(params.get('dirty[filters]')).toBe('1');
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

    test('bulk action payload reads selected checkbox data from the live DOM', () => {
        document.body.innerHTML = `
            <div data-controller="grid-view">
                <button type="button" data-bulk-action-key="workflow-decision"></button>
                <input type="checkbox" data-grid-view-target="rowCheckbox" value="499"
                    data-pending-approval-id="37" data-can-workflow-decide="true" checked>
            </div>
        `;
        controller.element = document.querySelector('[data-controller="grid-view"]');
        controller.hasRowCheckboxTarget = false;
        controller.selectedIds = ['499'];

        const button = document.querySelector('button');
        const noticeHandler = jest.fn();
        button.addEventListener('outlet-btn:notice', noticeHandler);

        controller.triggerBulkAction({ currentTarget: button });

        expect(noticeHandler).toHaveBeenCalledWith(expect.objectContaining({
            detail: expect.objectContaining({
                ids: ['499'],
                checkboxes: [
                    expect.objectContaining({
                        id: '499',
                        pendingApprovalId: '37',
                        canWorkflowDecide: 'true',
                    }),
                ],
            }),
        }));
        expect(JSON.parse(button.dataset.bulkActionSelection)).toEqual(expect.objectContaining({
            ids: ['499'],
            checkboxes: [
                expect.objectContaining({
                    id: '499',
                    pendingApprovalId: '37',
                }),
            ],
        }));
        expect(button.dataset.workflowDecisionSelection).toBe(button.dataset.bulkActionSelection);
    });

    test('toggleSubRow synchronizes aria-expanded and controlled region state', async () => {
        document.body.innerHTML = `
            <table>
                <tr>
                    <td>
                        <button type="button" data-action="click->grid-view#toggleSubRow"
                            data-row-id="99" data-subrow-type="details" data-subrow-url="/details/:id"
                            aria-expanded="false" aria-controls="subrow-99-details">
                            <i class="toggle-icon bi bi-chevron-right"></i><span>Details</span>
                        </button>
                    </td>
                </tr>
            </table>
        `;
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            text: () => Promise.resolve('<div>Loaded details</div>')
        }));

        const button = document.querySelector('button');
        controller.toggleSubRow({ preventDefault: jest.fn(), currentTarget: button });
        expect(button).toHaveAttribute('aria-busy', 'true');
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(button).toHaveAttribute('aria-expanded', 'true');
        expect(button).not.toHaveAttribute('aria-busy');
        expect(document.querySelector('#subrow-99-details [role="region"]')).toHaveTextContent('Loaded details');

        controller.toggleSubRow({ preventDefault: jest.fn(), currentTarget: button });
        expect(button).toHaveAttribute('aria-expanded', 'false');
        expect(document.querySelector('#subrow-99-details')).toBeNull();
    });
});
