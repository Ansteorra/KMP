<?php

use \App\Model\Entity\WarrantRoster;

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WarrantRoster[]|\Cake\Collection\CollectionInterface $warrantRosters
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Warrant Rosters';
$this->KMP->endBlock(); ?>
<h3>
    Warrant Rosters
</h3>
<?php

$tabs["Pending"] = [
    "label" => __("Pending"),
    "id" => "pending",
    "selected" => true,
    "turboUrl" => $this->URL->build(["controller" => "warrant-rosters", "action" => "All_Rosters", "plugin" => null, WarrantRoster::STATUS_PENDING])
];

$tabs["Approved"] = [
    "label" => __("Approved"),
    "id" => "approved",
    "selected" => false,
    "turboUrl" => $this->URL->build(["controller" => "warrant-rosters", "action" => "All_Rosters", "plugin" => null,  WarrantRoster::STATUS_APPROVED])
];

$tabs["Declined"] = [
    "label" => __("Declined"),
    "id" => "declined",
    "selected" => false,
    "turboUrl" => $this->URL->build(["controller" => "warrant-rosters", "action" => "All_Rosters", "plugin" => null,  WarrantRoster::STATUS_DECLINED])
];

echo $this->element('turboActiveTabs', [
    'user' => $user,
    'tabGroupName' => "authorizationTabs",
    'tabs' => $tabs,
    'updateUrl' => false,
]);
?>