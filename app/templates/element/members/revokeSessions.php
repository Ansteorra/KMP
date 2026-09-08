<?php
/** The action uses the same member-specific authority as changing a password. */
$securityIdentity = $this->request->getAttribute('identity');
if ($securityIdentity && $securityIdentity->can('changePassword', $member)) :
    $dialogId = 'revokeSessionsModal-' . (int)$member->id;
    $isSelf = (int)$securityIdentity->getIdentifier() === (int)$member->id;
?>
<button type="button" class="btn btn-outline-danger btn-sm online-only-btn" data-bs-toggle="modal"
    data-bs-target="#<?= h($dialogId) ?>">Sign out all devices</button>
<div class="modal fade" id="<?= h($dialogId) ?>" tabindex="-1" aria-labelledby="<?= h($dialogId) ?>Title">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="<?= h($dialogId) ?>Title">Sign out all devices?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel signing out devices"></button>
            </div>
            <div class="modal-body">
                <p><?= $isSelf ? 'This signs you out everywhere, including this device.' : h('This signs ' . $member->sca_name . ' out on all their devices.') ?></p>
                <ul>
                    <li>All passkeys for this account will stop working.</li>
                    <li><?= $isSelf ? 'You will need your KMP password to sign in again. You can then add a new passkey.' : 'The member will need their KMP password to sign in again, then can add a new passkey.' ?></li>
                    <li>Cards saved on a device without internet access may remain available for up to seven days.</li>
                </ul>
                <p class="mb-0">Continue only when you are ready to sign in again with a password.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <?= $this->Form->create(null, ['url' => ['plugin' => null, 'controller' => 'Members', 'action' => 'revokeSessions', $member->id]]) ?>
                <?= $this->Form->button('Sign out all devices', ['class' => 'btn btn-danger']) ?>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
