<?php
$officeOptions = [];

function addOptions($office, $depth, &$officeOptions)
{
    if (!isset($officeOptions[$office->id])) {
        $prefix = str_repeat("-", $depth);
        $officeOptions[$office->id] = $prefix . " " . $office->name;
        if (!empty($office->deputies)) {
            foreach ($office->deputies as $deputy) {
                addOptions($deputy, $depth + 1, $officeOptions);
            }
        }
    }
}
foreach ($offices as $office) {
    if ($office->deputy_to_id == null) {
        addOptions($office, 0, $officeOptions);
    }
}
echo $this->Modal->create("Assign Officer", [
    "id" => "assignOfficerModal",
    "close" => true,
]);
// get the id and name from the offices to use for dropdown options


?>
<fieldset>
    <?php
    echo $this->Form->create($newOfficer, [
        "id" => "assign_officer__form",
        "url" => [
            "controller" => "Officers",
            "action" => "add",
        ],
    ]);
    echo $this->Form->control("branch_id", [
        "type" => "hidden",
        "value" => $id,
    ]);
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "id" => "assign_officer__member_id",
    ]);
    echo $this->Form->control("office_id", [
        "id" => "assign_officer__office_id",
        "options" => $officeOptions,
    ]); ?>
    <div class="mb-3 form-group text" id="assign_officer__deputy_description_block">
        <label class="form-label" for="assign_officer__deputy_description">
            Deputy Description
        </label>
        <input type="text" name="deputy_description" class=" form-control" id="assign_officer__deputy_description" maxlength="255">
    </div>
    <?php
    echo $this->Form->control("sca_name", [
        "type" => "text",
        "label" => "SCA Name",
        "id" => "assign_officer__sca_name",
    ]);
    echo $this->Form->control("start_on", [
        "type" => "date",
        "label" => __("Start Date"),
    ]); ?>
    <div class="mb-3 form-group date" id="assign_officer__end_date_block">
        <label class="form-label" for="assign_officer__end_date">
            End Date
        </label>
        <input type="date" name="end_on" id="assign_officer__end_date" class="form-control" value="">
    </div>
    <?php
    echo $this->Form->end();
    ?>
</fieldset>
<script>
    var officeData = <?php echo json_encode($offices); ?>;
</script>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "assign_officer__submit",
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
?>