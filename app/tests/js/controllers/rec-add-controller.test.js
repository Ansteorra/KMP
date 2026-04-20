// Controller registers on window.Controllers (no default export)
import '../../../plugins/Awards/Assets/js/controllers/rec-add-controller.js';
const RecAddController = window.Controllers['awards-rec-add'];

describe('AwardsRecommendationAddForm', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="awards-rec-add"
                  data-awards-rec-add-public-profile-url-value="/members/public-profile"
                  data-awards-rec-add-award-list-url-value="/awards/list"
                  data-awards-rec-add-gatherings-url-value="/awards/gatherings">
                <div data-awards-rec-add-target="scaMember">
                    <input type="hidden" data-ac-target="hidden" value="">
                    <input type="hidden" data-ac-target="hiddenText" value="">
                </div>
                <input type="checkbox" data-awards-rec-add-target="notFound">
                <input type="hidden" data-awards-rec-add-target="branch" value="">
                <div data-awards-rec-add-target="externalLinks"></div>
                <div data-awards-rec-add-target="awardDescriptions"></div>
                <input type="hidden" data-awards-rec-add-target="award" value="">
                <textarea data-awards-rec-add-target="reason"></textarea>
                <div data-awards-rec-add-target="gatherings">
                    <label class="form-label">Gatherings/Events:</label>
                    <div class="form-check">
                        <input type="checkbox" name="gatherings[_ids][]" value="1" class="form-check-input">
                        <label class="form-check-label">Event 1</label>
                    </div>
                </div>
                <input type="hidden" data-awards-rec-add-target="specialty" value="">
            </form>
        `;

        controller = new RecAddController();
        controller.element = document.querySelector('[data-controller="awards-rec-add"]');

        // Wire up targets
        controller.scaMemberTarget = document.querySelector('[data-awards-rec-add-target="scaMember"]');
        controller.notFoundTarget = document.querySelector('[data-awards-rec-add-target="notFound"]');
        controller.branchTarget = document.querySelector('[data-awards-rec-add-target="branch"]');
        controller.externalLinksTarget = document.querySelector('[data-awards-rec-add-target="externalLinks"]');
        controller.awardDescriptionsTarget = document.querySelector('[data-awards-rec-add-target="awardDescriptions"]');
        controller.awardTarget = document.querySelector('[data-awards-rec-add-target="award"]');
        controller.reasonTarget = document.querySelector('[data-awards-rec-add-target="reason"]');
        controller.gatheringsTarget = document.querySelector('[data-awards-rec-add-target="gatherings"]');
        controller.specialtyTarget = document.querySelector('[data-awards-rec-add-target="specialty"]');

        // Wire up values
        controller.publicProfileUrlValue = '/members/public-profile';
        controller.awardListUrlValue = '/awards/list';
        controller.gatheringsUrlValue = '/awards/gatherings';

        // Wire up has* flags
        controller.hasScaMemberTarget = true;
        controller.hasGatheringsTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Instantiation ---

    test('instantiates with correct static targets', () => {
        expect(RecAddController.targets).toEqual(
            expect.arrayContaining([
                'scaMember', 'notFound', 'branch', 'externalLinks',
                'awardDescriptions', 'award', 'reason', 'gatherings', 'specialty',
            ])
        );
    });

    test('instantiates with correct static values', () => {
        expect(RecAddController.values).toHaveProperty('publicProfileUrl', String);
        expect(RecAddController.values).toHaveProperty('awardListUrl', String);
        expect(RecAddController.values).toHaveProperty('gatheringsUrl', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['awards-rec-add']).toBe(RecAddController);
    });

    // --- connect ---

    test('connect resets form state', () => {
        controller.notFoundTarget.checked = true;
        controller.reasonTarget.value = 'some text';
        controller.connect();

        expect(controller.notFoundTarget.checked).toBe(false);
        expect(controller.notFoundTarget.disabled).toBe(true);
        expect(controller.reasonTarget.value).toBe('');
    });

    test('connect disables gathering checkboxes', () => {
        controller.connect();
        const checkboxes = controller.gatheringsTarget.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            expect(cb.checked).toBe(false);
            expect(cb.disabled).toBe(true);
        });
    });

    // --- submit ---

    test('submit enables disabled fields', () => {
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
        const specSpy = jest.spyOn(controller, 'populateSpecialties').mockImplementation(() => {});
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.awardTarget.options = [{ value: '5', text: 'Award', data: { specialties: [] } }];

        controller.setAward({ target: { dataset: { awardId: '5' } } });

        expect(controller.awardTarget.value).toBe('5');
        expect(specSpy).toHaveBeenCalled();
    });

    test('setAward calls updateGatherings', () => {
        jest.spyOn(controller, 'populateSpecialties').mockImplementation(() => {});
        const gathSpy = jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});
        controller.awardTarget.options = [{ value: '7', text: 'Award', data: { specialties: [] } }];

        controller.setAward({ target: { dataset: { awardId: '7' } } });
        expect(gathSpy).toHaveBeenCalledWith('7');
    });

    // --- updateGatherings ---

    test('updateGatherings returns early with no awardId', () => {
        global.fetch = jest.fn();
        controller.updateGatherings(null);
        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('updateGatherings returns early without gatherings target', () => {
        global.fetch = jest.fn();
        controller.hasGatheringsTarget = false;
        controller.updateGatherings('5');
        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('updateGatherings fetches and builds checkboxes', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                gatherings: [
                    { id: 10, display: 'Event A' },
                    { id: 20, display: 'Event B' },
                ]
            })
        }));

        controller.scaMemberTarget.querySelector('[data-ac-target="hidden"]').value = '42';
        controller.updateGatherings('5');
        await new Promise(r => setTimeout(r, 0));

        expect(global.fetch).toHaveBeenCalledWith(
            '/awards/gatherings/5?member_id=42',
            expect.any(Object)
        );
        const checkboxes = controller.gatheringsTarget.querySelectorAll('input[type="checkbox"]');
        expect(checkboxes.length).toBe(2);
    });

    test('updateGatherings shows message when no gatherings', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ gatherings: [] })
        }));

        controller.scaMemberTarget.querySelector('[data-ac-target="hidden"]').value = '';
        controller.updateGatherings('5');
        await new Promise(r => setTimeout(r, 0));

        expect(controller.gatheringsTarget.querySelector('.text-muted').textContent)
            .toContain('No gatherings available');
    });

    // --- optionsForFetch ---

    test('optionsForFetch returns correct headers', () => {
        const opts = controller.optionsForFetch();
        expect(opts.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(opts.headers['Accept']).toBe('application/json');
    });

    // --- populateAwardDescriptions ---

    test('populateAwardDescriptions builds tabbed interface', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 1, name: 'Award A', description: 'Desc A', specialties: [] },
                { id: 2, name: 'Award B', description: 'Desc B', specialties: ['Sword'] },
            ])
        }));

        controller.populateAwardDescriptions({ target: { value: 'combat' } });
        await new Promise(r => setTimeout(r, 0));

        expect(global.fetch).toHaveBeenCalledWith('/awards/list/combat', expect.any(Object));
        const tabs = controller.awardDescriptionsTarget.querySelectorAll('button.nav-link');
        expect(tabs.length).toBe(2);
        expect(tabs[0].classList.contains('active')).toBe(true);
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
    });

    // --- populateSpecialties ---

    test('populateSpecialties shows specialties when present', () => {
        controller.awardTarget.value = '1';
        controller.awardTarget.options = [
            { value: '1', text: 'Award', data: { specialties: ['Sword', 'Archery'] } }
        ];
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});

        controller.populateSpecialties({ target: { value: '1' } });

        expect(controller.specialtyTarget.options.length).toBe(2);
        expect(controller.specialtyTarget.disabled).toBe(false);
        expect(controller.specialtyTarget.hidden).toBe(false);
    });

    test('populateSpecialties hides when no specialties', () => {
        controller.awardTarget.value = '1';
        controller.awardTarget.options = [
            { value: '1', text: 'Award', data: { specialties: null } }
        ];
        jest.spyOn(controller, 'updateGatherings').mockImplementation(() => {});

        controller.populateSpecialties({ target: { value: '1' } });

        expect(controller.specialtyTarget.disabled).toBe(true);
        expect(controller.specialtyTarget.hidden).toBe(true);
    });

    // --- loadScaMemberInfo ---

    test('loadScaMemberInfo loads member for valid ID', () => {
        const spy = jest.spyOn(controller, 'loadMember').mockImplementation(() => {});
        controller.loadScaMemberInfo({ target: { value: '123' } });

        expect(controller.notFoundTarget.checked).toBe(false);
        expect(controller.branchTarget.hidden).toBe(true);
        expect(controller.branchTarget.disabled).toBe(true);
        expect(spy).toHaveBeenCalledWith('123');
    });

    test('loadScaMemberInfo shows branch for empty value', () => {
        jest.spyOn(controller.branchTarget, 'focus').mockImplementation(() => {});
        controller.loadScaMemberInfo({ target: { value: '' } });

        expect(controller.notFoundTarget.checked).toBe(true);
        expect(controller.branchTarget.hidden).toBe(false);
        expect(controller.branchTarget.disabled).toBe(false);
    });

    test('loadScaMemberInfo shows branch when autocomplete has no selected member', () => {
        jest.spyOn(controller.branchTarget, 'focus').mockImplementation(() => {});

        controller.loadScaMemberInfo({
            detail: { value: 'Definitely Not In KMP', textValue: 'Definitely Not In KMP', selected: null },
            target: controller.scaMemberTarget,
        });

        expect(controller.notFoundTarget.checked).toBe(true);
        expect(controller.branchTarget.hidden).toBe(false);
        expect(controller.branchTarget.disabled).toBe(false);
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
        expect(spy).toHaveBeenCalledWith('123');
    });

    // --- loadMember ---

    test('loadMember displays external links', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                external_links: { 'OP Page': 'http://op.example.com' }
            })
        }));

        controller.loadMember('123');
        await new Promise(r => setTimeout(r, 0));

        const links = controller.externalLinksTarget.querySelectorAll('a');
        expect(links.length).toBe(1);
        expect(links[0].text).toBe('OP Page');
    });

    test('loadMember shows no links message when empty', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ external_links: {} })
        }));

        controller.loadMember('123');
        await new Promise(r => setTimeout(r, 0));

        expect(controller.externalLinksTarget.innerHTML).toContain('No links available');
    });

    // --- acConnected ---

    test('acConnected initializes branch target', () => {
        controller.acConnected({ detail: { awardsRecAddTarget: 'branch' } });
        expect(controller.branchTarget.disabled).toBe(true);
        expect(controller.branchTarget.hidden).toBe(true);
        expect(controller.branchTarget.value).toBe('');
    });

    test('acConnected initializes award target', () => {
        controller.acConnected({ detail: { awardsRecAddTarget: 'award' } });
        expect(controller.awardTarget.disabled).toBe(true);
        expect(controller.awardTarget.value).toBe('Select Award Type First');
    });

    test('acConnected initializes scaMember target', () => {
        controller.scaMemberTarget.value = 'test';
        controller.scaMemberTarget.querySelector('[data-ac-target="hidden"]').value = '123';
        controller.scaMemberTarget.querySelector('[data-ac-target="hiddenText"]').value = 'Known Member';
        controller.acConnected({ detail: { awardsRecAddTarget: 'scaMember' } });
        expect(controller.scaMemberTarget.value).toBe('');
        expect(controller.scaMemberTarget.querySelector('[data-ac-target="hidden"]').value).toBe('');
        expect(controller.scaMemberTarget.querySelector('[data-ac-target="hiddenText"]').value).toBe('');
    });

    test('acConnected initializes specialty target', () => {
        controller.acConnected({ detail: { awardsRecAddTarget: 'specialty' } });
        expect(controller.specialtyTarget.disabled).toBe(true);
        expect(controller.specialtyTarget.hidden).toBe(true);
        expect(controller.specialtyTarget.value).toBe('Select Award First');
    });

    test('acConnected handles default case', () => {
        const mockTarget = document.createElement('input');
        mockTarget.value = 'test';
        controller.acConnected({ detail: { awardsRecAddTarget: 'unknown' }, target: mockTarget });
        expect(mockTarget.value).toBe('');
    });
});
