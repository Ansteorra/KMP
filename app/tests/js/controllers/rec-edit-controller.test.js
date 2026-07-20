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
                  action="/awards/recommendations/edit/1">
                <div data-awards-rec-edit-target="scaMember">
                    <input type="hidden" data-ac-target="hidden" value="">
                </div>
                <input type="hidden" data-awards-rec-edit-target="notFound" value="">
                <input type="hidden" data-awards-rec-edit-target="branch" value="">
                <div data-awards-rec-edit-target="externalLinks"></div>
                <select data-awards-rec-edit-target="domain"></select>
                <input type="hidden" data-awards-rec-edit-target="award" value="">
                <input type="hidden" data-awards-rec-edit-target="currentAwardId" value="1">
                <input type="hidden" data-awards-rec-edit-target="currentApprovalProcessId" value="10">
                <input type="hidden" data-awards-rec-edit-target="approvalWorkflowRestartConfirmed" value="0">
                <textarea data-awards-rec-edit-target="reason"></textarea>
                <input type="hidden" data-awards-rec-edit-target="specialty" value="">
                <input type="hidden" data-awards-rec-edit-target="recId" value="42">
                <turbo-frame data-awards-rec-edit-target="turboFrame" src=""></turbo-frame>
            </form>
        `;

        controller = new RecEditController();
        controller.element = document.querySelector('[data-controller="awards-rec-edit"]');
        controller.scaMemberTarget = document.querySelector('[data-awards-rec-edit-target="scaMember"]');
        controller.notFoundTarget = document.querySelector('[data-awards-rec-edit-target="notFound"]');
        controller.branchTarget = document.querySelector('[data-awards-rec-edit-target="branch"]');
        controller.externalLinksTarget = document.querySelector('[data-awards-rec-edit-target="externalLinks"]');
        controller.domainTarget = document.querySelector('[data-awards-rec-edit-target="domain"]');
        controller.awardTarget = document.querySelector('[data-awards-rec-edit-target="award"]');
        controller.currentAwardIdTarget = document.querySelector('[data-awards-rec-edit-target="currentAwardId"]');
        controller.currentApprovalProcessIdTarget = document.querySelector('[data-awards-rec-edit-target="currentApprovalProcessId"]');
        controller.approvalWorkflowRestartConfirmedTarget = document.querySelector('[data-awards-rec-edit-target="approvalWorkflowRestartConfirmed"]');
        controller.reasonTarget = document.querySelector('[data-awards-rec-edit-target="reason"]');
        controller.specialtyTarget = document.querySelector('[data-awards-rec-edit-target="specialty"]');
        controller.recIdTarget = document.querySelector('[data-awards-rec-edit-target="recId"]');
        controller.turboFrameTarget = document.querySelector('[data-awards-rec-edit-target="turboFrame"]');

        controller.publicProfileUrlValue = '/members/public-profile';
        controller.awardListUrlValue = '/awards/list';
        controller.formUrlValue = '/awards/recommendations/edit';
        controller.turboFrameUrlValue = '/awards/recommendations/frame';

        controller.hasScaMemberTarget = true;
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
        expect(RecEditController.targets).toEqual(expect.arrayContaining([
            'scaMember', 'notFound', 'branch', 'externalLinks', 'domain', 'award',
            'currentAwardId', 'currentApprovalProcessId', 'approvalWorkflowRestartConfirmed',
            'reason', 'specialty', 'recId', 'turboFrame',
        ]));
        expect(RecEditController.targets).not.toContain('state');
        expect(RecEditController.targets).not.toContain('planToGiveGathering');
        expect(RecEditController.values).toHaveProperty('publicProfileUrl', String);
        expect(RecEditController.values).toHaveProperty('awardListUrl', String);
        expect(RecEditController.values).not.toHaveProperty('gatheringsUrl');
    });

    test('setId updates turbo frame src and form action', () => {
        controller.setId({ detail: { id: '99' } });
        expect(controller.turboFrameTarget.getAttribute('src')).toBe('/awards/recommendations/frame/99');
        expect(controller.element.getAttribute('action')).toBe('/awards/recommendations/edit/99');
    });

    test('setId updates nested form action when controller is mounted on modal wrapper', () => {
        document.body.innerHTML = `
            <div data-controller="awards-rec-edit">
                <form action="/awards/recommendations/edit/1">
                    <turbo-frame data-awards-rec-edit-target="turboFrame" src=""></turbo-frame>
                </form>
            </div>
        `;
        controller.element = document.querySelector('[data-controller="awards-rec-edit"]');
        controller.turboFrameTarget = document.querySelector('[data-awards-rec-edit-target="turboFrame"]');

        controller.setId({ detail: { id: '99' } });

        expect(controller.turboFrameTarget.getAttribute('src')).toBe('/awards/recommendations/frame/99');
        expect(document.querySelector('form').getAttribute('action')).toBe('/awards/recommendations/edit/99');
    });

    test('onTurboFrameLoad syncs page context after full edit form loads', () => {
        const dispatchSpy = jest.spyOn(window, 'dispatchEvent');

        controller.onTurboFrameLoad();

        expect(dispatchSpy).toHaveBeenCalledWith(expect.objectContaining({ type: 'page-context:sync' }));
    });

    test('submit enables disabled fields before submission', async () => {
        controller.notFoundTarget.disabled = true;
        controller.scaMemberTarget.disabled = true;
        controller.specialtyTarget.disabled = true;
        controller.awardTarget.value = '1';

        await controller.submit({});

        expect(controller.notFoundTarget.disabled).toBe(false);
        expect(controller.scaMemberTarget.disabled).toBe(false);
        expect(controller.specialtyTarget.disabled).toBe(false);
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

    test('setAward sets award value, clears restart confirmation, and populates specialties', () => {
        const spy = jest.spyOn(controller, 'populateSpecialties').mockImplementation(() => {});
        controller.approvalWorkflowRestartConfirmedTarget.value = '1';

        controller.setAward({ target: { dataset: { awardId: '5' } } });

        expect(controller.awardTarget.value).toBe('5');
        expect(controller.approvalWorkflowRestartConfirmedTarget.value).toBe('0');
        expect(spy).toHaveBeenCalled();
    });

    test('populateAwardDescriptions fetches and populates award options', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 1, name: 'Award A', specialties: ['Sword'], approval_process_id: 10 },
                { id: 2, name: 'Award B', specialties: null, approval_process_id: 20 },
            ])
        }));

        controller.populateAwardDescriptions({ target: { value: 'combat' } });
        await new Promise(r => setTimeout(r, 0));

        expect(global.fetch).toHaveBeenCalledWith('/awards/list/combat?current_award_id=1', expect.any(Object));
        expect(controller.awardTarget.options.length).toBe(2);
        expect(controller.awardTarget.disabled).toBe(false);
    });

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

    test('shouldConfirmApprovalWorkflowRestart detects different selected workflow', () => {
        controller.awardTarget.value = '2';
        controller.awardTarget.options = [
            { value: '2', text: 'Award B', data: { approval_process_id: 20 } }
        ];

        expect(controller.shouldConfirmApprovalWorkflowRestart()).toBe(true);
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

    test('loadScaMemberInfo loads member from autocomplete hidden value', () => {
        const spy = jest.spyOn(controller, 'loadMember').mockImplementation(() => {});
        controller.scaMemberTarget.querySelector('[data-ac-target="hidden"]').value = '123';

        controller.loadScaMemberInfo({
            detail: { value: '123', textValue: 'Known Member', selected: document.createElement('li') },
            target: controller.scaMemberTarget,
        });

        expect(controller.notFoundTarget.checked).toBe(false);
        expect(controller.branchTarget.hidden).toBe(true);
        expect(controller.branchTarget.disabled).toBe(true);
        expect(spy).toHaveBeenCalledWith(123);
    });

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

    test('recIdTargetConnected updates form action', () => {
        controller.element.setAttribute('action', '/awards/recommendations/edit/1');
        controller.recIdTarget.value = '42';
        controller.recIdTargetConnected();
        expect(controller.element.getAttribute('action')).toBe('/awards/recommendations/edit/42');
    });
});
