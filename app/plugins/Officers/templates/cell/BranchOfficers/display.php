<?php

/**
 * Branch Officers Cell Display Template
 * 
 * Uses the Dataverse Grid pattern with lazy-loading turbo-frame architecture.
 * The grid loads from the Officers/gridData endpoint with branch_id context.
 * Includes the Assign Officer button for authorized users.
 * 
 * @var \App\View\AppView $this
 * @var int $id Branch ID
 * @var int $branchId Internal branch ID for grid queries and permission checks
 * @var array $offices Office tree for assignment modal
 * @var \Officers\Model\Entity\Officer $newOfficer Empty officer entity for forms
 */

$user = $this->request->getAttribute("identity");
?>

<!-- Header with Assign Officer button -->
<?php if ($user->checkCan("assign", "Officers.Officers", $branchId) && count($offices) > 0): ?>
    <div class="mb-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
            data-bs-target="#assignOfficerModal">
            <i class="bi bi-plus-lg"></i> Assign Officer
        </button>
    </div>
<?php endif; ?>

<!-- Dataverse Grid with Branch Context -->
<?= $this->element('dv_grid', [
    'gridKey' => 'Officers.Officers.branch.main',
    'frameId' => 'branch-officers-grid',
    'dataUrl' => $this->Url->build([
        'plugin' => 'Officers',
        'controller' => 'Officers',
        'action' => 'gridData',
        '?' => ['branch_id' => $branchId]
    ]),
]) ?>

<?php
echo $this->KMP->startBlock("modals");

echo $this->element('releaseModal', [
    'user' => $user,
]);

echo $this->element('assignModal', [
    'user' => $user,
]);

echo $this->element('editModal', [
    'user' => $user,
]);
$this->KMP->endBlock();
?>