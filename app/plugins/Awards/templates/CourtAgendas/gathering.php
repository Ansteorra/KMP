<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\CourtAgenda $agenda
 * @var array<int, array<string, mixed>> $segments
 * @var array<int, array<string, mixed>> $unscheduledBestowals
 * @var array<int, string> $scheduledActivityOptions
 * @var int|null $selectedSegmentId
 * @var int $totalMinutes
 * @var string|null $totalWarning
 * @var bool $canManage
 * @var bool $canViewGathering
 * @var array<int, string> $unscheduledAwardActivities
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

$csrfToken = (string)($this->request->getAttribute('csrfToken') ?? '');
$blockRoleOptions = [
    'announce' => __('Announcement'),
    'break' => __('Break'),
    'start' => __('Opening / Processional'),
    'finish' => __('Closing / Recessional'),
];
$segmentDropActions = 'dragover->court-agenda-board#dragOver '
    . 'dragleave->court-agenda-board#dragLeave drop->court-agenda-board#drop';
$itemDragActions = 'dragstart->court-agenda-board#dragStart dragend->court-agenda-board#dragEnd';
$selectedSegmentData = null;
foreach ($segments as $segmentData) {
    if ((int)$segmentData['entity']->id === (int)$selectedSegmentId) {
        $selectedSegmentData = $segmentData;
        break;
    }
}
$selectedSegmentData ??= $segments[0] ?? null;
$selectedSegment = $selectedSegmentData['entity'] ?? null;
$selectedItems = $selectedSegmentData['items'] ?? [];
$selectedSegmentId = $selectedSegment !== null ? (int)$selectedSegment->id : null;
$selectedSegmentAcceptsItems = $selectedSegment !== null
    && (!empty($selectedSegmentData['isRoaming']) || $selectedSegment->gathering_scheduled_activity_id !== null);
$showTools = $canManage && $selectedSegmentAcceptsItems;
$showMoveToRoaming = empty($selectedSegmentData['isRoaming']);
$gatheringViewUrl = [
    'plugin' => null,
    'controller' => 'Gatherings',
    'action' => 'view',
    $agenda->gathering->public_id ?? $agenda->gathering_id,
];
$gatheringBestowalsUrl = $gatheringViewUrl + [
    '?' => ['tab' => 'gathering-bestowals'],
];
$eligibleForSelected = [];
if ($selectedSegmentId !== null) {
    foreach ($unscheduledBestowals as $bestowalData) {
        if (isset($bestowalData['eligibleSegmentOptions'][$selectedSegmentId])) {
            $eligibleForSelected[] = $bestowalData;
        }
    }
}
?>

<div class="container-fluid py-3 court-agenda-page"
    data-controller="court-agenda-board"
    data-court-agenda-board-move-url-value="<?= h($this->Url->build([
        'plugin' => 'Awards',
        'controller' => 'CourtAgendas',
        'action' => 'moveItem',
    ])) ?>"
    data-court-agenda-board-csrf-token-value="<?= h($csrfToken) ?>">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-start mb-3">
        <div>
            <h1 class="h3 mb-1"><?= __('Court Agenda') ?></h1>
            <p class="mb-1">
                <?= $this->Html->link(
                    $agenda->gathering->name ?? $agenda->name,
                    $gatheringBestowalsUrl,
                ) ?>
            </p>
            <p class="text-muted mb-0">
                <?= __(
                    'Build one court at a time. Arrange its script and keep working notes close at hand.',
                ) ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($canViewDelegations) : ?>
                <button type="button" class="btn btn-outline-secondary"
                    data-bs-toggle="modal" data-bs-target="#eventAwardDelegationModal">
                    <?= __('Event Award Delegation') ?>
                </button>
            <?php endif; ?>
            <?= $this->Html->link(
                __('Back to Gathering'),
                $gatheringBestowalsUrl,
                ['class' => 'btn btn-outline-secondary'],
            ) ?>
            <?= $this->Html->link(
                __('Printer Ready — Full'),
                ['action' => 'printAgenda', $agenda->id],
                ['class' => 'btn btn-outline-secondary', 'target' => '_blank', 'rel' => 'noopener'],
            ) ?>
            <?= $this->Html->link(
                __('Printer Ready — Heralds'),
                ['action' => 'printAgenda', $agenda->id, '?' => ['version' => 'heralds']],
                ['class' => 'btn btn-outline-secondary', 'target' => '_blank', 'rel' => 'noopener'],
            ) ?>
            <?php if ($canManage) : ?>
                <?= $this->Form->postLink(
                    __('Refresh Scheduled Bestowals'),
                    ['action' => 'import', $agenda->id],
                    ['class' => 'btn btn-primary'],
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="visually-hidden" role="status" data-court-agenda-board-target="status"></div>
    <?php if ($totalWarning) : ?>
        <div class="alert alert-warning" role="status"><?= h($totalWarning) ?></div>
    <?php endif; ?>

    <?php if ($segments !== []) : ?>
        <div class="court-agenda-toolbar mb-3">
            <?= $this->Form->create(null, [
                'type' => 'get',
                'url' => ['action' => 'gathering', $agenda->gathering_id],
                'class' => 'court-agenda-selector',
            ]) ?>
                <div class="flex-grow-1">
                    <label class="form-label" for="court-agenda-segment"><?= __('Court Activities') ?></label>
                    <select class="form-select" name="segment_id" id="court-agenda-segment">
                        <?php foreach ($segments as $segmentData) :
                            $segment = $segmentData['entity'];
                            ?>
                            <option value="<?= (int)$segment->id ?>"
                                <?= (int)$segment->id === $selectedSegmentId ? 'selected' : '' ?>>
                                <?= h($segment->name . ' · ' . $segmentData['scheduledActivityLabel']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?= $this->Form->button(__('Show court'), ['class' => 'btn btn-outline-secondary']) ?>
            <?= $this->Form->end() ?>
            <p class="small text-muted mb-0">
                <?= __('Projected Agenda Runtime') ?>:
                <strong><?= h((string)$totalMinutes) ?> <?= __('min') ?></strong>
                <span class="d-block"><?= __('Across all courts') ?></span>
            </p>
            <?php if ($showTools) : ?>
                <button type="button" class="btn btn-primary d-lg-none"
                    data-bs-toggle="offcanvas" data-bs-target="#court-agenda-tools"
                    aria-controls="court-agenda-tools">
                    <?= $this->Icon->render('plus') ?> <?= __('Add to court') ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($canManage) : ?>
        <details class="mb-3 court-agenda-help">
            <summary><?= __('Court scheduling help') ?></summary>
            <div class="small text-muted pt-2">
                <?= __(
                    'Each scheduled award-giving activity has its own agenda section. '
                    . 'Adding an activity under Activities does not schedule it. '
                    . 'Add an occurrence under Schedule, then return here; its section appears automatically. '
                    . 'Roaming court is available when placing a bestowal without a scheduled session.',
                ) ?>
                <?php if ($canViewGathering) : ?>
                    <?= $this->Html->link(
                        __('Open gathering schedule'),
                        $gatheringViewUrl + ['?' => ['tab' => 'schedule']],
                        [],
                    ) ?>.
                <?php else : ?>
                    <?= __('Ask the gathering organizer to add or update scheduled activities.') ?>
                <?php endif; ?>
            </div>
        </details>
    <?php endif; ?>

    <?php if ($unscheduledAwardActivities !== []) : ?>
        <section class="alert alert-warning" aria-labelledby="unscheduled-award-activities-heading">
            <h2 class="h5" id="unscheduled-award-activities-heading">
                <?= __('Award-giving activities need scheduling') ?>
            </h2>
            <p><?= __('These activities are included in the gathering but have no scheduled occurrence yet:') ?></p>
            <ul>
                <?php foreach ($unscheduledAwardActivities as $activityName) : ?>
                    <li><?= h($activityName) ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="mb-0"><?= __('Schedule an occurrence to create its agenda section.') ?></p>
        </section>
    <?php endif; ?>

    <?php if ($selectedSegment === null) : ?>
        <div class="alert alert-info" role="status">
            <?= __('No court activities are available yet.') ?>
            <?php if ($unscheduledAwardActivities === []) : ?>
                <?= __(
                    'Add an award-giving activity under Activities and schedule an occurrence. '
                    . 'If the intended activity is already scheduled, ask an awards administrator '
                    . 'to check its award eligibility in the awards catalog.',
                ) ?>
            <?php endif; ?>
            <?php if ($canViewGathering) : ?>
                <?= $this->Html->link(
                    __('Open gathering activities'),
                    $gatheringViewUrl + ['?' => ['tab' => 'activities']],
                    ['class' => 'alert-link'],
                ) ?>.
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="court-agenda-workspace<?= $showTools ? ' court-agenda-with-tools' : '' ?>">
            <div class="court-agenda-script">
                <section class="card" aria-labelledby="selected-court-heading">
                    <div class="card-header">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                            <div>
                                <h2 class="h4 mb-1" id="selected-court-heading"><?= h($selectedSegment->name) ?></h2>
                                <p class="small text-muted mb-0">
                                    <?= h($selectedSegmentData['scheduledActivityLabel']) ?>
                                    · <?= h((string)$selectedSegmentData['minutes']) ?> <?= __('min') ?>
                                    · <?= __n('{0} item', '{0} items', count($selectedItems), count($selectedItems)) ?>
                                </p>
                            </div>
                            <span class="badge text-bg-secondary align-self-md-start">
                                <?= h($selectedSegment->court_type) ?>
                            </span>
                        </div>
                        <?php if (!empty($selectedSegmentData['warning'])) : ?>
                            <div class="alert alert-warning small mt-2 mb-0" role="status">
                                <?= h($selectedSegmentData['warning']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$selectedSegmentAcceptsItems) : ?>
                            <div class="alert alert-warning small mt-2 mb-0" role="status">
                                <?= __(
                                    'This legacy court is not linked to a scheduled gathering activity. '
                                    . 'Move its items to a scheduled court before adding more.',
                                ) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="small text-muted px-3 pt-3 mb-0">
                        <?= $canManage
                            ? __('Use the arrows or drag to change the order. Open details for notes and court moves.')
                            : __('You can read and print this agenda. Editing requires court agenda access.') ?>
                    </p>
                    <div class="card-body court-agenda-list"
                        role="list"
                        aria-label="<?= h(__('{0} script items', $selectedSegment->name)) ?>"
                        data-court-agenda-board-target="segment"
                        data-segment-id="<?= (int)$selectedSegment->id ?>"
                        data-action="<?= h($segmentDropActions) ?>">
                        <?php foreach ($selectedItems as $index => $itemData) :
                            $item = $itemData['entity'];
                            $bestowal = $item->bestowal ?? null;
                            $detailsBlocked = !empty($itemData['detailsBlocked']);
                            $itemId = (int)$item->id;
                            $modalId = 'agenda-item-' . $itemId . '-edit-modal';
                            $modalTitleId = $modalId . '-title';
                            $minutesAriaLabel = h(__('Estimated minutes slider for {0}', $itemData['label']));
                            $isLastItem = $index === count($selectedItems) - 1;
                            $hasItemNotes = !empty($item->presentation_notes) || !empty($item->print_notes);
                            ?>
                            <article class="court-agenda-item"
                                role="listitem"
                                tabindex="-1"
                                draggable="<?= $canManage ? 'true' : 'false' ?>"
                                data-court-agenda-board-target="item"
                                data-item-id="<?= $itemId ?>"
                                data-sort-order="<?= (int)$item->sort_order ?>"
                                data-action="<?= h($itemDragActions) ?>">
                                <div class="court-agenda-item-row">
                                    <span class="court-agenda-position text-muted" aria-hidden="true"
                                        data-court-agenda-board-target="position"><?= $index + 1 ?>.</span>
                                    <div class="court-agenda-item-content">
                                        <span class="visually-hidden"><?= __('Script item') ?></span>
                                        <h3 class="h5 mb-1"><?= h($itemData['label']) ?></h3>
                                        <?php if ($itemData['awardLabel'] !== '') : ?>
                                            <p class="fw-semibold mb-1"><?= h($itemData['awardLabel']) ?></p>
                                        <?php elseif (!empty($item->planned_action)) : ?>
                                            <p class="fw-semibold mb-1"><?= h($item->planned_action) ?></p>
                                        <?php endif; ?>
                                        <p class="small text-muted mb-0">
                                            <?= h((string)$itemData['minutes']) ?> <?= __('min') ?>
                                        </p>
                                    </div>
                                    <?php if ($canManage) : ?>
                                        <div class="court-agenda-item-actions" role="group"
                                            aria-label="<?= h(__('Arrange {0}', $itemData['label'])) ?>">
                                            <button type="button" class="btn btn-outline-secondary"
                                                aria-label="<?= h(__('Move {0} up', $itemData['label'])) ?>"
                                                aria-disabled="<?= $index === 0 ? 'true' : 'false' ?>"
                                                data-direction="up" data-action="court-agenda-board#moveByButton">
                                                <?= $this->Icon->render('arrow-up') ?>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary"
                                                aria-label="<?= h(__('Move {0} down', $itemData['label'])) ?>"
                                                aria-disabled="<?= $isLastItem ? 'true' : 'false' ?>"
                                                data-direction="down" data-action="court-agenda-board#moveByButton">
                                                <?= $this->Icon->render('arrow-down') ?>
                                            </button>
                                            <?php if (!$detailsBlocked) : ?>
                                                <button type="button" class="btn btn-outline-primary"
                                                    aria-label="<?= h(__('Edit {0}', $itemData['label'])) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#<?= h($modalId) ?>">
                                                    <?= __('Edit') ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$detailsBlocked || $canManage) : ?>
                                    <details class="court-agenda-item-details">
                                        <summary><?= __('Details') ?>
                                            <span class="visually-hidden"><?= h($itemData['label']) ?></span>
                                        </summary>
                                        <div class="pt-2">
                                            <div class="d-flex flex-wrap gap-1 mb-2">
                                                <?php if ($bestowal !== null) : ?>
                                                    <span class="badge text-bg-light border">
                                                        <?= h($bestowal->lifecycle_status) ?>
                                                    </span>
                                                    <?php if (!empty($bestowal->call_into_court)) : ?>
                                                        <span class="badge text-bg-light border">
                                                            <?= h(__('Call: {0}', $bestowal->call_into_court)) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($bestowal->court_availability)) : ?>
                                                        <span class="badge text-bg-light border">
                                                            <?= h(__('Avail: {0}', $bestowal->court_availability)) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php elseif (!$detailsBlocked) : ?>
                                                    <span class="badge text-bg-light border">
                                                        <?= h($item->role) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($hasItemNotes) : ?>
                                                    <span class="badge text-bg-info"><?= __('Notes') ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($itemData['reasons'])) : ?>
                                                    <span class="badge text-bg-light border"><?= __('Reasons') ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($itemData['specialties'])) : ?>
                                                    <span class="badge text-bg-light border">
                                                        <?= __('Specialties') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($bestowal !== null) : ?>
                                                <dl class="row small mb-2">
                                                    <?php if ($itemData['specialties'] !== []) : ?>
                                                        <dt class="col-sm-3"><?= __('Specialties') ?></dt>
                                                        <dd class="col-sm-9">
                                                            <?= h(implode(', ', $itemData['specialties'])) ?>
                                                        </dd>
                                                    <?php endif; ?>
                                                    <dt class="col-sm-3"><?= __('Timing hint') ?></dt>
                                                    <dd class="col-sm-9"><?= h($itemData['durationHint']) ?></dd>
                                                    <?php if (!empty($bestowal->person_to_notify)) : ?>
                                                        <dt class="col-sm-3"><?= __('Notify') ?></dt>
                                                        <dd class="col-sm-9"><?= h($bestowal->person_to_notify) ?></dd>
                                                    <?php endif; ?>
                                                </dl>
                                            <?php elseif (!empty($item->presentation_notes)) : ?>
                                                <p class="small text-muted mb-2"><?= h($item->presentation_notes) ?></p>
                                            <?php endif; ?>

                                            <?php if ($canManage) : ?>
                                                <?php if (!empty($itemData['moveSegmentOptions'])) : ?>
                                                    <?= $this->Form->create(null, [
                                                        'url' => ['action' => 'moveItem'],
                                                        'class' => 'row g-2 align-items-end mb-2',
                                                    ]) ?>
                                                        <?= $this->Form->hidden('item_id', ['value' => $itemId]) ?>
                                                        <div class="col-12 col-md">
                                                            <?= $this->Form->control('court_agenda_segment_id', [
                                                                'label' => __('Move to another court'),
                                                                'options' => $itemData['moveSegmentOptions'],
                                                                'value' => $selectedSegment->id,
                                                                'id' => 'item-' . $itemId . '-move-segment',
                                                            ]) ?>
                                                        </div>
                                                        <div class="col-12 col-md-auto">
                                                            <?= $this->Form->button(
                                                                __('Move'),
                                                                ['class' => 'btn btn-outline-primary btn-sm'],
                                                            ) ?>
                                                        </div>
                                                    <?= $this->Form->end() ?>
                                                <?php endif; ?>

                                                <?php if ($bestowal !== null && $showMoveToRoaming) : ?>
                                                    <?= $this->Form->create(null, [
                                                        'url' => ['action' => 'moveToRoaming'],
                                                        'class' => 'd-inline',
                                                    ]) ?>
                                                        <?= $this->Form->hidden('court_agenda_id', [
                                                            'value' => $agenda->id,
                                                        ]) ?>
                                                        <?= $this->Form->hidden('item_id', ['value' => $itemId]) ?>
                                                        <?= $this->Form->hidden('return_segment_id', [
                                                            'value' => $selectedSegmentId,
                                                        ]) ?>
                                                        <?= $this->Form->button(
                                                            __('Move to Roaming'),
                                                            ['class' => 'btn btn-outline-secondary btn-sm'],
                                                        ) ?>
                                                    <?= $this->Form->end() ?>
                                                <?php endif; ?>
                                                <?= $this->Form->create(null, [
                                                    'url' => ['action' => 'removeItem'],
                                                    'class' => 'd-inline',
                                                ]) ?>
                                                    <?= $this->Form->hidden('item_id', ['value' => $itemId]) ?>
                                                    <?= $this->Form->hidden('return_segment_id', [
                                                        'value' => $selectedSegmentId,
                                                    ]) ?>
                                                    <?= $this->Form->button(
                                                        __('Remove from Agenda'),
                                                        [
                                                            'class' => 'btn btn-outline-danger btn-sm',
                                                            'data-confirm-message' => $bestowal !== null
                                                                ? __(
                                                                    'Remove {0} from this court agenda? '
                                                                    . 'The bestowal will stay scheduled for the '
                                                                    . 'gathering but will no longer be '
                                                                    . 'assigned to a court.',
                                                                    $itemData['label'],
                                                                )
                                                                : __(
                                                                    'Remove {0} from this court agenda?',
                                                                    $itemData['label'],
                                                                ),
                                                            'data-confirm-title' => __('Remove from agenda'),
                                                            'data-confirm-label' => __('Remove'),
                                                        ],
                                                    ) ?>
                                                <?= $this->Form->end() ?>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>

                            <?php if ($canManage && !$detailsBlocked) : ?>
                                <div class="modal fade"
                                    id="<?= h($modalId) ?>"
                                    tabindex="-1"
                                    aria-labelledby="<?= h($modalTitleId) ?>"
                                    aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <?= $this->Form->create(null, [
                                            'url' => ['action' => 'updateItem', $item->id],
                                            'class' => 'modal-content',
                                        ]) ?>
                                            <?= $this->Form->hidden('return_segment_id', [
                                                'value' => $selectedSegmentId,
                                            ]) ?>
                                            <div class="modal-header">
                                                <h2 class="modal-title h5" id="<?= h($modalTitleId) ?>">
                                                    <?= h(__('Edit agenda item: {0}', $itemData['label'])) ?>
                                                </h2>
                                                <button type="button"
                                                    class="btn-close"
                                                    data-bs-dismiss="modal"
                                                    aria-label="<?= h(__('Close')) ?>"></button>
                                            </div>
                                            <div class="modal-body">
                                                <dl class="row small">
                                                    <dt class="col-sm-3"><?= __('Item') ?></dt>
                                                    <dd class="col-sm-9"><?= h($itemData['label']) ?></dd>
                                                    <?php if ($itemData['awardLabel'] !== '') : ?>
                                                        <dt class="col-sm-3"><?= __('Award') ?></dt>
                                                        <dd class="col-sm-9"><?= h($itemData['awardLabel']) ?></dd>
                                                    <?php endif; ?>
                                                    <dt class="col-sm-3"><?= __('Timing hint') ?></dt>
                                                    <dd class="col-sm-9"><?= h($itemData['durationHint']) ?></dd>
                                                </dl>
                                                <div class="mb-3">
                                                    <label class="form-label" for="item-<?= $itemId ?>-minutes">
                                                        <?= __('Estimated minutes') ?>
                                                    </label>
                                                    <input type="range"
                                                        class="form-range"
                                                        min="0"
                                                        max="60"
                                                        step="1"
                                                        value="<?= (int)$item->estimated_minutes ?>"
                                                        aria-label="<?= $minutesAriaLabel ?>"
                                                        aria-describedby="item-<?= $itemId ?>-minutes-help"
                                                        oninput="this.nextElementSibling.value = this.value">
                                                    <input class="form-control form-control-sm"
                                                        id="item-<?= $itemId ?>-minutes"
                                                        name="estimated_minutes"
                                                        type="number"
                                                        min="0"
                                                        max="240"
                                                        value="<?= (int)$item->estimated_minutes ?>">
                                                    <div class="form-text" id="item-<?= $itemId ?>-minutes-help">
                                                        <?= __(
                                                            'Adjust when this award or block will run shorter or '
                                                            . 'longer than the default hint.',
                                                        ) ?>
                                                    </div>
                                                </div>
                                                <?= $this->Form->control('presentation_notes', [
                                                    'type' => 'textarea',
                                                    'label' => __('Agenda notes'),
                                                    'value' => $item->presentation_notes,
                                                    'rows' => 3,
                                                    'id' => 'item-' . $itemId . '-presentation-notes',
                                                ]) ?>
                                                <?= $this->Form->control('print_notes', [
                                                    'type' => 'textarea',
                                                    'label' => __('Print notes'),
                                                    'value' => $item->print_notes,
                                                    'rows' => 3,
                                                    'id' => 'item-' . $itemId . '-print-notes',
                                                ]) ?>
                                                <fieldset class="border rounded p-3">
                                                    <legend class="float-none w-auto px-2 h6">
                                                        <?= __('Print options') ?>
                                                    </legend>
                                                    <div class="row">
                                                        <div class="col-12 col-md-6">
                                                            <?= $this->Form->control('include_reasons', [
                                                                'type' => 'checkbox',
                                                                'label' => __('Print reasons'),
                                                                'checked' => (bool)$item->include_reasons,
                                                                'id' => 'item-' . $itemId . '-include-reasons',
                                                            ]) ?>
                                                        </div>
                                                        <div class="col-12 col-md-6">
                                                            <?= $this->Form->control('include_specialties', [
                                                                'type' => 'checkbox',
                                                                'label' => __('Print specialties'),
                                                                'checked' => (bool)$item->include_specialties,
                                                                'id' => 'item-' . $itemId . '-include-specialties',
                                                            ]) ?>
                                                        </div>
                                                    </div>
                                                </fieldset>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button"
                                                    class="btn btn-outline-secondary"
                                                    data-bs-dismiss="modal">
                                                    <?= __('Cancel') ?>
                                                </button>
                                                <?= $this->Form->button(
                                                    __('Save item'),
                                                    ['class' => 'btn btn-primary'],
                                                ) ?>
                                            </div>
                                        <?= $this->Form->end() ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            </article>
                        <?php endforeach; ?>

                        <?php if ($selectedItems === []) : ?>
                            <p class="text-muted mb-0">
                                <?= __(
                                    'No script items in this court yet.',
                                ) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer small text-muted">
                        <?= __(
                            'Agenda order does not mark awards as presented. '
                            . 'Bestowal completion is a separate action.',
                        ) ?>
                    </div>
                </section>
            </div>

            <?php if ($showTools) : ?>
                <aside class="offcanvas-lg offcanvas-end court-agenda-tools" tabindex="-1"
                    id="court-agenda-tools" aria-labelledby="court-agenda-tools-title">
                    <div class="offcanvas-header">
                        <h2 class="offcanvas-title h5" id="court-agenda-tools-title"><?= __('Add to this court') ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                            data-bs-target="#court-agenda-tools" aria-label="<?= __('Close court tools') ?>"></button>
                    </div>
                    <div class="offcanvas-body">
                    <section class="card mb-3" aria-labelledby="selected-backlog-heading">
                        <div class="card-header">
                            <h2 class="h5 mb-1" id="selected-backlog-heading"><?= __('Add Eligible Bestowals') ?></h2>
                            <p class="small text-muted mb-0">
                                <?= __('Only bestowals valid for this court activity are shown here.') ?>
                            </p>
                        </div>
                        <div class="card-body">
                            <?php if ($eligibleForSelected === []) : ?>
                                <p class="text-muted mb-0">
                                    <?= __('No unplaced bestowals are eligible for this court.') ?>
                                </p>
                            <?php else : ?>
                                <?php foreach ($eligibleForSelected as $bestowalData) :
                                    $bestowal = $bestowalData['entity'];
                                    $bestowalId = (int)$bestowal->id;
                                    ?>
                                    <article class="court-agenda-available-item">
                                        <h3 class="h6 mb-1"><?= h($bestowalData['label']) ?></h3>
                                        <?php if ($bestowalData['awardLabel'] !== '') : ?>
                                            <p class="small fw-semibold mb-1">
                                                <?= h($bestowalData['awardLabel']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                            <span class="badge text-bg-light border">
                                                <?= h($bestowal->lifecycle_status) ?>
                                            </span>
                                            <?php if (!empty($bestowal->call_into_court)) : ?>
                                                <span class="badge text-bg-light border">
                                                    <?= h(__('Call: {0}', $bestowal->call_into_court)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?= $this->Form->create(null, ['url' => ['action' => 'addBestowal']]) ?>
                                            <?= $this->Form->hidden('court_agenda_id', ['value' => $agenda->id]) ?>
                                            <?= $this->Form->hidden('bestowal_id', ['value' => $bestowalId]) ?>
                                            <?= $this->Form->hidden('court_agenda_segment_id', [
                                                'value' => $selectedSegmentId,
                                            ]) ?>
                                            <?= $this->Form->button(
                                                __('Add to this court'),
                                                [
                                                    'class' => 'btn btn-outline-primary btn-sm',
                                                    'aria-label' => __('Add {0} to this court', $bestowalData['label']),
                                                ],
                                            ) ?>
                                        <?= $this->Form->end() ?>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <?php if (empty($selectedSegmentData['isRoaming'])) : ?>
                        <section class="card mb-3" aria-labelledby="roaming-backlog-heading">
                            <div class="card-header">
                                <h2 class="h5 mb-1" id="roaming-backlog-heading"><?= __('Send to Roaming Court') ?></h2>
                                <p class="small text-muted mb-0">
                                    <?= __('Use this when the award can be given at any court during the gathering.') ?>
                                </p>
                            </div>
                            <div class="card-body">
                                <?php if ($unscheduledBestowals === []) : ?>
                                    <p class="text-muted mb-0">
                                        <?= __('No unplaced bestowals are available for roaming court.') ?>
                                    </p>
                                <?php else : ?>
                                    <?php foreach ($unscheduledBestowals as $bestowalData) :
                                        $bestowal = $bestowalData['entity'];
                                        $bestowalId = (int)$bestowal->id;
                                        ?>
                                        <article class="court-agenda-available-item">
                                            <h3 class="h6 mb-1"><?= h($bestowalData['label']) ?></h3>
                                            <?php if ($bestowalData['awardLabel'] !== '') : ?>
                                                <p class="small fw-semibold mb-1">
                                                    <?= h($bestowalData['awardLabel']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <?= $this->Form->create(null, [
                                                'url' => ['action' => 'moveToRoaming'],
                                                'class' => 'd-inline',
                                            ]) ?>
                                                <?= $this->Form->hidden('court_agenda_id', [
                                                    'value' => $agenda->id,
                                                ]) ?>
                                                <?= $this->Form->hidden('bestowal_id', [
                                                    'value' => $bestowalId,
                                                ]) ?>
                                                <?= $this->Form->hidden('return_segment_id', [
                                                    'value' => $selectedSegmentId,
                                                ]) ?>
                                                <?= $this->Form->button(
                                                    __('Send to Roaming Court'),
                                                    ['class' => 'btn btn-outline-secondary btn-sm'],
                                                ) ?>
                                            <?= $this->Form->end() ?>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="card" aria-labelledby="add-interjection-heading">
                        <div class="card-header">
                            <h2 class="h5 mb-0" id="add-interjection-heading"><?= __('Add Interjection') ?></h2>
                        </div>
                        <div class="card-body">
                            <?= $this->Form->create(null, [
                                'url' => ['action' => 'addBlock'],
                                'class' => 'row g-2',
                            ]) ?>
                                <?= $this->Form->hidden('court_agenda_segment_id', ['value' => $selectedSegmentId]) ?>
                                <div class="col-12">
                                    <?= $this->Form->control('title', [
                                        'label' => __('Title'),
                                        'placeholder' => __('Break, recess, court business, announcement'),
                                        'required' => true,
                                        'id' => 'segment-' . (int)$selectedSegmentId . '-block-title',
                                    ]) ?>
                                </div>
                                <div class="col-12">
                                    <?= $this->Form->control('role', [
                                        'label' => __('Type'),
                                        'options' => $blockRoleOptions,
                                        'value' => 'announce',
                                        'id' => 'segment-' . (int)$selectedSegmentId . '-block-role',
                                    ]) ?>
                                </div>
                                <div class="col-6">
                                    <?= $this->Form->control('estimated_minutes', [
                                        'type' => 'number',
                                        'min' => 0,
                                        'max' => 240,
                                        'label' => __('Minutes'),
                                        'value' => 5,
                                        'id' => 'segment-' . (int)$selectedSegmentId . '-block-minutes',
                                    ]) ?>
                                </div>
                                <div class="col-6 align-self-end">
                                    <?= $this->Form->button(__('Add'), ['class' => 'btn btn-outline-primary btn-sm']) ?>
                                </div>
                            <?= $this->Form->end() ?>
                        </div>
                    </section>
                    </div>
                </aside>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php if ($canViewDelegations) : ?>
    <?= $this->element('eventAwardDelegationModal', ['gatheringId' => $agenda->gathering_id]) ?>
<?php endif; ?>
