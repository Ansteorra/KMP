import { Controller } from '@hotwired/stimulus';
import { createControllerScope } from '../../../../../assets/js/services/controller-scope-service.js';

/** Configure the dialog on every visit; release listeners and delayed work on exit. */
class AwardsWorkflowDecisionController extends Controller {
    static values = {
        bindings: Object
    };
    connect() {
        this.lifecycle = createControllerScope();
        const { listen, setTimeout } = this.lifecycle;
        const bindings = this.bindingsValue;
        const modal = document.getElementById('recommendationWorkflowDecisionModal');
        const form = document.getElementById('recommendationWorkflowDecisionForm');
        if (!modal || !form) return;
        listen(document, 'outlet-btn:notice', function (event) {
            if (event.target?.dataset?.bulkActionKey === 'workflow-decision') {
                event.target.dataset.workflowDecisionSelection = JSON.stringify(event.detail || {});
            }
        });
        const configureApprovalController = function (config) {
            const controller = window.Stimulus?.getControllerForElementAndIdentifier(form, 'approval-response');
            if (controller) {
                controller.configure(config);
            }
        };
        const setNotice = function (elementId, message) {
            const element = document.getElementById(elementId);
            if (!element) return;
            element.textContent = message || '';
            element.hidden = !message;
        };
        const renderBulkApprovalInputs = function (approvalIds) {
            const container = document.getElementById('recommendationWorkflowDecisionBulkApprovalIds');
            if (!container) return;
            container.innerHTML = '';
            approvalIds.forEach(function (approvalId) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'approval_ids[]';
                input.value = approvalId;
                container.appendChild(input);
            });
        };
        const configureBulkDecision = function (btn) {
            let selection = {};
            try {
                selection = JSON.parse(btn.dataset.workflowDecisionSelection || btn.dataset.bulkActionSelection || '{}');
            } catch (e) {
                selection = {};
            }
            const selectedRows = Array.isArray(selection.checkboxes) ? selection.checkboxes : [];
            const approvalIds = [];
            let skipped = 0;
            selectedRows.forEach(function (row) {
                if (row.pendingApprovalId) {
                    approvalIds.push(row.pendingApprovalId);
                } else {
                    skipped += 1;
                }
            });
            form.action = form.dataset.bulkDecisionUrl;
            form.dataset.workflowDecisionMode = 'bulk';
            form.dataset.workflowDecisionBulkCount = String(approvalIds.length);
            document.getElementById('recommendationWorkflowDecisionApprovalId').value = '';
            document.getElementById('recommendationWorkflowDecisionRecommendationId').value = '';
            renderBulkApprovalInputs(approvalIds);
            document.getElementById('recommendationWorkflowDecisionModalTitle').textContent = bindings.respondToSelectedRecommendations;
            setNotice('recommendationWorkflowDecisionSelectionNotice', approvalIds.length === 1 ? bindings.oneSelectedRecommendationIsPendingYourApproval : approvalIds.length + (" " + bindings.selectedRecommendationsArePendingYourApproval));
            setNotice('recommendationWorkflowDecisionSkippedNotice', skipped === 0 ? '' : skipped + (" " + bindings.selectedRecommendationSAreNotPendingYour));
            configureApprovalController({
                id: 0,
                serialPickNext: false,
                requiredCount: 1,
                approvedCount: 0,
                eligibleUrl: '',
                commentWarning: '',
                requiresComment: false,
                feedbackResponse: false,
                decisionOptions: [],
                decisionPromptLabel: bindings.decision,
                approveLabel: bindings.approve,
                hideReject: false,
                defaultDecision: null,
                requiresBestowalGathering: false,
                bestowalGatheringOptions: [],
                bestowalGatheringUrl: ''
            });
        };
        const configureSingleDecision = function (btn) {
            let btnData = {};
            try {
                btnData = JSON.parse(btn.getAttribute('data-outlet-btn-btn-data-value') || '{}');
            } catch (e) {
                btnData = {};
            }
            const approvalId = btnData.approvalId || btnData.id || 0;
            const recommendationId = btnData.id || 0;
            const approverConfig = btnData.approverConfig || btnData.approver_config || {};
            const requiredCount = btnData.requiredCount || btnData.required_count || 1;
            const approvedCount = btnData.approvedCount || btnData.approved_count || 0;
            const feedbackResponse = approverConfig.feedback_response || false;
            const decisionOptions = Array.isArray(approverConfig.decision_options) ? approverConfig.decision_options : Array.isArray(approverConfig.decisionOptions) ? approverConfig.decisionOptions : [];
            form.action = form.dataset.singleDecisionUrl;
            form.dataset.workflowDecisionMode = 'single';
            form.dataset.workflowDecisionBulkCount = '0';
            document.getElementById('recommendationWorkflowDecisionApprovalId').value = approvalId;
            document.getElementById('recommendationWorkflowDecisionRecommendationId').value = recommendationId;
            renderBulkApprovalInputs([]);
            document.getElementById('recommendationWorkflowDecisionModalTitle').textContent = bindings.respondToRecommendationApproval;
            setNotice('recommendationWorkflowDecisionSelectionNotice', '');
            setNotice('recommendationWorkflowDecisionSkippedNotice', '');
            configureApprovalController({
                id: approvalId,
                serialPickNext: approverConfig.serial_pick_next || false,
                requiredCount: requiredCount,
                approvedCount: approvedCount,
                eligibleUrl: '/approvals/eligible-approvers/' + approvalId,
                commentWarning: approverConfig.comment_warning || '',
                requiresComment: approverConfig.requires_comment || false,
                feedbackResponse: feedbackResponse,
                decisionOptions: decisionOptions,
                decisionPromptLabel: approverConfig.decision_prompt_label || approverConfig.decisionPromptLabel || bindings.decision,
                approveLabel: approverConfig.approve_label || bindings.approve,
                hideReject: feedbackResponse || approverConfig.hide_reject || false,
                defaultDecision: feedbackResponse && decisionOptions.length === 0 ? 'approve' : null,
                requiresBestowalGathering: approverConfig.requires_bestowal_gathering || approverConfig.requiresBestowalGathering || false,
                bestowalGatheringOptions: Array.isArray(approverConfig.bestowal_gathering_options) ? approverConfig.bestowal_gathering_options : Array.isArray(approverConfig.bestowalGatheringOptions) ? approverConfig.bestowalGatheringOptions : [],
                bestowalGatheringUrl: approverConfig.bestowal_gathering_url || approverConfig.bestowalGatheringUrl || ''
            });
        };
        listen(modal, 'show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            if (btn.dataset.bulkActionKey === 'workflow-decision') {
                setTimeout(function () {
                    configureBulkDecision(btn);
                }, 0);
                return;
            }
            configureSingleDecision(btn);
        });
        listen(form, 'submit', function (event) {
            if (form.dataset.workflowDecisionMode === 'bulk' && Number(form.dataset.workflowDecisionBulkCount || '0') === 0) {
                event.preventDefault();
                setNotice('recommendationWorkflowDecisionSkippedNotice', bindings.selectAtLeastOneRecommendationThatIs);
            }
        });
    }
    disconnect() {
        this.lifecycle?.dispose();
    }
}
window.Controllers ??= {};
window.Controllers['awards-workflow-decision'] = AwardsWorkflowDecisionController;
export default AwardsWorkflowDecisionController;
