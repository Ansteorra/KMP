<?php

/**
 * Quick To-Dos modal frame for a single bestowal.
 *
 * Rendered into the grid To-Dos modal turbo-frame. Reuses the shared bestowal
 * checklist element so Complete / Reopen behave exactly like the bestowal view
 * "To-Dos" tab, returning to the bestowals index after a full-navigation post.
 *
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Bestowal $bestowal
 * @var array<\App\Model\Entity\ActionItem> $todoItems
 * @var array<int, bool> $todoEligibility
 * @var int $todoGatingTotal
 * @var int $todoGatingDone
 * @var int $gatingPercent
 */

use Awards\Model\Entity\Bestowal;

$memberName = $bestowal->member->sca_name ?? $bestowal->member_sca_name ?? '';
$lifecycleStatus = (string)($bestowal->lifecycle_status ?? Bestowal::LIFECYCLE_OPEN);
$currentPageUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Bestowals',
    'action' => 'index',
]);
$viewUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Bestowals',
    'action' => 'view',
    $bestowal->id,
]);
?>
<turbo-frame id="bestowalTodosQuick">
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
        <div>
            <h2 class="h6 mb-0"><?= h($memberName) ?></h2>
            <?php if ($lifecycleStatus === Bestowal::LIFECYCLE_CANCELLED) : ?>
                <span class="badge bg-secondary"><?= __('Cancelled') ?></span>
            <?php elseif ($lifecycleStatus === Bestowal::LIFECYCLE_GIVEN) : ?>
                <span class="badge bg-success"><?= __('Given') ?></span>
            <?php endif; ?>
        </div>
        <a href="<?= h($viewUrl) ?>" class="btn btn-sm btn-outline-secondary" data-turbo="false">
            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i><?= __('Open') ?>
        </a>
    </div>
    <?= $this->element('bestowal_todo_checklist', [
        'todoItems' => $todoItems,
        'todoEligibility' => $todoEligibility,
        'todoGatingTotal' => $todoGatingTotal,
        'todoGatingDone' => $todoGatingDone,
        'gatingPercent' => $gatingPercent,
        'currentPageUrl' => $currentPageUrl,
        'progressId' => 'bestowal-todos-modal-progress',
    ]) ?>
</turbo-frame>
