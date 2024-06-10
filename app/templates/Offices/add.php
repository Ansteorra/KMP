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
$user = $this->request->getAttribute("identity");
?>

<div class="officer form content">
    <?= $this->Form->create($office) ?>
    <fieldset>
        <legend><?= __("Add Office") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("department_id", [
            "options" => $departments,
        ]);
        echo $this->Form->control("term_length");
        echo $this->Form->control("required_office", ["switch" => true, 'label' => 'Required']);
        echo $this->Form->control("can_skip_report", ["switch" => true, 'label' => 'Skip Report']);
        echo $this->Form->control("requires_warrant", ["switch" => true, 'label' => 'Warrant']);
        echo $this->Form->control("only_one_per_branch", ["switch" => true, 'label' => 'One Per Branch']);
        echo $this->Form->control("deputy_to_id", [
            "options" => $offices,
            "empty" => true,
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