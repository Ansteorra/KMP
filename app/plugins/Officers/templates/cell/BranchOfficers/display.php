<?php
$user = $this->request->getAttribute("identity");
?>
<?php if ($user->checkCan("add", "Officers.Officers")): ?>
<button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
    data-bs-target="#assignOfficerModal">Assign Officer</button>
<?php endif; ?>
<?php if (!empty($previousOfficers) || !empty($currentOfficers) || !empty($upcomingOfficers)) {
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
        ],
    ];
    $currentAndUpcomingTemplate = [
        "Name" => "member->sca_name",
        "Office" => "{{office->name}}{{: (deputy_description) }}",
        "Start Date" => "start_on",
        "End Date" => "expires_on",
        "Reports To" => "reports_to",
        "Actions" => [
            $linkTemplate
        ],
    ];
    $previousTemplate = [
        "Name" => "member->sca_name",
        "Office" => "{{office->name}} {{: (deputy_description) }}",
        "Start Date" => "start_on",
        "End Date" => "expires_on",
        "Reason" => "revoked_reason",
    ];
    echo $this->element('activeWindowTabs', [
        'user' => $user,
        'tabGroupName' => "officeTabs",
        'tabs' => [
            "active" => [
                "label" => __("Active"),
                "id" => "active-office",
                "selected" => true,
                "columns" => $currentAndUpcomingTemplate,
                "data" => $currentOfficers,
            ],
            "upcoming" => [
                "label" => __("Incoming"),
                "id" => "upcoming-office",
                "selected" => false,
                "columns" => $currentAndUpcomingTemplate,
                "data" => $upcomingOfficers,
            ],
            "previous" => [
                "label" => __("Previous"),
                "id" => "previous-office",
                "selected" => false,
                "columns" => $previousTemplate,
                "data" => $previousOfficers,
            ]
        ],
    ]);
} else {
    echo "<p>No Offices assigned</p>";
} ?>
<?php

echo $this->KMP->startBlock("modals");

echo $this->element('releaseModal', [
    'user' => $user,
]);

echo $this->element('assignModal', [
    'user' => $user,
]);

$this->KMP->endBlock(); ?>