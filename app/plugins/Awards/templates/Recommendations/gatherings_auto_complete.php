<?php
$safeQuery = preg_quote((string)$q, '/');
?>
<?php if (!empty($rankedGatherings)) : ?>
    <?php foreach ($rankedGatherings as $group) : ?>
        <li class="list-group-item bg-light fw-semibold small text-uppercase text-muted"
            role="presentation">
            <?= h((string)($group['label'] ?? '')) ?>
        </li>
        <?php foreach (($group['items'] ?? []) as $item) : ?>
            <?php
            $gatheringId = (int)($item['id'] ?? 0);
            $isCancelled = !empty($item['cancelled']);
            $isSelectedCancelled = $isCancelled && ((int)$selectedId === $gatheringId);
            $isDisabled = $isCancelled && !$isSelectedCancelled;
            $rank = (string)($item['rank'] ?? 'other');
            $badgeClass = match ($rank) {
                'rsvp' => 'text-bg-success',
                'suggested' => 'text-bg-warning',
                default => 'text-bg-secondary',
            };
            $badgeText = match ($rank) {
                'rsvp' => __('Best match'),
                'suggested' => __('Suggested'),
                default => __('Eligible'),
            };
            $label = (string)($item['label'] ?? '');
            $renderedLabel = h($label);
    if (!empty($q)) {
        $renderedLabel = preg_replace(
            '/(' . $safeQuery . ')/i',
            '<span class="text-primary">$1</span>',
            $renderedLabel,
        );
    }
            $countParts = [];
    if (!empty($item['rsvpCount'])) {
        $countParts[] = __n(
            '{0} RSVP',
            '{0} RSVPs',
            (int)$item['rsvpCount'],
            (int)$item['rsvpCount'],
        );
    }
    if (!empty($item['suggestedCount'])) {
        $countParts[] = __n(
            '{0} suggested',
            '{0} suggested',
            (int)$item['suggestedCount'],
            (int)$item['suggestedCount'],
        );
    }
    ?>
            <li class="list-group-item <?= $isDisabled ? 'disabled text-danger' : '' ?>" role="option"
                aria-disabled="<?= $isDisabled ? 'true' : 'false' ?>"
                data-ac-value="<?= h((string)$gatheringId) ?>"
                data-ac-label="<?= h($label) ?>">
                <div class="d-flex justify-content-between gap-2 align-items-start">
                    <span><?= $renderedLabel ?></span>
                    <span class="badge <?= h($badgeClass) ?> flex-shrink-0"><?= h($badgeText) ?></span>
                </div>
                <?php if ($countParts !== []) : ?>
                    <div class="small text-muted mt-1"><?= h(implode(' | ', $countParts)) ?></div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php else : ?>
    <?php foreach ($gatherings as $gatheringId => $displayName) : ?>
        <?php
        $isCancelled = in_array((int)$gatheringId, $cancelledGatheringIds ?? [], true);
        $isSelectedCancelled = $isCancelled && ((int)$selectedId === (int)$gatheringId);
        $classes = $isCancelled && !$isSelectedCancelled ? 'disabled text-danger' : '';
        $rendered = h((string)$displayName);
        if (!empty($q)) {
            $rendered = preg_replace(
                '/(' . $safeQuery . ')/i',
                '<span class="text-primary">$1</span>',
                $rendered,
            );
        }
        ?>
        <li class="list-group-item <?= $classes ?>" role="option"
            aria-disabled="<?= $isCancelled && !$isSelectedCancelled ? 'true' : 'false' ?>"
            data-ac-value="<?= h((string)$gatheringId) ?>">
            <?= $rendered ?>
        </li>
    <?php endforeach; ?>
<?php endif; ?>
