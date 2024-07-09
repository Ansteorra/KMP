<?php

$viewBtn = [
    "type" => "link",
    "verify" => false,
    "label" => "View",
    "controller" => "Recommendations",
    "action" => "view",
    "id" => "id",
    "plugin" => "Awards",
    "options" => [
        "class" => "btn btn-secondary",
        "data-turbo-frame" => "_top"
    ],
];

$columnTemplate = [
    "Submitted" => "created",
    "Status" => "status",
    "Request By" => "requester_sca_name",
    "Award" => "award->name",
    "Reason" => "reason",
];
$columnTemplate["Actions"] = [
    $viewBtn,
];

$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $recommendations,
    "usePagination" => false,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);