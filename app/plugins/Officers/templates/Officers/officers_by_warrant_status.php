<?php

$columnTemplate = [
    "Member" => "sca_name",
    "Branch" => "branch_name",
    "Office" => "{{office_name}} : {{deputy_description}}",
    "Start Date" => "start_on",
    "End Date" => "expires_on",
    "Office Status" => "status",
];


$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $officers,
    "usePagination" => true,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);
