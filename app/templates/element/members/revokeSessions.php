<?php
/** The action uses the same member-specific authority as changing a password. */
$securityIdentity = $this->request->getAttribute('identity');
if ($securityIdentity && $securityIdentity->can('changePassword', $member)) : ?>
<?= $this->Form->postLink(
    __('Sign out all devices'),
    ['plugin' => null, 'controller' => 'Members', 'action' => 'revokeSessions', $member->id],
    [
        'class' => 'btn btn-outline-danger btn-sm online-only-btn',
        'confirm' => __('Sign out all devices for this member and disable their quick login devices? This also signs you out if this is your account. Disconnected offline cards expire within seven days.'),
    ],
) ?>
<?php endif; ?>
