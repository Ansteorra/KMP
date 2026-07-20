<?php

/**
 * Workflow Version History
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WorkflowDefinition $workflow
 * @var \App\Model\Entity\WorkflowVersion[] $versions
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Versions - ' . h($workflow->name);
$this->KMP->endBlock();

$csrfToken = $this->request->getAttribute('csrfToken');
?>

<div class="workflows versions content"
    data-controller="workflow-versions"
    data-workflow-versions-compare-url-value="<?= h($this->Url->build(['action' => 'compareVersions'])) ?>"
    data-workflow-versions-create-draft-url-value="<?= h($this->Url->build(['action' => 'createDraft'])) ?>"
    data-workflow-versions-migrate-url-value="<?= h($this->Url->build(['action' => 'migrateInstances'])) ?>"
    data-workflow-versions-csrf-value="<?= h($csrfToken) ?>"
    data-workflow-versions-workflow-id-value="<?= h($workflow->id) ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>
            <?= $this->element('backButton') ?>
            <?= __('Versions: {0}', h($workflow->name)) ?>
        </h3>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-success btn-sm"
                data-action="click->workflow-versions#createDraft">
                <i class="bi bi-plus-lg me-1"></i><?= __('Create New Draft') ?>
            </button>
            <?= $this->Html->link(
                '<i class="bi bi-pencil-square me-1"></i>' . __('Open Designer'),
                ['action' => 'designer', $workflow->id],
                ['class' => 'btn btn-primary btn-sm', 'escape' => false]
            ) ?>
        </div>
    </div>

    <!-- Version Compare Controls -->
    <?php if (count($versions) >= 2) : ?>
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row align-items-center g-2">
                <div class="col-auto">
                    <label class="form-label mb-0 fw-semibold small"><?= __('Compare') ?></label>
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" style="width:auto"
                        data-workflow-versions-target="v1"
                        data-action="change->workflow-versions#updateCompareBtn">
                        <option value=""><?= __('Select version...') ?></option>
                        <?php foreach ($versions as $v) : ?>
                        <option value="<?= h($v->id) ?>">v<?= h($v->version_number) ?> (<?= h($v->status) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto"><span class="text-muted"><?= __('vs') ?></span></div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" style="width:auto"
                        data-workflow-versions-target="v2"
                        data-action="change->workflow-versions#updateCompareBtn">
                        <option value=""><?= __('Select version...') ?></option>
                        <?php foreach ($versions as $v) : ?>
                        <option value="<?= h($v->id) ?>">v<?= h($v->version_number) ?> (<?= h($v->status) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-outline-primary" disabled
                        data-workflow-versions-target="compareBtn"
                        data-action="click->workflow-versions#compare">
                        <i class="bi bi-arrow-left-right me-1"></i><?= __('Compare') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Diff Results -->
    <div class="mb-3" style="display:none" data-workflow-versions-target="diffResults">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><?= __('Version Differences') ?></h6>
                <button type="button" class="btn-close btn-sm"
                    data-action="click->workflow-versions#closeDiff"></button>
            </div>
            <div class="card-body p-0" data-workflow-versions-target="diffBody"></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?= __('Version') ?></th>
                    <th><?= __('Status') ?></th>
                    <th><?= __('Published At') ?></th>
                    <th><?= __('Change Notes') ?></th>
                    <th><?= __('Created') ?></th>
                    <th class="text-end"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versions as $version) : ?>
                <tr>
                    <td>v<?= h($version->version_number) ?></td>
                    <td><?= $this->KMP->workflowStatusBadge($version->status) ?></td>
                    <td><?= $version->published_at ? h(\App\KMP\TimezoneHelper::formatDateTime($version->published_at)) : '—' ?></td>
                    <td><?= h($version->change_notes) ?: '—' ?></td>
                    <td><?= h(\App\KMP\TimezoneHelper::formatDateTime($version->created)) ?></td>
                    <td class="text-end">
                        <?php if ($version->status === 'published') : ?>
                        <button type="button" class="btn btn-outline-warning btn-sm"
                            data-action="click->workflow-versions#migrate"
                            data-version-id="<?= h($version->id) ?>"
                            title="<?= __('Migrate Running Instances') ?>"
                            aria-label="<?= __('Migrate Running Instances') ?>">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($versions)) : ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <?= __('No versions found.') ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
