<?php

/**
 * Sub-row template for grouped recommendation children (card layout).
 *
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Recommendation[] $children
 * @var int $headId
 * @var bool $canEdit
 */
?>
<div class="p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">
            <i class="bi bi-collection"></i>
            <?= __('Grouped Recommendations') ?>
        </h6>
        <?php if ($canEdit) : ?>
        <?= $this->Form->postLink(
                '<i class="bi bi-x-circle"></i> ' . __('Ungroup All'),
                ['action' => 'ungroupRecommendations'],
                [
                    'data' => ['recommendation_id' => $headId],
                    'confirm' => __('Ungroup all children? They will be restored to their previous states.'),
                    'class' => 'btn btn-outline-warning btn-sm',
                    'escape' => false,
                ]
            ) ?>
        <?php endif; ?>
    </div>
    <div class="row g-2">
        <?php foreach ($children as $child) : ?>
        <div class="col-12">
            <div class="card border-start border-info border-3">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1 me-2">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge bg-secondary"><?= h($child->award->abbreviation ?? '—') ?></span>
                                <small class="text-muted">
                                    <?= __('by') ?>
                                    <strong><?= h($child->requester->sca_name ?? $child->requester_sca_name) ?></strong>
                                    &middot; <?= $child->created ? $child->created->format('M j, Y') : '—' ?>
                                </small>
                            </div>
                            <div class="small"><?= h($child->reason ?? '') ?></div>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <?= $this->Html->link(
                                    '<i class="bi bi-eye"></i>',
                                    ['action' => 'view', $child->id],
                                    ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => __('View'), 'data-turbo-frame' => '_top']
                                ) ?>
                            <?php if ($canEdit) : ?>
                            <?= $this->Form->postLink(
                                        '<i class="bi bi-x-lg"></i>',
                                        ['action' => 'removeFromGroup'],
                                        [
                                            'data' => ['recommendation_id' => $child->id],
                                            'confirm' => __('Remove this recommendation from the group?'),
                                            'class' => 'btn btn-sm btn-outline-danger',
                                            'escape' => false,
                                            'title' => __('Remove from group'),
                                        ]
                                    ) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>