<?php
$user = $this->request->getAttribute("identity");
?>
<button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
    data-bs-target="#assignOfficerModal">Assign Officer</button>
<?php if (!empty($previousOfficers) || !empty($currentOfficers) || !empty($upcomingOfficers)) {
    $linkTemplate = [
        "type" => "button",
        "verify" => true,
        "label" => "Release",
        "controller" => "Officers",
        "action" => "release",
        "id" => "officer_id",
        "options" => [
            "class" => "btn btn-danger",
            "data-bs-toggle" => "modal",
            "data-bs-target" => "#releaseModal",
            "onclick" => "$('#release_officer__id').val('{{id}}')",
        ],
    ];
    $currentAndUpcomingTemplate = [
        "Name" => "member->sca_name",
        "Office" => "{{office->name}}{{: (deputy_description) }}",
        "Start Date" => "start_on",
        "End Date" => "expires_on",
        "Reports To" => "{{reports_to_branch->name}} - {{reports_to_office->name}}",
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
        ]
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

$this->KMP->endBlock();

echo $this->KMP->startBlock("script"); ?>
?>
<script>
class branchesView {
    constructor() {
        this.ac = null;
    };
    //onInput for Autocomplete

    run() {
        var me = this;
        var searchUrl =
            '<?= $this->URL->build(['controller' => 'Members', 'action' => 'SearchMembers']) ?>';
        KMP_utils.configureAutoComplete(me.ac, searchUrl, 'assign_officer__sca_name', 'id', 'sca_name',
            'assign_officer__member_id');

        $('#assign_officer__member_id').change(function() {
            if ($('#assign_officer__member_id').val() > 0) {
                //enable button
                $('#assign_officer__submit').prop('disabled', false);
            } else {
                //disable button
                $('#assign_officer__submit').prop('disabled', true);
            }
        });
        $('#assign_officer__submit').click(function() {
            if ($('#assign_officer__member_id').val() > 0) {
                $('#assign_officer__form').submit();
            }
        });
        $('#assign_officer__office_id').change(function() {
            var officeId = $('#assign_officer__office_id').val();
            //find the office from the officeData array
            officeData.forEach(office => {
                if (office.id == officeId) {
                    //show the deputy description field
                    if (office.deputy_to_id > 0) {
                        $('#assign_officer__end_date_block').show();
                        $('#assign_officer__deputy_description_block').show();
                    } else {
                        $('#assign_officer__end_date_block').hide();
                        $('#assign_officer__deputy_description_block').hide();
                    }
                }
            });
        });
        $('#assign_officer__office_id').trigger('change');
    }
};
var view = new branchesView();
view.run();
</script>
<?php $this->KMP->endBlock(); ?>