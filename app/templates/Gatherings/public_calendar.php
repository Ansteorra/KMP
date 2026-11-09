<?php
declare(strict_types=1);

use App\Model\Entity\GatheringActivity;

/** @var \App\View\AppView $this */
$siteTitle = $this->KMP->getAppSetting('KMP.ShortSiteTitle');
$this->assign('title', $siteTitle . ': ' . __('Kingdom Calendar'));
$this->append('css', $this->Vite->css('gatherings_public'));
$customCalendarCss = (string)$this->KMP->getAppSetting('Plugin.PublicGatherings.CustomCSS', '');
if (trim($customCalendarCss) !== '') {
    $this->append('css', '<style>' . str_ireplace('</style', '<\\/style', $customCalendarCss) . '</style>');
}
$calendarUrl = $this->Url->build(['controller' => 'Gatherings', 'action' => 'publicCalendar']);
$feedUrl = $this->Url->build([
    'controller' => 'Gatherings', 'action' => 'feed',
], ['fullBase' => true]);
$webcalUrl = preg_replace('/^https?:/', 'webcal:', $feedUrl);
?>
<div class="kingdom-calendar-page" data-controller="public-calendar"
    data-public-calendar-pending-message-value="<?= h(__('Selection changed. Apply filters to update events.')) ?>"
    data-action="focusin->public-calendar#revealFocus">
    <div class="kc-shell">
        <header class="kc-masthead">
            <div class="kc-brand">
                <?= $this->Icon->render('court-crown', ['size' => 36]) ?>
                <div><strong><?= h($siteTitle) ?></strong><span><?= __('Kingdom Calendar') ?></span></div>
            </div>
            <a href="<?= h($webcalUrl) ?>" class="kc-subscribe-link">
                <?= $this->Icon->render('calendar-plus') ?> <?= __('Subscribe to Calendar') ?>
            </a>
        </header>
        <main id="calendar-events">
            <div class="kc-heading">
                <h1><?= __('Find your next event') ?></h1>
                <p><?= __('Tourneys, courts, classes, and gatherings. All in one place.') ?></p>
            </div>
            <form method="get" action="<?= h($calendarUrl) ?>" class="kc-filter-bar"
                data-public-calendar-target="filterBar" aria-label="<?= h(__('Find events')) ?>">
                <details class="kc-filter-settings" data-public-calendar-target="filterDisclosure" open>
                    <summary class="kc-filters-toggle">
                        <?= $this->Icon->render('filter') ?> <?= __('Filters') ?>
                        <span class="kc-filter-hint">
                            <?php if ($search !== '' || $selectedMonth !== '' || $selectedBranchId > 0) : ?>
                                <?php if ($search !== '') : ?>
                                    <span><?= h(__('Search: {0}', $search)) ?></span>
                                <?php endif; ?>
                                <?php if ($selectedMonth !== '') : ?>
                                    <span><?= h($monthOptions[$selectedMonth]) ?></span>
                                <?php endif; ?>
                                <?php if ($selectedBranchId > 0) : ?>
                                    <span><?= h($branchOptions[$selectedBranchId] ?? __('Selected group')) ?></span>
                                <?php endif; ?>
                            <?php else : ?>
                                <?= __('Search, date, group, activities') ?>
                            <?php endif; ?>
                        </span>
                    </summary>
                    <div class="kc-filter-content">
                        <div class="kc-filter-fields">
                            <label for="calendar-search"><?= __('Search events') ?>
                                <input id="calendar-search" type="search" name="q" value="<?= h($search) ?>"
                                    placeholder="<?= h(__('Event name, group, or address')) ?>" maxlength="200">
                            </label>
                            <label for="calendar-month"><?= __('When') ?>
                                <select id="calendar-month" name="month">
                                    <option value=""><?= __('All upcoming months') ?></option>
                                    <?php foreach ($monthOptions as $key => $label) : ?>
                                        <option
                                            value="<?= h($key) ?>"
                                            <?= $selectedMonth === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label for="calendar-branch"><?= __('Hosted by') ?>
                                <select id="calendar-branch" name="branch">
                                    <option value=""><?= __('All groups') ?></option>
                                    <?php foreach ($branchOptions as $id => $name) : ?>
                                        <option
                                            value="<?= (int)$id ?>"
                                            <?= $selectedBranchId === (int)$id ? 'selected' : '' ?>>
                                            <?= h($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="kc-filter-tools">
                            <details class="kc-filter-picker">
                                <summary><?= __('Activities') ?>
                                    <span data-public-calendar-target="activityCount"><?php
                                    $selectedOptionCount = count($selectedActivityIds)
                                        + (int)$circlesOnly + (int)$royalsOnly;
                                    echo $selectedOptionCount ? '(' . $selectedOptionCount . ')' : '';
                                    ?></span></summary>
                                <div class="kc-picker-body">
                                    <label for="calendar-activity-search"><?= __('Find an activity') ?></label>
                                    <input id="calendar-activity-search" type="search" autocomplete="off"
                                        placeholder="<?= h(__('Activity or type')) ?>"

                                            data-public-calendar-target="activitySearch"
                                            data-action="input->public-calendar#filterActivities">
                                    <div class="kc-picker-options">
                                        <?php foreach ($activityGroups as $type => $options) : ?>
                                            <details class="kc-activity-group"
                                                data-public-calendar-target="activityGroup"
                                                data-activity-type="<?= h($type) ?>"
                                                <?= in_array(
                                                    $type,
                                                    ['Royal Progress', GatheringActivity::TYPE_ORDER_CIRCLE],
                                                    true,
                                                )
                                                    ? 'open' : '' ?>>
                                                <summary>
                                                    <?php if ($type === 'Royal Progress') : ?>
                                                        <?= $this->Icon->render('crown') ?>
                                                    <?php elseif ($type === GatheringActivity::TYPE_ORDER_CIRCLE) : ?>
                                                        <?= $this->Icon->render('circle-dot') ?>
                                                    <?php endif; ?>
                                                    <?= h(__($type)) ?>
                                                    <span
                                                        class="kc-group-count"
                                                        data-public-calendar-target="groupCount"></span>
                                                </summary>
                                                <fieldset>
                                                    <legend class="visually-hidden"><?= h(__($type)) ?></legend>
                                                <?php if ($type === GatheringActivity::TYPE_ROYAL_PROGRESS) : ?>
                                                    <?= $this->element('gatherings/public_calendar_activity_option', [
                                                        'name' => 'royals', 'value' => 1,
                                                        'label' => __('Any Royal Progress'),
                                                        'icon' => 'crown', 'selected' => $royalsOnly,
                                                    ]) ?>
                                                <?php elseif ($type === GatheringActivity::TYPE_ORDER_CIRCLE) : ?>
                                                    <?= $this->element('gatherings/public_calendar_activity_option', [
                                                        'name' => 'circles', 'value' => 1,
                                                        'label' => __('Any order circle'),
                                                        'icon' => 'circle-dot', 'selected' => $circlesOnly,
                                                    ]) ?>
                                                <?php endif; ?>
                                                <?php foreach ($options as $id => $name) : ?>
                                                    <?= $this->element('gatherings/public_calendar_activity_option', [
                                                        'name' => 'activities[]', 'value' => $id, 'label' => $name,
                                                        'activity' => $activitiesById[$id],
                                                        'selected' => in_array((int)$id, $selectedActivityIds, true),
                                                    ]) ?>
                                                <?php endforeach; ?>
                                                </fieldset>
                                            </details>
                                        <?php endforeach; ?>
                                    </div>
                                    <p role="status" data-public-calendar-target="activityStatus"></p>
                                </div>
                            </details>
                            <button type="submit" class="kc-button kc-apply"><?= __('Apply filters') ?></button>
                            <?php if ($hasFilters || $selectedMonth !== '') : ?>
                                <a href="<?= h($calendarUrl) ?>"><?= __('Clear filters') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
                <div class="kc-selected-activities" data-public-calendar-target="selectedSummary"
                    <?= $selectedActivityLabels === [] ? 'hidden' : '' ?>>
                    <p><?= __('Selected activities') ?></p>
                    <ul
                        data-public-calendar-target="selectedLabels"
                        tabindex="0"
                        aria-label="<?= h(__('Selected activities')) ?>">
                        <?php foreach ($selectedActivityLabels as $label) : ?>
                            <li><?= h($label) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <p class="kc-selection-status" role="status" data-public-calendar-target="selectionStatus"></p>
            </form>
            <div class="kc-results-heading">
                <p><?= __n('{0} published event', '{0} published events', $eventCount, $eventCount) ?></p>
                <span><?= __('Event local time · every weekend') ?></span>
            </div>
            <p id="calendar-scroll-help" class="kc-scroll-help">
                <span class="kc-wide-help"><?= __('Scroll sideways for all columns.') ?></span>
                <?= __('Select a row to expand; event names open the event page.') ?>
            </p>
            <div
                class="kc-table-scroll"
                role="region"
                tabindex="0"
                aria-label="<?= h(__('Kingdom events')) ?>"
                aria-describedby="calendar-scroll-help">
                <table class="kc-table" role="table">
                    <caption class="visually-hidden"><?= __(
                        'Published events and weekends, grouped by month',
                    ) ?></caption>
                    <thead role="rowgroup"><tr role="row">
                        <th scope="col" role="columnheader"><?= __('Date') ?></th>
                        <th scope="col" role="columnheader"><?= __('Event / address') ?></th>
                        <th scope="col" role="columnheader"><?= __('Group') ?></th>
                        <th scope="col" role="columnheader"><?= __('Activities') ?></th>
                    </tr></thead>
                    <?php foreach ($calendarMonths as $monthKey => $month) : ?>
                        <tbody role="rowgroup" aria-labelledby="month-<?= h($monthKey) ?>">
                            <tr class="kc-month" role="row"><th scope="rowgroup" role="rowheader" colspan="4">
                                <div><h2 id="month-<?= h($monthKey) ?>"><?= h($month['label']) ?></h2>
                                    <span><?= __n('{0} event', '{0} events', $month['count'], $month['count']) ?></span>
                                </div>
                            </th></tr>
                            <?php foreach ($month['rows'] as $row) : ?>
                                <?php if ($row['empty']) : ?>
                                    <tr class="kc-empty-weekend" role="row">
                                        <td class="kc-date" role="cell">
                                            <span class="kc-date-range"><?= h($row['start']->format('M j')) ?>–<?php
                                                echo h($row['end']->format('M j'));
                                            ?></span>
                                            <small><?= h($row['start']->format('Y')) ?> · <?= __('Fri–Sun') ?></small>
                                        </td>
                                        <td colspan="3" role="cell"><?= $hasFilters
                                            ? __('No matching events this weekend')
                                            : __('No published events this weekend') ?></td>
                                    </tr>
                                <?php else : ?>
                                    <?= $this->element('gatherings/public_calendar_row', ['row' => $row]) ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="kc-legend">
                <span><?= __('Select a row to expand · event names open the event page') ?></span>
            </div>
        </main>
        <footer class="kc-footer"><details><summary><?= __('About this calendar') ?></summary>
            <p><?= __(
                'Events are published by the kingdom calendar staff. Empty weekends show no published events; '
                . 'they do not guarantee that a date is available to book.',
            ) ?></p>
            <p><?= __('Use Subscribe to Calendar to follow published updates in your calendar app.') ?></p>
        </details></footer>
    </div>
</div>
<noscript><style>
.kingdom-calendar-page .kc-detail-row[hidden]{display:table-row!important}
.kingdom-calendar-page .kc-more,.kingdom-calendar-page .kc-overflow{display:none}
@media(max-width:700px){.kingdom-calendar-page .kc-detail-row[hidden]{display:block!important}}
.kingdom-calendar-page .kc-filter-bar{position:static;max-height:none;overflow:visible}
</style></noscript>
