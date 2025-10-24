<?php

/**
 * All Gatherings Template - Filtered Gathering Table View
 *
 * This template renders a filtered view of gatherings based on temporal state
 * (current, upcoming, or previous). It uses the turboSubTable element for
 * optimized rendering with pagination and CSV export capabilities.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering[]|\Cake\Collection\CollectionInterface $gatherings
 * @var string $state The temporal filter state (current|upcoming|previous)
 * @var string $turboFrameId The turbo frame ID for this view
 */

// Configure column templates based on state
$columnTemplate = [
    "Name" => "name",
    "Branch" => "branch->name",
    "Type" => "gathering_type->name",
    "Start Date" => "start_date_formatted",
    "End Date" => "end_date_formatted",
    "Activities" => "activity_count",
];

// Define action links for each gathering
$linkTemplate = [
    [
        "type" => "link",
        "verify" => true,
        "label" => "View",
        "controller" => "Gatherings",
        "action" => "view",
        "id" => "id",
        "options" => [
            "class" => "btn-sm btn btn-secondary"
        ],
    ],
    [
        "type" => "link",
        "verify" => true,
        "label" => "Edit",
        "controller" => "Gatherings",
        "action" => "edit",
        "id" => "id",
        "options" => [
            "class" => "btn-sm btn btn-primary"
        ],
    ],
];

// Only show delete button for previous/past gatherings
if ($state == "previous") {
    $linkTemplate[] = [
        "type" => "postLink",
        "verify" => true,
        "label" => "Delete",
        "controller" => "Gatherings",
        "action" => "delete",
        "id" => "id",
        "options" => [
            "confirm" => "Are you sure you want to delete {{name}}?",
            "class" => "btn-sm btn btn-danger"
        ],
    ];
}

$columnTemplate["Actions"] = $linkTemplate;

// Build export button config for turboSubTable
$exportButton = [
    'url' => $this->Url->build([
        'controller' => 'Gatherings',
        'action' => 'allGatherings',
        $state,
        '_ext' => 'csv',
    ] + $this->getRequest()->getQueryParams()),
    'filename' => 'gatherings-' . $state . '.csv',
];

// Prepare gatherings data with formatted fields
$formattedGatherings = [];
foreach ($gatherings as $gathering) {
    $formattedGathering = $gathering;

    // Format dates for display
    $formattedGathering->start_date_formatted = $gathering->start_date ?
        $gathering->start_date->format('Y-m-d') : '-';
    $formattedGathering->end_date_formatted = $gathering->end_date ?
        $gathering->end_date->format('Y-m-d') : '-';

    // Calculate activity count
    $formattedGathering->activity_count = !empty($gathering->gathering_activities) ?
        count($gathering->gathering_activities) : 0;

    $formattedGatherings[] = $formattedGathering;
}

// Generate human-readable label from state
$stateLabels = [
    'this_month' => 'This Month',
    'next_month' => 'Next Month',
    'future' => 'Future',
    'previous' => 'Previous'
];
$label = isset($stateLabels[$state]) ? $stateLabels[$state] : ucfirst($state);

// Generate turbo frame ID from state (must match the ID in index.php)
$turboFrameId = str_replace('_', '-', $state) . '-gatherings-frame';

// Configure table data
$tableData = [
    "label" => $label . " Gatherings",
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $formattedGatherings,
    "usePagination" => true,
    'exportButton' => $exportButton,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);
