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

$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $warrants,
    "usePagination" => true,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);