import { Controller } from '@hotwired/stimulus';
import { createControllerScope } from '../services/controller-scope-service.js';
import { createIcon } from '../services/icon-service.js';

/** Configure the dialog on every visit; release listeners and delayed work on exit. */
class AllApprovalsDialogController extends Controller {
    static values = {
        bindings: Object
    };
    connect() {
        this.lifecycle = createControllerScope();
        const { listen } = this.lifecycle;
        const bindings = this.bindingsValue;
        // Response modal handler
        const responseModal = document.getElementById('approvalResponseModal');
        if (responseModal) {
            listen(responseModal, 'show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                if (!btn) return;
                let btnData = {};
                try {
                    btnData = JSON.parse(btn.getAttribute('data-outlet-btn-btn-data-value') || '{}');
                } catch (e) {
                    return;
                }
                const approvalId = btnData.id || 0;
                const approverConfig = btnData.approver_config || {};
                const requiredCount = btnData.required_count || 1;
                const approvedCount = btnData.approved_count || 0;
                const serialPickNext = approverConfig.serial_pick_next || false;
                const commentWarning = approverConfig.comment_warning || '';
                const feedbackResponse = approverConfig.feedback_response || false;
                const responseLabel = approverConfig.response_label || (feedbackResponse ? bindings.sendFeedback : bindings.submitResponse);
                const approveLabel = approverConfig.approve_label || 'Approve';
                const hideReject = feedbackResponse || approverConfig.hide_reject || false;
                const requiresComment = approverConfig.requires_comment || false;
                const decisionPromptLabel = approverConfig.decision_prompt_label || approverConfig.decisionPromptLabel || bindings.decision;
                const decisionOptions = Array.isArray(approverConfig.decision_options) ? approverConfig.decision_options : Array.isArray(approverConfig.decisionOptions) ? approverConfig.decisionOptions : [];
                document.getElementById('approvalResponseApprovalId').value = approvalId;
                const form = document.getElementById('approvalResponseForm');
                const modalTitle = document.getElementById('approvalResponseModalLabel');
                if (modalTitle) {
                    modalTitle.replaceChildren(createIcon(feedbackResponse ? 'message' : 'checkbox', { class: 'me-2' }),
                        document.createTextNode(feedbackResponse ? bindings.sendFeedback : bindings.respondToApproval));
                }
                const approveLabelEl = document.querySelector('label[for="decisionApprove"]');
                if (approveLabelEl) {
                    approveLabelEl.replaceChildren(createIcon('circle-check', { class: 'me-1' }),
                        document.createTextNode(approveLabel));
                }
                const rejectOption = document.getElementById('decisionReject')?.closest('.form-check');
                if (rejectOption) {
                    rejectOption.hidden = !!hideReject;
                }
                const submitBtn = form?.querySelector('[data-approval-response-target="submitBtn"]');
                if (submitBtn) {
                    submitBtn.replaceChildren(createIcon('send', { class: 'me-1' }), document.createTextNode(responseLabel));
                }
                const commentHint = form?.querySelector('[data-approval-response-target="commentRequiredHint"]');
                if (commentHint) {
                    commentHint.textContent = feedbackResponse ? bindings.required : bindings.requiredForRejections;
                }
                const commentLabel = form?.querySelector('label[for="approvalComment"]');
                if (commentLabel) {
                    const labelText = feedbackResponse ? bindings.feedback : bindings.comment;
                    commentLabel.replaceChildren(document.createTextNode(labelText + ' '));
                    if (commentHint) commentLabel.append(commentHint);
                }
                const controller = window.Stimulus?.getControllerForElementAndIdentifier(form, 'approval-response');
                if (controller) {
                    controller.configure({
                        id: approvalId,
                        serialPickNext: serialPickNext,
                        requiredCount: requiredCount,
                        approvedCount: approvedCount,
                        eligibleUrl: '/approvals/eligible-approvers/' + approvalId,
                        commentWarning: commentWarning,
                        requiresComment: requiresComment,
                        feedbackResponse: feedbackResponse,
                        decisionOptions: decisionOptions,
                        decisionPromptLabel: decisionPromptLabel,
                        approveLabel: approveLabel,
                        hideReject: hideReject,
                        defaultDecision: feedbackResponse && decisionOptions.length === 0 ? 'approve' : null,
                        requiresBestowalGathering: approverConfig.requires_bestowal_gathering || approverConfig.requiresBestowalGathering || false,
                        bestowalGatheringOptions: Array.isArray(approverConfig.bestowal_gathering_options) ? approverConfig.bestowal_gathering_options : Array.isArray(approverConfig.bestowalGatheringOptions) ? approverConfig.bestowalGatheringOptions : [],
                        bestowalGatheringUrl: approverConfig.bestowal_gathering_url || approverConfig.bestowalGatheringUrl || ''
                    });
                }
            });
        }

        // Reassign modal handler
        const reassignModal = document.getElementById('approvalReassignModal');
        if (reassignModal) {
            listen(reassignModal, 'show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                if (!btn) return;
                let btnData = {};
                try {
                    btnData = JSON.parse(btn.getAttribute('data-outlet-btn-btn-data-value') || '{}');
                } catch (e) {
                    return;
                }
                const approvalId = btnData.id || 0;
                const form = document.getElementById('approvalReassignForm');
                const controller = window.Stimulus?.getControllerForElementAndIdentifier(form, 'approval-reassign');
                if (controller) {
                    controller.configure({
                        id: approvalId,
                        eligibleUrl: '/approvals/eligible-approvers/' + approvalId + '?context=reassign'
                    });
                }
            });
        }
    }
    disconnect() {
        this.lifecycle?.dispose();
    }
}
window.Controllers ??= {};
window.Controllers['all-approvals-dialog'] = AllApprovalsDialogController;
export default AllApprovalsDialogController;
