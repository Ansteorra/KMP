<?php
/** @var \App\View\AppView $this */
echo $name . "\n\n";
echo __('Current results from {0}. Up to 50 matching rows are included.', $report['label']) . "\n\n";
if ($report['rows'] === []) {
    echo __('No rows match this view right now. Scheduled emails are skipped when there are no matches.') . "\n\n";
}
foreach ($report['rows'] as $row) {
    foreach ($row as $index => $value) {
        echo ($report['headers'][$index] ?? '') . ': ' . $value . "\n";
    }
    echo "\n";
}
echo __('Open this view: {0}', $report['url']) . "\n\n";
if (!empty($report['sample'])) {
    echo __('This is a one-time sample. It does not create or change a subscription.') . "\n\n";
}
echo __('Manage or cancel email subscriptions: {0}', $report['manageUrl']) . "\n";
