import '../../../assets/js/controllers/member-card-profile-controller.js';
const MemberCardProfileController = window.Controllers['member-card-profile'];

describe('MemberCardProfileController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="member-card-profile"
                 data-member-card-profile-url-value="/api/member/1">
                <div data-member-card-profile-target="cardSet">
                    <div data-member-card-profile-target="firstCard" class="auth_card" style="height: 500px;">
                        <div data-member-card-profile-target="loading">Loading...</div>
                        <div data-member-card-profile-target="memberDetails" hidden>
                            <span data-member-card-profile-target="name"></span>
                            <span data-member-card-profile-target="scaName"></span>
                            <span data-member-card-profile-target="branchName"></span>
                            <span data-member-card-profile-target="membershipInfo"></span>
                            <span data-member-card-profile-target="backgroundCheck"></span>
                            <span data-member-card-profile-target="lastUpdate"></span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        controller = new MemberCardProfileController();
        controller.element = document.querySelector('[data-controller="member-card-profile"]');
        controller.cardSetTarget = document.querySelector('[data-member-card-profile-target="cardSet"]');
        controller.firstCardTarget = document.querySelector('[data-member-card-profile-target="firstCard"]');
        controller.nameTarget = document.querySelector('[data-member-card-profile-target="name"]');
        controller.scaNameTarget = document.querySelector('[data-member-card-profile-target="scaName"]');
        controller.branchNameTarget = document.querySelector('[data-member-card-profile-target="branchName"]');
        controller.membershipInfoTarget = document.querySelector('[data-member-card-profile-target="membershipInfo"]');
        controller.backgroundCheckTarget = document.querySelector('[data-member-card-profile-target="backgroundCheck"]');
        controller.lastUpdateTarget = document.querySelector('[data-member-card-profile-target="lastUpdate"]');
        controller.loadingTarget = document.querySelector('[data-member-card-profile-target="loading"]');
        controller.memberDetailsTarget = document.querySelector('[data-member-card-profile-target="memberDetails"]');
        controller.urlValue = '/api/member/1';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) delete global.fetch;
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(MemberCardProfileController.targets).toEqual(expect.arrayContaining([
            'cardSet', 'firstCard', 'name', 'scaName', 'branchName',
            'membershipInfo', 'backgroundCheck', 'lastUpdate', 'loading', 'memberDetails'
        ]));
    });

    test('has correct static values', () => {
        expect(MemberCardProfileController.values).toHaveProperty('url', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['member-card-profile']).toBe(MemberCardProfileController);
    });

    // --- initialize ---

    test('initialize sets default state', () => {
        controller.initialize();
        expect(controller.currentCard).toBeNull();
        expect(controller.cardCount).toBe(1);
        expect(controller.maxCardLength).toBe(0);
    });

    // --- optionsForFetch ---

    test('optionsForFetch returns correct headers', () => {
        const options = controller.optionsForFetch();
        expect(options.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(options.headers['Accept']).toBe('application/json');
    });

    // --- startCard ---

    test('startCard creates new card and appends to cardSet', () => {
        controller.initialize();
        controller.cardCount = 1;
        controller.startCard();

        expect(controller.cardCount).toBe(2);
        const newCard = document.getElementById('card_2');
        expect(newCard).toBeTruthy();
        expect(newCard.classList.contains('auth_card')).toBe(true);
        expect(controller.currentCard.id).toBe('cardDetails_2');
    });

    // --- usedSpaceInCard ---

    test('usedSpaceInCard sums child heights', () => {
        controller.initialize();
        // Create a mock card with children
        const div = document.createElement('div');
        const child1 = document.createElement('div');
        const child2 = document.createElement('div');
        // offsetHeight is 0 in jsdom, so test returns 0
        div.appendChild(child1);
        div.appendChild(child2);
        controller.currentCard = div;

        const result = controller.usedSpaceInCard();
        expect(result).toBe(0); // jsdom offsetHeight is always 0
    });

    // --- appendToCard ---

    test('appendToCard adds element to current card', () => {
        controller.initialize();
        controller.maxCardLength = 1000;
        const mockCard = document.createElement('div');
        controller.currentCard = mockCard;

        const element = document.createElement('p');
        element.textContent = 'Test';

        controller.appendToCard(element, null);

        expect(mockCard.contains(element)).toBe(true);
    });

    // --- loadCard with member data ---

    test('loadCard populates member data from API', async () => {
        const memberData = {
            member: {
                first_name: 'John',
                last_name: 'Doe',
                sca_name: 'Sir John',
                branch: { name: 'Stargate' },
                membership_number: '12345',
                membership_expires_on: '2099-12-31',
                background_check_expires_on: '2099-12-31'
            }
        };

        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve(memberData)
        });

        controller.loadCard();

        // Wait for fetch to resolve
        await new Promise(r => setTimeout(r, 0));

        expect(controller.nameTarget.textContent).toBe('John Doe');
        expect(controller.scaNameTarget.textContent).toBe('Sir John');
        expect(controller.branchNameTarget.textContent).toBe('Stargate');
        expect(controller.membershipInfoTarget.textContent).toContain('12345');
        expect(controller.loadingTarget.hidden).toBe(true);
        expect(controller.memberDetailsTarget.hidden).toBe(false);
    });

    test('loadCard shows no membership info when no number', async () => {
        const memberData = {
            member: {
                first_name: 'Jane',
                last_name: 'Doe',
                sca_name: 'Lady Jane',
                branch: { name: 'Test' },
                membership_number: '',
                background_check_expires_on: null
            }
        };

        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve(memberData)
        });

        controller.loadCard();
        await new Promise(r => setTimeout(r, 0));

        expect(controller.membershipInfoTarget.textContent).toBe('No Membership Info');
        expect(controller.backgroundCheckTarget.textContent).toBe('No Background Check');
    });

    test('loadCard shows expired membership', async () => {
        const memberData = {
            member: {
                first_name: 'Test',
                last_name: 'User',
                sca_name: 'Tester',
                branch: { name: 'Test' },
                membership_number: '999',
                membership_expires_on: '2020-01-01',
                background_check_expires_on: '2020-01-01'
            }
        };

        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve(memberData)
        });

        controller.loadCard();
        await new Promise(r => setTimeout(r, 0));

        expect(controller.membershipInfoTarget.textContent).toContain('Expired');
    });

    // --- connect ---

    test('connect calls loadCard', () => {
        const loadSpy = jest.spyOn(controller, 'loadCard').mockImplementation(() => {});
        controller.connect();
        expect(loadSpy).toHaveBeenCalled();
    });
});
