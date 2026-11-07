<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\CourtAgenda $agenda
 * @var array<int, array<string, mixed>> $segments
 * @var string $eventDates
 * @var bool $isHeraldsVersion
 * @var int $totalMinutes
 * @var string|null $totalWarning
 */

$title = ($isHeraldsVersion ? __('Court Agenda (Heralds)') : __('Court Agenda'))
    . ': ' . ($agenda->gathering->name ?? $agenda->name);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= h($title) ?></title>
    <style>
        body {
            color: #111;
            font-family: Arial, sans-serif;
            line-height: 1.35;
            margin: 1rem;
        }
        header {
            border-bottom: 2px solid #111;
            margin-bottom: 1rem;
            padding-bottom: .5rem;
        }
        h1, h2, h3 {
            margin: 0 0 .35rem;
        }
        .summary {
            font-size: 1.1rem;
            font-weight: bold;
        }
        .segment {
            break-inside: avoid;
            margin: 0 0 1.25rem;
            page-break-inside: avoid;
        }
        .segment-heading {
            background: #eee;
            border: 1px solid #333;
            padding: .35rem .5rem;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #333;
            padding: .3rem;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f5f5f5;
        }
        .notes,
        .reasons {
            font-size: .9rem;
            margin-top: .25rem;
        }
        .np-checkbox {
            accent-color: #111;
            height: 24px;
            margin: 0;
            width: 24px;
        }
        .np-checkbox:focus-visible {
            outline: 2px solid #111;
            outline-offset: 3px;
        }
        .court-note {
            white-space: pre-line;
        }
        .screen-actions {
            margin-bottom: 1rem;
        }
        @media print {
            body {
                margin: .5in;
            }
            .screen-actions {
                display: none;
            }
            a {
                color: #111;
                text-decoration: none;
            }
        }
    </style>
</head>
<body>
    <div class="screen-actions">
        <button type="button" onclick="window.print()"><?= h(__('Print Agenda')) ?></button>
    </div>

    <header>
        <h1><?= h($title) ?></h1>
        <?php if ($eventDates !== '') : ?>
            <p><?= h($eventDates) ?></p>
        <?php endif; ?>
        <?php if (!$isHeraldsVersion) : ?>
            <p class="summary">
                <?= __('Projected court runtime: {0} minutes', $totalMinutes) ?>
            </p>
            <?php if ($totalWarning) : ?>
                <p><strong><?= h($totalWarning) ?></strong></p>
            <?php endif; ?>
        <?php endif; ?>
    </header>

    <?php foreach ($segments as $segmentData) :
        $segment = $segmentData['entity'];
        $items = $segmentData['items'];
        ?>
        <section class="segment" aria-labelledby="segment-<?= (int)$segment->id ?>-print-title">
            <div class="segment-heading">
                <h2 id="segment-<?= (int)$segment->id ?>-print-title">
                    <?= h($segmentData['printHeading']) ?>
                    <?php if (!$isHeraldsVersion) : ?>
                        <span>(<?= h((string)$segmentData['minutes']) ?> <?= h(__('min')) ?>)</span>
                    <?php endif; ?>
                </h2>
                <?php if (!empty($segment->notes)) : ?>
                    <p><?= h($segment->notes) ?></p>
                <?php endif; ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <th scope="col"><?= __('Order') ?></th>
                        <th scope="col"><?= __('Recipient / Block') ?></th>
                        <th scope="col"><?= __('Award / Action') ?></th>
                        <th scope="col"><?= $isHeraldsVersion ? __('Notes') : __('Court Notes') ?></th>
                        <th scope="col"><abbr title="<?= h(__('Not Present')) ?>"><?= __('NP') ?></abbr></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $itemData) :
                        $item = $itemData['entity'];
                        $bestowal = $item->bestowal ?? null;
                        ?>
                        <tr>
                            <td><?= h((string)($index + 1)) ?></td>
                            <td>
                                <strong><?= h($itemData['label']) ?></strong>
                                <?php if ($bestowal !== null && !empty($bestowal->member->pronunciation)) : ?>
                                    <div><?= __('Pronunciation:') ?> <?= h($bestowal->member->pronunciation) ?></div>
                                <?php endif; ?>
                                <?php if ($bestowal !== null && !empty($bestowal->member->pronouns)) : ?>
                                    <div><?= __('Pronouns:') ?> <?= h($bestowal->member->pronouns) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= h($itemData['awardLabel'] ?: ($item->planned_action ?? $item->title ?? '')) ?>
                                <?php if ($itemData['specialties'] !== []) : ?>
                                    <div class="notes">
                                        <strong><?= __('Class:') ?></strong>
                                        <?= h(implode(', ', $itemData['specialties'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isHeraldsVersion) : ?>
                                    <?php if ($itemData['callIntoCourt'] !== '') : ?>
                                        <div class="notes"><strong><?= __('Call into court:') ?></strong>
                                            <?= h($itemData['callIntoCourt']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($itemData['heraldNotes'] !== '') : ?>
                                        <div class="notes court-note"><strong><?= __('Notes:') ?></strong> <?=
                                            h($itemData['heraldNotes']) ?></div>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php foreach ($itemData['printNotes'] as $note) : ?>
                                        <div class="notes court-note"><strong><?= h($note['label']) ?></strong> <?=
                                            h($note['value']) ?></div>
                                    <?php endforeach; ?>
                                    <?php if (!empty($itemData['reasons'])) : ?>
                                        <div class="reasons">
                                            <strong><?= __('Reasons:') ?></strong>
                                            <ol>
                                                <?php foreach ($itemData['reasons'] as $reason) : ?>
                                                    <li><?= h($reason) ?></li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </div>
                                    <?php endif; ?>
                                    <?php foreach ($itemData['recommendationNotes'] as $note) : ?>
                                        <div class="notes court-note"><strong><?= h($note['label']) ?></strong> <?=
                                            h($note['value']) ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="checkbox" class="np-checkbox"
                                    aria-label="<?= h(__(
                                        'Not Present: item {0} in {1}',
                                        $index + 1,
                                        $segment->name,
                                    )) ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []) : ?>
                        <tr>
                            <td colspan="5"><?=
                                __('No agenda items in this segment.') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>
</body>
</html>
