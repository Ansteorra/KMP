<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $warrants
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Officers';
$this->KMP->endBlock(); ?>
<h3>
    Officers
</h3>
<?php
echo $this->element('turboActiveTabs', [
    'user' => $user,
    'tabGroupName' => "authorizationTabs",
    'tabs' => [
        "active" => [
            "label" => __("Active"),
            "id" => "current-officers",
            "selected" => true,
            "turboUrl" => $this->URL->build(["controller" => "Officers", "action" => "AllOfficers", "current"])
        ],

    ]
]);
?>