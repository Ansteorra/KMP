<?php

/**
 * Gatherings Calendar Content (Loaded via Turbo Frame)
 * 
 * This template provides a two-level turbo-frame structure for the calendar:
 * - Outer frame: Contains toolbar with filters (static - doesn't reload)
 * - Inner frame: Contains calendar grid (dynamic - reloads with filters/navigation)
 * 
 * This structure keeps the filter dropdown open when filters change, since only
 * the inner calendar frame reloads, not the outer frame with the toolbar.
 * 
 * @var \App\View\AppView $this
 * @var iterable $data The gatherings data to display
 * @var array $gridState Complete grid state object
 * @var array $calendarMeta Calendar metadata (year, month, view mode, etc.)
 * @var string $frameId Outer turbo frame ID (e.g., 'gatherings-calendar-grid')
 * @var string|null $dataUrl URL for reloading calendar data
 */

$dataUrl = $dataUrl ?? $this->getRequest()->getRequestTarget();
$queryParams = $this->getRequest()->getQueryParams();
if (!empty($queryParams) && strpos($dataUrl, '?') === false) {
    $dataUrl .= '?' . http_build_query($queryParams);
}

$tableFrameId = $frameId . '-table';
$calendarMeta = $calendarMeta ?? [];
$viewMode = $calendarMeta['view'] ?? 'month';
?>
<turbo-frame id="<?= h($frameId) ?>">
    <!-- Calendar Toolbar (Static - doesn't reload with filters) -->
    <?= $this->element('gatherings/calendar_toolbar', [
        'gridState' => $gridState,
        'calendarMeta' => $calendarMeta,
        'viewMode' => $viewMode,
    ]) ?>

    <!-- Calendar Grid Frame (Dynamic - reloads with filters/navigation) -->
    <turbo-frame
        id="<?= h($tableFrameId) ?>"
        data-grid-src="<?= h($dataUrl) ?>">

        <!-- Grid State (JSON) - Read by grid-view-controller on load -->
        <script type="application/json" id="<?= h($tableFrameId) ?>-state">
            <?= json_encode($gridState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
        </script>

        <!-- Calendar Renderer -->
        <?= $this->element('gatherings/calendar_renderer', [
            'data' => $data,
            'calendarMeta' => $calendarMeta,
            'viewMode' => $viewMode,
            'tableFrameId' => $tableFrameId,
        ]) ?>
    </turbo-frame>
</turbo-frame>