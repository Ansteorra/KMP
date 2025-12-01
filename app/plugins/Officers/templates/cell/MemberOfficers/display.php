<?php

/**
 * Member Officers Cell Display Template
 * 
 * Uses the Dataverse Grid pattern with lazy-loading turbo-frame architecture.
 * The grid loads from the Officers/gridData endpoint with member_id context.
 * 
 * @var \App\View\AppView $this
 * @var int $id Member ID
 */

$user = $this->request->getAttribute("identity");
if ($id == -1) {
    $id = $user->id;
} else {
    $id = (int)$id;
}
?>

<!-- Dataverse Grid with Member Context -->
<?= $this->element('dv_grid', [
    'gridKey' => 'Officers.Officers.member.main',
    'frameId' => 'member-officers-grid',
    'dataUrl' => $this->Url->build([
        'plugin' => 'Officers',
        'controller' => 'Officers',
        'action' => 'gridData',
        '?' => ['member_id' => $id]
    ]),
]) ?>

<?php
echo $this->KMP->startBlock("modals");

echo $this->element('releaseModal', [
    'user' => $user,
]);

echo $this->element('editModal', [
    'user' => $user,
]);
$this->KMP->endBlock();
?>