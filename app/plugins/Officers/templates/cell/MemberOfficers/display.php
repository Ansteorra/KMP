<?php
$user = $this->request->getAttribute("identity");
if (!empty($currentOfficers) || !empty($upcomingOfficers) || !empty($previousOfficers)) {
    $linkTemplate = [
        "type" => "link",
        "verify" => true,
        "authData" => "office",
        "label" => "View",
        "controller" => "Offices",
        "plugin" => "Officers",
        "action" => "view",
        "id" => "office_id",
        "options" => ["class" => "btn btn-secondary"],
    ];
    $columnsTemplate = [
        "Office" => "office->name",
        "Branch" => "branch->name",
        "Start Date" => "start_on",
        "End Date" => "expires_on",
        "Actions" => [
            $linkTemplate
        ],
    ];
    echo $this->element('activeWindowTabs', [
        'user' => $user,
        'tabGroupName' => "officeTabs",
        'tabs' => [
            "active" => [
                "label" => __("Active"),
                "id" => "active-office",
                "selected" => true,
                "columns" => $columnsTemplate,
                "data" => $currentOfficers,
            ],
            "upcoming" => [
                "label" => __("Upcoming"),
                "id" => "upcoming-office",
                "selected" => false,
                "columns" => $columnsTemplate,
                "data" => $upcomingOfficers,
            ],
            "previous" => [
                "label" => __("Previous"),
                "id" => "previous-office",
                "selected" => false,
                "columns" => $columnsTemplate,
                "data" => $previousOfficers,
            ]
        ]
    ]);
} else {
    echo "<p>No Offices assigned</p>";
}