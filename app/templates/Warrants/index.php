<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $warrants
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Warrants';
$this->KMP->endBlock(); ?>
<h3>
    Warrants
</h3>
<?php
echo $this->element('turboActiveTabs', [
    'user' => $user,
    'tabGroupName' => "authorizationTabs",
    'tabs' => [
        "active" => [
            "label" => __("Active"),
            "id" => "current-warrants",
            "selected" => true,
            "turboUrl" => $this->URL->build(["controller" => "Warrants", "action" => "AllWarrants", "current"])
        ],
        "upcoming" => [
            "label" => __("Upcoming"),
            "id" => "upcoming-warrants",
            "selected" => true,
            "turboUrl" => $this->URL->build(["controller" => "Warrants", "action" => "AllWarrants", "upcoming"])
        ],
        "pending" => [
            "label" => __("Pending"),
            "id" => "pending-warrants",
            "selected" => true,
            "turboUrl" => $this->URL->build(["controller" => "Warrants", "action" => "AllWarrants", "pending"])
        ],
        "previous" => [
            "label" => __("Previous"),
            "id" => "previous-warrants",
            "selected" => false,
            "turboUrl" => $this->URL->build(["controller" => "Warrants", "action" => "AllWarrants", "previous"])
        ]
    ]
]);
?>