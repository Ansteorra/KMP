<?php
$linkTemplate = [
    "type" => "link",
    "verify" => true,
    "plugin" => "Awards",
    "label" => "",
    "controller" => "Events",
    "action" => "view",
    "id" => "id",
    "options" => ["class" => "btn-sm btn btn-secondary bi-binoculars-fill", "data-turbo-frame" => "_top"],
];
$columnTemplate = [
    "name" => "name",
    "Branch" => "branch->name",
    "start_date" => "start_date",
    "end_date" => "end_date",
    "Actions" => [
        $linkTemplate,
    ]

];

$tableData = [
    "label" => __("Active"),
    "id" => $turboFrameId,
    "columns" => $columnTemplate,
    "data" => $events,
    "usePagination" => true,
    "sortable" => ["name", "start_date", "end_date"]
];

echo $this->element('turboSubTable', ['user' => $user, 'tableConfig' => $tableData]);
