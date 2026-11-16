<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
/** @var string $showcaseState */

$canManage = $showcaseState !== 'restricted';
$courts = [
    'kingdom' => [
        'label' => __('Kingdom Court · 5:00 PM'),
        'items' => [
            ['id' => 'opening', 'title' => __('Opening of court'), 'detail' => __('Herald announcement · 2 min')],
            [
                'id' => 'service', 'title' => __('Alys of Oakmere'),
                'detail' => __('Award of service · Scroll ready · 3 min'),
            ],
            ['id' => 'archery', 'title' => __('Archery championship'), 'detail' => __('Recognition · 4 min')],
        ],
    ],
    'baronial' => [
        'label' => __('Baronial Court · 6:00 PM'),
        'items' => [
            ['id' => 'welcome', 'title' => __('Welcome from the Coronet'), 'detail' => __('Opening · 2 min')],
            [
                'id' => 'volunteers', 'title' => __('Recognition of the event volunteers'),
                'detail' => __('Announcement · 3 min'),
            ],
        ],
    ],
];
?>
<?= $this->element('uiPatterns/pageHeader', [
    'eyebrow' => __('Oakmere Winter Court'),
    'title' => __('Court agenda'),
    'description' => __('Arrange each court and keep its working notes close at hand.'),
    'back' => true,
    'badges' => [['label' => $canManage ? __('Working agenda') : __('View only'), 'tone' => 'neutral']],
    'actions' => [
        [
            'label' => __('Print agenda'), 'icon' => 'printer', 'action' => 'notice',
            'message' => __('The printed agenda includes the chosen court, its ordered items, and space for notes.'),
        ],
    ],
]) ?>

<?php if ($showcaseState === 'error') : ?>
    <div class="alert alert-danger" role="alert">
        <strong><?= __('The latest agenda could not be retrieved.') ?></strong>
        <?= __('The entries below are your last loaded copy. Check the latest version before making changes.') ?>
    </div>
<?php endif; ?>

<div class="ui-toolbar ui-workspace-toolbar">
    <div class="ui-control-group">
        <label class="form-label" for="workspace-court"><?= __('Court') ?></label>
        <select class="form-select" id="workspace-court" data-ui-patterns-target="court"
            data-action="change->ui-patterns#selectCourt">
            <?php foreach ($courts as $courtKey => $court) : ?>
                <option value="<?= h($courtKey) ?>"><?= h($court['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <p class="text-muted small mb-0"><?= __('December 5, 2026 · Central Time') ?></p>
    <?php if ($canManage) : ?>
        <button type="button" class="btn btn-primary d-lg-none" data-bs-toggle="offcanvas"
            data-bs-target="#workspace-tools" aria-controls="workspace-tools">
            <?= $this->Icon->render('plus') ?> <?= __('Add to court') ?>
        </button>
    <?php endif; ?>
</div>

<div class="ui-workspace">
    <section class="ui-surface ui-agenda" aria-labelledby="workspace-agenda-title">
        <div class="ui-section-heading d-flex justify-content-between align-items-start gap-3">
            <h2 id="workspace-agenda-title" data-ui-patterns-target="courtName">
                <?= h($courts['kingdom']['label']) ?>
            </h2>
            <?= $this->Icon->render('court-crown') ?>
        </div>
        <p class="text-muted">
            <?= $canManage ? __('Use the arrows to change the order. Each court keeps its own agenda.')
                : __('You can read and print the agenda. Editing requires access to this court activity.') ?>
        </p>
        <?php foreach ($courts as $courtKey => $court) : ?>
            <ol class="ui-agenda-list" data-ui-patterns-target="agendaList" data-court="<?= h($courtKey) ?>"
                aria-label="<?= h($court['label']) ?>" <?= $courtKey !== 'kingdom' ? 'hidden' : '' ?>>
                <?php if ($showcaseState !== 'empty') : ?>
                    <?php foreach ($court['items'] as $item) : ?>
                        <li class="ui-agenda-item" data-ui-patterns-target="agendaItem"
                            data-item-id="<?= h($item['id']) ?>">
                            <div class="ui-agenda-item-content ui-agenda-item-heading">
                                <h3 data-ui-patterns-target="agendaTitle"><?= h($item['title']) ?></h3>
                                <p class="text-muted mb-0"><?= h($item['detail']) ?></p>
                            </div>
                            <?php if ($canManage) : ?>
                                <div class="ui-agenda-move ui-agenda-item-actions"
                                    aria-label="<?= h(__('Move {0}', $item['title'])) ?>">
                                    <button type="button" class="btn btn-outline-secondary"
                                        data-action="ui-patterns#moveUp"
                                        aria-label="<?= h(__('Move {0} up', $item['title'])) ?>">
                                        <?= $this->Icon->render('arrow-up') ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary"
                                        data-action="ui-patterns#moveDown"
                                        aria-label="<?= h(__('Move {0} down', $item['title'])) ?>">
                                        <?= $this->Icon->render('arrow-down') ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ol>
        <?php endforeach; ?>
        <?php if ($showcaseState === 'empty') : ?>
            <div class="ui-empty" data-ui-patterns-target="agendaEmpty">
                <h3><?= __('This court is ready to build') ?></h3>
                <p><?= __('Add a scheduled bestowal or an announcement to begin.') ?></p>
            </div>
        <?php endif; ?>
        <div class="ui-context-note">
            <?= $this->Icon->render('info-circle') ?>
            <span>
                <?= __('Agenda order does not mark awards as presented. Bestowal completion is a separate action.') ?>
            </span>
        </div>
    </section>

    <?php if ($canManage) : ?>
        <aside class="offcanvas-lg offcanvas-end ui-workspace-tools" tabindex="-1" id="workspace-tools"
            aria-labelledby="workspace-tools-title">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title" id="workspace-tools-title"><?= __('Add to this court') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#workspace-tools"
                    aria-label="<?= __('Close court tools') ?>"></button>
            </div>
            <div class="offcanvas-body">
                <section class="ui-surface" aria-labelledby="workspace-available-title">
                    <h2 class="ui-section-heading" id="workspace-available-title"><?= __('Scheduled bestowals') ?></h2>
                    <p class="text-muted"><?= __('Eligible for this gathering and not yet on an agenda.') ?></p>
                    <div class="ui-available-item">
                        <div>
                            <h3><?= __('Rowan of Willowbank') ?></h3><p><?= __('Award of arms · Scroll ready') ?></p>
                        </div>
                        <button type="button" class="btn btn-outline-primary" data-action="ui-patterns#addAgendaItem"
                            data-item-id="rowan" data-title="<?= __('Rowan of Willowbank') ?>"
                            data-detail="<?= __('Award of arms · Scroll ready · 3 min') ?>"
                            aria-label="<?= __('Add Rowan of Willowbank to this court') ?>">
                            <?= $this->Icon->render('plus') ?> <?= __('Add') ?>
                        </button>
                    </div>
                    <div class="ui-available-item">
                        <div><h3><?= __('Maren the Scribe') ?></h3><p><?= __('Arts award · Scroll ready') ?></p></div>
                        <button type="button" class="btn btn-outline-primary" data-action="ui-patterns#addAgendaItem"
                            data-item-id="maren" data-title="<?= __('Maren the Scribe') ?>"
                            data-detail="<?= __('Arts award · Scroll ready · 3 min') ?>"
                            aria-label="<?= __('Add Maren the Scribe to this court') ?>">
                            <?= $this->Icon->render('plus') ?> <?= __('Add') ?>
                        </button>
                    </div>
                </section>
                <section class="ui-surface" aria-labelledby="workspace-announcement-title">
                    <h2 class="ui-section-heading" id="workspace-announcement-title"><?= __('Announcements') ?></h2>
                    <p class="text-muted"><?= __('Leave space for words of welcome or gathering business.') ?></p>
                    <button type="button" class="btn btn-outline-secondary" data-action="ui-patterns#addAgendaItem"
                        data-title="<?= __('Gathering announcement') ?>"
                        data-detail="<?= __('Herald notes · 2 min') ?>">
                        <?= $this->Icon->render('plus') ?> <?= __('Add announcement') ?>
                    </button>
                </section>
            </div>
        </aside>
    <?php endif; ?>
</div>
