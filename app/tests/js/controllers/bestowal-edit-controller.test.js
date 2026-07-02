import '../../../plugins/Awards/Assets/js/controllers/bestowal-edit-controller.js';

const BestowalEditController = window.Controllers['awards-bestowal-edit'];

describe('AwardsBestowalEditForm', () => {
    let controller;

    beforeEach(() => {
        document.body.replaceChildren();
        const root = document.createElement('div');
        root.id = 'bestowal_edit_root';
        root.setAttribute('data-controller', 'awards-bestowal-edit');
        root.dataset.awardsBestowalEditFormUrlValue = '/awards/bestowals/edit';
        root.dataset.awardsBestowalEditTurboFrameUrlValue = '/awards/bestowals/turbo-edit-form';
        root.dataset.awardsBestowalEditAwardListUrlValue = '/awards/awards-by-domain';
        root.dataset.awardsBestowalEditGatheringsLookupUrlValue = '/awards/bestowals/gatherings-for-bestowal-auto-complete';
        root.dataset.awardsBestowalEditModalIdValue = 'editBestowalModal';

        const form = document.createElement('form');
        form.id = 'bestowal_form';
        form.setAttribute('action', '/awards/bestowals/edit');

        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.id = 'editBestowalModal';
        const frame = document.createElement('turbo-frame');
        frame.id = 'editBestowalQuick';
        frame.setAttribute('data-awards-bestowal-edit-target', 'turboFrame');
        modal.appendChild(frame);
        root.appendChild(modal);

        const domain = document.createElement('div');
        domain.setAttribute('data-awards-bestowal-edit-target', 'domain');
        const domainHidden = document.createElement('input');
        domainHidden.type = 'hidden';
        domainHidden.name = 'domain_id';
        domainHidden.setAttribute('data-ac-target', 'hidden');
        domainHidden.value = '1';
        domain.appendChild(domainHidden);
        form.appendChild(domain);

        const award = document.createElement('div');
        award.setAttribute('data-awards-bestowal-edit-target', 'award');
        const awardHidden = document.createElement('input');
        awardHidden.type = 'hidden';
        awardHidden.name = 'award_id';
        awardHidden.setAttribute('data-ac-target', 'hidden');
        awardHidden.value = '2';
        award.appendChild(awardHidden);
        form.appendChild(award);

        const currentAwardId = document.createElement('input');
        currentAwardId.type = 'hidden';
        currentAwardId.setAttribute('data-awards-bestowal-edit-target', 'currentAwardId');
        currentAwardId.value = '2';
        form.appendChild(currentAwardId);

        const submit = document.createElement('button');
        submit.type = 'submit';
        submit.id = 'bestowal_submit';
        submit.setAttribute('data-awards-bestowal-edit-target', 'submitButton');
        submit.disabled = true;
        form.appendChild(submit);

        root.appendChild(form);
        document.body.appendChild(root);

        controller = new BestowalEditController();
        controller.element = root;
        controller.domainTarget = domain;
        controller.awardTarget = award;
        controller.currentAwardIdTarget = currentAwardId;
        controller.turboFrameTarget = frame;
        controller.submitButtonTarget = submit;
        controller.formUrlValue = '/awards/bestowals/edit';
        controller.turboFrameUrlValue = '/awards/bestowals/turbo-edit-form';
        controller.awardListUrlValue = '/awards/awards-by-domain';
        controller.gatheringsLookupUrlValue = '/awards/bestowals/gatherings-for-bestowal-auto-complete';
        controller.modalIdValue = 'editBestowalModal';
        controller.hasDomainTarget = true;
        controller.hasAwardTarget = true;
        controller.hasCurrentAwardIdTarget = true;
        controller.hasTurboFrameTarget = true;
        controller.hasSubmitButtonTarget = true;
        controller.hasAwardListUrlValue = true;
        controller.hasGatheringsLookupUrlValue = true;
    });

    test('hasValidAwardSelection requires both domain and award', () => {
        expect(controller.hasValidAwardSelection()).toBe(true);
        controller.domainTarget.querySelector('[data-ac-target="hidden"]').value = '';
        expect(controller.hasValidAwardSelection()).toBe(false);
    });

    test('updateSubmitState enables submit when award pair is valid', () => {
        controller.updateSubmitState();
        expect(controller.submitButtonTarget.disabled).toBe(false);
    });

    test('updateSubmitState requires member when member target is present', () => {
        const member = document.createElement('div');
        member.setAttribute('data-awards-bestowal-edit-target', 'member');
        const memberHidden = document.createElement('input');
        memberHidden.type = 'hidden';
        memberHidden.name = 'member_public_id';
        memberHidden.setAttribute('data-ac-target', 'hidden');
        member.appendChild(memberHidden);
        controller.element.appendChild(member);
        controller.memberTarget = member;
        controller.hasMemberTarget = true;

        controller.updateSubmitState();
        expect(controller.submitButtonTarget.disabled).toBe(true);

        memberHidden.value = 'abc123';
        controller.updateSubmitState();
        expect(controller.submitButtonTarget.disabled).toBe(false);
    });

    test('loadBestowalForm sets turbo frame src', () => {
        controller.loadBestowalForm(42);
        expect(controller.turboFrameTarget.src).toContain('/awards/bestowals/turbo-edit-form/42');
        expect(controller.submitButtonTarget.disabled).toBe(true);
    });

    test('loadBestowalForm ignores duplicate in-flight frame loads', () => {
        controller.loadBestowalForm(42);
        const loading = controller.turboFrameTarget.querySelector('.text-center');
        loading.textContent = 'Still loading';

        controller.loadBestowalForm(42);

        expect(controller.turboFrameTarget.querySelector('.text-center').textContent).toBe('Still loading');
        expect(controller.turboFrameTarget.dataset.bestowalEditLoadingId).toBe('42');
    });

    test('onTurboFrameLoad clears duplicate-load guard', () => {
        controller.loadBestowalForm(42);
        controller.onTurboFrameLoad();

        expect(controller.turboFrameTarget.dataset.bestowalEditLoadingId).toBeUndefined();
    });

    test('handleModalShown does not reload turbo frame', () => {
        const reload = jest.fn();
        controller.turboFrameTarget.src = '/awards/bestowals/turbo-edit-form/42';
        controller.turboFrameTarget.reload = reload;
        controller.handleModalShown();
        expect(reload).not.toHaveBeenCalled();
    });

    test('onDomainChange clears paired award when domain is cleared', () => {
        controller.domainTarget.querySelector('[data-ac-target="hidden"]').value = '';
        controller.onDomainChange();
        expect(controller.awardTarget.querySelector('[data-ac-target="hidden"]').value).toBe('');
        expect(controller.submitButtonTarget.disabled).toBe(true);
    });

    test('updateGatherings builds add-form lookup without bestowal id', () => {
        const gathering = document.createElement('div');
        gathering.setAttribute('data-awards-bestowal-edit-target', 'planToGiveGathering');
        const gatheringHidden = document.createElement('input');
        gatheringHidden.type = 'hidden';
        gatheringHidden.setAttribute('data-ac-target', 'hidden');
        gathering.appendChild(gatheringHidden);
        controller.element.appendChild(gathering);
        controller.planToGiveGatheringTarget = gathering;
        controller.hasPlanToGiveGatheringTarget = true;

        const member = document.createElement('div');
        member.setAttribute('data-awards-bestowal-edit-target', 'member');
        const memberHidden = document.createElement('input');
        memberHidden.type = 'hidden';
        memberHidden.name = 'member_public_id';
        memberHidden.setAttribute('data-ac-target', 'hidden');
        memberHidden.value = 'public-member-id';
        member.appendChild(memberHidden);
        controller.element.appendChild(member);
        controller.memberTarget = member;
        controller.hasMemberTarget = true;

        controller.updateGatherings();

        expect(gathering.getAttribute('data-ac-url-value'))
            .toBe('/awards/bestowals/gatherings-for-bestowal-auto-complete?award_id=2&member_public_id=public-member-id');
    });

    test('include past toggle appends lookup flag and clears stale gathering selection', () => {
        const gathering = document.createElement('div');
        gathering.setAttribute('data-awards-bestowal-edit-target', 'planToGiveGathering');
        const input = document.createElement('input');
        input.setAttribute('data-ac-target', 'input');
        input.value = 'Future Court';
        input.disabled = true;
        const hidden = document.createElement('input');
        hidden.setAttribute('data-ac-target', 'hidden');
        hidden.value = '7';
        const hiddenText = document.createElement('input');
        hiddenText.setAttribute('data-ac-target', 'hiddenText');
        hiddenText.value = 'Future Court';
        const clearBtn = document.createElement('button');
        clearBtn.setAttribute('data-ac-target', 'clearBtn');
        clearBtn.disabled = false;
        gathering.append(input, hidden, hiddenText, clearBtn);
        controller.element.appendChild(gathering);
        controller.planToGiveGatheringTarget = gathering;
        controller.hasPlanToGiveGatheringTarget = true;

        const includePast = document.createElement('input');
        includePast.type = 'checkbox';
        includePast.checked = true;
        includePast.setAttribute('data-awards-bestowal-edit-target', 'includePastGatherings');
        controller.element.appendChild(includePast);
        controller.includePastGatheringsTarget = includePast;
        controller.hasIncludePastGatheringsTarget = true;

        controller.onIncludePastGatheringsChange();

        expect(gathering.getAttribute('data-ac-url-value'))
            .toBe('/awards/bestowals/gatherings-for-bestowal-auto-complete?award_id=2&include_past=1');
        expect(hidden.value).toBe('');
        expect(input.value).toBe('');
        expect(input.disabled).toBe(false);
        expect(hiddenText.value).toBe('');
        expect(clearBtn.disabled).toBe(true);
    });

    test('extractBestowalIdFromTrigger reads outlet button payload', () => {
        const trigger = document.createElement('button');
        trigger.setAttribute('data-outlet-btn-btn-data-value', '{"id":99}');
        expect(controller.extractBestowalIdFromTrigger(trigger)).toBe(99);
    });

    test('resolveDefaultBestowedDate prefers court session over gathering start', () => {
        controller._gatheringStartDate = '2026-06-01';
        controller._courtOptionDates = { 5: '2026-06-02' };
        const courtSlot = document.createElement('select');
        courtSlot.setAttribute('data-awards-bestowal-edit-target', 'courtSlot');
        const option = document.createElement('option');
        option.value = '5';
        option.selected = true;
        courtSlot.appendChild(option);
        controller.element.appendChild(courtSlot);
        controller.courtSlotTarget = courtSlot;
        controller.hasCourtSlotTarget = true;

        expect(controller.resolveDefaultBestowedDate()).toBe('2026-06-02');
    });

    test('applyDefaultGivenDate sets gathering start when Given and field empty', () => {
        const state = document.createElement('select');
        state.setAttribute('data-awards-bestowal-edit-target', 'state');
        const givenOption = document.createElement('option');
        givenOption.value = 'Given';
        givenOption.selected = true;
        state.appendChild(givenOption);
        controller.element.appendChild(state);
        controller.stateTarget = state;
        controller.hasStateTarget = true;

        const givenDate = document.createElement('input');
        givenDate.type = 'date';
        givenDate.setAttribute('data-awards-bestowal-edit-target', 'givenDate');
        controller.element.appendChild(givenDate);
        controller.givenDateTarget = givenDate;
        controller.hasGivenDateTarget = true;

        controller._gatheringStartDate = '2026-06-01';
        controller.applyDefaultGivenDate();

        expect(givenDate.value).toBe('2026-06-01');
        expect(givenDate.dataset.autoValue).toBe('2026-06-01');
    });

    test('applyDefaultGivenDate does not overwrite manual user edits', () => {
        const state = document.createElement('select');
        state.setAttribute('data-awards-bestowal-edit-target', 'state');
        const givenOption = document.createElement('option');
        givenOption.value = 'Given';
        givenOption.selected = true;
        state.appendChild(givenOption);
        controller.element.appendChild(state);
        controller.stateTarget = state;
        controller.hasStateTarget = true;

        const givenDate = document.createElement('input');
        givenDate.type = 'date';
        givenDate.value = '2026-06-03';
        givenDate.dataset.userEdited = 'true';
        givenDate.dataset.autoValue = '2026-06-01';
        givenDate.setAttribute('data-awards-bestowal-edit-target', 'givenDate');
        controller.element.appendChild(givenDate);
        controller.givenDateTarget = givenDate;
        controller.hasGivenDateTarget = true;

        controller._gatheringStartDate = '2026-06-01';
        controller.applyDefaultGivenDate();

        expect(givenDate.value).toBe('2026-06-03');
    });
});
