<?php
$user = $this->request->getAttribute("identity");
if (!empty($currentOfficers) || !empty($upcomingOfficers) || !empty($previousOfficers)) {
    $linkTemplate = [
        "type" => "link",
        "verify" => true,
        "authData" => "member",
        "label" => "",
        "controller" => "Members",
        "action" => "view",
        '?' => ['tab' => 'member-officers'],
        "id" => "id",
        "options" => ["class" => "btn-sm btn btn-secondary bi-binoculars-fill"],
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
    $releaseLinkTemplate = [
        "type" => "button",
        "verify" => true,
        "label" => "Release",
        "plugin" => "Officers",
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
            "class" => "btn btn-warning btn-sm",
        ],
    ];
    $currentTemplate = [
        "Office" => "{{office->name}}{{: (deputy_description) }}",
        "Contact" => "<a href='mailto:{{email_address}}'>{{email_address}}</a>",
        "Warrant Expires" => "warrant_state",
        "Branch" => "branch->name",
        "Start Date" => "start_on_to_string",
        "End Date" => "expires_on_to_string",

        "Reports To" => "reports_to_list",
        "Actions" => [
            $linkTemplate,
            $editTemplate,
            $newWarrantTemplate,
            $releaseLinkTemplate
        ],
    ];
    $previousTemplate = [
        "Office" => "{{office->name}}{{: (deputy_description) }}",
        "Branch" => "branch->name",
        "Start Date" => "start_on_to_string",
        "End Date" => "expires_on_to_string",
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
echo $this->element('editModal', [
    'user' => $user,
]);
$this->KMP->endBlock();