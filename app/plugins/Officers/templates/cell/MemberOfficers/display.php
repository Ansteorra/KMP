<?php
$user = $this->request->getAttribute("identity");
if ($id == -1) {
    $id = $user->id;
} else {
    $id = (int)$id;
}
?>

<?php



echo $this->element('turboActiveTabs', [
    'user' => $user,
    'tabGroupName' => "authorizationTabs",
    'tabs' => [
        "active" => [
            "label" => __("Active"),
            "id" => "current-officers",
            "selected" => true,
            "turboUrl" => $this->URL->build(["controller" => "Officers", "action" => "MemberOfficers", "plugin" =>
            "Officers", $id, "current"])
        ],
        "upcoming" => [
            "label" => __("Incoming"),
            "id" => "upcoming-officers",
            "selected" => false,
            "turboUrl" => $this->URL->build(["controller" => "Officers", "action" => "MemberOfficers", "plugin" =>
            "Officers", $id, "upcoming"])
        ],
        "previous" => [
            "label" => __("Previous"),
            "id" => "previous-officers",
            "selected" => false,
            "turboUrl" => $this->URL->build(["controller" => "Officers", "action" => "MemberOfficers", "plugin" =>
            "Officers", $id, "previous"])
        ]
    ]
]);
?>

<?php

echo $this->KMP->startBlock("modals");

echo $this->element('releaseModal', [
    'user' => $user,
]);

echo $this->element('editModal', [
    'user' => $user,
]);
$this->KMP->endBlock(); ?>