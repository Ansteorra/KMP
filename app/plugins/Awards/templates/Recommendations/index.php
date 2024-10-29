<?php

use Cake\Utility\Inflector;

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $activityGroup
 */
$ticks = microtime(true);
if (!$isTurboFrame) {
    $this->extend("/layout/TwitterBootstrap/dashboard");

    echo $this->KMP->startBlock("title");
    echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Award Recommendations';
    $this->KMP->endBlock();
}
$recommendation = [];
?>
<h3>
    Award Recommendations
    <?php if ($status != "Index") : ?>
    : <?= Inflector::humanize($status) ?>
    <?php endif; ?>
</h3>
<?php
$tabs = [];
if ($pageConfig['table']['use']) {
    $tabs["table"] = [
        "label" => __("Table"),
        "id" => "tableView",
        "selected" => true,
        "turboUrl" => $this->URL->build(["controller" => "Recommendations", "action" => "Table", "plugin" => "Awards", $view, $status])
    ];
}
if ($pageConfig['board']['use']) {
    $tabs["board"] = [
        "label" => __("Board"),
        "id" => "boardView",
        "selected" => false,
        "turboUrl" => $this->URL->build(["controller" => "Recommendations", "action" => "Board", "plugin" => "Awards", $view])
    ];
}

echo $this->element('turboActiveTabs', [
    'user' => $user,
    'tabGroupName' => "authorizationTabs",
    'tabs' => $tabs,
    'updateUrl' => false,
]);
echo $this->KMP->startBlock("modals"); ?>

<?= $this->element('recommendationQuickEditModal') ?>
<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>