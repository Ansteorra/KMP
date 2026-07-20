<?php

/**
 * Workflow Instance Detail with Execution Log and Approvals
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WorkflowInstance $instance
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Instance #' . $instance->id;
$this->KMP->endBlock();

?>

<div class="workflows view-instance content">
    <h3>
        <?= $this->element('backButton') ?>
        <?= __('Workflow Instance') ?> #<?= h($instance->id) ?>
        <?= $this->KMP->workflowStatusBadge($instance->status) ?>
    </h3>

    <!-- Instance Summary -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong><?= __('Workflow') ?></strong><br>
                    <?= h($instance->workflow_definition->name ?? '—') ?>
                </div>
                <div class="col-md-2">
                    <strong><?= __('Version') ?></strong><br>
                    v<?= h($instance->workflow_version->version_number ?? '?') ?>
                </div>
                <div class="col-md-2">
                    <strong><?= __('Entity') ?></strong><br>
                    <?= $instance->entity_type ? h($instance->entity_type) . '#' . h($instance->entity_id) : '—' ?>
                </div>
                <div class="col-md-2">
                    <strong><?= __('Started') ?></strong><br>
                    <?= h(\App\KMP\TimezoneHelper::formatDateTime($instance->created)) ?>
                </div>
                <div class="col-md-3">
                    <strong><?= __('Completed') ?></strong><br>
                    <?= $instance->completed_at ? h(\App\KMP\TimezoneHelper::formatDateTime($instance->completed_at)) : __('In progress') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Execution Log Timeline -->
    <h4 class="mb-3"><i class="bi bi-journal-text me-1"></i><?= __('Execution Log') ?></h4>
    <?php if (!empty($instance->workflow_execution_logs)) : ?>
    <div class="list-group mb-4">
        <?php foreach ($instance->workflow_execution_logs as $log) : ?>
        <div class="list-group-item">
            <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1">
                    <span class="badge bg-info me-1"><?= h($log->node_type) ?></span>
                    <?= h($log->node_id) ?>
                </h6>
                <small class="text-muted"><?= h(\App\KMP\TimezoneHelper::formatDateTime($log->created)) ?></small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?= $this->KMP->workflowStatusBadge($log->status) ?>
                <?php if ($log->output_port) : ?>
                    <span class="text-muted small">→ <?= h($log->output_port) ?></span>
                <?php endif; ?>
                <?php if ($log->duration_ms !== null) : ?>
                    <span class="text-muted small"><?= h($log->duration_ms) ?>ms</span>
                <?php endif; ?>
            </div>
            <?php if ($log->error_message) : ?>
                <div class="alert alert-danger mt-2 mb-0 py-1 px-2 small" role="alert">
                    <?= h($log->error_message) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else : ?>
    <p class="text-muted"><?= __('No execution logs recorded.') ?></p>
    <?php endif; ?>

    <!-- Approvals -->
    <?php if (!empty($instance->workflow_approvals)) : ?>
    <h4 class="mb-3"><i class="bi bi-check-circle me-1"></i><?= __('Approvals') ?></h4>
    <?php foreach ($instance->workflow_approvals as $approval) : ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <span>
                <?= __('Approval') ?> #<?= h($approval->id) ?>
                — <?= __('Node:') ?> <?= h($approval->node_id) ?>
            </span>
            <?= $this->KMP->workflowStatusBadge($approval->status) ?>
        </div>
        <div class="card-body">
            <?php if (!empty($approval->workflow_approval_responses)) : ?>
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th><?= __('Responder') ?></th>
                        <th><?= __('Decision') ?></th>
                        <th><?= __('Comment') ?></th>
                        <th><?= __('Date') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approval->workflow_approval_responses as $response) : ?>
                    <tr>
                        <td><?= h($response->member->sca_name ?? $response->member->email_address ?? $response->member_id) ?></td>
                        <td><?= $this->KMP->workflowStatusBadge($response->decision) ?></td>
                        <td><?= h($response->comment) ?: '—' ?></td>
                        <td><?= h(\App\KMP\TimezoneHelper::formatDateTime($response->created)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p class="text-muted mb-0"><?= __('No responses yet.') ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
