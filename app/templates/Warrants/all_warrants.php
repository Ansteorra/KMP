<?php

$linkTemplate = [
    "type" => "postLink",
    "verify" => true,
    "label" => "Deactivate",
    "controller" => "Warrants",
    "action" => "deactivate",
    "id" => "id",
    "condition" => ["entity_type" => "Direct Grant"],
    "options" => [
        "confirm" => "Are you sure you want to deactivate for {{member->sca_name}}?",
        "class" => "btn-sm btn btn-danger"
    ],
];
$columnTemplate = [
    "Name" => "name",
    "Member" => "member->sca_name",
    "Start Date" => "start_on_to_string",
    "End Date" => "expires_on_to_string",
    "Status" => "status",
];

if ($state == "current") {
    $columnTemplate["Actions"] = [
        $linkTemplate
    ];
}
if ($state == "previous") {
    $columnTemplate["Deactivated By"] = "revoked_by->sca_name";

    $columnTemplate["Deactivated Reason"] = "revoked_reason";
}

// Build export button config for turboSubTable
$exportButton = [
    'url' => $this->Url->build([
        'controller' => 'Warrants',
        'action' => 'allWarrants',
        $state,
        '_ext' => 'csv',
    ] + $this->getRequest()->getQueryParams()),
    'filename' => 'warrants.csv',
    // 'fields' => [...] // Optionally add fields if you want to restrict columns
];

$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $warrants,
    "usePagination" => true,
    'exportButton' => $exportButton,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);
