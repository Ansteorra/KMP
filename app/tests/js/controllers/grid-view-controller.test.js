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
        controller.syncUrlValue = true;
        controller.hasPageSizeTarget = false;
    });

    afterEach(() => {
        window.history.replaceState({}, '', '/');
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test.each(['My Approvals', 'My To-Dos', 'Warrant Rosters', 'Bestowals'])(
        'subscription defaults include the tab and %s page name', pageName => {
            controller.element.insertAdjacentHTML('beforeend', `<details><summary>Email this view</summary>
                <form><input name="subscriptionName" maxlength="150"></form></details>`);
            const form = controller.element.querySelector('form');
            form.dataset.subscriptionPageName = pageName;
            controller.state = { view: { currentName: 'Pending' } };
            const event = { currentTarget: controller.element.querySelector('summary') };
            controller.prepareSubscription(event);
            expect(form.elements.subscriptionName.value).toBe(`Pending - ${pageName}`);
            form.elements.subscriptionName.value = 'My custom summary';
            controller.prepareSubscription(event);
            expect(form.elements.subscriptionName.value).toBe('My custom summary');
            form.elements.subscriptionName.value = '';
            controller.state.view.currentName = 'A'.repeat(150);
            controller.prepareSubscription(event);
            expect(form.elements.subscriptionName.value).toHaveLength(150);
            expect(form.elements.subscriptionName.value).toMatch(new RegExp(` - ${pageName}$`));
        }
    );

    test('subscription preserves the selected view and filters and announces success', async () => {
        controller.element.insertAdjacentHTML('beforeend', `<form action="/grid-subscriptions/add">
            <input name="subscriptionName" value="Warrant approvals">
            <select name="intervalDays"><option value="3">Every 3 days</option></select>
            <button type="submit">Subscribe</button><p role="status" data-subscription-status></p>
        </form>`);
        controller.state = { view: { currentId: 'sys-pending' }, config: { gridKey: 'Workflows.approvals.main' } };
        controller.buildUrl = jest.fn(() => '/approvals/approvals?view_id=sys-pending&filter[workflow][]=warrants');
        controller.getCsrfToken = () => 'test-csrf';
        global.fetch = jest.fn().mockResolvedValue({ ok: true, json: async () => ({ success: true }) });
        const form = controller.element.querySelector('form');
        const preventDefault = jest.fn();
        await controller.subscribeToView({ preventDefault, currentTarget: form });
        const options = fetch.mock.calls[0][1];
        expect(preventDefault).toHaveBeenCalled();
        expect(options.headers['X-CSRF-Token']).toBe('test-csrf');
        expect(JSON.parse(options.body)).toMatchObject({ name: 'Warrant approvals', intervalDays: 3 });
        expect(JSON.parse(options.body).query).toContain('filter[workflow][]=warrants');
        expect(form.querySelector('[role=status]')).toHaveTextContent('Subscribed.');
        expect(form.querySelector('button')).not.toBeDisabled();
    });

    test('subscription restores the button and announces a readable server failure', async () => {
        controller.element.insertAdjacentHTML('beforeend', `<form action="/grid-subscriptions/add">
            <input name="subscriptionName" value="My tasks"><input name="intervalDays" value="1">
            <button type="submit">Subscribe</button><p role="status" data-subscription-status></p>
        </form>`);
        controller.state = { view: { currentId: 'sys-todos-open' }, config: { gridKey: 'Core.actionItems.myTasks' } };
        controller.buildUrl = () => '/action-items/my-tasks';
        controller.getCsrfToken = () => 'test-csrf';
        global.fetch = jest.fn().mockResolvedValue({ ok: false, json: async () => { throw new Error('Invalid JSON'); } });
        const form = controller.element.querySelector('form');
        await controller.subscribeToView({ preventDefault() {}, currentTarget: form });
        expect(form.querySelector('[role=status]')).toHaveTextContent('Check your access');
        expect(form.querySelector('button')).not.toBeDisabled();
    });

    test('sample sends the current unsaved view and prevents duplicate requests', async () => {
        controller.element.insertAdjacentHTML('beforeend', `<form action="/grid-subscriptions/add">
            <input name="subscriptionName" value="Current view"><input name="intervalDays" value="3">
            <button type="submit">Subscribe</button>
            <button type="submit" data-subscription-sample formaction="/grid-subscriptions/sample">Send me a sample</button>
            <p role="status" data-subscription-status></p>
        </form>`);
        controller.state = { view: { currentId: 'sys-todos-open' }, config: { gridKey: 'Core.actionItems.myTasks' } };
        controller.buildUrl = () => '/action-items/my-tasks?view_id=sys-todos-open&search=Unsaved&columns=title';
        controller.getCsrfToken = () => 'test-csrf';
        let resolve;
        global.fetch = jest.fn(() => new Promise(done => { resolve = done; }));
        const form = controller.element.querySelector('form');
        const button = form.querySelector('[data-subscription-sample]');
        const event = { preventDefault() {}, currentTarget: form, submitter: button };
        const pending = controller.subscribeToView(event);
        expect(form).toHaveAttribute('aria-busy', 'true');
        expect(button).toBeDisabled();
        expect(form.querySelector('button')).toBeDisabled();
        await controller.subscribeToView(event);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch.mock.calls[0][0]).toBe('/grid-subscriptions/sample');
        expect(JSON.parse(fetch.mock.calls[0][1].body).query).toContain('search=Unsaved&columns=title');
        resolve({ ok: true, json: async () => ({ success: true }) });
        await pending;
        expect(form.querySelector('[role=status]')).toHaveTextContent('Sample sent to your account email');
        expect(form.querySelector('[role=status]')).toHaveTextContent('No subscription was created or changed');
        expect(form).toHaveAttribute('aria-busy', 'false');
        expect(button).not.toBeDisabled();
    });

    test('disconnect aborts a pending email request', () => {
        controller.subscriptionRequest = new AbortController();
        const signal = controller.subscriptionRequest.signal;
        controller.disconnect();
        expect(signal.aborted).toBe(true);
        expect(controller.subscriptionRequest).toBeNull();
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
        expect(GridViewController.values.syncUrl.default).toBe(true);
    });

    test('row count changes keep the selected view and filters while resetting pagination', () => {
        window.history.replaceState({}, '', '/members?view_id=4&page=3&filter[status][]=verified&sort=sca_name&direction=asc');
        controller.navigate = jest.fn();
        controller.changePageSize({ currentTarget: { value: '50' } });
        const url = new URL(controller.navigate.mock.calls[0][0], window.location.origin);
        expect(url.searchParams.get('limit')).toBe('50');
        expect(url.searchParams.has('page')).toBe(false);
        expect(url.searchParams.get('view_id')).toBe('4');
        expect(url.searchParams.get('filter[status][]')).toBe('verified');
        expect(url.searchParams.get('sort')).toBe('sca_name');
    });

    test('saved row count updates the selector and retains focus in the persistent toolbar', () => {
        controller.element.insertAdjacentHTML('afterbegin', '<label for="page-size">Rows per page</label><select id="page-size"><option>25</option><option>50</option></select>');
        controller.hasPageSizeTarget = true;
        controller.pageSizeTarget = controller.element.querySelector('select');
        controller.pageSizeTarget.focus();
        controller.state = {
            filters: { active: {} }, sort: {}, columns: { visible: ['name'] },
            config: { pageSize: 15 }, search: ''
        };
        controller.updatePageSize();
        expect(controller.pageSizeTarget.value).toBe('15');
        expect(document.activeElement).toBe(controller.pageSizeTarget);
        expect(controller.getCurrentConfig().pageSize).toBe(15);
        controller.state.config.pageSize = 50;
        controller.updatePageSize();
        expect(controller.pageSizeTarget.value).toBe('50');
        expect(controller.getCurrentConfig().pageSize).toBe(50);
    });

    test('frame refresh records the effective clamped page and selected row count', () => {
        window.history.replaceState({}, '', '/members?view_id=4&page=3&filter[status][]=verified');
        const frame = controller.element.querySelector('turbo-frame');
        frame.setAttribute('src', '/members/grid-data?view_id=4&page=3&filter[status][]=verified');
        controller.state = { config: { pageSize: 50 }, pagination: { currentPage: 2 } };
        const syncSpy = jest.fn();
        window.addEventListener('page-context:sync', syncSpy);
        controller.syncFrameLocation(frame);
        expect(window.location.pathname).toBe('/members');
        expect(new URLSearchParams(window.location.search).get('page')).toBe('2');
        expect(new URLSearchParams(window.location.search).get('limit')).toBe('50');
        expect(new URLSearchParams(window.location.search).get('filter[status][]')).toBe('verified');
        expect(new URL(controller.currentFrameUrl(frame), window.location.origin).searchParams.get('page')).toBe('2');
        expect(syncSpy).toHaveBeenCalledTimes(1);
        window.removeEventListener('page-context:sync', syncSpy);
    });

    test('calendar frame refresh synchronizes sticky context without adding pagination', () => {
        window.history.replaceState({}, '', '/gatherings/calendar?year=2026&month=9');
        const frame = controller.element.querySelector('turbo-frame');
        frame.dataset.gridSrc = '/gatherings/calendar-data?year=2026&month=10&filter[branch_id][]=2';
        controller.state = { config: { disablePagination: true }, pagination: null };
        controller.syncFrameLocation(frame);
        const params = new URLSearchParams(window.location.search);
        expect(params.get('month')).toBe('10');
        expect(params.get('filter[branch_id][]')).toBe('2');
        expect(params.has('limit')).toBe(false);
    });

    test('detail tab frame updates leave the host URL alone even when the controller default syncs URLs', () => {
        window.history.replaceState({}, '', '/members/view/7?tab=officers');
        const panel = document.createElement('div');
        panel.setAttribute('role', 'tabpanel');
        controller.element.before(panel);
        panel.append(controller.element);
        const frame = controller.element.querySelector('turbo-frame');
        frame.setAttribute('src', '/officers/officers/grid-data?member_id=7&page=3&limit=50');
        controller.state = { config: { pageSize: 50 }, pagination: { currentPage: 3 } };
        controller.syncFrameLocation(frame);
        expect(controller.syncsBrowserUrl).toBe(false);
        expect(window.location.search).toBe('?tab=officers');
        const params = new URL(controller.buildUrl({ sort: 'name' }), window.location.origin).searchParams;
        expect(params.has('member_id')).toBe(false);
        expect(params.get('tab')).toBe('officers');
        expect(params.get('grid_context')).toBe('members');
        expect(params.get('page')).toBe('3');
    });

    test('embedded grids retain independent page and filters without changing the host URL', () => {
        window.history.replaceState({}, '', '/members/view/7?tab=roles');
        controller.syncUrlValue = false;
        const frame = controller.element.querySelector('turbo-frame');
        frame.setAttribute('src', '/members/roles-grid-data/7?page=3&limit=10&filter[role_id][]=4');
        controller.state = { config: { pageSize: 10 }, pagination: { currentPage: 2 } };
        controller.syncFrameLocation(frame);
        expect(window.location.href).toContain('/members/view/7?tab=roles');
        const url = new URL(controller.buildUrl({ limit: 50, page: null }), window.location.origin);
        expect(url.searchParams.get('filter[role_id][]')).toBe('4');
        expect(url.searchParams.get('limit')).toBe('50');
        expect(url.searchParams.get('tab')).toBe('roles');
        expect(url.searchParams.get('grid_context')).toBe('members');
        controller.handlePopState();
        expect(new URL(controller.currentFrameUrl(frame), window.location.origin).searchParams.get('page')).toBe('2');
    });

    function embeddedSavedView() {
        window.history.replaceState({}, '', '/members/view/7?tab=roles&search=Sibling&view_id=foreign#member-details');
        controller.syncUrlValue = false;
        const frame = controller.element.querySelector('turbo-frame');
        frame.id = 'member-roles-grid-table';
        frame.src = '/members/roles-grid-data/7?page=99&limit=25';
        frame.dataset.gridCurrentSrc = '/members/roles-grid-data/7?member_id=7&branch_id=2&gathering_id=11&frame_id=member-roles-grid&page=3&limit=50&search=Knight&view_id=own-view&filter[role_id][]=4&sort=name&direction=desc';
        controller.state = {
            view: { currentId: 'own-view' },
            config: { gridKey: 'Members.roles', pageSize: 50 },
            filters: { active: { role_id: ['4'] } },
            sort: { field: 'name', direction: 'desc' },
            columns: { visible: ['name'] }, search: 'Knight'
        };
        global.fetch = jest.fn().mockResolvedValue({ ok: true, json: async () => ({ success: true, data: { view: { id: 'new-view' } } }) });
        return frame;
    }

    function expectOwnSavedQuery(url) {
        expect(url.searchParams.get('page')).toBe('3');
        expect(url.searchParams.get('limit')).toBe('50');
        expect(url.searchParams.get('search')).toBe('Knight');
        expect(url.searchParams.getAll('filter[role_id][]')).toEqual(['4']);
        expect(url.searchParams.get('sort')).toBe('name');
        expect(url.searchParams.get('direction')).toBe('desc');
    }

    test.each(['saveView', 'deleteView'])('%s navigates the host with its own marked grid state and detail tab', async method => {
        embeddedSavedView();
        window.KMP_accessibility.prompt.mockResolvedValue('Knight roles');
        const navigate = jest.spyOn(controller, 'navigate');
        const replaceState = jest.spyOn(window.history, 'replaceState');

        await controller[method]();

        expect(navigate).toHaveBeenCalledTimes(1);
        expect(navigate.mock.calls[0][1]).toBe(true);
        const url = new URL(navigate.mock.calls[0][0], window.location.origin);
        expect(url.pathname).toBe('/members/view/7');
        expect(url.hash).toBe('#member-details');
        expect(url.searchParams.get('tab')).toBe('roles');
        expect(url.searchParams.get('grid_context')).toBe('member-roles-grid');
        expect(url.searchParams.get('view_id')).toBe(method === 'saveView' ? 'new-view' : null);
        expectOwnSavedQuery(url);
        ['frame_id', 'member_id', 'branch_id', 'gathering_id'].forEach(key => expect(url.searchParams.has(key)).toBe(false));
        if (method === 'saveView') expect(JSON.parse(fetch.mock.calls[0][1].body).config.pageSize).toBe(50);
        expect(replaceState).toHaveBeenCalledTimes(1);
        const previous = new URL(replaceState.mock.calls[0][2], window.location.origin);
        expectOwnSavedQuery(previous);
        expect(previous.searchParams.get('view_id')).toBe('own-view');
        expect(previous.searchParams.get('grid_context')).toBe('member-roles-grid');
        expect(previous.searchParams.get('tab')).toBe('roles');
        expect(previous.hash).toBe('#member-details');
    });

    test.each(['setDefault', 'clearDefault'])('%s refreshes its own embedded frame while preserving endpoint identities', async method => {
        const frame = embeddedSavedView();
        const hostUrl = window.location.href;

        await controller[method]();

        const url = new URL(frame.src, window.location.origin);
        expectOwnSavedQuery(url);
        expect(url.pathname).toBe('/members/roles-grid-data/7');
        expect(url.searchParams.get('view_id')).toBe('own-view');
        expect(url.searchParams.get('member_id')).toBe('7');
        expect(url.searchParams.get('branch_id')).toBe('2');
        expect(url.searchParams.get('gathering_id')).toBe('11');
        expect(url.searchParams.get('frame_id')).toBe('member-roles-grid');
        expect(url.searchParams.has('grid_context')).toBe(false);
        expect(window.location.href).toBe(hostUrl);
    });

    test('primary grid URL builders retain their existing unmarked navigation shape', () => {
        window.history.replaceState({}, '', '/members?search=Knight#details');
        expect(controller.buildUrl({ view_id: 'new-view' })).toBe('/members?search=Knight&view_id=new-view');
    });

    test('external embedded frame URLs cannot replace the host query', () => {
        const frame = embeddedSavedView();
        frame.dataset.gridCurrentSrc = 'https://example.test/grid?page=3&limit=50';

        expect(controller.currentGridUrl().href).toBe(window.location.href);
    });

    test('the current default detail tab clears a stale tab from the embedded frame query', () => {
        const frame = embeddedSavedView();
        frame.dataset.gridCurrentSrc += '&tab=previous-tab';
        window.history.replaceState({}, '', '/members/view/7#member-details');

        const url = new URL(controller.buildUrl({ view_id: 'new-view' }), window.location.origin);
        expect(url.searchParams.has('tab')).toBe(false);
        expect(url.searchParams.get('grid_context')).toBe('member-roles-grid');
        expect(url.hash).toBe('#member-details');
        expectOwnSavedQuery(url);
    });

    test('pagination records browser history and Back reloads the former page', () => {
        window.history.replaceState({}, '', '/members?limit=10&page=2&filter[status][]=verified');
        const frame = controller.element.querySelector('turbo-frame');
        frame.dataset.gridSrc = '/members/grid-data?limit=10&page=2&filter[status][]=verified';
        frame.insertAdjacentHTML('beforeend', '<div class="paginator"><a href="/members/grid-data?limit=10&page=3&filter[status][]=verified">3</a></div>');
        const pushSpy = jest.spyOn(window.history, 'pushState');
        const event = { target: frame.querySelector('a'), button: 0, preventDefault: jest.fn() };
        controller.handlePaginationClick(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(pushSpy).toHaveBeenCalledTimes(1);
        expect(new URLSearchParams(window.location.search).get('page')).toBe('3');
        window.history.replaceState({}, '', '/members?limit=10&page=2&filter[status][]=verified');
        controller.handlePopState();
        expect(new URL(frame.src, window.location.origin).searchParams.get('page')).toBe('2');
        expect(new URL(frame.src, window.location.origin).searchParams.get('filter[status][]')).toBe('verified');
        expect(pushSpy).toHaveBeenCalledTimes(1);
    });

    test('keyboard pagination restores visible focus to the current page after table replacement', () => {
        const frame = controller.element.querySelector('turbo-frame');
        frame.dataset.gridSrc = '/members/grid-data?page=2';
        frame.insertAdjacentHTML('beforeend', '<div class="paginator"><a href="/members/grid-data?page=3">3</a></div>');
        const link = frame.querySelector('a');
        link.focus();
        controller.handlePaginationClick({ target: link, button: 0, preventDefault() {} });
        frame.innerHTML = '<div class="paginator"><ul class="pagination"><li class="active"><span class="page-link" aria-current="page">3</span></li><li><a href="?page=4">4</a></li></ul></div>';
        controller.restorePaginationFocus(frame);
        expect(document.activeElement).toHaveTextContent('3');
        expect(document.activeElement).toHaveAttribute('tabindex', '-1');
        expect(document.activeElement).toHaveClass('focus-ring');
        expect(controller.paginationFocusPending).toBeNull();
    });

    test('pagination refresh retains focus when the user moved to another toolbar control', () => {
        const frame = controller.element.querySelector('turbo-frame');
        frame.dataset.gridSrc = '/members/grid-data?page=2';
        frame.insertAdjacentHTML('beforeend', '<div class="paginator"><a href="/members/grid-data?page=3">3</a></div>');
        const link = frame.querySelector('a');
        link.focus();
        controller.handlePaginationClick({ target: link, button: 0, preventDefault() {} });
        controller.searchInputTarget.focus();
        frame.innerHTML = '<div class="paginator"><a aria-current="page" href="?page=3">3</a></div>';
        controller.restorePaginationFocus(frame);
        expect(document.activeElement).toBe(controller.searchInputTarget);
        expect(controller.paginationFocusPending).toBeNull();
    });

    test('disconnect cleans up the delegated pagination listener', () => {
        controller.connect();
        const removeSpy = jest.spyOn(controller.element, 'removeEventListener');
        controller.disconnect();
        expect(removeSpy).toHaveBeenCalledWith('click', controller.boundPaginationClick);
    });

    test('failed frame requests clear the busy state without moving focus', () => {
        const frame = controller.element.querySelector('turbo-frame');
        frame.setAttribute('aria-busy', 'true');
        controller.searchInputTarget.focus();
        controller.setSearchBusy(true);
        controller.handleFrameError({ target: frame });
        expect(frame).not.toHaveAttribute('aria-busy');
        expect(controller.searchInputTarget).toHaveAttribute('aria-busy', 'false');
        expect(document.activeElement).toBe(controller.searchInputTarget);
    });

    test('updateViewTabs renders views when the save action is outside the tablist', () => {
        controller.element.insertAdjacentHTML('beforeend', `
            <div data-saved-views-region>
                <ul data-view-tabs-container role="tablist">
                    <li class="d-none" data-no-all-tab></li>
                </ul>
                <button type="button" data-action="click->grid-view#saveView">Save view</button>
            </div>
        `);
        controller.state = {
            view: {
                currentId: 'sys-pending',
                available: [{
                    id: 'sys-pending',
                    name: 'Pending',
                    canManage: false,
                }],
            },
        };

        expect(() => controller.updateViewTabs()).not.toThrow();

        const tablist = controller.element.querySelector('[data-view-tabs-container]');
        expect(tablist.querySelector('[role="tab"]')).toHaveTextContent('Pending');
        expect(tablist.querySelector('[data-action*="saveView"]')).toBeNull();
        expect(controller.element.querySelector('[data-action*="saveView"]')).not.toBeNull();
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

    test('updateColumnPicker excludes filter-only columns', () => {
        const container = document.createElement('div');
        container.dataset.columnListContainer = '';
        controller.element.appendChild(container);
        controller.state = {
            config: {
                gridKey: 'Gatherings.index.main'
            },
            columns: {
                visible: ['name'],
                all: {
                    name: { label: 'Name' },
                    relative_event_date: {
                        label: 'Event Date',
                        filterOnly: true
                    }
                }
            }
        };

        controller.updateColumnPicker();

        expect(container.querySelector('[data-column-key="name"]')).not.toBeNull();
        expect(container.querySelector('[data-column-key="relative_event_date"]')).toBeNull();
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
        expect(pill.classList).toContain('grid-view-filter-badge');
        expect(removeButton.classList).toContain('grid-view-filter-badge-control');
        expect(removeButton.getAttribute('aria-label')).toBe('Remove filter Branch: Aethelmearc');
        expect(icon.getAttribute('aria-hidden')).toBe('true');
    });

    test('createFilterPill gives locked filters the same badge and control footprint', () => {
        controller.state = {
            filters: {
                active: { status_label: ['Pending'] },
                available: {
                    status_label: {
                        label: 'Status',
                        options: [{ value: 'Pending', label: 'Pending' }]
                    }
                }
            }
        };

        const pill = controller.createFilterPill('status_label', 'Pending', true);
        const lockIndicator = pill.querySelector('.grid-view-filter-badge-control');

        expect(pill.classList).toContain('grid-view-filter-badge');
        expect(pill.getAttribute('data-filter-locked')).toBe('true');
        expect(pill.querySelector('button')).toBeNull();
        expect(lockIndicator).not.toBeNull();
        expect(lockIndicator.getAttribute('aria-hidden')).toBe('true');
        expect(lockIndicator.querySelector('.bi-lock-fill')).not.toBeNull();
        expect(pill.textContent).toContain('Locked filter; cannot be removed.');
    });

    test('hidden-menu active filters remain locked pills and serialize with the view', () => {
        controller.element.insertAdjacentHTML('beforeend', `
            <div data-filter-pills-container></div>
            <div data-filter-nav-container></div>
            <div data-filter-panels-container></div>
        `);
        controller.state = {
            filters: {
                active: { status_label: ['Pending'] },
                available: {
                    status_label: {
                        label: 'Status',
                        options: [{ value: 'Pending', label: 'Pending' }],
                        showInFilterMenu: false,
                    },
                    branch_id: {
                        label: 'Branch',
                        options: [{ value: '1', label: 'Aethelmearc' }],
                        showInFilterMenu: true,
                    },
                },
            },
            sort: {},
            columns: { visible: ['request'] },
            config: {
                lockedFilters: ['status_label'],
                pageSize: 25,
            },
            search: '',
        };

        controller.updateFilterPills();
        controller.updateFilterNavigation();
        controller.updateFilterPanels();

        const pill = controller.element.querySelector('[data-filter-pills-container] [data-filter-badge]');
        expect(pill.classList).toContain('grid-view-filter-badge');
        expect(pill.getAttribute('data-filter-locked')).toBe('true');
        expect(pill.textContent).toContain('Status: Pending');
        expect(pill.querySelector('button')).toBeNull();
        expect(pill.querySelector('.bi-lock-fill')).not.toBeNull();

        const navigation = controller.element.querySelector('[data-filter-nav-container]');
        const panels = controller.element.querySelector('[data-filter-panels-container]');
        expect(navigation.querySelector('[data-filter-key="status_label"]')).toBeNull();
        expect(panels.querySelector('[data-filter-key="status_label"]')).toBeNull();
        expect(navigation.querySelector('[data-filter-key="branch_id"]')).not.toBeNull();
        expect(panels.querySelector('[data-filter-key="branch_id"]')).not.toBeNull();

        expect(controller.getCurrentConfig().filters).toContainEqual({
            field: 'status_label',
            operator: 'in',
            value: ['Pending'],
            locked: true,
        });
    });

    test('buildFilterItems excludes hidden filters and groups date ranges once', () => {
        controller.state = {
            filters: {
                available: {
                    hidden_scope: {
                        label: 'Scope',
                        options: [],
                        showInFilterMenu: false,
                    },
                    branch_id: {
                        label: 'Branch',
                        options: [],
                    },
                    created_start: {
                        label: 'Created (after)',
                        type: 'date-range-start',
                        baseField: 'created',
                    },
                    created_end: {
                        label: 'Created (before)',
                        type: 'date-range-end',
                        baseField: 'created',
                    },
                },
            },
        };

        const items = controller.buildFilterItems();

        expect(items.map(({ key, type }) => ({ key, type }))).toEqual([
            { key: 'branch_id', type: 'dropdown' },
            { key: 'created', type: 'date-range' },
        ]);
        expect(items[1].label).toBe('Created');
        expect(items[1].group.filters.map(({ key }) => key)).toEqual([
            'created_start',
            'created_end',
        ]);
    });

    test('createSearchBadge generates remove button with accessible target size and decorative icon', () => {
        const badge = controller.createSearchBadge('smith');
        const removeButton = badge.querySelector('button[data-action="click->grid-view#clearSearch"]');
        const icon = removeButton.querySelector('i');

        expect(badge.classList).toContain('grid-view-filter-badge');
        expect(removeButton.classList).toContain('grid-view-filter-badge-control');
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
                            { value: 'open:has_scroll', label: 'Open: Scroll Ready' },
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
