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
            "id" => "current-memberRoles",
            "selected" => true,
            "turboUrl" => $this->URL->build(["controller" => "Warrants", "action" => "AllWarrants", "current"])
        ],
        "pending" => [
            "label" => __("Pending"),
            "id" => "pending-memberRoles",
            "selected" => true,
            "turboUrl" => $this->URL->build(["controller" => "Warrants", "action" => "AllWarrants", "pending"])
        ],
        "upcoming" => [
            "label" => __("Upcoming"),
            "id" => "pending-memberRoles",
            "selected" => true,
            "turboUrl" => $this->URL->build(["controller" => "Warrants", "action" => "AllWarrants", "upcoming"])
        ],
        "previous" => [
            "label" => __("Previous"),
            "id" => "previous-memberRoles",
            "selected" => false,
            "turboUrl" => $this->URL->build(["controller" => "Warrants", "action" => "AllWarrants", "previous"])
        ]
    ]
]);
?>