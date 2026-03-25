// Controller registers on window.Controllers (no default export)
import '../../../plugins/Awards/Assets/js/controllers/rec-bulk-edit-controller.js';
const RecBulkEditController = window.Controllers['awards-rec-bulk-edit'];

describe('AwardsRecommendationBulkEditForm', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="awards-rec-bulk-edit"
                  data-awards-rec-bulk-edit-form-url-value="/awards/recommendations/update-states"
                  data-awards-rec-bulk-edit-turbo-frame-url-value="/awards/recommendations/frame"
                  data-awards-rec-bulk-edit-gatherings-url-value="/awards/gatherings"
                  data-awards-rec-bulk-edit-gatherings-lookup-url-value="/awards/gatherings-lookup"
                  action="/awards/recommendations/update-states">
                <input type="hidden" data-awards-rec-bulk-edit-target="bulkIds" value="">
                <div data-awards-rec-bulk-edit-target="gatherings"></div>
                <select data-awards-rec-bulk-edit-target="state"><option value="Submitted">Submitted</option><option value="Given">Given</option><option value="Closed">Closed</option></select>
                <div data-awards-rec-bulk-edit-target="planToGiveBlock"></div>
                <input data-awards-rec-bulk-edit-target="planToGiveGathering" value="">
                <div data-awards-rec-bulk-edit-target="givenBlock"></div>
                <input type="hidden" data-awards-rec-bulk-edit-target="recId" value="">
                <turbo-frame data-awards-rec-bulk-edit-target="turboFrame" src=""></turbo-frame>
                <input type="date" data-awards-rec-bulk-edit-target="givenDate" value="">
                <textarea data-awards-rec-bulk-edit-target="closeReason"></textarea>
                <div data-awards-rec-bulk-edit-target="closeReasonBlock"></div>
                <div data-awards-rec-bulk-edit-target="stateRulesBlock">{"Submitted":{},"Given":{"Visible":["givenBlockTarget","planToGiveBlockTarget"],"Required":["givenDateTarget"]},"Closed":{"Visible":["closeReasonBlockTarget"],"Required":["closeReasonTarget"]}}</div>
                <button id="recommendation_bulk_edit_close" style="display:none;"></button>
            </form>
        `;

        controller = new RecBulkEditController();
        controller.element = document.querySelector('[data-controller="awards-rec-bulk-edit"]');

        // Wire up targets
        controller.bulkIdsTarget = document.querySelector('[data-awards-rec-bulk-edit-target="bulkIds"]');
        controller.gatheringsTarget = document.querySelector('[data-awards-rec-bulk-edit-target="gatherings"]');
        controller.stateTarget = document.querySelector('[data-awards-rec-bulk-edit-target="state"]');
        controller.planToGiveBlockTarget = document.querySelector('[data-awards-rec-bulk-edit-target="planToGiveBlock"]');
        controller.planToGiveGatheringTarget = document.querySelector('[data-awards-rec-bulk-edit-target="planToGiveGathering"]');
        controller.givenBlockTarget = document.querySelector('[data-awards-rec-bulk-edit-target="givenBlock"]');
        controller.recIdTarget = document.querySelector('[data-awards-rec-bulk-edit-target="recId"]');
        controller.turboFrameTarget = document.querySelector('[data-awards-rec-bulk-edit-target="turboFrame"]');
        controller.givenDateTarget = document.querySelector('[data-awards-rec-bulk-edit-target="givenDate"]');
        controller.closeReasonTarget = document.querySelector('[data-awards-rec-bulk-edit-target="closeReason"]');
        controller.closeReasonBlockTarget = document.querySelector('[data-awards-rec-bulk-edit-target="closeReasonBlock"]');
        controller.stateRulesBlockTarget = document.querySelector('[data-awards-rec-bulk-edit-target="stateRulesBlock"]');

        // Wire up values
        controller.formUrlValue = '/awards/recommendations/update-states';
        controller.turboFrameUrlValue = '/awards/recommendations/frame';
        controller.gatheringsUrlValue = '/awards/gatherings';
        controller.gatheringsLookupUrlValue = '/awards/gatherings-lookup';
        controller.bulkIdsValue = [];

        // Wire up has* checks
        controller.hasPlanToGiveGatheringTarget = true;
        controller.hasGatheringsLookupUrlValue = true;
        controller.hasStateTarget = true;
        controller.hasRecIdTarget = false;
        controller.hasAwardTarget = false;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Instantiation ---

    test('instantiates with correct static targets', () => {
        expect(RecBulkEditController.targets).toEqual(
            expect.arrayContaining([
                'bulkIds', 'gatherings', 'state', 'planToGiveBlock',
                'planToGiveGathering', 'givenBlock', 'recId', 'turboFrame',
                'givenDate', 'closeReason', 'closeReasonBlock', 'stateRulesBlock',
            ])
        );
    });

    test('instantiates with correct static values', () => {
        expect(RecBulkEditController.values).toHaveProperty('formUrl', String);
        expect(RecBulkEditController.values).toHaveProperty('bulkIds', Array);
        expect(RecBulkEditController.values).toHaveProperty('gatheringsLookupUrl', String);
    });

    test('defines outlet-btn outlet', () => {
        expect(RecBulkEditController.outlets).toEqual(
            expect.arrayContaining(['outlet-btn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['awards-rec-bulk-edit']).toBe(RecBulkEditController);
    });

    // --- setId ---

    test('setId stores selected IDs and updates form action', () => {
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.setId({ detail: { ids: [1, 2, 3] } });

        expect(controller.bulkIdsValue).toEqual([1, 2, 3]);
        expect(controller.bulkIdsTarget.value).toBe('1,2,3');
        expect(controller.element.getAttribute('action'))
            .toContain('updateStates');
    });

    test('setId returns early when no ids', () => {
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        const origAction = controller.element.getAttribute('action');
        controller.setId({ detail: { ids: null } });
        expect(controller.element.getAttribute('action')).toBe(origAction);
    });

    test('setId returns early when ids is empty array', () => {
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        const origAction = controller.element.getAttribute('action');
        controller.setId({ detail: { ids: [] } });
        expect(controller.element.getAttribute('action')).toBe(origAction);
    });

    // --- updateGatherings ---

    test('updateGatherings returns early without required targets', () => {
        controller.hasPlanToGiveGatheringTarget = false;
        // Should not throw
        controller.updateGatherings();
    });

    test('updateGatherings builds lookup URL with ids and status', () => {
        controller.bulkIdsValue = [10, 20, 30];
        controller.stateTarget.value = 'Given';
        controller.planToGiveGatheringTarget.value = '';

        controller.updateGatherings();

        const url = controller.planToGiveGatheringTarget.getAttribute('data-ac-url-value');
        expect(url).toContain('/awards/gatherings-lookup');
        expect(url).toContain('ids=10%2C20%2C30');
        expect(url).toContain('status=Given');
    });

    test('updateGatherings clears value when ids change', () => {
        controller.planToGiveGatheringTarget.value = '77';
        controller.planToGiveGatheringTarget.dataset.lookupIdsKey = '1,2';
        controller.bulkIdsValue = [3, 4];

        controller.updateGatherings();

        expect(controller.planToGiveGatheringTarget.value).toBe('');
    });

    test('updateGatherings preserves selected_id param', () => {
        controller.bulkIdsValue = [10];
        controller.stateTarget.value = 'Submitted';
        controller.planToGiveGatheringTarget.value = '55';

        controller.updateGatherings();

        const url = controller.planToGiveGatheringTarget.getAttribute('data-ac-url-value');
        expect(url).toContain('selected_id=55');
    });

    // --- setPlanToGiveRequired ---

    test('setPlanToGiveRequired sets required true', () => {
        controller.setPlanToGiveRequired(true);
        expect(controller.planToGiveGatheringTarget.required).toBe(true);
    });

    test('setPlanToGiveRequired sets required false', () => {
        controller.planToGiveGatheringTarget.required = true;
        controller.setPlanToGiveRequired(false);
        expect(controller.planToGiveGatheringTarget.required).toBe(false);
    });

    test('setPlanToGiveRequired also sets required on inner input', () => {
        const inner = document.createElement('input');
        inner.setAttribute('data-ac-target', 'input');
        controller.planToGiveGatheringTarget.appendChild(inner);

        controller.setPlanToGiveRequired(true);
        expect(inner.required).toBe(true);
    });

    test('setPlanToGiveRequired returns early without target', () => {
        controller.hasPlanToGiveGatheringTarget = false;
        controller.setPlanToGiveRequired(true); // Should not throw
    });

    // --- outlet communication ---

    test('outletBtnOutletConnected adds listener', () => {
        const mockOutlet = { addListener: jest.fn() };
        controller.outletBtnOutletConnected(mockOutlet, document.createElement('button'));
        expect(mockOutlet.addListener).toHaveBeenCalledWith(expect.any(Function));
    });

    test('outletBtnOutletDisconnected removes listener', () => {
        const mockOutlet = { removeListener: jest.fn() };
        controller.outletBtnOutletDisconnected(mockOutlet);
        expect(mockOutlet.removeListener).toHaveBeenCalledWith(expect.any(Function));
    });

    // --- submit ---

    test('submit clicks the close button', () => {
        const closeBtn = document.getElementById('recommendation_bulk_edit_close');
        const spy = jest.spyOn(closeBtn, 'click');
        controller.submit({});
        expect(spy).toHaveBeenCalled();
    });

    // --- setFieldRules ---

    test('setFieldRules applies Given state rules', () => {
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.stateTarget.value = 'Given';
        controller.setFieldRules();

        expect(controller.givenBlockTarget.style.display).toBe('block');
        expect(controller.planToGiveBlockTarget.style.display).toBe('block');
        expect(controller.givenDateTarget.required).toBe(true);
    });

    test('setFieldRules applies Closed state rules', () => {
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.stateTarget.value = 'Closed';
        controller.setFieldRules();

        expect(controller.closeReasonBlockTarget.style.display).toBe('block');
        expect(controller.closeReasonTarget.required).toBe(true);
    });

    test('setFieldRules hides all blocks for Submitted state', () => {
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.stateTarget.value = 'Submitted';
        controller.setFieldRules();

        expect(controller.planToGiveBlockTarget.style.display).toBe('none');
        expect(controller.givenBlockTarget.style.display).toBe('none');
        expect(controller.closeReasonBlockTarget.style.display).toBe('none');
    });

    // --- connect / disconnect ---

    test('connect adds grid-view:bulk-action listener', () => {
        const spy = jest.spyOn(document, 'addEventListener');
        controller.connect();
        expect(spy).toHaveBeenCalledWith('grid-view:bulk-action', expect.any(Function));
    });

    test('disconnect removes grid-view:bulk-action listener', () => {
        controller.connect();
        const spy = jest.spyOn(document, 'removeEventListener');
        controller.disconnect();
        expect(spy).toHaveBeenCalledWith('grid-view:bulk-action', expect.any(Function));
    });

    // --- handleGridBulkAction ---

    test('handleGridBulkAction delegates to setId', () => {
        const spy = jest.spyOn(controller, 'setId').mockImplementation(() => {});
        controller.handleGridBulkAction({ detail: { ids: [1, 2] } });
        expect(spy).toHaveBeenCalledWith({ detail: { ids: [1, 2] } });
    });
});
