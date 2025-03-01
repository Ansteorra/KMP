<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission $permission
 * @var \App\Model\Entity\Activity[]|\Cake\Collection\CollectionInterface $activities
 * @var \App\Model\Entity\Role[]|\Cake\Collection\CollectionInterface $roles
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Office';
$this->KMP->endBlock();

?>

<div class="officer form content">
    <?= $this->Form->create($office, ['data-controller' => 'office-form',]) ?>
    <fieldset>
        <legend><?= __("Add Office") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("department_id", [
            "options" => $departments,
            "empty" => true,
        ]);
        echo $this->Form->control("branch_types", [
            "type" => "select",
            "multiple" => "checkbox",
            "options" => $branch_types,
            "switch" => true,
            "label" => [
                "class" => "required",
            ],

        ]);
        echo $this->Form->control("term_length", [
            "label" => "Term (Months)",
            "type" => "number",
            "tooltip" => "value of 0 will be treated as no term limit",
        ]);
        echo $this->Form->control("default_contact_address");
        echo $this->Form->control("required_office", ["switch" => true, 'label' => 'Required']);
        echo $this->Form->control("can_skip_report", ["switch" => true, 'label' => 'Skip Report']);
        echo $this->Form->control("requires_warrant", ["switch" => true, 'label' => 'Warrant']);
        echo $this->Form->control("only_one_per_branch", ["switch" => true, 'label' => 'One Per Branch']);
        echo $this->Form->control("is_deputy", [
            "type" => "checkbox",
            "switch" => true,
            'label' => 'Is Deputy Office',
            'data-action' => 'office-form#toggleIsDeputy',
            'data-office-form-target' => 'isDeputy'
        ]);
        echo $this->Form->control("reports_to_id", [
            "options" => $report_to_offices,
            "empty" => true,
            'data-office-form-target' => 'reportsTo',
            'container' => ['data-office-form-target' => 'reportsToBlock']
        ]);
        echo $this->Form->control("deputy_to_id", [
            "required" => true,
            "options" => $deputy_to_offices,
            "empty" => true,
            'data-office-form-target' => 'deputyTo',
            'container' => ['data-office-form-target' => 'deputyToBlock']
        ]);
        echo $this->Form->control("grants_role_id", [
            "options" => $roles,
            "empty" => true,
        ]);
        ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>