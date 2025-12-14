<?php

/**
 * Member Authorizations Cell Display Template
 * 
 * Displays authorization status for a member using the dv_grid system
 * with integrated system views for current/pending/previous states.
 *
 * @var \App\View\AppView $this
 * @var int $id Member ID
 * @var int $pendingAuthCount Count of pending authorizations
 * @var bool $isEmpty Whether member has any authorizations
 * @var \Cake\ORM\ResultSet $activities Available activities for requesting
 */

$user = $this->request->getAttribute("identity");
?>
<button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
    data-bs-target="#requestAuthModal">Request Authorization</button>
<?= $this->Form->postLink(
    __("Email Link to Mobile Card"),
    ["controller" => "Members", "action" => "SendMobileCardEmail", $id],
    ["class" => "btn btn-sm mb-3 btn-secondary"],
) ?>

<?php if (!$isEmpty): ?>
    <?= $this->element('dv_grid', [
        'gridKey' => 'Activities.Authorizations.member',
        'frameId' => 'member-auth-grid',
        'dataUrl' => $this->Url->build([
            'plugin' => 'Activities',
            'controller' => 'Authorizations',
            'action' => 'memberAuthorizationsGridData',
            '?' => ['member_id' => $id, 'system_view' => 'current']
        ]),
        'title' => null,
        'lazyLoad' => true,
    ]) ?>
<?php else: ?>
    <p>No Authorizations</p>
<?php endif; ?>

<?php
echo $this->KMP->startBlock("modals");
echo $this->element('requestAuthorizationModal', [
    'user' => $user,
]);
echo $this->element('revokeAuthorizationModal', [
    'user' => $user,
]);
echo $this->element('renewAuthorizationModal', [
    'user' => $user,
]);
$this->KMP->endBlock();
?>