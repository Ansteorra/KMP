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
 * @var array<int, string> $activityOptions Activity id => name for the filter bar
 * @var array<int> $selectedActivityIds Currently applied activity filter
 */

use Cake\I18n\DateTime;

// Circles are activities flagged is_circle (Laurel Circle, Pelican Circle, etc.)
$isCircleActivity = fn($activity) => (bool)$activity->is_circle;
$hasCircle = fn($gathering) => (bool)array_filter(
    $gathering->gathering_activities ?? [],
    $isCircleActivity,
);

$siteTitle = $this->KMP->getAppSetting('KMP.ShortSiteTitle');
$this->assign('title', $siteTitle . ': ' . __('Kingdom Calendar'));

// Base stylesheet + the manuscript type pairing this design is built on.
$this->append('css', implode('', [
    '<link rel="preconnect" href="https://fonts.googleapis.com">',
    '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
    '<link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Spectral:wght@400;500;600;700&display=swap" rel="stylesheet">',
]));
$this->append('css', $this->Vite->css('gatherings_public'));

// Admin-authored theme overrides (App Setting), injected AFTER the base
// stylesheet so a kingdom's custom CSS wins. Guard against tag breakout since
// this is raw CSS placed inside <style>.
$customCalendarCss = (string)$this->KMP->getAppSetting('Plugin.PublicGatherings.CustomCSS', '');
if (trim($customCalendarCss) !== '') {
    $this->append('css', '<style>' . str_ireplace('</style', '<\/style', $customCalendarCss) . '</style>');
}

$feedUrl = $this->Url->build(
    ['controller' => 'Gatherings', 'action' => 'feed'],
    ['fullBase' => true],
);
$webcalUrl = preg_replace('/^https?:/', 'webcal:', $feedUrl);
?>
<div class="kingdom-calendar-page">
    <header class="kc-header">
        <div class="kc-header-ornament" aria-hidden="true">&#x2766;</div>
        <span class="kc-eyebrow"><?= __('The Kingdom Calendar of') ?> <?= h($siteTitle) ?></span>
        <h1 class="kc-title"><?= __('Upcoming Events') ?></h1>
        <p class="kc-subtitle"><?= __('Tourneys, courts, feasts, and gatherings across the realm') ?></p>
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
            <span class="kc-legend-item">
                <i class="bi bi-record-circle kc-circle-icon" aria-hidden="true"></i>
                <?= __('Order Circle') ?>
            </span>
        </div>

        <?php if (!empty($activityOptions)): ?>
            <form method="get" action="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'publicCalendar']) ?>"
                class="kc-filter-bar">
                <fieldset class="kc-filter-fieldset">
                    <legend class="kc-filter-legend"><?= __('Filter by activity') ?></legend>
                    <div class="kc-filter-options">
                        <?php foreach ($activityOptions as $activityId => $activityName): ?>
                            <label class="kc-filter-chip">
                                <input type="checkbox" name="activities[]" value="<?= (int)$activityId ?>"
                                    <?= in_array((int)$activityId, $selectedActivityIds, true) ? 'checked' : '' ?>>
                                <span><?= h($activityName) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="kc-filter-actions">
                        <button type="submit" class="kc-filter-apply"><?= __('Apply Filter') ?></button>
                        <?php if (!empty($selectedActivityIds)): ?>
                            <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'publicCalendar']) ?>"
                                class="kc-filter-clear"><?= __('Clear') ?></a>
                        <?php endif; ?>
                    </div>
                </fieldset>
            </form>
        <?php endif; ?>
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
                    // The event's web link: the KMP public page when enabled
                    // (it supersedes the Event Website field), else the
                    // external website URL when populated.
                    $eventUrl = null;
                    $eventUrlIsExternal = false;
                    if ($hasPublicPage) {
                        $eventUrl = $this->Url->build([
                            'controller' => 'Gatherings',
                            'action' => 'public-landing',
                            $gathering->public_id,
                        ]);
                    } elseif (!empty($gathering->website_url)) {
                        $eventUrl = $gathering->website_url;
                        $eventUrlIsExternal = true;
                    }
                    ?>
                    <article class="kc-event<?= $isCancelled ? ' kc-event-cancelled' : '' ?>">
                        <div class="kc-event-daterail" aria-hidden="true">
                            <div class="kc-event-date">
                                <span class="kc-event-month">
                                    <?= $this->Timezone->format($gathering->start_date, 'M', false, null, $gathering) ?>
                                </span>
                                <span class="kc-event-day">
                                    <?= $this->Timezone->format($gathering->start_date, 'j', false, null, $gathering) ?>
                                </span>
                                <span class="kc-event-dow">
                                    <?= $this->Timezone->format($gathering->start_date, 'D', false, null, $gathering) ?>
                                </span>
                            </div>
                            <?php if ($gathering->is_multi_day): ?>
                                <?php
                                // Count calendar days in the event's own timezone (date-only,
                                // so a Fri 5pm–Sun 3pm event counts as 3 days, not 2).
                                $startLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, null, null, $gathering);
                                $endLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, null, null, $gathering);
                                $dayCount = 0;
                                if ($startLocal && $endLocal) {
                                    $startDay = \Cake\I18n\Date::parse($startLocal->format('Y-m-d'));
                                    $endDay = \Cake\I18n\Date::parse($endLocal->format('Y-m-d'));
                                    $dayCount = (int)$startDay->diffInDays($endDay) + 1;
                                }
                                ?>
                                <span class="kc-event-through">
                                    <?= $dayCount > 0
                                        ? h(__n('{0} day', '{0} days', $dayCount, $dayCount))
                                        : __('multi-day') ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="kc-event-body">
                            <div class="kc-event-title-row">
                                <?php if ($eventUrl !== null): ?>
                                    <a class="kc-event-name" href="<?= h($eventUrl) ?>"
                                        <?= $eventUrlIsExternal ? 'target="_blank" rel="noopener"' : '' ?>
                                        title="<?= h($eventUrlIsExternal ? __('Open the event website') : __('View the event page')) ?>"><?= h($gathering->name) ?></a>
                                <?php else: ?>
                                    <span class="kc-event-name"><?= h($gathering->name) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="kc-event-tags">
                                <?php if ($isCancelled): ?>
                                    <span class="kc-badge kc-badge-cancelled"><?= __('Cancelled') ?></span>
                                <?php endif; ?>

                                <span class="kc-badge kc-badge-type"
                                    <?php if ($typeColor): ?>style="--kc-type-color: <?= h($typeColor) ?>;"<?php endif; ?>>
                                    <?= h($gathering->gathering_type->name) ?>
                                </span>

                                <?php if (!empty($progressAttendances)): ?>
                                    <span class="kc-progress-icon"
                                        title="<?= h(__('Royal Progress')) ?>"
                                        aria-label="<?= h(__('Royal Progress')) ?>">&#x1F451;</span>
                                <?php endif; ?>

                                <?php if ($hasCircle($gathering)): ?>
                                    <i class="bi bi-record-circle kc-circle-icon"
                                        title="<?= h(__('Order Circle')) ?>"
                                        aria-label="<?= h(__('Order Circle')) ?>"></i>
                                <?php endif; ?>
                            </div>

                            <div class="kc-event-meta">
                                <span class="kc-meta-item kc-meta-when">
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
                            </div>

                            <?php // The title is the event-page link; the only standalone
                                  // action is Pre-Register (an external, time-sensitive CTA). ?>
                            <?php if ($gathering->is_preregistration_open): ?>
                                <div class="kc-event-actions">
                                    <a class="kc-action kc-action-prereg" href="<?= h($gathering->preregister_url) ?>"
                                        target="_blank" rel="noopener"
                                        title="<?= h(__('Pre-register and pay for this event (external site)')) ?>">
                                        <i class="bi bi-ticket-perforated"></i> <?= __('Pre-Register') ?>
                                        <?php if ($gathering->preregister_closes_on !== null): ?>
                                            <small><?= __('until {0}', h($gathering->preregister_closes_on->format('M j'))) ?></small>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php
                            // Secondary detail folds behind a "Details" drawer so the mobile
                            // list stays scannable. iCal lives here too (always present, so
                            // the drawer renders on every event).
                            $hasActivities = !empty($gathering->gathering_activities);
                            $hasProgress = !empty($progressAttendances);
                            if ($hasActivities && $hasProgress) {
                                $moreLabel = __('Activities & royal progress');
                            } elseif ($hasActivities) {
                                $moreLabel = __n('{0} activity', '{0} activities', count($gathering->gathering_activities), count($gathering->gathering_activities));
                            } elseif ($hasProgress) {
                                $moreLabel = __('Royal progress');
                            } else {
                                $moreLabel = __('Add to calendar');
                            }
                            ?>
                            <details class="kc-more">
                                <summary class="kc-more-summary">
                                    <span><?= h($moreLabel) ?></span>
                                    <i class="bi bi-chevron-down kc-more-chevron" aria-hidden="true"></i>
                                </summary>
                                <div class="kc-more-body">
                                    <?php if ($hasActivities): ?>
                                        <div class="kc-event-activities">
                                            <?php foreach ($gathering->gathering_activities as $activity): ?>
                                                <span class="kc-activity-chip<?= $isCircleActivity($activity) ? ' kc-activity-chip-circle' : '' ?>">
                                                    <?php if ($isCircleActivity($activity)): ?>
                                                        <i class="bi bi-record-circle" aria-hidden="true"></i>
                                                    <?php endif; ?>
                                                    <?= h($activity->name) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($hasProgress): ?>
                                        <div class="kc-event-progress">
                                            <strong><?= __('Progress:') ?></strong>
                                            <?= h(implode(', ', array_map(
                                                fn($attendance) => $attendance->progress_title
                                                    . (!empty($attendance->member->sca_name) ? ' (' . $attendance->member->sca_name . ')' : ''),
                                                $progressAttendances,
                                            ))) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="kc-more-actions">
                                        <a class="kc-action" href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'downloadCalendar', $gathering->public_id]) ?>"
                                            title="<?= h(__('Download calendar file (.ics)')) ?>">
                                            <i class="bi bi-download"></i> <?= __('Add to calendar (.ics)') ?>
                                        </a>
                                    </div>
                                </div>
                            </details>
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
