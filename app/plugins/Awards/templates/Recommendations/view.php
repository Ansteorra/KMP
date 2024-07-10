<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Recommendation $recommendation
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': View Award Recommendation - ' . $recommendation->member_sca_name . ' for ' . $recommendation->award->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($recommendation->member_sca_name . ' for ' . $recommendation->award->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php
echo $this->Form->postLink(
    __("Delete"),
    ["action" => "delete", $recommendation->id],
    [
        "confirm" => __(
            "Are you sure you want to delete recommendation for {0}?",
            h($recommendation->member_sca_name . ' about ' . $recommendation->award->name),
        ),
        "title" => __("Delete"),
        "class" => "btn btn-danger btn-sm",
    ],
); ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __('Award') ?></th>
    <td><?= $recommendation->hasValue('award') ? $this->Html->link($recommendation->award->name, ['controller' => 'Awards', 'action' => 'view', $recommendation->award->id]) : '' ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Member Sca Name') ?></th>
    <td><?php
        if ($recommendation->member_id == null) {
            echo h($recommendation->member_sca_name);
        } else {
            echo $this->Html->link($recommendation->member->sca_name, ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $recommendation->member_id]);
        } ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Status') ?></th>
    <td><?= h($recommendation->status) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Requester Sca Name') ?></th>
    <td><?php
        if ($recommendation->requester_id == null) {
            echo h($recommendation->requester_sca_name);
        } else {
            echo $this->Html->link($recommendation->requester->sca_name, ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $recommendation->requester_id]);
        } ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Contact Email') ?></th>
    <td><?= h($recommendation->contact_email) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Contact Number') ?></th>
    <td><?= h($recommendation->contact_number) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Suggested Events') ?></th>
    <td>
        <ul>
            <?php foreach ($recommendation->events as $events) : ?>
            <li><?= $this->Html->link($events->name, ['controller' => 'Events', 'action' => 'view', $events->id]) ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Call Into Court') ?></th>
    <td><?= h($recommendation->call_into_court) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Court Availability') ?></th>
    <td><?= h($recommendation->court_availability) ?></td>
</tr>
<?php if ($recommendation->member) : ?>
<tr>
    <th colspan='2' scope="row">
        <h4><?= __('Member Details') ?></h4>
    </th>
</tr>
<?php
    $member = $recommendation->member;
    echo $this->element('members/memberDetails', [
        'member' => $member,
    ]);
else :
    echo '<tr><th colspan="2" scope="row">' . __('Member Details') . '</th></tr>';
    echo '<tr><td colspan="2">' . __('Member not found in database') . '</td></tr>';
endif;
$this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link active" id="nav-reason-tab" data-bs-toggle="tab" data-bs-target="#nav-reason" type="button"
    role="tab" aria-controls="nav-reason" aria-selected="true"><?= __("Reason") ?>
</button>
<button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab"
    aria-controls="nav-notes" aria-selected="false"><?= __("Notes") ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade active show m-3" id="nav-reason" role="tabpanel" aria-labelledby="nav-reason-tab">
    <?= $this->Text->autoParagraph(h($recommendation->reason)) ?>
</div>
<div class="related tab-pane fade m-3" id="nav-notes" role="tabpanel" aria-labelledby="nav-notes-tab">
    <?= $this->cell('Notes', [
        'topic_id' => $recommendation->id,
        'topic_model' => 'Awards.Recommendations',
        'viewPrivate' => $user->can("viewPrivateNotes", "Awards.Recommendations"),
    ]) ?>
</div>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals");

echo $this->Form->create($recommendation, [
    "id" => "recommendation_form",
    "url" => [
        "controller" => "Recommendations",
        "action" => "edit",
        $recommendation->id,
    ],
]);
echo $this->Modal->create("Edit Recommendation", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("requester_id", [
        "type" => "hidden",
        "value" => $this->Identity->get("id"),
    ]);
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "id" => "recommendation__member_id",
    ]);
    echo $this->Form->control("member_sca_name", [
        "type" => "text",
        "label" => "Recommendation For",
        "id" => "recommendation__sca_name",
    ]);
    echo $this->Form->control('not_found', [
        'type' => 'checkbox',
        'label' => "Name not registered in " . $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . " database",
        "id" => "recommendation__not_found",
        "value" => "on",
        "disabled" => true,
        "checked" => ($recommendation->member_id == null)
    ]);
    echo $this->Form->control('branch_id', ['options' => $branches, 'empty' => true, "label" => "Member Of", "id" => "recommendation__branch_id"]);
    $selectOptions = [];
    foreach ($callIntoCourtOptions as $option) {
        $selectOptions[$option] = $option;
    }
    echo $this->Form->control(
        'call_into_court',
        [
            'options' => $selectOptions,
            'empty' => true,
            "id" => "recommendation__call_into_court",
            "required" => true
        ]
    );
    $selectOptions = [];
    foreach ($courtAvailabilityOptions as $option) {
        $selectOptions[$option] = $option;
    }
    echo $this->Form->control(
        'court_availability',
        [
            'options' => $selectOptions,
            'empty' => true,
            "id" => "recommendation__court_availability",
            "required" => true
        ]
    );
    echo $this->Form->control('status', ['options' => $statusList]);
    echo $this->Form->control('domain_id', ['options' => $awardsDomains, 'empty' => true, "label" => "Award Type", "id" => "recommendation__domain_id"]); ?>
    <div class="role p-3" id="award_descriptions">

    </div>
    <?php
    echo $this->Form->control('award_id', ['options' => ["Please select the type of award first."], "disabled" => true, "id" => "recommendation__award_id"]);
    echo $this->Form->control('contact_number');
    echo $this->Form->control('contact_email');
    echo $this->Form->control('reason');
    echo $this->Form->control('events._ids', [
        'label' => 'Events They may Attend:',
        "type" => "select",
        "multiple" => "checkbox",
        'options' => $eventList
    ]);
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "recommendation_submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);

echo $this->Form->end();
?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>
<?php echo $this->KMP->startBlock("script"); ?>
<script>
class recommendationsAdd {
    constructor() {
        this.ac = null;
    };
    //onInput for Autocomplete
    nameNotFound() {
        var notFound = $('#recommendation__not_found');
        var branch = $('#recommendation__branch_id').parent();
        notFound.prop('checked', true);
        branch.removeClass('d-none');
        $('#recommendation_submit').prop('disabled', false);
    }
    searchHadResults() {
        var notFound = $('#recommendation__not_found');
        var branch = $('#recommendation__branch_id').parent();
        notFound.prop('checked', false);
        branch.addClass('d-none');
    }

    getPublicProfile(memberId) {
        var url =
            '<?= $this->URL->build(['controller' => 'Members', 'action' => 'PublicProfile', 'plugin' => null]) ?>/' +
            memberId;
        $.get(url, function(data) {
            if (data) {
                var memberLinks = $('#member_links');
                memberLinks.empty();
                $("#recommendation__call_into_court").prop('disabled', false);
                $("#recommendation__court_availability").prop('disabled', false);
                if (data["additional_info"]) {
                    var callIntoCourt = data["additional_info"]["CallIntoCourt"];
                    var courtAvailability = data["additional_info"]["CourtAvailability"];
                    if (callIntoCourt && callIntoCourt != "") {
                        $("#recommendation__call_into_court").val(callIntoCourt);
                    }
                    if (courtAvailability && courtAvailability != "") {
                        $("#recommendation__court_availability").val(courtAvailability);
                    }
                }
            }
        });
    }

    run() {
        $('#recommendation__branch_id').parent().addClass('d-none');
        var me = this;
        var searchUrl =
            '<?= $this->URL->build(['controller' => 'Members', 'action' => 'SearchMembers', 'plugin' => null]) ?>';
        KMP_utils.configureAutoComplete(me.ac, searchUrl, 'recommendation__sca_name', 'id', 'sca_name',
            'recommendation__member_id', me.searchHadResults, me.nameNotFound);


        $('#recommendation__member_id').change(function() {
            if ($('#recommendation__member_id').val() > 0) {
                //enable button
                var notFound = $('#recommendation__not_found');
                var branch = $('#recommendation__branch_id').parent();
                notFound.prop('checked', false);
                branch.addClass('d-none');
                $('#recommendation_submit').prop('disabled', false);
            } else {
                //disable button
                $('#recommendation_submit').prop('disabled', true);
            }
        });
        $('#recommendation_form').on('submit', function(e) {
            if (
                ($('#recommendation__member_id').val() > 0 ||
                    $('#recommendation__not_found').prop('checked')
                ) &&
                $('#recommendation__award_id').val() > 0
            ) {
                $('#recommendation__not_found').prop('disabled', false);
            }
        });

        $('#recommendation__domain_id').change(function() {
            var domainId = $('#recommendation__domain_id').val();
            var awardSelect = $('#recommendation__award_id');
            var awardDescription = $('#award_descriptions');
            if (domainId > 0) {
                var awardUrl =
                    '<?= $this->URL->build(['controller' => 'Awards', 'action' => 'awardsByDomain', 'plugin' => "Awards"]) ?>/' +
                    domainId;
                $.get(awardUrl, function(data) {
                    awardSelect.empty();
                    awardSelect.append('<option value=""></option>');
                    awardDescription.empty();
                    var tabButtons = $('<ul class="nav nav-tabs" role="tablist"></div>');
                    var tabContentArea = $(
                        '<div class="tab-content border border-top-0 border-light-subtle p-2"></div>'
                    );
                    var active = "active";
                    var show = "show";
                    data.forEach(award => {
                        var selected = "";
                        if (award.id == <?= $recommendation->award_id ?>) {
                            selected = "selected";
                        };
                        awardSelect.append('<option value="' + award.id + '" ' + selected +
                            ' >' + award
                            .name + " - " + award.level.name +
                            '</option>');
                        var tabButton = $(
                            '<li class="nav-item" role="presentation"><button class="nav-link ' +
                            active +
                            '" id="award_' + award.id +
                            '_btn" data-bs-toggle="tab" data-bs-target="#award_' + award
                            .id + '"' +
                            ' type="button" role="tab" aria-controls="haward_' + award
                            .id + '" aria-selected="true">' + award.name +
                            '</button></li>');
                        var tabContent = $('<div class="tab-pane fade ' + active + ' ' +
                            show + '" id="award_' + award.id +
                            '" role="tabpanel" aria-labelledby="award_' + award.id +
                            '_btn">' + award.name + ": " + award.description + '</div>');
                        active = "";
                        show = "";
                        tabButtons.append(tabButton);
                        tabContentArea.append(tabContent);
                    });
                    awardDescription.append(tabButtons);
                    awardDescription.append(tabContentArea);
                    awardSelect.prop('disabled', false);
                });
            } else {
                awardSelect.prop('disabled', true);
                awardSelect.append('<option value="">Please select the type of award first.</option>');
            }
        });
        $('#recommendation__award_id').on("change", function() {
            var awardId = $('#recommendation__award_id').val();
            if (awardId > 0) {
                var tabid = "award_" + awardId + "_btn";
                $("#" + tabid).click();
            }
        });
        $('#recommendation__domain_id').trigger('change');
    }
};
window.addEventListener('DOMContentLoaded', function() {
    var view = new recommendationsAdd();
    view.run();
});
</script>
<?php echo $this->KMP->endBlock(); ?>