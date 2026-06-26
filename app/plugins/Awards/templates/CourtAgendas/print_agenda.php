<?php
/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\CourtAgenda $agenda
 * @var array<int, array<string, mixed>> $segments
 * @var int $totalMinutes
 * @var string|null $totalWarning
 */

$title = __('Court Agenda') . ': ' . ($agenda->gathering->name ?? $agenda->name);
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
        <p class="summary">
            <?= __('Projected court runtime: {0} minutes', $totalMinutes) ?>
        </p>
        <?php if ($totalWarning) : ?>
            <p><strong><?= h($totalWarning) ?></strong></p>
        <?php endif; ?>
    </header>

    <?php foreach ($segments as $segmentData) :
        $segment = $segmentData['entity'];
        $items = $segmentData['items'];
        ?>
        <section class="segment" aria-labelledby="segment-<?= (int)$segment->id ?>-print-title">
            <div class="segment-heading">
                <h2 id="segment-<?= (int)$segment->id ?>-print-title">
                    <?= h($segment->name) ?>
                    <span>(<?= h((string)$segmentData['minutes']) ?> <?= h(__('min')) ?>)</span>
                </h2>
                <?php if (!empty($segment->notes)) : ?>
                    <p><?= h($segment->notes) ?></p>
                <?php endif; ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <th scope="col"><?= __('Order') ?></th>
                        <th scope="col"><?= __('Minutes') ?></th>
                        <th scope="col"><?= __('Recipient / Block') ?></th>
                        <th scope="col"><?= __('Award / Action') ?></th>
                        <th scope="col"><?= __('Court Notes') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $itemData) :
                        $item = $itemData['entity'];
                        $bestowal = $item->bestowal ?? null;
                        ?>
                        <tr>
                            <td><?= h((string)($index + 1)) ?></td>
                            <td><?= h((string)$itemData['minutes']) ?></td>
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
                                <div class="notes"><?= h($itemData['durationHint']) ?></div>
                                <?php if ($bestowal !== null) : ?>
                                    <div class="notes"><?= __('State:') ?> <?= h($bestowal->state) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($bestowal->call_into_court)) : ?>
                                    <div><strong><?= __('Call in:') ?></strong> <?= h($bestowal->call_into_court) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item->presentation_notes)) : ?>
                                    <div><strong><?= __('Agenda:') ?></strong> <?= h($item->presentation_notes) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item->print_notes)) : ?>
                                    <div><strong><?= __('Print:') ?></strong> <?= h($item->print_notes) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($bestowal->herald_notes)) : ?>
                                    <div><strong><?= __('Herald:') ?></strong> <?= h($bestowal->herald_notes) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($bestowal->noble_notes)) : ?>
                                    <div><strong><?= __('Noble:') ?></strong> <?= h($bestowal->noble_notes) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($itemData['specialties'])) : ?>
                                    <div><strong><?= __('Specialties:') ?></strong> <?= h(implode(', ', $itemData['specialties'])) ?></div>
                                <?php endif; ?>
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
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []) : ?>
                        <tr>
                            <td colspan="5"><?= __('No agenda items in this segment.') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>
</body>
</html>
