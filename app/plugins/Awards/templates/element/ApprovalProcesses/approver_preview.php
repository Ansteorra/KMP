<?php
/**
 * Approval-process approver preview frame.
 *
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\ApprovalProcess $approvalProcess
 * @var array $awards
 * @var array|null $preview
 * @var string|int|null $previewAwardId
 * @var string $previewFrameId
 */
?>
<turbo-frame id="<?= h($previewFrameId) ?>">
    <h5><?= __('Approver Preview') ?></h5>
    <p class="text-muted">
        <?= __('Choose an award to verify the exact branch-scoped approvers each step will resolve.') ?>
    </p>
    <?= $this->Form->create(null, [
        'type' => 'get',
        'url' => ['action' => 'view', $approvalProcess->id],
        'valueSources' => ['query'],
        'data-turbo-frame' => $previewFrameId,
    ]) ?>
    <div class="row g-2 align-items-end">
        <div class="col-md-8">
            <?= $this->Form->control('preview_award_id', [
                'options' => $awards,
                'empty' => __('-- Select an award --'),
                'label' => __('Award'),
                'value' => $previewAwardId,
            ]) ?>
        </div>
        <div class="col-md-4 text-md-end">
            <?= $this->Form->button(__('Preview Approvers'), ['class' => 'btn btn-primary']) ?>
        </div>
    </div>
    <?= $this->Form->end() ?>

    <div id="<?= h($previewFrameId) ?>-results" class="mt-3" aria-live="polite" aria-atomic="false">
        <?php if ($preview !== null) : ?>
            <?php foreach ($preview as $stepPreview) : ?>
                <section class="card mb-2" aria-labelledby="preview-step-<?= h($stepPreview['step']->id) ?>">
                    <div class="card-header">
                        <h6 class="mb-0" id="preview-step-<?= h($stepPreview['step']->id) ?>">
                            <?= h($stepPreview['step']->label) ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stepPreview['error'])) : ?>
                            <div class="alert alert-danger mb-0" role="alert"><?= h($stepPreview['error']) ?></div>
                        <?php elseif (empty($stepPreview['members'])) : ?>
                            <div class="alert alert-warning mb-0" role="alert">
                                <?=
                                __(
                                    'No approvers resolved for this step. Fix the source or branch scope ' .
                                    'before activating this process.',
                                )
                                ?>
                            </div>
                        <?php else : ?>
                            <p class="mb-2">
                                <?=
                                __(
                                    'Branch: {0}',
                                    $stepPreview['branch'] ? $stepPreview['branch']->name : __('Not branch-scoped'),
                                )
                                ?>
                            </p>
                            <ul class="mb-0">
                                <?php foreach ($stepPreview['members'] as $member) : ?>
                                    <li><?= h($member->sca_name) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</turbo-frame>
