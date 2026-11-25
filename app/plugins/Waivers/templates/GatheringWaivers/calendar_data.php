<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var array $calendar
 * @var string $frameId
 * @var array $gridState
 */

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
?>
<turbo-frame id="<?= h($frameId) ?>" target="_top">
    <script type="application/json" id="<?= h($frameId) ?>-state">
        <?= json_encode($gridState, $jsonFlags) ?>
    </script>
    <div data-waiver-calendar-target="calendar"></div>
    <script type="application/json" data-waiver-calendar-target="monthData">
        <?= json_encode($calendar, $jsonFlags) ?>
    </script>
</turbo-frame>
