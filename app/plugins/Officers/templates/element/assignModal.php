<?php
$officeOptions = [];

function addOptions($office, $depth, &$officeOptions)
{
    if (!isset($officeOptions[$office['id']])) {
        $prefix = str_repeat("-", $depth);
        $officeOptions[$office['id']] = [
            'text' => $prefix . " " . $office['name'],
            'value' => $office['id'],
            'is_deputy' => $office['deputy_to_id'] != null,
            'email_address' => $office['email_address'],
        ];
        if (!empty($office['deputies'])) {
            foreach ($office['deputies'] as $deputy) {
                addOptions($deputy, $depth + 1, $officeOptions);
            }
        }
    }
}
foreach ($offices as $office) {
    if ($office['enabled']) {
        addOptions($office, 0, $officeOptions);
    }
}
echo $this->Form->create($newOfficer, [
    "id" => "assign_officer__form",
    "url" => [
        "controller" => "Officers",
        "action" => "assign",
    ],
    "data-controller" => "officers-assign-officer",
]);
echo $this->Modal->create("Assign Officer", [
    "id" => "assignOfficerModal",
    "close" => true,
]);
// get the id and name from the offices to use for dropdown options


?>
<fieldset>
    <?php
    echo $this->Form->control("branch_id", [
        "type" => "hidden",
        "value" => $id,
    ]);
    echo $this->KMP->comboBoxControl(
        $this->Form,
        'office_name',
        'office_id',
        $officeOptions,
        "Office",
        true,
        false,
        [
            'data-officers-assign-officer-target' => 'office',
            'data-action' => 'change->officers-assign-officer#setOfficeQuestions'
        ]
    );
    ?>

    <div class="mb-3 form-group text" data-officers-assign-officer-target="deputyDescBlock">
        <label class="form-label" for="assign_officer__deputy_description">
            Deputy Description
        </label>
        <input type="text" name="deputy_description" class=" form-control" maxlength="255"
            data-officers-assign-officer-target="deputyDesc">
    </div>
    <?php
    $url = $this->Url->build([
        'controller' => 'Officers',
        'action' => 'AutoComplete',
        'plugin' => 'Officers'
    ]);
    echo $this->KMP->autoCompleteControl(
        $this->Form,
        'sca_name',
        'member_id',
        $url,
        "Officer",
        true,
        true,
        3,
        [
            'data-officers-assign-officer-target' => 'assignee',
            'data-action' => 'change->officers-assign-officer#checkReadyToSubmit',
        ]
    );
    echo $this->Form->control("start_on", [
        "type" => "date",
        "label" => __("Start Date"),
    ]); ?>
    <div class="mb-3 form-group date" data-officers-assign-officer-target="endDateBlock">
        <label class="form-label" for="assign_officer__end_date">
            End Date
        </label>
        <input type="date" name="end_on" id="assign_officer__end_date" class="form-control" value=""
            data-officers-assign-officer-target="endDate">
    </div>
    <div class="mb-3 form-group date" data-officers-assign-officer-target="emailAddressBlock">
        <label class="form-label" for="assign_officer__end_date">
            Email Address
        </label>
        <input type="email" name="email_address" id="assign_officer__email_address" class="form-control" value=""
            data-officers-assign-officer-target="emailAddress">
    </div>

</fieldset>
<?php

echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "data-officers-assign-officer-target" => "submitBtn",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>