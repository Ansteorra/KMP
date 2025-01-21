<?php
$user = $this->request->getAttribute("identity");
if (!empty($currentOfficers) || !empty($upcomingOfficers) || !empty($previousOfficers)) {
    $linkTemplate = [
        "type" => "link",
        "verify" => true,
        "authData" => "branch",
        "label" => "View",
        "controller" => "Branches",
        "action" => "view",
        '?' => ['tab' => 'branch-officers'],
        "id" => "branch_id",
        "options" => ["class" => "btn btn-secondary"],
    ];
    $releaseLinkTemplate = [
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
        ],
    ];
    $currentTemplate = [
        "Office" => "{{office->name}}{{: (deputy_description) }}",
        "Branch" => "branch->name",
        "Start Date" => "start_on",
        "End Date" => "expires_on",
        "Reports To" => "reports_to",
        "Actions" => [
            $linkTemplate,
            $releaseLinkTemplate
        ],
    ];
    $previousTemplate = [
        "Office" => "{{office->name}}{{: (deputy_description) }}",
        "Branch" => "branch->name",
        "Start Date" => "start_on",
        "End Date" => "expires_on",
        "Actions" => [
            $linkTemplate,
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
                "columns" => $currentTemplate,
                "data" => $currentOfficers,
            ],
            "upcoming" => [
                "label" => __("Upcoming"),
                "id" => "upcoming-office",
                "selected" => false,
                "columns" => $currentTemplate,
                "data" => $upcomingOfficers,
            ],
            "previous" => [
                "label" => __("Previous"),
                "id" => "previous-office",
                "selected" => false,
                "columns" => $previousTemplate,
                "data" => $previousOfficers,
            ]
        ]
    ]);
} else {
    echo "<p>No Offices assigned</p>";
}

echo $this->KMP->startBlock("modals");
echo $this->element('releaseModal', [
    'user' => $user,
]);
$this->KMP->endBlock();