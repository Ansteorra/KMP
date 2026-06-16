import '../../../assets/js/controllers/approval-response-controller.js';

const ApprovalResponseController = window.Controllers['approval-response'];

describe('ApprovalResponseController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form>
                <div data-approval-response-target="decisionSection">
                    <legend data-approval-response-target="decisionLegend">Decision</legend>
                    <div data-approval-response-target="decisionOptions">
                        <input type="radio" name="decision" value="approve" data-approval-response-target="decision">
                        <input type="radio" name="decision" value="reject" data-approval-response-target="decision">
                    </div>
                </div>
                <textarea data-approval-response-target="comment"></textarea>
                <input type="hidden" data-approval-response-target="approvalIds">
                <div data-approval-response-target="bulkSummary" hidden></div>
                <span data-approval-response-target="commentRequiredHint" hidden></span>
                <div data-approval-response-target="commentWarning" hidden>
                    <span data-approval-response-target="commentWarningText"></span>
                </div>
                <div data-approval-response-target="nextApproverSection" hidden>
                    <input type="hidden" data-approval-response-target="nextApproverInput">
                    <div data-approval-response-target="infoText"></div>
                </div>
                <button type="submit" data-approval-response-target="submitBtn" disabled>Submit</button>
            </form>
        `;
        controller = new ApprovalResponseController();
        const target = selector => document.querySelector(selector);
        const targets = selector => Array.from(document.querySelectorAll(selector));
        Object.defineProperties(controller, {
            decisionTargets: { get: () => targets('[data-approval-response-target="decision"]') },
            decisionSectionTarget: { get: () => target('[data-approval-response-target="decisionSection"]') },
            hasDecisionSectionTarget: { get: () => true },
            decisionLegendTarget: { get: () => target('[data-approval-response-target="decisionLegend"]') },
            hasDecisionLegendTarget: { get: () => true },
            decisionOptionsTarget: { get: () => target('[data-approval-response-target="decisionOptions"]') },
            hasDecisionOptionsTarget: { get: () => true },
            commentTarget: { get: () => target('[data-approval-response-target="comment"]') },
            hasCommentTarget: { get: () => true },
            approvalIdsTarget: { get: () => target('[data-approval-response-target="approvalIds"]') },
            hasApprovalIdsTarget: { get: () => true },
            bulkSummaryTarget: { get: () => target('[data-approval-response-target="bulkSummary"]') },
            hasBulkSummaryTarget: { get: () => true },
            commentRequiredHintTarget: { get: () => target('[data-approval-response-target="commentRequiredHint"]') },
            hasCommentRequiredHintTarget: { get: () => true },
            commentWarningTarget: { get: () => target('[data-approval-response-target="commentWarning"]') },
            hasCommentWarningTarget: { get: () => true },
            commentWarningTextTarget: { get: () => target('[data-approval-response-target="commentWarningText"]') },
            hasCommentWarningTextTarget: { get: () => true },
            nextApproverSectionTarget: { get: () => target('[data-approval-response-target="nextApproverSection"]') },
            hasNextApproverSectionTarget: { get: () => true },
            nextApproverInputTarget: { get: () => target('[data-approval-response-target="nextApproverInput"]') },
            hasNextApproverInputTarget: { get: () => true },
            submitBtnTarget: { get: () => target('[data-approval-response-target="submitBtn"]') },
            hasSubmitBtnTarget: { get: () => true },
            infoTextTarget: { get: () => target('[data-approval-response-target="infoText"]') },
            hasInfoTextTarget: { get: () => true },
        });
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('hides decision controls and requires comments for feedback responses', () => {
        const form = document.querySelector('form');

        controller.configure({
            id: 14,
            defaultDecision: 'approve',
            feedbackResponse: true,
            requiresComment: true,
            requiredCount: 1,
            approvedCount: 0,
        });

        expect(form.querySelector('[data-approval-response-target="decisionSection"]')).toHaveAttribute('hidden');
        expect(form.querySelector('input[value="approve"]')).toBeChecked();
        expect(form.querySelector('textarea')).toBeRequired();
        expect(form.querySelector('textarea')).toHaveAttribute('placeholder', 'Feedback is required...');
        expect(form.querySelector('[data-approval-response-target="submitBtn"]')).not.toBeDisabled();
        expect(form.querySelector('[data-approval-response-target="nextApproverSection"]')).toHaveAttribute('hidden');
    });

    test('shows decision controls for standard approvals', () => {
        const form = document.querySelector('form');

        controller.configure({
            id: 15,
            feedbackResponse: false,
            requiredCount: 1,
            approvedCount: 0,
        });

        expect(form.querySelector('[data-approval-response-target="decisionSection"]')).not.toHaveAttribute('hidden');
        expect(form.querySelector('[data-approval-response-target="submitBtn"]')).toBeDisabled();
        expect(form.querySelector('textarea')).not.toBeRequired();
    });

    test('stores bulk approval IDs and shows the bulk summary', () => {
        const form = document.querySelector('form');

        controller.configure({
            id: 21,
            bulkIds: ['21', '22', '23'],
            feedbackResponse: false,
            requiredCount: 1,
            approvedCount: 0,
            defaultDecision: 'approve',
        });

        expect(form.querySelector('[data-approval-response-target="approvalIds"]').value).toBe('21,22,23');
        expect(form.querySelector('[data-approval-response-target="bulkSummary"]')).not.toHaveAttribute('hidden');
        expect(form.querySelector('[data-approval-response-target="bulkSummary"]')).toHaveTextContent(
            'This response will be applied to 3 selected approvals.',
        );
        expect(form.querySelector('[data-approval-response-target="submitBtn"]')).not.toBeDisabled();
    });

    test('blocks submit when bulk selection mixes approval types', () => {
        const form = document.querySelector('form');

        controller.configure({
            id: 21,
            bulkIds: ['21', '22'],
            bulkError: 'Select approvals of the same type before responding in bulk.',
            feedbackResponse: false,
            requiredCount: 1,
            approvedCount: 0,
            defaultDecision: 'approve',
        });

        expect(form.querySelector('[data-approval-response-target="approvalIds"]').value).toBe('21,22');
        expect(form.querySelector('[data-approval-response-target="bulkSummary"]')).toHaveClass('alert-danger');
        expect(form.querySelector('[data-approval-response-target="submitBtn"]')).toBeDisabled();
    });

    test('renders configured decision options for feedback responses', () => {
        const form = document.querySelector('form');

        controller.configure({
            id: 16,
            feedbackResponse: true,
            requiresComment: false,
            decisionPromptLabel: 'Your View',
            decisionOptions: [
                { value: 'support', label: 'Support' },
                { value: 'oppose', label: 'Oppose' },
                { value: 'indifferent', label: 'Indifferent' },
            ],
        });

        expect(form.querySelector('[data-approval-response-target="decisionSection"]')).not.toHaveAttribute('hidden');
        expect(form.querySelector('[data-approval-response-target="decisionLegend"]')).toHaveTextContent('Your View');
        expect(form.querySelector('input[value="support"]')).not.toBeNull();
        expect(form.querySelector('input[value="oppose"]')).not.toBeNull();
        expect(form.querySelector('input[value="indifferent"]')).not.toBeNull();
        expect(form.querySelector('[data-approval-response-target="submitBtn"]')).toBeDisabled();

        form.querySelector('input[value="support"]').checked = true;
        controller.onDecisionChange();

        expect(form.querySelector('[data-approval-response-target="submitBtn"]')).not.toBeDisabled();
        expect(form.querySelector('textarea')).not.toBeRequired();
    });
});
