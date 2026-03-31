// Controller registers on window.Controllers (no default export)
import '../../../plugins/Awards/Assets/js/controllers/rec-quick-edit-controller.js';
const RecQuickEditController = window.Controllers['awards-rec-quick-edit'];

describe('AwardsRecommendationQuickEditForm', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="awards-rec-quick-edit"
                  data-awards-rec-quick-edit-form-url-value="/awards/recommendations/edit"
                  data-awards-rec-quick-edit-turbo-frame-url-value="/awards/recommendations/frame"
                  data-awards-rec-quick-edit-award-list-url-value="/awards/list"
                  data-awards-rec-quick-edit-gatherings-url-value="/awards/gatherings"
                  data-awards-rec-quick-edit-gatherings-lookup-url-value="/awards/gatherings-lookup"
                  action="/awards/recommendations/edit/1">
                <select data-awards-rec-quick-edit-target="domain"></select>
                <input type="hidden" data-awards-rec-quick-edit-target="award" value="">
                <textarea data-awards-rec-quick-edit-target="reason"></textarea>
                <div data-awards-rec-quick-edit-target="gatherings"></div>
                <input type="hidden" data-awards-rec-quick-edit-target="specialty" value="">
                <select data-awards-rec-quick-edit-target="state"><option value="Submitted">Submitted</option><option value="Given">Given</option><option value="Closed">Closed</option></select>
                <div data-awards-rec-quick-edit-target="planToGiveBlock"></div>
                <input data-awards-rec-quick-edit-target="planToGiveGathering" value="">
                <div data-awards-rec-quick-edit-target="givenBlock"></div>
                <input type="hidden" data-awards-rec-quick-edit-target="recId" value="42">
                <input type="hidden" data-awards-rec-quick-edit-target="memberId" value="10">
                <turbo-frame data-awards-rec-quick-edit-target="turboFrame" src=""></turbo-frame>
                <input type="date" data-awards-rec-quick-edit-target="givenDate" value="">
                <textarea data-awards-rec-quick-edit-target="closeReason"></textarea>
                <div data-awards-rec-quick-edit-target="closeReasonBlock"></div>
                <div data-awards-rec-quick-edit-target="stateRulesBlock">{"Submitted":{},"Given":{"Visible":["givenBlockTarget","planToGiveBlockTarget"],"Required":["givenDateTarget"]},"Closed":{"Visible":["closeReasonBlockTarget"],"Required":["closeReasonTarget"],"Disabled":["domainTarget","awardTarget"]}}</div>
                <button id="recommendation_edit_close" style="display:none;"></button>
            </form>
        `;

        controller = new RecQuickEditController();
        controller.element = document.querySelector('[data-controller="awards-rec-quick-edit"]');

        // Wire up targets
        controller.domainTarget = document.querySelector('[data-awards-rec-quick-edit-target="domain"]');
        controller.awardTarget = document.querySelector('[data-awards-rec-quick-edit-target="award"]');
        controller.reasonTarget = document.querySelector('[data-awards-rec-quick-edit-target="reason"]');
        controller.gatheringsTarget = document.querySelector('[data-awards-rec-quick-edit-target="gatherings"]');
        controller.specialtyTarget = document.querySelector('[data-awards-rec-quick-edit-target="specialty"]');
        controller.stateTarget = document.querySelector('[data-awards-rec-quick-edit-target="state"]');
        controller.planToGiveBlockTarget = document.querySelector('[data-awards-rec-quick-edit-target="planToGiveBlock"]');
        controller.planToGiveGatheringTarget = document.querySelector('[data-awards-rec-quick-edit-target="planToGiveGathering"]');
        controller.givenBlockTarget = document.querySelector('[data-awards-rec-quick-edit-target="givenBlock"]');
        controller.recIdTarget = document.querySelector('[data-awards-rec-quick-edit-target="recId"]');
        controller.memberIdTarget = document.querySelector('[data-awards-rec-quick-edit-target="memberId"]');
        controller.turboFrameTarget = document.querySelector('[data-awards-rec-quick-edit-target="turboFrame"]');
        controller.givenDateTarget = document.querySelector('[data-awards-rec-quick-edit-target="givenDate"]');
        controller.closeReasonTarget = document.querySelector('[data-awards-rec-quick-edit-target="closeReason"]');
        controller.closeReasonBlockTarget = document.querySelector('[data-awards-rec-quick-edit-target="closeReasonBlock"]');
        controller.stateRulesBlockTarget = document.querySelector('[data-awards-rec-quick-edit-target="stateRulesBlock"]');

        // Wire up values
        controller.formUrlValue = '/awards/recommendations/edit';
        controller.turboFrameUrlValue = '/awards/recommendations/frame';
        controller.awardListUrlValue = '/awards/list';
        controller.gatheringsUrlValue = '/awards/gatherings';
        controller.gatheringsLookupUrlValue = '/awards/gatherings-lookup';

        // Wire up has* checks
        controller.hasPlanToGiveGatheringTarget = true;
        controller.hasGatheringsLookupUrlValue = true;
        controller.hasMemberIdTarget = true;
        controller.hasStateTarget = true;
        controller.hasRecIdTarget = true;
        controller.hasAwardTarget = true;

        // Give specialty an options array
        controller.specialtyTarget.options = [];
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Instantiation ---

    test('instantiates with correct static targets', () => {
        expect(RecQuickEditController.targets).toEqual(
            expect.arrayContaining([
                'domain', 'award', 'reason', 'gatherings', 'specialty',
                'state', 'planToGiveBlock', 'planToGiveGathering',
                'givenBlock', 'recId', 'memberId', 'turboFrame',
                'givenDate', 'closeReason', 'closeReasonBlock', 'stateRulesBlock',
            ])
        );
    });

    test('instantiates with correct static values', () => {
        expect(RecQuickEditController.values).toHaveProperty('formUrl', String);
        expect(RecQuickEditController.values).toHaveProperty('turboFrameUrl', String);
        expect(RecQuickEditController.values).toHaveProperty('gatheringsLookupUrl', String);
    });

    test('defines outlet-btn outlet', () => {
        expect(RecQuickEditController.outlets).toEqual(
            expect.arrayContaining(['outlet-btn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['awards-rec-quick-edit']).toBe(RecQuickEditController);
    });

    // --- setId ---

    test('setId updates turbo frame src and form action', () => {
        controller.setId({ detail: { id: '99' } });
        expect(controller.turboFrameTarget.getAttribute('src'))
            .toBe('/awards/recommendations/frame/99');
        expect(controller.element.getAttribute('action'))
            .toBe('/awards/recommendations/edit/99');
    });

    test('setId does nothing when event.detail.id is falsy', () => {
        const origSrc = controller.turboFrameTarget.getAttribute('src');
        controller.setId({ detail: { id: null } });
        expect(controller.turboFrameTarget.getAttribute('src')).toBe(origSrc);
    });

    // --- submit ---

    test('submit clicks the close button', () => {
        const closeBtn = document.getElementById('recommendation_edit_close');
        const spy = jest.spyOn(closeBtn, 'click');
        controller.submit({});
        expect(spy).toHaveBeenCalled();
    });

    // --- setAward ---

    test('setAward sets award value and calls helpers', () => {
        const specSpy = jest.spyOn(controller, 'populateSpecialties').mockImplementation(() => {});
        const gathSpy = jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.awardTarget.options = [{ value: '5', text: 'Award', data: { specialties: [] } }];

        controller.setAward({ target: { dataset: { awardId: '5' } } });

        expect(controller.awardTarget.value).toBe('5');
        expect(specSpy).toHaveBeenCalled();
        expect(gathSpy).toHaveBeenCalledWith('5');
    });

    test('setAward skips helpers when value is empty', () => {
        const spy = jest.spyOn(controller, 'populateSpecialties').mockImplementation(() => {});
        controller.setAward({ target: { dataset: { awardId: '' } } });
        expect(spy).not.toHaveBeenCalled();
    });

    // --- updateGatherings ---

    test('updateGatherings returns early without required targets', () => {
        controller.hasPlanToGiveGatheringTarget = false;
        // Should not throw
        controller.updateGatherings('5');
    });

    test('updateGatherings builds lookup URL with params', () => {
        controller.stateTarget.value = 'Given';
        controller.memberIdTarget.value = '10';
        controller.recIdTarget.value = '42';
        controller.planToGiveGatheringTarget.value = '';

        controller.updateGatherings('5');

        const url = controller.planToGiveGatheringTarget.getAttribute('data-ac-url-value');
        expect(url).toContain('/awards/gatherings-lookup/5');
        expect(url).toContain('member_id=10');
        expect(url).toContain('status=Given');
        expect(url).toContain('recommendation_id=42');
    });

    test('updateGatherings clears value when award changes', () => {
        controller.planToGiveGatheringTarget.value = '77';
        controller.planToGiveGatheringTarget.dataset.lookupAwardId = '3';

        controller.updateGatherings('5');

        expect(controller.planToGiveGatheringTarget.value).toBe('');
    });

    // --- setPlanToGiveRequired ---

    test('setPlanToGiveRequired sets required on target', () => {
        controller.setPlanToGiveRequired(true);
        expect(controller.planToGiveGatheringTarget.required).toBe(true);
    });

    test('setPlanToGiveRequired returns early without target', () => {
        controller.hasPlanToGiveGatheringTarget = false;
        controller.setPlanToGiveRequired(true); // Should not throw
    });

    // --- populateAwardDescriptions ---

    test('populateAwardDescriptions populates options from API', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 1, name: 'Award A', specialties: ['Sword'] },
            ])
        }));

        controller.populateAwardDescriptions({ target: { value: 'combat' } });
        await new Promise(r => setTimeout(r, 0));

        expect(controller.awardTarget.options.length).toBe(1);
        expect(controller.awardTarget.disabled).toBe(false);
    });

    test('populateAwardDescriptions disables when empty', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([])
        }));

        controller.populateAwardDescriptions({ target: { value: 'empty' } });
        await new Promise(r => setTimeout(r, 0));

        expect(controller.awardTarget.disabled).toBe(true);
        expect(controller.specialtyTarget.disabled).toBe(true);
    });

    // --- populateSpecialties ---

    test('populateSpecialties shows specialties when available', () => {
        controller.awardTarget.value = '1';
        controller.awardTarget.options = [
            { value: '1', text: 'Award', data: { specialties: ['Sword', 'Archery'] } }
        ];
        controller.populateSpecialties({ target: { value: '1' } });

        expect(controller.specialtyTarget.options.length).toBe(2);
        expect(controller.specialtyTarget.disabled).toBe(false);
        expect(controller.specialtyTarget.hidden).toBe(false);
    });

    test('populateSpecialties hides when none', () => {
        controller.awardTarget.value = '1';
        controller.awardTarget.options = [
            { value: '1', text: 'Award', data: { specialties: null } }
        ];

        controller.populateSpecialties({ target: { value: '1' } });

        expect(controller.specialtyTarget.disabled).toBe(true);
        expect(controller.specialtyTarget.hidden).toBe(true);
    });

    // --- loadScaMemberInfo ---

    test('loadScaMemberInfo is a no-op', () => {
        // Quick edit does not load member info
        expect(() => controller.loadScaMemberInfo({})).not.toThrow();
    });

    // --- optionsForFetch ---

    test('optionsForFetch returns correct headers', () => {
        const opts = controller.optionsForFetch();
        expect(opts.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(opts.headers['Accept']).toBe('application/json');
    });

    // --- setFieldRules ---

    test('setFieldRules applies Given state rules', () => {
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.stateTarget.value = 'Given';
        controller.awardTarget.value = '5';
        controller.setFieldRules();

        expect(controller.givenBlockTarget.style.display).toBe('block');
        expect(controller.planToGiveBlockTarget.style.display).toBe('block');
        expect(controller.givenDateTarget.required).toBe(true);
    });

    test('setFieldRules applies Closed state rules', () => {
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.stateTarget.value = 'Closed';
        controller.awardTarget.value = '5';
        controller.setFieldRules();

        expect(controller.closeReasonBlockTarget.style.display).toBe('block');
        expect(controller.closeReasonTarget.required).toBe(true);
        expect(controller.domainTarget.disabled).toBe(true);
        expect(controller.awardTarget.disabled).toBe(true);
    });

    test('setFieldRules hides blocks for Submitted state', () => {
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.stateTarget.value = 'Submitted';
        controller.awardTarget.value = '5';
        controller.setFieldRules();

        expect(controller.planToGiveBlockTarget.style.display).toBe('none');
        expect(controller.givenBlockTarget.style.display).toBe('none');
        expect(controller.closeReasonBlockTarget.style.display).toBe('none');
    });

    // --- connect ---

    test('connect stores initial gathering value', () => {
        controller.planToGiveGatheringTarget.value = '77';
        controller.connect();
        expect(controller.planToGiveGatheringTarget.dataset.initialValue).toBe('77');
    });

    // --- recIdTargetConnected ---

    test('recIdTargetConnected updates form action', () => {
        controller.element.setAttribute('action', '/awards/recommendations/edit/1');
        controller.recIdTarget.value = '42';
        controller.recIdTargetConnected();
        expect(controller.element.getAttribute('action'))
            .toBe('/awards/recommendations/edit/42');
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

    // --- target connected callbacks ---

    test('givenDateTargetConnected stores initial value', () => {
        controller.givenDateTarget.value = '2024-06-01';
        controller.givenDateTargetConnected();
        expect(controller.givenDateTarget.dataset.initialValue).toBe('2024-06-01');
    });

    test('planToGiveGatheringTargetConnected stores initial value', () => {
        controller.planToGiveGatheringTarget.value = '55';
        controller.planToGiveGatheringTargetConnected();
        expect(controller.planToGiveGatheringTarget.dataset.initialValue).toBe('55');
    });
});
