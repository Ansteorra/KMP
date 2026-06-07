// Controller registers on window.Controllers (no default export)
import '../../../plugins/Awards/Assets/js/controllers/rec-quick-edit-controller.js';
const RecQuickEditController = window.Controllers['awards-rec-quick-edit'];

describe('AwardsRecommendationQuickEditForm', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form id="recommendation_form" data-controller="awards-rec-quick-edit"
                  data-awards-rec-quick-edit-form-url-value="/awards/recommendations/edit"
                  data-awards-rec-quick-edit-turbo-frame-url-value="/awards/recommendations/frame"
                  data-awards-rec-quick-edit-award-list-url-value="/awards/list"
                  action="/awards/recommendations/edit/1">
                <select data-awards-rec-quick-edit-target="domain"></select>
                <input type="hidden" data-awards-rec-quick-edit-target="award" value="">
                <input type="hidden" data-awards-rec-quick-edit-target="currentAwardId" value="1">
                <input type="hidden" data-awards-rec-quick-edit-target="currentApprovalProcessId" value="10">
                <input type="hidden" data-awards-rec-quick-edit-target="approvalWorkflowRestartConfirmed" value="0">
                <textarea data-awards-rec-quick-edit-target="reason"></textarea>
                <input type="hidden" data-awards-rec-quick-edit-target="specialty" value="">
                <input type="hidden" data-awards-rec-quick-edit-target="recId" value="42">
                <input type="hidden" data-awards-rec-quick-edit-target="memberId" value="10">
                <turbo-frame data-awards-rec-quick-edit-target="turboFrame" src=""></turbo-frame>
            </form>
        `;

        controller = new RecQuickEditController();
        controller.element = document.querySelector('[data-controller="awards-rec-quick-edit"]');
        controller.domainTarget = document.querySelector('[data-awards-rec-quick-edit-target="domain"]');
        controller.awardTarget = document.querySelector('[data-awards-rec-quick-edit-target="award"]');
        controller.currentAwardIdTarget = document.querySelector('[data-awards-rec-quick-edit-target="currentAwardId"]');
        controller.currentApprovalProcessIdTarget = document.querySelector('[data-awards-rec-quick-edit-target="currentApprovalProcessId"]');
        controller.approvalWorkflowRestartConfirmedTarget = document.querySelector('[data-awards-rec-quick-edit-target="approvalWorkflowRestartConfirmed"]');
        controller.reasonTarget = document.querySelector('[data-awards-rec-quick-edit-target="reason"]');
        controller.specialtyTarget = document.querySelector('[data-awards-rec-quick-edit-target="specialty"]');
        controller.recIdTarget = document.querySelector('[data-awards-rec-quick-edit-target="recId"]');
        controller.memberIdTarget = document.querySelector('[data-awards-rec-quick-edit-target="memberId"]');
        controller.turboFrameTarget = document.querySelector('[data-awards-rec-quick-edit-target="turboFrame"]');

        controller.formUrlValue = '/awards/recommendations/edit';
        controller.turboFrameUrlValue = '/awards/recommendations/frame';
        controller.awardListUrlValue = '/awards/list';

        controller.hasCurrentAwardIdTarget = true;
        controller.hasCurrentApprovalProcessIdTarget = true;
        controller.hasApprovalWorkflowRestartConfirmedTarget = true;
        controller.hasRecIdTarget = true;
        controller.specialtyTarget.options = [];
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    test('instantiates with workflow-centric targets and values', () => {
        expect(RecQuickEditController.targets).toEqual(expect.arrayContaining([
            'domain', 'award', 'currentAwardId', 'currentApprovalProcessId',
            'approvalWorkflowRestartConfirmed', 'reason', 'specialty', 'recId',
            'memberId', 'turboFrame',
        ]));
        expect(RecQuickEditController.targets).not.toContain('state');
        expect(RecQuickEditController.targets).not.toContain('planToGiveGathering');
        expect(RecQuickEditController.values).toHaveProperty('formUrl', String);
        expect(RecQuickEditController.values).toHaveProperty('turboFrameUrl', String);
        expect(RecQuickEditController.values).not.toHaveProperty('gatheringsLookupUrl');
    });

    test('setId updates turbo frame src and form action', () => {
        controller.setId({ detail: { id: '99' } });
        expect(controller.turboFrameTarget.getAttribute('src')).toBe('/awards/recommendations/frame/99');
        expect(controller.element.getAttribute('action')).toBe('/awards/recommendations/edit/99');
    });

    test('submit prevents default when recommendation is bestowal-locked', async () => {
        const locked = document.createElement('div');
        locked.setAttribute('data-recommendation-locked', '1');
        controller.turboFrameTarget.appendChild(locked);
        const event = { preventDefault: jest.fn(), stopImmediatePropagation: jest.fn() };

        await controller.submit(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(event.stopImmediatePropagation).toHaveBeenCalled();
    });

    test('setAward sets award value and populates specialties without gathering lookup', () => {
        const specSpy = jest.spyOn(controller, 'populateSpecialties').mockImplementation(() => {});
        controller.approvalWorkflowRestartConfirmedTarget.value = '1';

        controller.setAward({ target: { dataset: { awardId: '5' } } });

        expect(controller.awardTarget.value).toBe('5');
        expect(controller.approvalWorkflowRestartConfirmedTarget.value).toBe('0');
        expect(specSpy).toHaveBeenCalled();
        expect(controller.updateGatherings).toBeUndefined();
    });

    test('populateAwardDescriptions populates options from API', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 1, name: 'Award A', specialties: ['Sword'], approval_process_id: 10 },
            ])
        }));

        controller.populateAwardDescriptions({ target: { value: 'combat' } });
        await new Promise(r => setTimeout(r, 0));

        expect(global.fetch).toHaveBeenCalledWith('/awards/list/combat?current_award_id=1', expect.any(Object));
        expect(controller.awardTarget.options.length).toBe(1);
        expect(controller.awardTarget.disabled).toBe(false);
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

    test('confirmApprovalWorkflowRestart submits after accessible confirmation', async () => {
        controller.awardTarget.value = '2';
        controller.awardTarget.options = [
            { value: '2', text: 'Award B', data: { approval_process_id: 20 } }
        ];
        const requestSubmit = jest.fn();
        const event = {
            target: { requestSubmit },
            preventDefault: jest.fn(),
            stopImmediatePropagation: jest.fn(),
        };
        window.KMP_accessibility.confirm = jest.fn(() => Promise.resolve(true));

        const handled = await controller.confirmApprovalWorkflowRestart(event);

        expect(handled).toBe(true);
        expect(controller.approvalWorkflowRestartConfirmedTarget.value).toBe('1');
        expect(requestSubmit).toHaveBeenCalled();
    });

    test('loadScaMemberInfo is a no-op', () => {
        expect(() => controller.loadScaMemberInfo({})).not.toThrow();
    });

    test('recIdTargetConnected updates form action', () => {
        controller.element.setAttribute('action', '/awards/recommendations/edit/1');
        controller.recIdTarget.value = '42';
        controller.recIdTargetConnected();
        expect(controller.element.getAttribute('action')).toBe('/awards/recommendations/edit/42');
    });
});
