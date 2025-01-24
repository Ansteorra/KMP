<?php

// $linkTemplate = [
//     "type" => "postLink",
//     "verify" => true,
//     "label" => "Deactivate",
//     "controller" => "Warrants",
//     "action" => "deactivate",
//     "id" => "id",
//     "condition" => ["entity_type" => "Direct Grant"],
//     "options" => [
//         "confirm" => "Are you sure you want to deactivate for {{member->sca_name}}?",
//         "class" => "btn btn-danger"
//     ],
// ];
$columnTemplate = [
    "Member" => "sca_name",
    "Branch" => "branch_name",
    "Office" => "{{office_name}} : {{deputy_description}}",
    "Start Date" => "start_on",
    "End Date" => "expires_on",
    //"Warrant Status" => "warrant_status",
    "Office Status" => "status",
];

// if ($state == "current") {
//     $columnTemplate["Actions"] = [
//         $linkTemplate
//     ];
// }
// if ($state == "previous") {
//     $columnTemplate["Deactivated By"] = "revoked_by";

//     $columnTemplate["Deactivated Reason"] = "revoked_reason";
// }

$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $officers,
    "usePagination" => true,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);