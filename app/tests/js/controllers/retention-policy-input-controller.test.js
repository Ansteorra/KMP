// Controller registers on window.Controllers (no default export)
import '../../../plugins/Waivers/assets/js/controllers/retention-policy-input-controller.js';
const RetentionPolicyInputController = window.Controllers['retention-policy-input'];

describe('RetentionPolicyInputController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="retention-policy-input">
                <select data-retention-policy-input-target="anchorSelect">
                    <option value="gathering_end_date">Gathering End Date</option>
                    <option value="upload_date">Upload Date</option>
                    <option value="permanent">Permanent</option>
                </select>
                <div data-retention-policy-input-target="durationSection">
                    <input type="number" data-retention-policy-input-target="yearsInput" value="0">
                    <input type="number" data-retention-policy-input-target="monthsInput" value="0">
                    <input type="number" data-retention-policy-input-target="daysInput" value="0">
                </div>
                <div data-retention-policy-input-target="preview"></div>
                <input type="hidden" data-retention-policy-input-target="hiddenInput" value="">
            </div>
        `;

        controller = new RetentionPolicyInputController();
        controller.element = document.querySelector('[data-controller="retention-policy-input"]');

        // Wire up targets
        controller.anchorSelectTarget = document.querySelector('[data-retention-policy-input-target="anchorSelect"]');
        controller.yearsInputTarget = document.querySelector('[data-retention-policy-input-target="yearsInput"]');
        controller.monthsInputTarget = document.querySelector('[data-retention-policy-input-target="monthsInput"]');
        controller.daysInputTarget = document.querySelector('[data-retention-policy-input-target="daysInput"]');
        controller.durationSectionTarget = document.querySelector('[data-retention-policy-input-target="durationSection"]');
        controller.previewTarget = document.querySelector('[data-retention-policy-input-target="preview"]');
        controller.hiddenInputTarget = document.querySelector('[data-retention-policy-input-target="hiddenInput"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(RetentionPolicyInputController.targets).toEqual(
            expect.arrayContaining([
                'anchorSelect', 'yearsInput', 'monthsInput', 'daysInput',
                'durationSection', 'preview', 'hiddenInput'
            ])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['retention-policy-input']).toBe(RetentionPolicyInputController);
    });

    // --- connect ---

    test('connect calls updatePreview', () => {
        const spy = jest.spyOn(controller, 'updatePreview');
        controller.connect();
        expect(spy).toHaveBeenCalled();
    });

    // --- updatePreview ---

    test('updatePreview generates JSON for gathering_end_date with duration', () => {
        controller.anchorSelectTarget.value = 'gathering_end_date';
        controller.yearsInputTarget.value = '2';
        controller.monthsInputTarget.value = '0';
        controller.daysInputTarget.value = '0';
        controller.updatePreview();

        const json = JSON.parse(controller.hiddenInputTarget.value);
        expect(json.anchor).toBe('gathering_end_date');
        expect(json.duration.years).toBe(2);
    });

    test('updatePreview hides duration section for permanent anchor', () => {
        controller.anchorSelectTarget.value = 'permanent';
        controller.updatePreview();

        expect(controller.durationSectionTarget.style.display).toBe('none');
        const json = JSON.parse(controller.hiddenInputTarget.value);
        expect(json.anchor).toBe('permanent');
        expect(json.duration).toBeUndefined();
    });

    test('updatePreview shows duration section for non-permanent anchor', () => {
        controller.anchorSelectTarget.value = 'upload_date';
        controller.updatePreview();

        expect(controller.durationSectionTarget.style.display).toBe('block');
    });

    test('updatePreview omits zero duration values from JSON', () => {
        controller.anchorSelectTarget.value = 'gathering_end_date';
        controller.yearsInputTarget.value = '0';
        controller.monthsInputTarget.value = '6';
        controller.daysInputTarget.value = '0';
        controller.updatePreview();

        const json = JSON.parse(controller.hiddenInputTarget.value);
        expect(json.duration.months).toBe(6);
        expect(json.duration.years).toBeUndefined();
        expect(json.duration.days).toBeUndefined();
    });

    // --- formatPreviewText ---

    test('formatPreviewText returns permanent text for permanent anchor', () => {
        const text = controller.formatPreviewText('permanent', 0, 0, 0);
        expect(text).toBe('Permanent retention (never expires)');
    });

    test('formatPreviewText shows warning when no duration specified', () => {
        const text = controller.formatPreviewText('gathering_end_date', 0, 0, 0);
        expect(text).toContain('No duration specified');
    });

    test('formatPreviewText handles singular year/month/day', () => {
        const text = controller.formatPreviewText('gathering_end_date', 1, 1, 1);
        expect(text).toContain('1 year');
        expect(text).toContain('1 month');
        expect(text).toContain('1 day');
        expect(text).not.toContain('years');
    });

    test('formatPreviewText handles plural values', () => {
        const text = controller.formatPreviewText('upload_date', 2, 3, 5);
        expect(text).toContain('2 years');
        expect(text).toContain('3 months');
        expect(text).toContain('5 days');
        expect(text).toContain('from upload date');
    });

    test('formatPreviewText shows gathering end date anchor text', () => {
        const text = controller.formatPreviewText('gathering_end_date', 1, 0, 0);
        expect(text).toContain('from gathering end date');
    });

    // --- parseJson ---

    test('parseJson sets form fields from JSON string', () => {
        const json = JSON.stringify({
            anchor: 'upload_date',
            duration: { years: 1, months: 6, days: 15 }
        });
        controller.parseJson(json);

        expect(controller.anchorSelectTarget.value).toBe('upload_date');
        expect(controller.yearsInputTarget.value).toBe('1');
        expect(controller.monthsInputTarget.value).toBe('6');
        expect(controller.daysInputTarget.value).toBe('15');
    });

    test('parseJson handles permanent anchor without duration', () => {
        const json = JSON.stringify({ anchor: 'permanent' });
        controller.parseJson(json);

        expect(controller.anchorSelectTarget.value).toBe('permanent');
    });

    test('parseJson handles invalid JSON gracefully', () => {
        controller.parseJson('not-json');
        expect(controller.previewTarget.textContent).toContain('Invalid JSON format');
    });

    // --- validate ---

    test('validate returns true for permanent anchor', () => {
        controller.anchorSelectTarget.value = 'permanent';
        expect(controller.validate()).toBe(true);
    });

    test('validate returns false when no duration values specified', () => {
        controller.anchorSelectTarget.value = 'gathering_end_date';
        controller.yearsInputTarget.value = '0';
        controller.monthsInputTarget.value = '0';
        controller.daysInputTarget.value = '0';

        // Mock alert
        global.alert = jest.fn();
        expect(controller.validate()).toBe(false);
        expect(global.alert).toHaveBeenCalled();
    });

    test('validate returns true when at least one duration value is set', () => {
        controller.anchorSelectTarget.value = 'gathering_end_date';
        controller.yearsInputTarget.value = '0';
        controller.monthsInputTarget.value = '3';
        controller.daysInputTarget.value = '0';

        expect(controller.validate()).toBe(true);
    });
});
