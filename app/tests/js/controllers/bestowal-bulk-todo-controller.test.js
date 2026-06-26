import '../../../plugins/Awards/Assets/js/controllers/bestowal-bulk-todo-controller.js';

const BestowalBulkTodoController = window.Controllers['awards-bestowal-bulk-todo'];

describe('AwardsBestowalBulkTodo', () => {
    let controller;
    let ids;
    let summary;
    let submit;

    beforeEach(() => {
        document.body.replaceChildren();
        const root = document.createElement('div');
        root.setAttribute('data-controller', 'awards-bestowal-bulk-todo');

        ids = document.createElement('input');
        ids.type = 'hidden';
        summary = document.createElement('p');
        submit = document.createElement('button');
        submit.disabled = true;
        root.append(ids, summary, submit);
        document.body.appendChild(root);

        controller = new BestowalBulkTodoController();
        controller.element = root;
        controller.idsTarget = ids;
        controller.summaryTarget = summary;
        controller.submitTarget = submit;
        controller.hasIdsTarget = true;
        controller.hasSummaryTarget = true;
        controller.hasSubmitTarget = true;
    });

    /**
     * @param {string[]} values
     * @returns {{button: HTMLElement, grid: HTMLElement}}
     */
    function buildGridSelection(values) {
        const grid = document.createElement('div');
        grid.setAttribute('data-controller', 'grid-view');
        values.forEach((value, index) => {
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.setAttribute('data-grid-view-target', 'rowCheckbox');
            cb.value = value;
            cb.checked = true;
            grid.appendChild(cb);
            if (index === 0) {
                const disabled = document.createElement('input');
                disabled.type = 'checkbox';
                disabled.setAttribute('data-grid-view-target', 'rowCheckbox');
                disabled.value = 'skip';
                disabled.checked = true;
                disabled.disabled = true;
                grid.appendChild(disabled);
            }
        });
        const button = document.createElement('button');
        grid.appendChild(button);
        document.body.appendChild(grid);
        return { button, grid };
    }

    test('registers under its kebab-case identifier', () => {
        expect(typeof BestowalBulkTodoController).toBe('function');
    });

    test('readSelection collects checked, enabled grid checkboxes', () => {
        const { button } = buildGridSelection(['3', '4']);
        expect(controller.readSelection(button)).toEqual(['3', '4']);
    });

    test('readSelection falls back to the serialized button selection', () => {
        const button = document.createElement('button');
        button.dataset.bulkActionSelection = JSON.stringify({ ids: ['8', '9'] });
        document.body.appendChild(button);
        expect(controller.readSelection(button)).toEqual(['8', '9']);
    });

    test('applySelection populates ids, summary, and enables submit', () => {
        controller.applySelection(['3', '4', '4']);
        expect(ids.value).toBe('3,4');
        expect(summary.textContent).toContain('2 selected bestowals');
        expect(submit.disabled).toBe(false);
    });

    test('applySelection uses singular copy for one bestowal', () => {
        controller.applySelection(['3']);
        expect(summary.textContent).toContain('1 selected bestowal');
        expect(submit.disabled).toBe(false);
    });

    test('applySelection disables submit when nothing is selected', () => {
        controller.applySelection([]);
        expect(ids.value).toBe('');
        expect(submit.disabled).toBe(true);
    });
});
