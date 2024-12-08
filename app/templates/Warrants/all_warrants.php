<?php

$linkTemplate = [
    "type" => "postLink",
    "verify" => true,
    "label" => "Deactivate",
    "controller" => "Warrants",
    "action" => "deactivate",
    "id" => "id",
    "condition" => ["warrant_for_model" => "Direct Grant"],
    "options" => [
        "confirm" => "Are you sure you want to deactivate for {{member->sca_name}}?",
        "class" => "btn btn-danger"
    ],
];
$columnTemplate = [
    "Member" => "member->sca_name",
    "Start Date" => "start_on",
    "End Date" => "expires_on",
    "Warrant For" => "warrant_for_model",
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

$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $warrants,
    "usePagination" => true,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);