<?php
$user = $this->request->getAttribute("identity");
$search = $this->request->getQuery("search");
$search = $search ? trim($search) : null;
?>




<table class="table table-striped">
    <thead>
        <tr>
            <td colspan="4">
                <?php if ($user->checkCan("add", "Officers.Officers")): ?>
                <button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
                    data-bs-target="#assignOfficerModal">Assign Officer</button>
                <?php endif; ?>
            <td colspan="2" class="text-end">
                <form class="form-inline">

                    <div class="input-group">
                        <div class="input-group-text" id="btnSearch"><span class='bi bi-search'></span></div>
                        <input type="text" name="search" class="form-control" placeholder="Search..."
                            value="<?= $search ?>" aria-describedby="btnSearch" aria-label="Search">
                    </div>
                </form>
            </td>
        </tr>
    </thead>
</table>



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
            "Officers", $id, "current", '?' => ['search' => $search]])
        ],
        "upcoming" => [
            "label" => __("Incoming"),
            "id" => "upcoming-officers",
            "selected" => false,
            "turboUrl" => $this->URL->build(["controller" => "Officers", "action" => "BranchOfficers", "plugin" =>
            "Officers", $id, "upcoming", '?' => ['search' => $search]])
        ],
        "previous" => [
            "label" => __("Previous"),
            "id" => "previous-officers",
            "selected" => false,
            "turboUrl" => $this->URL->build(["controller" => "Officers", "action" => "BranchOfficers", "plugin" =>
            "Officers", $id, "previous", '?' => ['search' => $search]])
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