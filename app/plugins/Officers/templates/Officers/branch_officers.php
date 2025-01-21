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
        "class" => "btn btn-danger revoke-btn",
        "data-bs-toggle" => "modal",
        "data-bs-target" => "#releaseModal",
        "data-controller" => "outlet-btn",
        "data-action" => "click->outlet-btn#fireNotice",
        "data-outlet-btn-btn-data-value" => '{ "id":{{id}} }',
    ]
];

$currentAndUpcomingTemplate = [
    "Name" => "member->sca_name",
    "Office" => "{{office->name}} : {{deputy_description}}",
    "Start Date" => "start_on",
    "End Date" => "expires_on",
    "Reports To" => "reports_to",
    "Actions" => [
        $linkTemplate
    ],
];
$previousTemplate = [
    "Name" => "member->sca_name",
    "Office" => "office->name",
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