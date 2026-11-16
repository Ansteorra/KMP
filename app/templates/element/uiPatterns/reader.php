<?php
declare(strict_types=1);

$canManage = $showcaseState !== 'restricted';
$readerActions = [[
    'label' => __('Attend this gathering'), 'icon' => 'calendar-check', 'primary' => true,
    'modal' => 'reader-attendance',
]];
if ($canManage) {
    $readerActions[] = ['label' => __('Edit details'), 'icon' => 'pencil', 'modal' => 'reader-edit'];
}
echo $this->element('uiPatterns/pageHeader', [
    'back' => true, 'eyebrow' => __('Gathering'), 'title' => __('Oakmere Winter Court'),
    'description' => __('Hosted by the Barony of Oakmere'),
    'badges' => [['label' => __('Upcoming'), 'tone' => 'neutral']],
    'actions' => $readerActions,
]);
?>
<dl class="ui-summary" aria-label="<?= __('At a glance') ?>">
    <div>
        <dt><?= $this->Icon->render('calendar-event') ?> <?= __('When') ?></dt>
        <dd><time datetime="2026-12-05T09:00:00-06:00"><?= __('Saturday, December 5') ?></time>
            <span class="d-block small text-muted"><?= __('9 am–8 pm · Central Time') ?></span></dd>
    </div>
    <div>
        <dt><?= $this->Icon->render('map-pin') ?> <?= __('Where') ?></dt>
        <dd><?= __('Oakmere Community Hall') ?>
            <span class="d-block small text-muted"><?= __('Indoor hall and outdoor activity field') ?></span></dd>
    </div>
    <div>
        <dt><?= $this->Icon->render('calendar-check') ?> <?= __('Your RSVP') ?></dt>
        <dd id="reader-rsvp-summary"><?= __('Not yet responded') ?></dd>
    </div>
</dl>
<nav class="ui-anchor-nav" aria-label="<?= __('On this page') ?>">
    <a href="#reader-about"><?= __('About') ?></a>
    <a href="#reader-schedule"><?= __('Schedule') ?></a>
    <a href="#reader-activities"><?= __('Activities') ?></a>
    <a href="#reader-contacts"><?= __('Contacts') ?></a>
</nav>
<div class="ui-content-grid">
    <div class="ui-main-column">
        <section class="ui-surface" id="reader-about" aria-labelledby="reader-about-title">
            <h2 id="reader-about-title"><?= __('About this gathering') ?></h2>
            <p><?= __('Join us for archery, scribal arts, kingdom court, and a bardic circle.') ?></p>
            <p class="mb-0">
                <?= __('The hall has step-free access and accessible restrooms. Bring drinking water and a chair.') ?>
                <?= __('Field activities are weather dependent.') ?>
            </p>
        </section>
        <section class="ui-surface" id="reader-schedule" aria-labelledby="reader-schedule-title">
            <div class="ui-section-heading">
                <h2 id="reader-schedule-title"><?= __('Schedule') ?></h2>
                <span class="text-muted small"><?= __('Central Time') ?></span>
            </div>
            <?php if ($showcaseState === 'error') : ?>
                <div class="alert alert-danger" role="alert">
                    <p class="mb-2">
                        <?= __('The schedule could not be loaded. Gathering details are still available.') ?>
                    </p>
                    <a class="btn btn-outline-danger" href="<?= h($this->Url->build([
                        'controller' => 'UiPatterns', 'action' => 'index', '?' => ['pattern' => 'reader'],
                    ])) ?>"><?= __('Try again') ?></a>
                </div>
            <?php elseif ($showcaseState === 'empty') : ?>
                <div class="ui-empty">
                    <h3><?= __('The schedule is not published yet') ?></h3>
                    <p class="mb-0"><?= __('Check back for activity times, or contact the event steward.') ?></p>
                </div>
            <?php else : ?>
                <ol class="ui-schedule-list">
                    <?php foreach (
                    [
                        ['09:00', '9 am', 'Gate opens', 'Check in at the hall entrance.', 'door-enter'],
                        ['10:00', '10 am', 'Archery', 'Outdoor field · equipment inspection first.', 'archery'],
                        ['13:00', '1 pm', 'Scribal workshop', 'Main hall · materials provided.', 'scribal'],
                        ['17:00', '5 pm', 'Kingdom court', 'Main hall · seating opens at 4:45 pm.', 'court-crown'],
                        ['18:00', '6 pm', 'Baronial court', 'Main hall · following kingdom court.', 'court-coronet'],
                        ['19:00', '7 pm', 'Bardic circle', 'Main hall · songs and stories welcome.', 'bardic-circle'],
                    ] as [$time, $label, $name, $description, $icon]
) : ?>
                        <li class="ui-schedule-item">
                            <time class="ui-schedule-time" datetime="<?= h('2026-12-05T' . $time . ':00-06:00') ?>">
                                <?= h($label) ?>
                            </time>
                            <div class="ui-schedule-body">
                                <h3 class="h6 mb-1"><?= $this->Icon->render($icon) ?> <?= h($name) ?></h3>
                                <p class="text-muted mb-0"><?= h($description) ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
    <aside class="ui-aside" aria-label="<?= __('Gathering details') ?>">
        <section class="ui-surface" id="reader-activities" aria-labelledby="reader-activities-title">
            <h2 id="reader-activities-title"><?= __('Activities') ?></h2>
            <ul class="ui-activity-list list-unstyled mb-0">
                <?php foreach (
                    ['court-crown' => 'Kingdom court', 'court-coronet' => 'Baronial court', 'archery' => 'Archery',
                    'scribal' => 'Scribal arts', 'bardic-circle' => 'Bardic circle'] as $icon => $label
) : ?>
                    <li><?= $this->Icon->render($icon) ?> <?= h(__($label)) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canManage) : ?>
                <a class="btn btn-outline-secondary mt-3" href="<?= h($this->Url->build([
                    'controller' => 'UiPatterns', 'action' => 'index', '?' => ['pattern' => 'workspace'],
                ])) ?>"><?= $this->Icon->render('list-details') ?> <?= __('Open court agenda') ?></a>
            <?php endif; ?>
        </section>
        <section class="ui-surface" aria-labelledby="reader-progress-title">
            <h2 id="reader-progress-title"><?= $this->Icon->render('crown') ?> <?= __('Royal Progress') ?></h2>
            <p class="mb-1"><?= __('Crown of Ansteorra') ?></p>
            <p class="small text-muted mb-0"><?= __('Attendance shared with the kingdom') ?></p>
        </section>
        <section class="ui-surface" id="reader-contacts" aria-labelledby="reader-contacts-title">
            <h2 id="reader-contacts-title"><?= __('Contacts') ?></h2>
            <dl class="mb-0">
                <dt><?= __('Event steward') ?></dt>
                <dd><?= __('Alys of Oakmere') ?></dd>
                <dt><?= __('Contact') ?></dt>
                <dd class="mb-0"><a href="mailto:steward@example.test">steward@example.test</a></dd>
            </dl>
        </section>
    </aside>
</div>
<div class="ui-task-footer d-md-none">
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#reader-attendance">
        <?= $this->Icon->render('calendar-check') ?> <?= __('Update your attendance') ?>
    </button>
</div>
<div class="modal fade" id="reader-attendance" tabindex="-1"
    aria-labelledby="reader-attendance-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <form class="modal-content" data-action="submit->ui-patterns#savePreview"
            data-update-target="#reader-rsvp-summary" data-update-field="attendance"
            data-message="<?= __('Your RSVP has been updated in this review session.') ?>">
            <div class="modal-header">
                <h2 class="modal-title h5" id="reader-attendance-title"><?= __('Your attendance') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold"><?= __('Oakmere Winter Court') ?></p>
                <label for="reader-attendance-choice" class="form-label"><?= __('Will you attend?') ?></label>
                <select id="reader-attendance-choice" name="attendance" class="form-select mb-3">
                    <option><?= __('Attending') ?></option><option><?= __('Not attending') ?></option>
                </select>
                <fieldset class="mb-3">
                    <legend class="fs-6"><?= __('Share your name and note with') ?></legend>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="reader-visibility-host"
                            name="share_with_hosting_group" value="1">
                        <label class="form-check-label" for="reader-visibility-host"><?= __('Hosting group') ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="reader-visibility-crown"
                            name="share_with_crown" value="1">
                        <label class="form-check-label" for="reader-visibility-crown">
                            <?= __('Nobility and Crown') ?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="reader-visibility-kingdom"
                            name="share_with_kingdom" value="1">
                        <label class="form-check-label" for="reader-visibility-kingdom">
                            <?= __('Kingdom members') ?>
                        </label>
                    </div>
                </fieldset>
                <label for="reader-attendance-notes" class="form-label"><?= __('Attendance note') ?></label>
                <textarea id="reader-attendance-notes" name="public_note" class="form-control" rows="3"
                    aria-describedby="reader-attendance-help"></textarea>
                <div class="form-text" id="reader-attendance-help">
                    <?= __('Shared only with the audiences you choose. The total RSVP count remains visible.') ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                    <?= __('Cancel') ?>
                </button>
                <button class="btn btn-primary" type="submit"><?= __('Save RSVP') ?></button>
            </div>
        </form>
    </div>
</div>
<?php if ($canManage) : ?>
    <div class="modal fade" id="reader-edit" tabindex="-1" aria-labelledby="reader-edit-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
            <form class="modal-content" data-action="submit->ui-patterns#savePreview"
                data-update-target=".ui-page-header h1" data-update-field="name"
                data-message="<?= __('Gathering name updated in this review session.') ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="reader-edit-title"><?= __('Edit gathering details') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?= __('Close') ?>"></button>
                </div>
                <div class="modal-body">
                    <label for="reader-edit-name" class="form-label"><?= __('Gathering name') ?></label>
                    <input id="reader-edit-name" name="name" class="form-control" value="Oakmere Winter Court" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                        <?= __('Cancel') ?>
                    </button>
                    <button class="btn btn-primary" type="submit"><?= __('Save changes') ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
