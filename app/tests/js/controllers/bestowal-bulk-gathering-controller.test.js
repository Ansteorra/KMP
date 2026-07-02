import '../../../plugins/Awards/Assets/js/controllers/bestowal-bulk-gathering-controller.js';

const BestowalBulkGatheringController = window.Controllers['awards-bestowal-bulk-gathering'];

describe('AwardsBestowalBulkGathering', () => {
    let controller;
    let ids;
    let summary;
    let submit;
    let gatheringControl;
    let hidden;
    let input;
    let hiddenText;
    let clearBtn;

    beforeEach(() => {
        document.body.replaceChildren();
        const root = document.createElement('div');
        root.setAttribute('data-controller', 'awards-bestowal-bulk-gathering');

        ids = document.createElement('input');
        ids.type = 'hidden';
        summary = document.createElement('p');
        submit = document.createElement('button');
        submit.disabled = true;
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
        root.append(ids, summary, gatheringControl, submit);
        document.body.appendChild(root);

        controller = new BestowalBulkGatheringController();
        controller.element = root;
        controller.idsTarget = ids;
        controller.summaryTarget = summary;
        controller.submitTarget = submit;
        controller.gatheringControlTarget = gatheringControl;
        controller.hasIdsTarget = true;
        controller.hasSummaryTarget = true;
        controller.hasSubmitTarget = true;
        controller.hasGatheringControlTarget = true;
        controller.lookupUrlValue = '/awards/bestowals/gatherings-for-bestowal-auto-complete';
        controller.hasLookupUrlValue = true;
    });

    function buildGridSelection(values) {
        const grid = document.createElement('div');
        grid.setAttribute('data-controller', 'grid-view');
        values.forEach((value) => {
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.setAttribute('data-grid-view-target', 'rowCheckbox');
            cb.value = value;
            cb.checked = true;
            grid.appendChild(cb);
        });
        const button = document.createElement('button');
        grid.appendChild(button);
        document.body.appendChild(grid);
        return button;
    }

    test('registers under its kebab-case identifier', () => {
        expect(typeof BestowalBulkGatheringController).toBe('function');
    });

    test('readSelection collects checked grid rows', () => {
        const button = buildGridSelection(['11', '12']);
        expect(controller.readSelection(button)).toEqual(['11', '12']);
    });

    test('applySelection populates selected ids and live summary', () => {
        controller.applySelection(['11', '12', '12']);
        expect(ids.value).toBe('11,12');
        expect(summary.textContent).toContain('2 selected bestowals');
    });

    test('updateLookupUrl scopes autocomplete to selected bestowals', () => {
        controller.updateLookupUrl(['11', '12']);
        expect(gatheringControl.dataset.acUrlValue)
            .toBe('http://localhost/awards/bestowals/gatherings-for-bestowal-auto-complete?bestowal_ids=11%2C12');
    });

    test('resetGathering clears current autocomplete state', () => {
        input.value = 'Court';
        input.disabled = true;
        input.setAttribute('aria-invalid', 'true');
        hidden.value = '4';
        hiddenText.value = 'Court';
        clearBtn.disabled = false;

        controller.resetGathering();

        expect(input.value).toBe('');
        expect(input.disabled).toBe(false);
        expect(input).not.toHaveAttribute('aria-invalid');
        expect(hidden.value).toBe('');
        expect(hiddenText.value).toBe('');
        expect(clearBtn.disabled).toBe(true);
    });

    test('submit is enabled only when ids and gathering are selected', () => {
        controller.applySelection(['11']);
        controller.updateSubmitState();
        expect(submit.disabled).toBe(true);

        hidden.value = '5';
        controller.updateSubmitState();
        expect(submit.disabled).toBe(false);
    });
});
