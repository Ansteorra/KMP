<?php

use Cake\Utility\Text;


$linkTemplate = [
    "type" => "button",
    "verify" => true,
    "label" => "Release",
    "controller" => "Officers",
    "action" => "release",
    "id" => "officer_id",
    "options" => [
        "class" => "btn-sm btn btn-danger revoke-btn",
        "data-bs-toggle" => "modal",
        "data-bs-target" => "#releaseModal",
        "data-controller" => "outlet-btn",
        "data-action" => "click->outlet-btn#fireNotice",
        "data-outlet-btn-btn-data-value" => '{ "id":{{id}} }',
    ]
];
$editTemplate = [
    "type" => "button",
    "verify" => true,
    "label" => "",
    "plugin" => "Officers",
    "controller" => "Officers",
    "action" => "edit",
    "id" => "officer_id",
    "condition" => ["is_editable" => "1"],
    "options" => [
        "class" => "btn-sm btn btn-primary bi-pencil-fill edit-btn",
        "data-bs-toggle" => "modal",
        "data-bs-target" => "#editOfficerModal",
        "data-controller" => "outlet-btn",
        "data-action" => "click->outlet-btn#fireNotice",
        "data-outlet-btn-btn-data-value" => '{ "id":{{id}}, "is_deputy":"{{office->is_deputy}}", "email_address":"{{email_address}}", "deputy_description":"{{deputy_description}}" }',
    ],
];
$newWarrantTemplate = [
    "type" => "postLink",
    "verify" => true,
    "label" => "Request Warrant",
    "plugin" => "Officers",
    "controller" => "Officers",
    "action" => "requestWarrant",
    "id" => "id",
    "condition" => ["warrant_state" => "Missing"],
    "options" => [
        "confirm" => "Are you sure you want to request a new warrant for {{member->sca_name}}?",
        "class" => "btn-sm btn btn-warning"
    ],
];

$currentAndUpcomingTemplate = [
    "Name" => "member->sca_name",
    "Office" => "{{office->name}}{{: (deputy_description) }}",
    "Contact" => "<a href='mailto:{{email_address}}'>{{email_address}}</a>",
    "Warrant Expires" => "warrant_state",
    "Start Date" => "start_on",
    "End Date" => "expires_on",
    "Reports To" => "reports_to_list",
    "Actions" => [
        $editTemplate,
        $newWarrantTemplate,
        $linkTemplate,
    ],
];
$previousTemplate = [
    "Name" => "member->sca_name",
    "Office" => "{{office->name}}{{: (deputy_description) }}",
    "Start Date" => "start_on",
    "End Date" => "expires_on",
    "Reason" => "revoked_reason",
];

if ($state == "previous") {
    $columnTemplate = $previousTemplate;
} else {
    $columnTemplate = $currentAndUpcomingTemplate;
}




$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $officers,
    "usePagination" => true,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);
