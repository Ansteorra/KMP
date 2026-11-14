import '../../../assets/js/controllers/ui-pattern-grid-controller.js';
import '../../../assets/js/controllers/grid-view-controller.js';

const UiPatternGridController = window.Controllers['ui-pattern-grid'];
const GridViewController = window.Controllers['grid-view'];

/** Keep target getters live so table refreshes exercise the same contract as Stimulus. */
function mount(grid, path = '/ui-patterns?pattern=collection&state=normal') {
    window.history.replaceState({}, '', path);
    document.body.innerHTML = `<section data-controller="grid-view ui-pattern-grid">
        <input data-ui-pattern-grid-target="search">
        <p data-ui-pattern-grid-target="viewStatus"></p><button data-ui-pattern-grid-target="restore" hidden>Restore</button>
        <details data-ui-pattern-grid-target="displayOptions"></details>
        <button data-ui-pattern-grid-target="layoutButton" data-layout="cards">Cards</button>
        <button data-ui-pattern-grid-target="layoutButton" data-layout="table">Table</button>
        <div data-ui-pattern-grid-target="selectionBar"><span data-ui-pattern-grid-target="selectionCount"></span></div>
        <ul data-ui-pattern-grid-target="selectedNames"></ul>
        <table data-grid-view-target="gridTable"><thead><tr><th>Select</th><th>Name</th><th>Branch</th><th>Actions</th></tr></thead><tbody>
            <tr data-id="court"><td><input type="checkbox" value="court"></td><td><a href="#court">Winter Court</a></td><td>Oakmere</td><td></td></tr>
            <tr data-id="practice"><td><input type="checkbox" value="practice"></td><td>Archery Practice</td><td>Willowford</td><td></td></tr>
        </tbody></table>
    </section>`;
    const controller = new UiPatternGridController();
    controller.element = document.querySelector('section');
    Object.defineProperty(controller, 'application', { value: {
        getControllerForElementAndIdentifier: jest.fn(() => grid),
    } });
    for (const target of UiPatternGridController.targets) {
        Object.defineProperty(controller, `${target}Targets`, {
            get: () => [...controller.element.querySelectorAll(`[data-ui-pattern-grid-target~="${target}"]`)],
        });
        Object.defineProperty(controller, `${target}Target`, {
            get: () => controller[`${target}Targets`][0],
        });
    }
    controller.connect();
    return controller;
}

describe('Dataverse showcase presentation adapter', () => {
    let controller;
    let grid;
    let media;
    const originalMatchMedia = window.matchMedia;

    beforeEach(() => {
        media = { matches: true, addEventListener: jest.fn(), removeEventListener: jest.fn() };
        window.matchMedia = jest.fn(() => media);
        grid = {
            state: {
                view: { currentId: '7', currentName: 'Court visits', hasChanges: false },
                search: 'court', sort: { field: 'name', direction: 'asc' },
                columns: { visible: ['name', 'branch'], all: {
                    name: { label: 'Gathering', sortable: true }, branch: { label: 'Branch', sortable: true },
                } },
            },
            selectedIds: [], selectedRowCheckboxTargets: () => [...document.querySelectorAll('tbody input:checked')],
            hasSearchInputTarget: true, searchInputTarget: { value: '' },
            performSearch: jest.fn(),
            switchView: jest.fn(), showAll: jest.fn(), captureStickyParamsFromUrl: jest.fn(),
            toggleAllSelection: jest.fn(GridViewController.prototype.toggleAllSelection),
            rowCheckboxElements: () => [...document.querySelectorAll('tbody input')],
            updateBulkSelectionUI: jest.fn(),
        };
    });

    afterEach(() => {
        controller?.disconnect();
        controller = null;
        jest.restoreAllMocks();
        window.matchMedia = originalMatchMedia;
        window.history.replaceState({}, '', '/');
        document.body.innerHTML = '';
    });

    test('delegates search and restore to the existing grid owner', () => {
        controller = mount(grid);
        controller.searchTarget.value = 'archery';
        controller.search({ preventDefault: jest.fn() });
        expect(grid.searchInputTarget.value).toBe('archery');
        expect(grid.performSearch).toHaveBeenCalledTimes(1);
        controller.restoreView();
        expect(grid.switchView).toHaveBeenCalledWith({ currentTarget: { dataset: { viewId: '7' } } });
        grid.state.view.currentId = null;
        controller.restoreView();
        expect(grid.showAll).toHaveBeenCalledTimes(1);
    });

    test('cards and table share exactly the same rows, labels and selection controls', () => {
        controller = mount(grid);
        const checkboxes = [...document.querySelectorAll('tbody input')];
        checkboxes[0].checked = true;
        grid.selectedIds = ['court'];
        controller.syncSelection();
        expect(controller.selectedNamesTarget).toHaveTextContent('Winter Court');
        expect(controller.selectionCountTarget).toHaveTextContent('1 selected on this page');
        expect(controller.selectionBarTarget.hidden).toBe(false);
        expect(document.querySelector('table')).toHaveAttribute('role', 'list');
        controller.updateLayout();
        controller.updateLayout();
        expect(document.querySelectorAll('.ui-grid-card-label')).toHaveLength(4);
        controller.layout({ currentTarget: controller.layoutButtonTargets[1] });
        expect(document.querySelector('table')).toHaveAttribute('role', 'table');
        expect([...document.querySelectorAll('tbody input')]).toEqual(checkboxes);
        expect(checkboxes[0]).toBeChecked();
        expect(controller.layoutButtonTargets[1]).toHaveAttribute('aria-pressed', 'true');
        controller.selectPage();
        expect(grid.toggleAllSelection).toHaveBeenCalledWith({ target: { checked: true } });
        expect(checkboxes.every(checkbox => checkbox.checked)).toBe(true);
        expect(grid.selectedIds).toEqual(['court', 'practice']);
    });

    test('keeps URL context and user layout choice while following responsive defaults', () => {
        controller = mount(grid, '/ui-patterns?pattern=collection&state=restricted&view_id=7#results');
        expect(controller.element.dataset.gridLayout).toBe('cards');
        expect(controller.displayOptionsTarget.open).toBe(false);
        controller.layout({ currentTarget: controller.layoutButtonTargets[1] });
        const url = new URL(window.location.href);
        expect(url.searchParams.get('pattern')).toBe('collection');
        expect(url.searchParams.get('state')).toBe('restricted');
        expect(url.searchParams.get('view_id')).toBe('7');
        expect(url.hash).toBe('#results');
        expect(grid.captureStickyParamsFromUrl).toHaveBeenCalledWith(url.toString());
        media.matches = true;
        controller.updateLayout();
        expect(controller.element.dataset.gridLayout).toBe('table');
    });

    test('refresh announces unsaved settings without replacing an active search draft', () => {
        controller = mount(grid);
        controller.searchTarget.focus();
        controller.searchTarget.value = 'unfinished';
        grid.state.search = 'server search';
        grid.state.view.hasChanges = true;
        controller.sync();
        expect(controller.searchTarget.value).toBe('unfinished');
        expect(controller.viewStatusTarget).toHaveTextContent('Court visits · Unsaved changes');
        expect(controller.restoreTarget.hidden).toBe(false);
        controller.searchTarget.blur();
        controller.sync();
        expect(controller.searchTarget.value).toBe('server search');
        grid.state.view.hasChanges = false;
        controller.sync();
        expect(controller.restoreTarget.hidden).toBe(true);
    });

    test.each([true, false])('restoring a view returns focus to its active tab (tab present: %s)', hasTab => {
        grid.state.view.hasChanges = true;
        controller = mount(grid);
        controller.element.insertAdjacentHTML('beforeend', '<button data-filter-button>Filters</button>');
        if (hasTab) {
            controller.element.insertAdjacentHTML('beforeend', `<div data-view-tabs-container role="tablist">
                <button role="tab" aria-selected="false" tabindex="-1">All gatherings</button>
                <button role="tab" aria-selected="true" data-view-id="7">Court visits</button></div>`);
        }
        controller.restoreTarget.addEventListener('click', () => controller.restoreView());
        controller.restoreTarget.focus();
        controller.restoreTarget.click();
        expect(grid.switchView).toHaveBeenCalledWith({ currentTarget: { dataset: { viewId: '7' } } });

        grid.state.view.hasChanges = false;
        controller.sync();

        expect(controller.restoreTarget.hidden).toBe(true);
        expect(controller.element.querySelector(hasTab ? '[aria-selected="true"]' : '[data-filter-button]')).toHaveFocus();
    });

    test('restored view does not take focus after the user moves to another control', () => {
        grid.state.view.hasChanges = true;
        controller = mount(grid);
        controller.element.insertAdjacentHTML('beforeend', '<button data-filter-button>Filters</button>');
        controller.restoreTarget.focus();
        controller.restoreView();
        controller.searchTarget.focus();
        grid.state.view.hasChanges = false;

        controller.sync();

        expect(controller.restoreTarget.hidden).toBe(true);
        expect(controller.searchTarget).toHaveFocus();
    });

    test('disconnect cancels pending frame work and removes media and selection listeners', () => {
        let refresh;
        jest.spyOn(window, 'requestAnimationFrame').mockImplementation(callback => { refresh = callback; return 82; });
        const cancel = jest.spyOn(window, 'cancelAnimationFrame').mockImplementation(() => {});
        controller = mount(grid);
        const remove = jest.spyOn(controller.element, 'removeEventListener');
        controller.element.dispatchEvent(new Event('turbo:frame-load'));
        expect(refresh).toEqual(expect.any(Function));
        controller.disconnect();
        expect(cancel).toHaveBeenCalledWith(82);
        expect(remove).toHaveBeenCalledWith('turbo:frame-load', controller.frameListener);
        expect(remove).toHaveBeenCalledWith('grid-view:selection-changed', controller.selectionListener);
        expect(media.removeEventListener).toHaveBeenCalledWith('change', controller.mediaListener);
        controller = null;
    });
});
