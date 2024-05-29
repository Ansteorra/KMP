<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Branch $branch
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>

<?php $this->start("tb_actions"); ?>
<li><?= $this->Html->link(
        __("List Branches"),
        ["action" => "index"],
        ["class" => "nav-link"],
    ) ?></li>
<?php $this->end(); ?>
<?php $this->assign(
    "tb_sidebar",
    '<ul class="nav flex-column">' . $this->fetch("tb_actions") . "</ul>",
); ?>

<div class="branches form content">
    <?= $this->Form->create($branch) ?>
    <fieldset>
        <legend><?= __("Add Branch") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("location");
        echo $this->Form->control("parent_id", [
            "options" => $treeList,
            "empty" => true,
        ]);
        ?>
    </fieldset>
    <?= $this->Form->button(__("Submit")) ?>
    <?= $this->Form->end() ?>
</div>