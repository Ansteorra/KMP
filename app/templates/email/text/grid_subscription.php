<?php
/** @var \App\View\AppView $this */
echo $name . "\n\n";
echo __('Current results from {0}. Up to 50 matching rows are included.', $report['label']) . "\n\n";
foreach ($report['rows'] as $row) {
    foreach ($row as $index => $value) {
        echo ($report['headers'][$index] ?? '') . ': ' . $value . "\n";
    }
    echo "\n";
}
echo __('Open this view: {0}', $report['url']) . "\n\n";
echo __('Manage or cancel email subscriptions: {0}', $report['manageUrl']) . "\n";
