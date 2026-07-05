<?php
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
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

$title = __('Court Agenda') . ': ' . ($agenda->gathering->name ?? $agenda->name);
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
$gatheringViewUrl = [
    'plugin' => null,
    'controller' => 'Gatherings',
    'action' => 'view',
    $agenda->gathering->public_id ?? $agenda->gathering_id,
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
            <h1 class="h3 mb-1"><?= h($title) ?></h1>
            <p class="text-muted mb-0">
                <?= __(
                    'Build one court at a time. Choose a court activity, place eligible bestowals, '
                    . 'add interjections, and prepare print notes.',
                ) ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?= $this->Html->link(
                __('Back to Gathering'),
                $gatheringViewUrl,
                ['class' => 'btn btn-outline-secondary'],
            ) ?>
            <?= $this->Html->link(
                __('Printer Ready'),
                ['action' => 'printAgenda', $agenda->id],
                ['class' => 'btn btn-outline-secondary', 'target' => '_blank'],
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

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5 card-title"><?= __('Projected Agenda Runtime') ?></h2>
                    <p class="display-6 mb-1"><?= h((string)$totalMinutes) ?> <?= __('min') ?></p>
                    <p class="text-muted mb-0">
                        <?= __('Total across all courts and roaming court for this gathering.') ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="alert <?= $totalWarning ? 'alert-warning' : 'alert-info' ?> mb-0" role="status">
                <?= h($totalWarning ?? __(
                    'Choose a court below to build its script. '
                    . 'Long courts are easier to manage one activity at a time.',
                )) ?>
            </div>
            <div class="visually-hidden" aria-live="polite" data-court-agenda-board-target="status"></div>
        </div>
    </div>

    <section class="card mb-3" aria-labelledby="court-selector-heading">
        <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-2">
            <div>
                <h2 class="h5 mb-1" id="court-selector-heading"><?= __('Court Activities') ?></h2>
                <p class="small text-muted mb-0"><?= __('Select the court activity you want to build.') ?></p>
            </div>
            <span class="badge text-bg-secondary align-self-md-start">
                <?= __n('{0} court', '{0} courts', count($segments), count($segments)) ?>
            </span>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($segments as $segmentData) :
                $segment = $segmentData['entity'];
                $isSelected = (int)$segment->id === (int)$selectedSegmentId;
                ?>
                <?= $this->Html->link(
                    '<div class="d-flex w-100 justify-content-between gap-3">'
                        . '<div><span class="fw-semibold">' . h($segment->name) . '</span>'
                        . '<div class="small ' . ($isSelected ? 'text-white-50' : 'text-muted') . '">'
                        . h($segmentData['scheduledActivityLabel']) . '</div></div>'
                        . '<span class="badge '
                        . ($isSelected ? 'text-bg-light' : 'text-bg-secondary')
                        . ' align-self-start">'
                        . h((string)$segmentData['minutes']) . ' ' . h(__('min')) . '</span></div>',
                    [
                        'action' => 'gathering',
                        $agenda->gathering_id,
                        '?' => ['segment_id' => (int)$segment->id],
                    ],
                    [
                        'class' => 'list-group-item list-group-item-action' . ($isSelected ? ' active' : ''),
                        'aria-current' => $isSelected ? 'true' : null,
                        'escape' => false,
                    ],
                ) ?>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($canManage) : ?>
        <div class="alert alert-info mb-3" role="status">
            <?= __(
                'Court activities come from the gathering schedule. Add or edit courts on the gathering, '
                . 'then refresh scheduled bestowals here.',
            ) ?>
            <?= $this->Html->link(__('Open gathering schedule'), $gatheringViewUrl, ['class' => 'alert-link']) ?>.
        </div>
    <?php endif; ?>

    <?php if ($selectedSegment === null) : ?>
        <div class="alert alert-info" role="status"><?= __('No court activities are available yet.') ?></div>
    <?php else : ?>
        <div class="row g-3 align-items-start">
            <div class="col-12 col-xl-8">
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
                    <div class="card-body"
                        role="list"
                        aria-label="<?= h(__('{0} script items', $selectedSegment->name)) ?>"
                        data-court-agenda-board-target="segment"
                        data-segment-id="<?= (int)$selectedSegment->id ?>"
                        data-action="<?= h($segmentDropActions) ?>">
                        <?php foreach ($selectedItems as $index => $itemData) :
                            $item = $itemData['entity'];
                            $bestowal = $item->bestowal ?? null;
                            $itemId = (int)$item->id;
                            $modalId = 'agenda-item-' . $itemId . '-edit-modal';
                            $modalTitleId = $modalId . '-title';
                            $minutesAriaLabel = h(__('Estimated minutes slider for {0}', $itemData['label']));
                            ?>
                            <article class="card mb-3 court-agenda-item"
                                role="listitem"
                                tabindex="-1"
                                draggable="<?= $canManage ? 'true' : 'false' ?>"
                                data-court-agenda-board-target="item"
                                data-item-id="<?= $itemId ?>"
                                data-sort-order="<?= (int)$item->sort_order ?>"
                                data-action="<?= h($itemDragActions) ?>">
                                <div class="card-body">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                        <div>
                                            <p class="small text-muted mb-1">
                                                <?= __('Script item {0}', $index + 1) ?>
                                            </p>
                                            <h3 class="h5 mb-1"><?= h($itemData['label']) ?></h3>
                                            <?php if ($itemData['awardLabel'] !== '') : ?>
                                                <p class="fw-semibold mb-1"><?= h($itemData['awardLabel']) ?></p>
                                            <?php elseif (!empty($item->planned_action)) : ?>
                                                <p class="fw-semibold mb-1"><?= h($item->planned_action) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge text-bg-primary align-self-md-start">
                                            <?= h((string)$itemData['minutes']) ?> <?= __('min') ?>
                                        </span>
                                    </div>

                                    <div class="d-flex flex-wrap gap-1 my-2">
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
                                        <?php else : ?>
                                            <span class="badge text-bg-light border"><?= h($item->role) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item->presentation_notes) || !empty($item->print_notes)) : ?>
                                            <span class="badge text-bg-info"><?= __('Notes') ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($itemData['reasons'])) : ?>
                                            <span class="badge text-bg-light border"><?= __('Reasons') ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($itemData['specialties'])) : ?>
                                            <span class="badge text-bg-light border"><?= __('Specialties') ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($bestowal !== null) : ?>
                                        <dl class="row small mb-2">
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
                                        <div class="d-flex flex-wrap gap-1 mb-2"
                                            aria-label="<?= h(__('Move agenda item')) ?>">
                                            <button type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                aria-label="<?= h(__('Move {0} up', $itemData['label'])) ?>"
                                                data-direction="up"
                                                data-action="court-agenda-board#moveByButton">
                                                <?= __('Up') ?>
                                            </button>
                                            <button type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                aria-label="<?= h(__('Move {0} down', $itemData['label'])) ?>"
                                                data-direction="down"
                                                data-action="court-agenda-board#moveByButton">
                                                <?= __('Down') ?>
                                            </button>
                                            <button type="button"
                                                class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#<?= h($modalId) ?>">
                                                <?= __('Edit') ?>
                                            </button>
                                        </div>

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

                                        <?php if ($bestowal !== null && empty($selectedSegmentData['isRoaming'])) : ?>
                                            <?= $this->Form->create(null, [
                                                'url' => ['action' => 'moveToRoaming'],
                                                'class' => 'd-inline',
                                            ]) ?>
                                                <?= $this->Form->hidden('court_agenda_id', ['value' => $agenda->id]) ?>
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
                                                            'Remove {0} from this court agenda? The bestowal will stay '
                                                            . 'scheduled for the gathering but will no longer be '
                                                            . 'assigned to a court.',
                                                            $itemData['label'],
                                                        )
                                                        : __('Remove {0} from this court agenda?', $itemData['label']),
                                                    'data-confirm-title' => __('Remove from agenda'),
                                                    'data-confirm-label' => __('Remove'),
                                                ],
                                            ) ?>
                                        <?= $this->Form->end() ?>
                                    <?php endif; ?>
                                </div>
                            </article>

                            <?php if ($canManage) : ?>
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
                        <?php endforeach; ?>

                        <?php if ($selectedItems === []) : ?>
                            <p class="text-muted mb-0">
                                <?= __(
                                    'No script items in this court yet. Add eligible bestowals or interjections '
                                    . 'from the side panel.',
                                ) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <?php if ($canManage && $selectedSegmentAcceptsItems) : ?>
                <aside class="col-12 col-xl-4">
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
                                    <article class="border rounded p-2 mb-2">
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
                                                ['class' => 'btn btn-outline-primary btn-sm'],
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
                                        <article class="border rounded p-2 mb-2">
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
                </aside>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
