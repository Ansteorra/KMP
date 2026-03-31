// Controller registers on window.Controllers (no default export)
import '../../../plugins/Awards/Assets/js/controllers/rec-edit-controller.js';
const RecEditController = window.Controllers['awards-rec-edit'];

describe('AwardsRecommendationEditForm', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="awards-rec-edit"
                  data-awards-rec-edit-public-profile-url-value="/members/public-profile"
                  data-awards-rec-edit-award-list-url-value="/awards/list"
                  data-awards-rec-edit-form-url-value="/awards/recommendations/edit"
                  data-awards-rec-edit-turbo-frame-url-value="/awards/recommendations/frame"
                  data-awards-rec-edit-gatherings-url-value="/awards/gatherings"
                  data-awards-rec-edit-gatherings-lookup-url-value="/awards/gatherings-lookup"
                  action="/awards/recommendations/edit/1">
                <input type="hidden" data-awards-rec-edit-target="scaMember" value="">
                <input type="hidden" data-awards-rec-edit-target="notFound" value="">
                <input type="hidden" data-awards-rec-edit-target="branch" value="">
                <div data-awards-rec-edit-target="externalLinks"></div>
                <select data-awards-rec-edit-target="domain"></select>
                <input type="hidden" data-awards-rec-edit-target="award" value="">
                <textarea data-awards-rec-edit-target="reason"></textarea>
                <div data-awards-rec-edit-target="gatherings"></div>
                <input type="hidden" data-awards-rec-edit-target="specialty" value="">
                <select data-awards-rec-edit-target="state"><option value="Submitted">Submitted</option><option value="Given">Given</option><option value="Closed">Closed</option></select>
                <div data-awards-rec-edit-target="planToGiveBlock"></div>
                <input data-awards-rec-edit-target="planToGiveGathering" value="">
                <div data-awards-rec-edit-target="givenBlock"></div>
                <input type="hidden" data-awards-rec-edit-target="recId" value="42">
                <turbo-frame data-awards-rec-edit-target="turboFrame" src=""></turbo-frame>
                <input type="date" data-awards-rec-edit-target="givenDate" value="">
                <textarea data-awards-rec-edit-target="closeReason"></textarea>
                <div data-awards-rec-edit-target="closeReasonBlock"></div>
                <div data-awards-rec-edit-target="stateRulesBlock">{"Submitted":{},"Given":{"Visible":["givenBlockTarget","planToGiveBlockTarget"],"Required":["givenDateTarget"]},"Closed":{"Visible":["closeReasonBlockTarget"],"Required":["closeReasonTarget"],"Disabled":["domainTarget","awardTarget","scaMemberTarget"]}}</div>
            </form>
        `;

        controller = new RecEditController();
        controller.element = document.querySelector('[data-controller="awards-rec-edit"]');

        // Wire up targets
        controller.scaMemberTarget = document.querySelector('[data-awards-rec-edit-target="scaMember"]');
        controller.notFoundTarget = document.querySelector('[data-awards-rec-edit-target="notFound"]');
        controller.branchTarget = document.querySelector('[data-awards-rec-edit-target="branch"]');
        controller.externalLinksTarget = document.querySelector('[data-awards-rec-edit-target="externalLinks"]');
        controller.domainTarget = document.querySelector('[data-awards-rec-edit-target="domain"]');
        controller.awardTarget = document.querySelector('[data-awards-rec-edit-target="award"]');
        controller.reasonTarget = document.querySelector('[data-awards-rec-edit-target="reason"]');
        controller.gatheringsTarget = document.querySelector('[data-awards-rec-edit-target="gatherings"]');
        controller.specialtyTarget = document.querySelector('[data-awards-rec-edit-target="specialty"]');
        controller.stateTarget = document.querySelector('[data-awards-rec-edit-target="state"]');
        controller.planToGiveBlockTarget = document.querySelector('[data-awards-rec-edit-target="planToGiveBlock"]');
        controller.planToGiveGatheringTarget = document.querySelector('[data-awards-rec-edit-target="planToGiveGathering"]');
        controller.givenBlockTarget = document.querySelector('[data-awards-rec-edit-target="givenBlock"]');
        controller.recIdTarget = document.querySelector('[data-awards-rec-edit-target="recId"]');
        controller.turboFrameTarget = document.querySelector('[data-awards-rec-edit-target="turboFrame"]');
        controller.givenDateTarget = document.querySelector('[data-awards-rec-edit-target="givenDate"]');
        controller.closeReasonTarget = document.querySelector('[data-awards-rec-edit-target="closeReason"]');
        controller.closeReasonBlockTarget = document.querySelector('[data-awards-rec-edit-target="closeReasonBlock"]');
        controller.stateRulesBlockTarget = document.querySelector('[data-awards-rec-edit-target="stateRulesBlock"]');

        // Wire up values
        controller.publicProfileUrlValue = '/members/public-profile';
        controller.awardListUrlValue = '/awards/list';
        controller.formUrlValue = '/awards/recommendations/edit';
        controller.turboFrameUrlValue = '/awards/recommendations/frame';
        controller.gatheringsUrlValue = '/awards/gatherings';
        controller.gatheringsLookupUrlValue = '/awards/gatherings-lookup';

        // Wire up has* checks
        controller.hasScaMemberTarget = true;
        controller.hasStateTarget = true;
        controller.hasPlanToGiveGatheringTarget = true;
        controller.hasGatheringsLookupUrlValue = true;
        controller.hasRecIdTarget = true;
        controller.hasAwardTarget = true;

        // Give specialty an options array for setFieldRules
        controller.specialtyTarget.options = [];
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Instantiation ---

    test('instantiates with correct static targets', () => {
        expect(RecEditController.targets).toEqual(
            expect.arrayContaining([
                'scaMember', 'notFound', 'branch', 'externalLinks',
                'domain', 'award', 'reason', 'gatherings', 'specialty',
                'state', 'planToGiveBlock', 'planToGiveGathering',
                'givenBlock', 'recId', 'turboFrame', 'givenDate',
                'closeReason', 'closeReasonBlock', 'stateRulesBlock',
            ])
        );
    });

    test('instantiates with correct static values', () => {
        expect(RecEditController.values).toHaveProperty('publicProfileUrl', String);
        expect(RecEditController.values).toHaveProperty('awardListUrl', String);
        expect(RecEditController.values).toHaveProperty('formUrl', String);
        expect(RecEditController.values).toHaveProperty('turboFrameUrl', String);
        expect(RecEditController.values).toHaveProperty('gatheringsUrl', String);
        expect(RecEditController.values).toHaveProperty('gatheringsLookupUrl', String);
    });

    test('defines outlet-btn outlet', () => {
        expect(RecEditController.outlets).toEqual(
            expect.arrayContaining(['outlet-btn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['awards-rec-edit']).toBe(RecEditController);
    });

    // --- setId ---

    test('setId updates turbo frame src and form action', () => {
        controller.setId({ detail: { id: '99' } });
        expect(controller.turboFrameTarget.getAttribute('src'))
            .toBe('/awards/recommendations/frame/99');
        expect(controller.element.getAttribute('action'))
            .toBe('/awards/recommendations/edit/99');
    });

    // --- submit ---

    test('submit enables disabled fields before submission', () => {
        controller.notFoundTarget.disabled = true;
        controller.scaMemberTarget.disabled = true;
        controller.specialtyTarget.disabled = true;

        controller.submit({});

        expect(controller.notFoundTarget.disabled).toBe(false);
        expect(controller.scaMemberTarget.disabled).toBe(false);
        expect(controller.specialtyTarget.disabled).toBe(false);
    });

    // --- setAward ---

    test('setAward sets award value and calls populateSpecialties', () => {
        const spy = jest.spyOn(controller, 'populateSpecialties').mockImplementation(() => {});
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.awardTarget.options = [{ value: '5', text: 'Award A', data: { specialties: [] } }];

        controller.setAward({ target: { dataset: { awardId: '5' } } });

        expect(controller.awardTarget.value).toBe('5');
        expect(spy).toHaveBeenCalled();
    });

    test('setAward does not populate when awardId results in empty value', () => {
        const spy = jest.spyOn(controller, 'populateSpecialties').mockImplementation(() => {});
        controller.setAward({ target: { dataset: { awardId: '' } } });
        expect(spy).not.toHaveBeenCalled();
    });

    // --- updateGatherings ---

    test('updateGatherings returns early with no awardId', () => {
        global.fetch = jest.fn();
        controller.updateGatherings(null);
        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('updateGatherings fetches and populates checkbox list', async () => {
        document.body.innerHTML += '<div id="recommendation__gathering_ids"></div>';

        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                gatherings: [
                    { id: 1, display: 'Event A', cancelled: false },
                    { id: 2, display: 'Event B', cancelled: true },
                ]
            })
        }));

        jest.spyOn(controller, 'updatePlanToGiveLookupUrl').mockImplementation(() => {});
        controller.stateTarget.value = 'Submitted';

        controller.updateGatherings('10');
        await new Promise(r => setTimeout(r, 0));

        const container = document.getElementById('recommendation__gathering_ids');
        expect(container.querySelectorAll('input[type="checkbox"]').length).toBe(2);
        // Cancelled gathering checkbox should be disabled
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        expect(checkboxes[1].disabled).toBe(true);
    });

    // --- updatePlanToGiveLookupUrl ---

    test('updatePlanToGiveLookupUrl sets data-ac-url-value on target', () => {
        controller.planToGiveGatheringTarget.value = '';
        controller.recIdTarget.value = '42';
        controller.updatePlanToGiveLookupUrl('10', '5', 'Submitted');

        const url = controller.planToGiveGatheringTarget.getAttribute('data-ac-url-value');
        expect(url).toContain('/awards/gatherings-lookup/10');
        expect(url).toContain('member_id=5');
        expect(url).toContain('status=Submitted');
        expect(url).toContain('recommendation_id=42');
    });

    test('updatePlanToGiveLookupUrl clears value when award changes', () => {
        controller.planToGiveGatheringTarget.value = '77';
        controller.planToGiveGatheringTarget.dataset.lookupAwardId = '5';
        controller.updatePlanToGiveLookupUrl('10');
        expect(controller.planToGiveGatheringTarget.value).toBe('');
    });

    test('updatePlanToGiveLookupUrl returns early without required targets', () => {
        controller.hasPlanToGiveGatheringTarget = false;
        // Should not throw
        controller.updatePlanToGiveLookupUrl('10');
    });

    // --- setPlanToGiveRequired ---

    test('setPlanToGiveRequired sets required on target', () => {
        controller.setPlanToGiveRequired(true);
        expect(controller.planToGiveGatheringTarget.required).toBe(true);
    });

    test('setPlanToGiveRequired sets required false', () => {
        controller.planToGiveGatheringTarget.required = true;
        controller.setPlanToGiveRequired(false);
        expect(controller.planToGiveGatheringTarget.required).toBe(false);
    });

    test('setPlanToGiveRequired returns early without target', () => {
        controller.hasPlanToGiveGatheringTarget = false;
        // Should not throw
        controller.setPlanToGiveRequired(true);
    });

    // --- populateAwardDescriptions ---

    test('populateAwardDescriptions fetches and populates award options', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 1, name: 'Award A', specialties: ['Sword'], description: 'desc' },
                { id: 2, name: 'Award B', specialties: null, description: 'desc' },
            ])
        }));

        controller.populateAwardDescriptions({ target: { value: 'combat' } });
        await new Promise(r => setTimeout(r, 0));

        expect(global.fetch).toHaveBeenCalledWith(
            '/awards/list/combat',
            expect.any(Object)
        );
        expect(controller.awardTarget.options.length).toBe(2);
        expect(controller.awardTarget.disabled).toBe(false);
    });

    test('populateAwardDescriptions disables when no awards', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([])
        }));

        controller.populateAwardDescriptions({ target: { value: 'empty' } });
        await new Promise(r => setTimeout(r, 0));

        expect(controller.awardTarget.disabled).toBe(true);
        expect(controller.specialtyTarget.disabled).toBe(true);
        expect(controller.specialtyTarget.hidden).toBe(true);
    });

    // --- populateSpecialties ---

    test('populateSpecialties shows specialties when present', () => {
        controller.awardTarget.value = '1';
        controller.awardTarget.options = [
            { value: '1', text: 'Award A', data: { specialties: ['Sword', 'Archery'] } }
        ];
        controller.populateSpecialties({ target: { value: '1' } });

        expect(controller.specialtyTarget.options.length).toBe(2);
        expect(controller.specialtyTarget.disabled).toBe(false);
        expect(controller.specialtyTarget.hidden).toBe(false);
    });

    test('populateSpecialties hides when no specialties', () => {
        controller.awardTarget.value = '1';
        controller.awardTarget.options = [
            { value: '1', text: 'Award A', data: { specialties: null } }
        ];

        controller.populateSpecialties({ target: { value: '1' } });

        expect(controller.specialtyTarget.disabled).toBe(true);
        expect(controller.specialtyTarget.hidden).toBe(true);
    });

    // --- loadScaMemberInfo ---

    test('loadScaMemberInfo calls loadMember for valid member', () => {
        const spy = jest.spyOn(controller, 'loadMember').mockImplementation(() => {});
        controller.loadScaMemberInfo({ target: { value: '123' } });

        expect(controller.notFoundTarget.checked).toBe(false);
        expect(controller.branchTarget.hidden).toBe(true);
        expect(spy).toHaveBeenCalledWith(123);
    });

    test('loadScaMemberInfo shows branch for not-found member', () => {
        jest.spyOn(controller.branchTarget, 'focus').mockImplementation(() => {});
        controller.loadScaMemberInfo({ target: { value: '' } });

        expect(controller.notFoundTarget.checked).toBe(true);
        expect(controller.branchTarget.hidden).toBe(false);
        expect(controller.branchTarget.disabled).toBe(false);
    });

    // --- loadMember ---

    test('loadMember fetches and displays external links', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                external_links: { 'OP Page': 'http://op.example.com' }
            })
        }));

        controller.loadMember(123);
        await new Promise(r => setTimeout(r, 0));

        expect(controller.externalLinksTarget.querySelectorAll('a').length).toBe(1);
        expect(controller.externalLinksTarget.querySelector('a').href).toBe('http://op.example.com/');
    });

    test('loadMember shows no links message when empty', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ external_links: {} })
        }));

        controller.loadMember(123);
        await new Promise(r => setTimeout(r, 0));

        expect(controller.externalLinksTarget.innerHTML).toContain('No links available');
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
        expect(controller.scaMemberTarget.disabled).toBe(true);
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

    test('connect does not store when gathering has no value', () => {
        controller.planToGiveGatheringTarget.value = '';
        controller.connect();
        expect(controller.planToGiveGatheringTarget.dataset.initialValue).toBeUndefined();
    });

    // --- recIdTargetConnected ---

    test('recIdTargetConnected updates form action with rec id', () => {
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

    // --- givenDateTargetConnected ---

    test('givenDateTargetConnected stores initial value', () => {
        controller.givenDateTarget.value = '2024-01-15';
        controller.givenDateTargetConnected();
        expect(controller.givenDateTarget.dataset.initialValue).toBe('2024-01-15');
    });

    // --- planToGiveGatheringTargetConnected ---

    test('planToGiveGatheringTargetConnected stores initial value', () => {
        controller.planToGiveGatheringTarget.value = '55';
        controller.planToGiveGatheringTargetConnected();
        expect(controller.planToGiveGatheringTarget.dataset.initialValue).toBe('55');
    });
});
