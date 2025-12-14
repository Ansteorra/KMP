<?php

/**
 * Member Authorizations View - Dataverse Grid Version
 *
 * This template displays authorizations for a member using the dv_grid system
 * with system views for current/pending/previous states.
 *
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $authorizations
 * @var \Activities\Model\Entity\Member $member
 * @var string $state Initial state (current, pending, previous)
 */

// Map old state to system view
$initialSystemView = match ($state) {
    'current' => 'current',
    'pending' => 'pending',
    'previous' => 'previous',
    default => 'current',
};

echo $this->element('dv_grid', [
    'gridKey' => 'Activities.Authorizations.member',
    'frameId' => $turboFrameId ?? 'member-auth-grid',
    'dataUrl' => $this->Url->build([
        'plugin' => 'Activities',
        'controller' => 'Authorizations',
        'action' => 'memberAuthorizationsGridData',
        '?' => ['member_id' => $member->id, 'system_view' => $initialSystemView]
    ]),
    'title' => null,
    'lazyLoad' => true,
]);
