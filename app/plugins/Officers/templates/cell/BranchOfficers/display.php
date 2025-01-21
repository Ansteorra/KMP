<?php
$user = $this->request->getAttribute("identity");
?>
<?php if ($user->checkCan("add", "Officers.Officers")): ?>
<button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
    data-bs-target="#assignOfficerModal">Assign Officer</button>
<?php endif; ?>
<?php

echo $this->element('turboActiveTabs', [
    'user' => $user,
    'tabGroupName' => "authorizationTabs",
    'tabs' => [
        "active" => [
            "label" => __("Active"),
            "id" => "current-officers",
            "selected" => true,
            "turboUrl" => $this->URL->build(["controller" => "Officers", "action" => "BranchOfficers", "plugin" =>
            "Officers", $id, "current"])
        ],
        "upcoming" => [
            "label" => __("Incoming"),
            "id" => "upcoming-officers",
            "selected" => false,
            "turboUrl" => $this->URL->build(["controller" => "Officers", "action" => "BranchOfficers", "plugin" =>
            "Officers", $id, "upcoming"])
        ],
        "previous" => [
            "label" => __("Previous"),
            "id" => "previous-officers",
            "selected" => false,
            "turboUrl" => $this->URL->build(["controller" => "Officers", "action" => "BranchOfficers", "plugin" =>
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

echo $this->element('assignModal', [
    'user' => $user,
]);

$this->KMP->endBlock(); ?>