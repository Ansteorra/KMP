<?php
declare(strict_types=1);

use App\Services\ViewCellRegistry;

/** @var \App\View\AppView $this */
$this->extend('/layout/TwitterBootstrap/dashboard');
$this->assign('title', $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': ' . $gathering->name);
$user = $this->getRequest()->getAttribute('identity');
$publicLandingUrl = $this->Url->build(['action' => 'publicLanding', $gathering->public_id], ['fullBase' => true]);
$hasRecordDetails = trim($this->fetch('recordDetails')) !== ''
    || !empty($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_DETAIL]);
$statusLabels = [
    'cancelled' => __('Cancelled'), 'past' => __('Ended'),
    'ongoing' => __('Happening now'), 'upcoming' => __('Upcoming'),
];
$pluginOptions = [
    'pluginViewCells' => $pluginViewCells, 'id' => $recordId,
    'model' => $recordModel, 'activateFirst' => false,
];
?>
<article class="gathering-reader" aria-labelledby="gathering-title">
    <header class="gathering-reader-header">
        <div class="d-flex flex-wrap align-items-center gap-2 text-muted mb-2">
            <?= $this->element('backButton', ['text' => __('Back')]) ?>
            <span aria-hidden="true">/</span>
            <span><?= h($gathering->gathering_type->name) ?></span>
            <span class="badge <?= $readerStatus === 'cancelled' ? 'text-bg-danger' : 'text-bg-light border' ?>">
                <?= h($statusLabels[$readerStatus]) ?>
            </span>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="gathering-reader-heading">
                <h1 id="gathering-title"><?= h($gathering->name) ?></h1>
                <p class="text-muted mb-0"><?= __('Hosted by {0}', $gathering->branch->name) ?></p>
            </div>
            <div class="gathering-reader-actions d-flex flex-wrap gap-2 align-items-start">
                <?php if ($readerCanRsvp) : ?>
                    <button type="button" class="btn btn-success"
                        data-bs-toggle="modal" data-bs-target="#attendGatheringModal">
                        <?= $this->Icon->render('calendar-check') ?>
                        <?= $userAttendance ? __('Update Attendance') : __('Attend This Gathering') ?>
                    </button>
                <?php endif; ?>
                <?= $this->fetch('recordActions') ?>
            </div>
        </div>
        <?php if ($gathering->is_cancelled) : ?>
            <div class="alert alert-danger mt-3 mb-0">
                <strong><?= __('This gathering has been cancelled.') ?></strong>
                <?php if ($gathering->cancellation_reason) :
                    ?><?= h($gathering->cancellation_reason) ?><?php
                endif; ?>
            </div>
        <?php endif; ?>
        <dl class="gathering-reader-facts">
            <div>
                <dt><?= $this->Icon->render('calendar-event') ?> <?= __('When') ?></dt>
                <dd>
                    <?= $this->Timezone->format(
                        $gathering->start_date,
                        'D, M j, Y · g:i A',
                        false,
                        null,
                        $gathering,
                    ) ?><br>
                    <span class="text-muted"><?= __('to') ?> <?= $this->Timezone->format(
                        $gathering->end_date,
                        'D, M j, Y · g:i A',
                        false,
                        null,
                        $gathering,
                    ) ?></span>
                    <small class="d-block text-muted"><?= h($readerTimezone) ?></small>
                </dd>
            </div>
            <div>
                <dt><?= $this->Icon->render('map-pin') ?> <?= __('Where') ?></dt>
                <dd><?= $gathering->location ? nl2br(h($gathering->location)) : __('Location to be announced') ?></dd>
            </div>
            <div>
                <dt><?= $this->Icon->render('calendar-check') ?> <?= __('Your RSVP') ?></dt>
                <dd>
                    <?php if ($userAttendance) : ?>
                        <strong><?= __('You’re planning to attend') ?></strong>
                    <?php else : ?>
                        <?= $readerStatus === 'past' ? __('No attendance recorded') : __('You haven’t RSVP’d yet') ?>
                    <?php endif; ?>
                    <small class="d-block text-muted"><?= __('RSVP and event registration are separate.') ?></small>
                </dd>
            </div>
        </dl>
        <div class="d-flex flex-wrap gap-2 gathering-reader-links">
            <?php if ($readerStatus !== 'past') : ?>
                <?= $this->Html->link(
                    __('Add to Calendar'),
                    ['action' => 'downloadCalendar', $gathering->public_id],
                    ['class' => 'btn btn-outline-secondary btn-sm', 'icon' => 'calendar-plus'],
                ) ?>
            <?php endif; ?>
            <?php if ($readerDirectionsUrl) : ?>
                <?= $this->Html->link(
                    __('Directions'),
                    $readerDirectionsUrl,
                    [
                        'class' => 'btn btn-outline-secondary btn-sm',
                        'icon' => 'map-pin',
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                    ],
                ) ?>
            <?php endif; ?>
            <?php if ($gathering->preregister_url) : ?>
                <?= $this->Html->link(
                    __('Event registration'),
                    $gathering->preregister_url,
                    [
                        'class' => 'btn btn-outline-secondary btn-sm',
                        'icon' => 'external-link',
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                    ],
                ) ?>
            <?php endif; ?>
            <?php if ($gathering->website_url) : ?>
                <?= $this->Html->link(
                    __('Event website'),
                    $gathering->website_url,
                    [
                        'class' => 'btn btn-outline-secondary btn-sm',
                        'icon' => 'link',
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                    ],
                ) ?>
            <?php endif; ?>
            <?php if ($gathering->public_page_enabled) : ?>
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <?= $this->Icon->render('share') ?> <?= __('Share Event') ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <?= $this->Html->link(
                                __('View Public Page'),
                                $publicLandingUrl,
                                [
                                'class' => 'dropdown-item',
                                'target' => '_blank',
                                'rel' => 'noopener noreferrer',
                                'icon' => 'external-link',
                                ],
                            ) ?>
                        </li>
                        <li><button type="button" class="dropdown-item"
                            data-controller="clipboard" data-action="clipboard#copy"
                            data-clipboard-text-value="<?= h($publicLandingUrl) ?>"
                            data-clipboard-success-message-value="<?= h(__('Link copied to clipboard!')) ?>">
                            <?= __('Copy Link') ?>
                        </button></li>
                        <li><button type="button" class="dropdown-item"
                            data-bs-toggle="modal" data-bs-target="#qrCodeModal"><?= __('Show QR Code') ?></button></li>
                    </ul>
                </div>
            <?php elseif ($canEditGathering) : ?>
                <span class="small text-muted align-self-center">
                    <?= __('Public landing page is disabled for this gathering.') ?>
                </span>
            <?php endif; ?>
        </div>
    </header>

    <div data-controller="detail-tabs" class="gathering-reader-tabs">
        <nav aria-label="<?= h(__('Gathering sections')) ?>">
            <div class="nav nav-tabs" id="nav-tabButtons" role="tablist">
                <button class="nav-link active" id="nav-overview-tab" type="button" role="tab" data-bs-toggle="tab"
                    data-bs-target="#nav-overview" aria-controls="nav-overview" aria-selected="true"
                    data-detail-tabs-target="tabBtn" data-tab-order="0" style="order: 0;"><?= __('Overview') ?></button>
                <?= $this->fetch('tabButtons') ?>
                <?= $this->element('pluginTabButtons', $pluginOptions) ?>
                <?php if ($hasRecordDetails) : ?>
                    <button class="nav-link" id="nav-details-tab" type="button" role="tab" data-bs-toggle="tab"
                        data-bs-target="#nav-details" aria-controls="nav-details" aria-selected="false"
                        data-detail-tabs-target="tabBtn" data-tab-order="100" style="order: 100;">
                        <?= __('Record details') ?>
                    </button>
                <?php endif; ?>
            </div>
        </nav>
        <div class="tab-content" id="nav-tabContent">
            <div class="tab-pane fade show active" id="nav-overview" role="tabpanel" aria-labelledby="nav-overview-tab"
                data-detail-tabs-target="tabContent" data-tab-order="0" style="order: 0;" tabindex="0">
                <?= $this->element('gatherings/readerOverview') ?>
            </div>
            <?= $this->fetch('tabContent') ?>
            <?= $this->element('pluginTabBodies', $pluginOptions) ?>
            <?php if ($hasRecordDetails) : ?>
                <div class="tab-pane fade p-3" id="nav-details" role="tabpanel" aria-labelledby="nav-details-tab"
                    data-detail-tabs-target="tabContent" data-tab-order="100" style="order: 100;" tabindex="0">
                    <h2 class="h5"><?= __('Record details') ?></h2>
                    <div class="table-responsive"><table class="table table-striped"><tbody>
                        <?= $this->fetch('recordDetails') ?>
                        <?= $this->element('pluginDetailBodies', $pluginOptions) ?>
                    </tbody></table></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</article>
<?php
if ($readerCanRsvp) {
    $modalData = compact('gathering', 'userAttendance', 'user');
    $this->append('modals', $this->element('gatherings/attendGatheringModal', $modalData));
}
if ($gathering->public_page_enabled) {
    $this->append('modals', $this->element('gatherings/shareModal', compact('publicLandingUrl')));
}
