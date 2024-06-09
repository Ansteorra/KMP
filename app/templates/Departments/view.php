<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>

<div class="activityGroup view large-9 medium-8 columns content">
    <div class="row align-items-start">
        <div class="col">
            <h3>
                <a href="#" onclick="window.history.back();" class="bi bi-arrow-left-circle"></a>
                <?= h($department->name) ?>
            </h3>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
        </div>
    </div>
</div>
<?php
$this->start("modals");
echo $this->Modal->create("Edit Department", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create($department, [
        "id" => "edit_entity",
        "url" => [
            "controller" => "Departments",
            "action" => "edit",
            $department->id,
        ],
    ]);
    echo $this->Form->control("name");
    echo $this->Form->end();
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit",
        "onclick" => '$("#edit_entity").submit();',
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>

<?php //finish writing to modal block in layout
$this->end(); ?>