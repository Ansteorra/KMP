<?php

$linkTemplate = [
    "type" => "postLink",
    "verify" => true,
    "label" => "Deactivate",
    "controller" => "MemberRoles",
    "action" => "deactivate",
    "id" => "id",
    "condition" => ["entity_type" => "Direct Grant"],
    "options" => [
        "confirm" => "Are you sure you want to deactivate for {{member->sca_name}}?",
        "class" => "btn-sm btn btn-danger"
    ],
];
$columnTemplate = [
    "Member" => "member->sca_name",
    "Start Date" => "start_on_to_string",
    "End Date" => "expires_on_to_string",
    "Approved By" => "approved_by->sca_name",
    "Granted By" => "granted_by",
    "Scope" => "branch->name",
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