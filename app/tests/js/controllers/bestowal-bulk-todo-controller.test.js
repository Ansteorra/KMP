import '../../../plugins/Awards/Assets/js/controllers/bestowal-bulk-todo-controller.js';

const BestowalBulkTodoController = window.Controllers['awards-bestowal-bulk-todo'];

describe('AwardsBestowalBulkTodo', () => {
    let controller;
    let ids;
    let summary;
    let submit;
    let checkSelect;
    let gatheringSection;
    let gatheringControl;
    let hidden;
    let input;
    let hiddenText;
    let clearBtn;

    beforeEach(() => {
        document.body.replaceChildren();
        const root = document.createElement('div');
        root.setAttribute('data-controller', 'awards-bestowal-bulk-todo');

        ids = document.createElement('input');
        ids.type = 'hidden';
        summary = document.createElement('p');
        submit = document.createElement('button');
        submit.disabled = true;
        checkSelect = document.createElement('select');
        gatheringSection = document.createElement('div');
        gatheringControl = document.createElement('div');
        input = document.createElement('input');
        input.setAttribute('data-ac-target', 'input');
        hidden = document.createElement('input');
        hidden.setAttribute('data-ac-target', 'hidden');
        hiddenText = document.createElement('input');
        hiddenText.setAttribute('data-ac-target', 'hiddenText');
        clearBtn = document.createElement('button');
        clearBtn.setAttribute('data-ac-target', 'clearBtn');
        gatheringControl.append(input, hidden, hiddenText, clearBtn);
        gatheringSection.append(gatheringControl);
        root.append(ids, summary, checkSelect, gatheringSection, submit);
        document.body.appendChild(root);

        controller = new BestowalBulkTodoController();
        controller.element = root;
        controller.idsTarget = ids;
        controller.summaryTarget = summary;
        controller.submitTarget = submit;
        controller.checkSelectTarget = checkSelect;
        controller.gatheringSectionTarget = gatheringSection;
        controller.gatheringControlTarget = gatheringControl;
        controller.hasIdsTarget = true;
        controller.hasSummaryTarget = true;
        controller.hasSubmitTarget = true;
        controller.hasCheckSelectTarget = true;
        controller.hasGatheringSectionTarget = true;
        controller.hasGatheringControlTarget = true;
        controller.lookupUrlValue = '/awards/bestowals/gatherings-for-bestowal-auto-complete';
        controller.hasLookupUrlValue = true;
    });

    /**
     * @param {string[]} values
     * @returns {{button: HTMLElement, grid: HTMLElement}}
     */
    function buildGridSelection(rows) {
        const grid = document.createElement('div');
        grid.setAttribute('data-controller', 'grid-view');
        rows.forEach((row, index) => {
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.setAttribute('data-grid-view-target', 'rowCheckbox');
            cb.value = row.id;
            cb.checked = true;
            cb.dataset.bulkTodoOptions = JSON.stringify(row.options || []);
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
        const { button } = buildGridSelection([{ id: '3' }, { id: '4' }]);
        expect(controller.readSelection(button)).toEqual(['3', '4']);
    });

    test('readSelection falls back to the serialized button selection', () => {
        const button = document.createElement('button');
        button.dataset.bulkActionSelection = JSON.stringify({ ids: ['8', '9'] });
        document.body.appendChild(button);
        expect(controller.readSelection(button)).toEqual(['8', '9']);
    });

    test('applySelection populates ids, summary, and check options', () => {
        controller.applySelection(['3', '4', '4'], [
            { id: '3', options: [{ key: 'has_scroll', label: 'Has Scroll' }] },
            { id: '4', options: [{ key: 'event_scheduled', label: 'Event Scheduled', requiresGathering: true }] },
        ]);
        expect(ids.value).toBe('3,4');
        expect(summary.textContent).toContain('2 selected bestowals');
        expect(Array.from(checkSelect.options).map((option) => option.value))
            .toEqual(['', 'event_scheduled', 'has_scroll']);
        expect(submit.disabled).toBe(true);
    });

    test('applySelection uses singular copy for one bestowal', () => {
        controller.applySelection(['3'], [
            { id: '3', options: [{ key: 'has_scroll', label: 'Has Scroll' }] },
        ]);
        expect(summary.textContent).toContain('1 selected bestowal');
        checkSelect.value = 'has_scroll';
        controller.updateSubmitState();
        expect(submit.disabled).toBe(false);
    });

    test('applySelection disables submit when nothing is selected', () => {
        controller.applySelection([]);
        expect(ids.value).toBe('');
        expect(submit.disabled).toBe(true);
    });

    test('selecting a gathering-required check shows and scopes the gathering field', () => {
        controller.applySelection(['3'], [
            {
                id: '3',
                options: [{
                    key: 'event_scheduled',
                    label: 'Event Scheduled',
                    requiresGathering: true,
                    gatheringHelp: 'Choose a court.',
                }],
            },
        ]);

        checkSelect.value = 'event_scheduled';
        controller.handleCheckChange();

        expect(gatheringSection.hidden).toBe(false);
        expect(input.disabled).toBe(false);
        expect(input.required).toBe(true);
        expect(gatheringControl.dataset.acUrlValue)
            .toBe('http://localhost/awards/bestowals/gatherings-for-bestowal-auto-complete?bestowal_ids=3');
        expect(submit.disabled).toBe(true);

        hidden.value = '5';
        controller.updateSubmitState();
        expect(submit.disabled).toBe(false);
    });

    test('switching away from a gathering-required check hides and disables the gathering field', () => {
        controller.applySelection(['3'], [
            { id: '3', options: [{ key: 'event_scheduled', label: 'Event Scheduled', requiresGathering: true }] },
            { id: '3', options: [{ key: 'has_scroll', label: 'Has Scroll' }] },
        ]);

        checkSelect.value = 'event_scheduled';
        controller.handleCheckChange();
        checkSelect.value = 'has_scroll';
        controller.handleCheckChange();

        expect(gatheringSection.hidden).toBe(true);
        expect(input.disabled).toBe(true);
        expect(input.required).toBe(false);
    });
});
