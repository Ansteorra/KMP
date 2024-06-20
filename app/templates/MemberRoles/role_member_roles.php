<?php

$linkTemplate = [
    "type" => "postLink",
    "verify" => true,
    "label" => "Deactivate",
    "controller" => "MemberRoles",
    "action" => "deactivate",
    "id" => "id",
    "condition" => ["granting_model" => "Direct Grant"],
    "options" => [
        "confirm" => "Are you sure you want to deactivate for {{member->sca_name}}?",
        "class" => "btn btn-danger"
    ],
];
$columnTemplate = [
    "Member" => "member->sca_name",
    "Start Date" => "start_on",
    "End Date" => "expires_on",
    "Approved By" => "approved_by->sca_name",
    "Granted By" => "granting_model",
];

if ($state == "current") {
    $columnTemplate["Actions"] = [
        $linkTemplate
    ];
}
if ($state == "previous") {
    $columnTemplate["Deactivated By"] = "revoked_by->sca_name";
}

$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $memberRoles,
    "usePagination" => true,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);
