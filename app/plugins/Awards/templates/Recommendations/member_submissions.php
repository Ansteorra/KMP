<?php
$columnTemplate = [
    "Request Date" => "created",
    "Request For" => "member_sca_name",
    "Award" => "award->name",
    "Reason" => "reason",
];

$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $recommendations,
    "usePagination" => false,
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);