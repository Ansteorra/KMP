<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>

<div class="activityGroup form content">
    <?= $this->Form->create($department) ?>
    <fieldset>
        <legend><?= __("Add Department") ?></legend>
        <?php echo $this->Form->control("name"); ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>