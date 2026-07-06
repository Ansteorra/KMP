<?php

/**
 * Public Kingdom Calendar
 *
 * Read-only, list-first view of gatherings published to the kingdom calendar.
 * Requires no authentication (issue #60). Events are grouped by month and show
 * web links (#59) and royal progress (#61/#63) inline.
 *
 * @var \App\View\AppView $this
 * @var array<string, array<\App\Model\Entity\Gathering>> $gatheringsByMonth Keyed by "Y-m"
 */

use Cake\I18n\DateTime;

$siteTitle = $this->KMP->getAppSetting('KMP.ShortSiteTitle');
$this->assign('title', $siteTitle . ': ' . __('Kingdom Calendar'));
$this->append('css', $this->Vite->css('gatherings_public'));

$feedUrl = $this->Url->build(
    ['controller' => 'Gatherings', 'action' => 'feed'],
    ['fullBase' => true],
);
$webcalUrl = preg_replace('/^https?:/', 'webcal:', $feedUrl);
?>
<div class="kingdom-calendar-page">
    <header class="kc-header">
        <div class="kc-header-ornament" aria-hidden="true">&#x2694;</div>
        <h1 class="kc-title"><?= __('Kingdom Calendar') ?></h1>
        <p class="kc-subtitle"><?= h($siteTitle) ?> &mdash; <?= __('Upcoming Events') ?></p>
        <div class="kc-actions">
            <a href="<?= h($webcalUrl) ?>" class="kc-subscribe-link">
                <i class="bi bi-calendar-plus"></i> <?= __('Subscribe to Calendar') ?>
            </a>
        </div>
        <div class="kc-legend">
            <span class="kc-legend-item">
                <span class="kc-progress-icon" aria-hidden="true">&#x1F451;</span>
                <?= __('Royal Progress') ?>
            </span>
        </div>
    </header>

    <main class="kc-list">
        <?php if (empty($gatheringsByMonth)): ?>
            <div class="kc-empty">
                <i class="bi bi-calendar-x"></i>
                <p><?= __('No published events on the calendar yet. Check back soon!') ?></p>
            </div>
        <?php endif; ?>

        <?php foreach ($gatheringsByMonth as $monthKey => $gatherings): ?>
            <?php $monthDate = DateTime::createFromFormat('Y-m-d', $monthKey . '-01'); ?>
            <section class="kc-month">
                <h2 class="kc-month-header"><?= h($monthDate->format('F Y')) ?></h2>

                <?php foreach ($gatherings as $gathering): ?>
                    <?php
                    $isCancelled = $gathering->is_cancelled ?? false;
                    $hasPublicPage = (bool)$gathering->public_page_enabled;
                    $progressAttendances = $gathering->gathering_attendances ?? [];
                    $typeColor = $gathering->gathering_type->color ?? null;
                    ?>
                    <article class="kc-event<?= $isCancelled ? ' kc-event-cancelled' : '' ?>">
                        <div class="kc-event-date" aria-hidden="true">
                            <span class="kc-event-day">
                                <?= $this->Timezone->format($gathering->start_date, 'j', false, null, $gathering) ?>
                            </span>
                            <span class="kc-event-dow">
                                <?= $this->Timezone->format($gathering->start_date, 'D', false, null, $gathering) ?>
                            </span>
                        </div>

                        <div class="kc-event-body">
                            <div class="kc-event-title-row">
                                <?php if ($hasPublicPage): ?>
                                    <a class="kc-event-name"
                                        href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'public-landing', $gathering->public_id]) ?>">
                                        <?= h($gathering->name) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="kc-event-name"><?= h($gathering->name) ?></span>
                                <?php endif; ?>

                                <?php if (!empty($progressAttendances)): ?>
                                    <span class="kc-progress-icon"
                                        title="<?= h(__('Royal Progress')) ?>"
                                        aria-label="<?= h(__('Royal Progress')) ?>">&#x1F451;</span>
                                <?php endif; ?>

                                <?php if ($isCancelled): ?>
                                    <span class="kc-badge kc-badge-cancelled"><?= __('Cancelled') ?></span>
                                <?php endif; ?>

                                <span class="kc-badge kc-badge-type"
                                    <?php if ($typeColor): ?>style="--kc-type-color: <?= h($typeColor) ?>;"<?php endif; ?>>
                                    <?= h($gathering->gathering_type->name) ?>
                                </span>
                            </div>

                            <div class="kc-event-meta">
                                <span class="kc-meta-item">
                                    <i class="bi bi-calendar3"></i>
                                    <?php if ($gathering->is_multi_day): ?>
                                        <?= $this->Timezone->format($gathering->start_date, 'M j', false, null, $gathering) ?>
                                        &ndash; <?= $this->Timezone->format($gathering->end_date, 'M j, Y', false, null, $gathering) ?>
                                    <?php else: ?>
                                        <?= $this->Timezone->format($gathering->start_date, 'M j, Y g:i A', false, null, $gathering) ?>
                                        &ndash; <?= $this->Timezone->format($gathering->end_date, 'g:i A', false, null, $gathering) ?>
                                        <?php if (!empty($gathering->timezone)): ?>
                                            (<?= $this->Timezone->getAbbreviation($gathering->start_date, $gathering->timezone) ?>)
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </span>

                                <span class="kc-meta-item">
                                    <i class="bi bi-building"></i>
                                    <?= h($gathering->branch->name) ?>
                                </span>

                                <?php if (!empty($gathering->location)): ?>
                                    <span class="kc-meta-item">
                                        <i class="bi bi-geo-alt"></i>
                                        <?= h($gathering->location) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($gathering->website_url)): ?>
                                    <span class="kc-meta-item">
                                        <i class="bi bi-link-45deg"></i>
                                        <a href="<?= h($gathering->website_url) ?>" target="_blank" rel="noopener">
                                            <?= h(preg_replace('/^https?:\/\/(www\.)?/', '', $gathering->website_url)) ?>
                                        </a>
                                    </span>
                                <?php endif; ?>

                                <span class="kc-meta-item">
                                    <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'downloadCalendar', $gathering->public_id]) ?>"
                                        title="<?= h(__('Download calendar file (.ics)')) ?>">
                                        <i class="bi bi-download"></i> <?= __('iCal') ?>
                                    </a>
                                </span>
                            </div>

                            <?php if (!empty($progressAttendances)): ?>
                                <div class="kc-event-progress">
                                    <strong><?= __('Progress:') ?></strong>
                                    <?= h(implode(', ', array_map(
                                        fn($attendance) => $attendance->progress_title
                                            . (!empty($attendance->member->sca_name) ? ' (' . $attendance->member->sca_name . ')' : ''),
                                        $progressAttendances,
                                    ))) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </main>

    <footer class="kc-footer">
        <p>
            <?= __('Events are published to this calendar by the kingdom calendar staff.') ?>
            <a href="<?= h($webcalUrl) ?>"><?= __('Subscribe') ?></a>
            <?= __('to keep your personal calendar up to date.') ?>
        </p>
    </footer>
</div>
