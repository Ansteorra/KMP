<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
$event = $row['event'];
$gathering = $event['gathering'];
$isCancelled = $gathering->is_cancelled;
$eventUrl = $gathering->public_page_enabled
    ? $this->Url->build(['controller' => 'Gatherings', 'action' => 'public-landing', $gathering->public_id])
    : $gathering->website_url;
$panelId = 'event-details-' . $row['key'];
?>
<tr class="kc-event<?= $isCancelled ? ' kc-event-cancelled' : '' ?>" role="row"
    data-action="click->public-calendar#toggleRow">
    <td class="kc-date" role="cell">
        <span class="kc-date-range">
            <?= h($row['start']->format('M j')) ?>
            <?php if ($row['end'] > $row['start']) : ?>
                → <?= h($row['end']->format('M j')) ?>
            <?php endif; ?>
        </span>
        <small><?= h($row['start']->format('Y')) ?> ·
            <span class="kc-event-through"><?= $event['days'] > 1
                ? __n('{0} day', '{0} days', $event['days'], $event['days'])
                : h($row['start']->format('l')) ?></span>
        </small>
    </td>
    <th scope="row" role="rowheader">
        <div class="kc-event-title-row">
            <?php if ($eventUrl) : ?>
                <a class="kc-event-name" href="<?= h($eventUrl) ?>"><?= h($gathering->name) ?></a>
            <?php else : ?>
                <span class="kc-event-name"><?= h($gathering->name) ?></span>
            <?php endif; ?>
            <?php if ($row['continuing']) :
                ?><span class="kc-continuing"><?= __('Continues') ?></span><?php
            endif; ?>
            <?php if ($isCancelled) :
                ?><span class="kc-cancelled"><?= __('Cancelled') ?></span><?php
            endif; ?>
        </div>
        <?php if ($gathering->location) :
            ?><span class="kc-address"><?= nl2br(h($gathering->location)) ?></span><?php
        endif; ?>
    </th>
    <td class="kc-group" role="cell"><?= h($gathering->branch->name) ?></td>
    <td class="kc-activity-cell" role="cell"><div class="kc-indicators">
        <div class="kc-icons">
            <?php foreach ($event['summaryActivities'] as $activity) : ?>
                <?= $this->GatheringActivity->badge($activity, true) ?>
            <?php endforeach; ?>
            <?php if ($event['overflow'] > 0) : ?>
                <button class="kc-overflow" type="button" data-action="public-calendar#expand"
                    aria-label="<?= h(__(
                        '{0} more activities for {1}. Expand details.',
                        $event['overflow'],
                        $gathering->name,
                    )) ?>">…</button>
            <?php endif; ?>
        </div>
        <details class="kc-more" data-panel="<?= h($panelId) ?>" data-action="toggle->public-calendar#sync">
            <summary aria-expanded="false" aria-controls="<?= h($panelId) ?>">
                <?= $this->Icon->render('chevron-down') ?>
                <span class="visually-hidden"><?= __('Details for {0}', h($gathering->name)) ?></span>
            </summary>
        </details>
    </div></td>
</tr>
<tr class="kc-detail-row" role="row" id="<?= h($panelId) ?>" hidden><td colspan="4" role="cell">
    <div class="kc-detail-body">
        <section aria-label="<?= h(__('Event information')) ?>">
            <h3><?= h($gathering->name) ?></h3>
            <?php if ($gathering->description) :
                ?><?= $this->Markdown->toHtml($gathering->description) ?><?php
            endif; ?>
            <div class="kc-event-activities">
                <?php foreach ($event['activities'] as $activity) : ?>
                    <span class="kc-activity-chip">
                        <?= $this->GatheringActivity->badge($activity) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
        <section aria-label="<?= h(__('When and where')) ?>">
            <p><strong><?= __('When:') ?></strong>
                <?= $this->Timezone->format($gathering->start_date, 'M j, Y g:i A T', false, null, $gathering) ?>
                → <?= $this->Timezone->format($gathering->end_date, 'M j, Y g:i A T', false, null, $gathering) ?>
            </p>
            <?php if ($gathering->location) :
                ?><p><strong><?= __('Where:') ?></strong> <?= nl2br(h($gathering->location)) ?></p><?php
            endif; ?>
            <p><strong><?= __('Event type:') ?></strong> <?= h($gathering->gathering_type->name) ?></p>
            <div class="kc-detail-actions">
                <?php if (!$isCancelled && $gathering->is_preregistration_open) : ?>
                    <a href="<?= h($gathering->preregister_url) ?>" class="kc-button"><?= __('Pre-Register') ?></a>
                <?php endif; ?>
                <a href="<?= $this->Url->build([
                    'controller' => 'Gatherings', 'action' => 'downloadCalendar', $gathering->public_id,
                ]) ?>" class="kc-button">
                    <?= $this->Icon->render('download') ?> <?= __('Add to calendar (.ics)') ?>
                </a>
            </div>
        </section>
    </div>
</td></tr>
