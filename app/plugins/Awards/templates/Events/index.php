<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $activityGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Award Rec Events';
$this->KMP->endBlock(); ?>
<h3>
    Award Rec Events
</h3>
<?php
echo $this->element('turboActiveTabs', [
    'user' => $user,
    'tabGroupName' => "authorizationTabs",
    'tabs' => [
        "active" => [
            "label" => __("Active"),
            "id" => "active-events",
            "selected" => true,
            "turboUrl" => $this->URL->build(["plugin" => "Awards", "controller" => "Events", "action" => "AllEvents", "active"])
        ],
        "closed" => [
            "label" => __("Closed"),
            "id" => "closed-events",
            "selected" => true,
            "turboUrl" => $this->URL->build(["plugin" => "Awards", "controller" => "Events", "action" => "AllEvents", "closed"])
        ]
    ]
]);