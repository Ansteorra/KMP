<?php
/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\CourtAgenda $agenda
 * @var array<int, array<string, mixed>> $segments
 * @var int $totalMinutes
 * @var string|null $totalWarning
 * @var bool $canManage
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

$title = __('Court Agenda') . ': ' . ($agenda->gathering->name ?? $agenda->name);
$csrfToken = (string)($this->request->getAttribute('csrfToken') ?? '');
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
                <?= __('Plan bestowal order, court segments, timing, notes, and print handouts for heralds and court staff.') ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?= $this->Html->link(
                __('Printer Ready'),
                ['action' => 'printAgenda', $agenda->id],
                ['class' => 'btn btn-outline-secondary', 'target' => '_blank'],
            ) ?>
            <?php if ($canManage) : ?>
                <?= $this->Form->postLink(
                    __('Import Gathering Bestowals'),
                    ['action' => 'import', $agenda->id],
                    ['class' => 'btn btn-primary'],
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <h2 class="h5 card-title"><?= __('Projected Court Runtime') ?></h2>
                    <p class="display-6 mb-1"><?= h((string)$totalMinutes) ?> <?= __('min') ?></p>
                    <p class="text-muted mb-0"><?= __('Timing is a planning estimate; adjust each item as needed.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="alert <?= $totalWarning ? 'alert-warning' : 'alert-info' ?> mb-0" role="status">
                <?= h($totalWarning ?? __('Court runtime looks manageable. Add breaks or split court if local needs change.')) ?>
            </div>
            <div class="visually-hidden" aria-live="polite" data-court-agenda-board-target="status"></div>
        </div>
    </div>

    <?php if ($canManage) : ?>
        <section class="card mb-3">
            <div class="card-header">
                <h2 class="h5 mb-0"><?= __('Add Segment or Break') ?></h2>
            </div>
            <div class="card-body">
                <?= $this->Form->create(null, [
                    'url' => ['action' => 'addSegment'],
                    'class' => 'row g-3 align-items-end',
                ]) ?>
                <?= $this->Form->hidden('court_agenda_id', ['value' => $agenda->id]) ?>
                <div class="col-12 col-md-4">
                    <?= $this->Form->control('name', [
                        'label' => __('Segment name'),
                        'placeholder' => __('Kingdom Court, Baronial Court, Break'),
                        'required' => true,
                    ]) ?>
                </div>
                <div class="col-12 col-md-3">
                    <?= $this->Form->control('court_type', [
                        'label' => __('Segment type'),
                        'options' => [
                            'court' => __('Court'),
                            'break' => __('Break'),
                            'business' => __('Court business'),
                        ],
                        'value' => 'court',
                    ]) ?>
                </div>
                <div class="col-12 col-md-2">
                    <?= $this->Form->control('planned_duration_minutes', [
                        'type' => 'number',
                        'min' => 0,
                        'max' => 240,
                        'label' => __('Base minutes'),
                        'value' => 0,
                    ]) ?>
                </div>
                <div class="col-12 col-md-3">
                    <?= $this->Form->button(__('Add Segment'), ['class' => 'btn btn-primary']) ?>
                </div>
                <?= $this->Form->end() ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="row flex-nowrap overflow-auto pb-3" aria-label="<?= h(__('Court agenda segments')) ?>">
        <?php foreach ($segments as $segmentData) :
            $segment = $segmentData['entity'];
            $items = $segmentData['items'];
            ?>
            <section class="col-12 col-xl-4 col-xxl-3" aria-labelledby="segment-<?= (int)$segment->id ?>-title">
                <div class="card h-100 shadow-sm">
                    <div class="card-header">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <h2 class="h5 mb-1" id="segment-<?= (int)$segment->id ?>-title">
                                    <?= h($segment->name) ?>
                                </h2>
                                <p class="small text-muted mb-0">
                                    <?= h((string)$segmentData['minutes']) ?> <?= __('min') ?>
                                    · <?= __n('{0} item', '{0} items', count($items), count($items)) ?>
                                </p>
                            </div>
                            <span class="badge text-bg-secondary align-self-start"><?= h($segment->court_type) ?></span>
                        </div>
                        <?php if ($segmentData['warning']) : ?>
                            <div class="alert alert-warning small mt-2 mb-0" role="status">
                                <?= h($segmentData['warning']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body bg-light"
                        role="list"
                        aria-label="<?= h(__('{0} agenda items', $segment->name)) ?>"
                        data-court-agenda-board-target="segment"
                        data-segment-id="<?= (int)$segment->id ?>"
                        data-action="dragover->court-agenda-board#dragOver dragleave->court-agenda-board#dragLeave drop->court-agenda-board#drop">
                        <?php foreach ($items as $itemData) :
                            $item = $itemData['entity'];
                            $bestowal = $item->bestowal ?? null;
                            $itemId = (int)$item->id;
                            $modalId = 'agenda-item-' . $itemId . '-edit-modal';
                            $modalTitleId = $modalId . '-title';
                            ?>
                            <article class="card mb-2 court-agenda-item"
                                role="listitem"
                                tabindex="-1"
                                draggable="<?= $canManage ? 'true' : 'false' ?>"
                                data-court-agenda-board-target="item"
                                data-item-id="<?= $itemId ?>"
                                data-sort-order="<?= (int)$item->sort_order ?>"
                                data-action="dragstart->court-agenda-board#dragStart dragend->court-agenda-board#dragEnd">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between gap-2 align-items-start">
                                        <div class="min-w-0">
                                            <h3 class="h6 card-title mb-1"><?= h($itemData['label']) ?></h3>
                                            <?php if ($itemData['awardLabel'] !== '') : ?>
                                                <p class="small fw-semibold mb-1"><?= h($itemData['awardLabel']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge text-bg-primary">
                                            <?= h((string)$itemData['minutes']) ?> <?= __('min') ?>
                                        </span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                        <?php if ($bestowal !== null) : ?>
                                            <span class="badge text-bg-light border"><?= h($bestowal->state) ?></span>
                                            <?php if (!empty($bestowal->call_into_court)) : ?>
                                                <span class="badge text-bg-light border">
                                                    <?= h(__('Call: {0}', $bestowal->call_into_court)) ?>
                                                </span>
                                            <?php endif; ?>
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
                                        <p class="small text-muted mb-2"><?= h($itemData['durationHint']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($canManage) : ?>
                                        <div class="d-flex flex-wrap gap-1" aria-label="<?= h(__('Move agenda item')) ?>">
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
                                                class="btn btn-outline-secondary btn-sm"
                                                aria-label="<?= h(__('Move {0} to previous segment', $itemData['label'])) ?>"
                                                data-direction="previous-segment"
                                                data-action="court-agenda-board#moveByButton">
                                                <?= __('Prev') ?>
                                            </button>
                                            <button type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                aria-label="<?= h(__('Move {0} to next segment', $itemData['label'])) ?>"
                                                data-direction="next-segment"
                                                data-action="court-agenda-board#moveByButton">
                                                <?= __('Next') ?>
                                            </button>
                                            <button type="button"
                                                class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#<?= h($modalId) ?>">
                                                <?= __('Edit') ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                            <?php if ($canManage) : ?>
                                <div class="modal fade" id="<?= h($modalId) ?>" tabindex="-1" aria-labelledby="<?= h($modalTitleId) ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <?= $this->Form->create(null, [
                                            'url' => ['action' => 'updateItem', $item->id],
                                            'class' => 'modal-content',
                                        ]) ?>
                                            <div class="modal-header">
                                                <h2 class="modal-title h5" id="<?= h($modalTitleId) ?>">
                                                    <?= h(__('Edit agenda item: {0}', $itemData['label'])) ?>
                                                </h2>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('Close')) ?>"></button>
                                            </div>
                                            <div class="modal-body">
                                                <dl class="row small">
                                                    <dt class="col-sm-3"><?= __('Item') ?></dt>
                                                    <dd class="col-sm-9"><?= h($itemData['label']) ?></dd>
                                                    <?php if ($itemData['awardLabel'] !== '') : ?>
                                                        <dt class="col-sm-3"><?= __('Award') ?></dt>
                                                        <dd class="col-sm-9"><?= h($itemData['awardLabel']) ?></dd>
                                                    <?php endif; ?>
                                                    <?php if ($bestowal !== null) : ?>
                                                        <dt class="col-sm-3"><?= __('State') ?></dt>
                                                        <dd class="col-sm-9"><?= h($bestowal->state) ?></dd>
                                                        <?php if (!empty($bestowal->call_into_court)) : ?>
                                                            <dt class="col-sm-3"><?= __('Call in') ?></dt>
                                                            <dd class="col-sm-9"><?= h($bestowal->call_into_court) ?></dd>
                                                        <?php endif; ?>
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
                                                        aria-label="<?= h(__('Estimated minutes slider for {0}', $itemData['label'])) ?>"
                                                        aria-describedby="item-<?= $itemId ?>-minutes-help"
                                                        oninput="document.getElementById('item-<?= $itemId ?>-minutes').value = this.value">
                                                    <input class="form-control form-control-sm"
                                                        id="item-<?= $itemId ?>-minutes"
                                                        name="estimated_minutes"
                                                        type="number"
                                                        min="0"
                                                        max="240"
                                                        value="<?= (int)$item->estimated_minutes ?>">
                                                    <div class="form-text" id="item-<?= $itemId ?>-minutes-help">
                                                        <?= __('Adjust when this award or block will run shorter or longer than the default hint.') ?>
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
                                                    <legend class="float-none w-auto px-2 h6"><?= __('Print options') ?></legend>
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
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                                    <?= __('Cancel') ?>
                                                </button>
                                                <?= $this->Form->button(__('Save item'), ['class' => 'btn btn-primary']) ?>
                                            </div>
                                        <?= $this->Form->end() ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($items === []) : ?>
                            <p class="text-muted mb-0"><?= __('Drop agenda items here or import gathering bestowals.') ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($canManage) : ?>
                        <div class="card-footer bg-white">
                            <?= $this->Form->create(null, [
                                'url' => ['action' => 'addBlock'],
                                'class' => 'row g-2',
                            ]) ?>
                            <?= $this->Form->hidden('court_agenda_segment_id', ['value' => $segment->id]) ?>
                            <div class="col-12">
                                <?= $this->Form->control('title', [
                                    'label' => __('Manual block'),
                                    'placeholder' => __('Break, recess, court business'),
                                    'required' => true,
                                    'id' => 'segment-' . (int)$segment->id . '-block-title',
                                ]) ?>
                            </div>
                            <div class="col-6">
                                <?= $this->Form->control('estimated_minutes', [
                                    'type' => 'number',
                                    'min' => 0,
                                    'max' => 240,
                                    'label' => __('Minutes'),
                                    'value' => 5,
                                    'id' => 'segment-' . (int)$segment->id . '-block-minutes',
                                ]) ?>
                            </div>
                            <div class="col-6 align-self-end">
                                <?= $this->Form->button(__('Add Block'), ['class' => 'btn btn-outline-primary btn-sm']) ?>
                            </div>
                            <?= $this->Form->end() ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>
