import GatheringWizardController from '../../../assets/js/controllers/gathering-wizard-controller.js';

describe('Gathering creation wizard', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form>
                <nav hidden>
                    <button type="button">Basics</button><button type="button">When and where</button>
                    <button type="button">Public page</button><button type="button">Review</button>
                </nav>
                <section id="basics"><h2 tabindex="-1">Basics</h2><input name="name" required></section>
                <section id="when"><h2 tabindex="-1">When and where</h2>
                    <input type="datetime-local" name="start_date" required value="2027-02-10T10:00">
                    <input type="datetime-local" name="end_date">
                </section>
                <section id="public"><h2 tabindex="-1">Public page</h2>
                    <label><input type="radio" name="website_destination" value="kmp" checked>Hosted page</label>
                    <label><input type="radio" name="website_destination" value="external">External</label>
                    <div id="external">
                        <input type="url" name="website_url">
                        <input type="checkbox" name="public_page_enabled">
                    </div>
                </section>
                <section id="review" hidden><h2 tabindex="-1">Review</h2>
                    <dd data-field="name"></dd><dd data-field="website_destination"></dd>
                    <dd data-field="public_page_enabled"></dd>
                </section>
                <button type="button" id="previous" hidden>Previous step</button>
                <button type="button" id="next" hidden>Continue</button>
                <button type="submit" id="create">Create Gathering</button>
            </form>`;
        controller = new GatheringWizardController();
        controller.element = document.querySelector('form');
        controller.panelTargets = [...document.querySelectorAll('section')];
        controller.navigationTarget = document.querySelector('nav');
        controller.stepButtonTargets = [...document.querySelectorAll('nav button')];
        controller.previousButtonTarget = document.getElementById('previous');
        controller.nextButtonTarget = document.getElementById('next');
        controller.submitButtonTarget = document.getElementById('create');
        controller.externalFieldsTarget = document.getElementById('external');
        controller.reviewTargets = [...document.querySelectorAll('dd')];
        controller.endErrorValue = 'End must be after start.';
        controller.yesValue = 'Yes';
        controller.noValue = 'No';
    });

    afterEach(() => {
        controller.disconnect();
        document.body.innerHTML = '';
    });

    test('validates the active step and moves keyboard focus on Continue and Previous', () => {
        controller.connect();
        expect(controller.panelTargets[1].hidden).toBe(true);
        controller.next();
        expect(controller.field('name')).toHaveFocus();
        controller.field('name').value = 'Fighter practice';
        controller.next();
        expect(controller.panelTargets[1].querySelector('h2')).toHaveFocus();
        expect(controller.stepButtonTargets[1]).toHaveAttribute('aria-current', 'step');

        const end = controller.field('end_date');
        end.value = '2027-02-10T09:00';
        controller.next();
        expect(end).toHaveFocus();
        end.value = '2027-02-10T11:00';
        controller.previous();
        expect(controller.field('name')).toHaveValue('Fighter practice');
    });

    test('requires an external URL, shows it on review, and submits the original form fields', () => {
        controller.connect();
        controller.field('name').value = 'Fighter practice';
        controller.next();
        controller.next();
        expect(controller.externalFieldsTarget.hidden).toBe(true);
        controller.element.querySelector('[value="external"]').checked = true;
        controller.updateWebsite();
        const url = controller.field('website_url');
        expect(url.required).toBe(true);
        controller.next();
        expect(url).toHaveFocus();
        url.value = 'https://example.test/practice';
        controller.next();
        expect(controller.reviewTargets[1]).toHaveTextContent(url.value);
        expect(controller.reviewTargets[2]).toHaveTextContent('No');
        expect(controller.submitButtonTarget.hidden).toBe(false);
        expect(new FormData(controller.element).get('name')).toBe('Fighter practice');
        const event = { preventDefault: jest.fn() };
        controller.submit(event);
        expect(event.preventDefault).not.toHaveBeenCalled();
    });

    test('opens the server error step and opens an invalid optional section', () => {
        controller.panelTargets[2].insertAdjacentHTML('beforeend',
            '<details><summary>Optional</summary><div class="invalid-feedback">Bad URL</div></details>');
        controller.errorStepValue = 3;
        controller.connect();
        expect(controller.panelTargets[2].querySelector('h2')).toHaveFocus();
        expect(controller.panelTargets[2].querySelector('details').open).toBe(true);
        expect(controller.submitButtonTarget.hidden).toBe(true);
    });

    test('requires a committed hosting group and shows its name on review', () => {
        controller.panelTargets[0].insertAdjacentHTML('beforeend', `
            <fieldset data-gathering-form-target="branchAutocomplete">
                <input type="hidden" name="branch_id">
                <input name="branch_name-Disp" role="combobox" required value="Typed group">
            </fieldset>
        `);
        controller.reviewTargets[0].insertAdjacentHTML('afterend', '<dd data-field="branch_id"></dd>');
        const groupReview = controller.element.querySelector('[data-field="branch_id"]');
        controller.reviewTargets.push(groupReview);
        controller.hostingGroupErrorValue = 'Select a hosting group from the list.';
        controller.connect();
        controller.field('name').value = 'Fighter practice';

        const group = controller.field('branch_id');
        controller.next();
        expect(group).toHaveFocus();
        expect(group.validationMessage).toBe(controller.hostingGroupErrorValue);

        controller.element.querySelector('input[name="branch_id"]').value = '39';
        group.value = 'Selected group';
        group.disabled = true;
        controller.next();
        controller.next();
        controller.next();
        expect(groupReview).toHaveTextContent('Selected group');
        expect(new FormData(controller.element).get('branch_id')).toBe('39');
    });
});
