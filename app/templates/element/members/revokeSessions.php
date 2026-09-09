<?php
declare(strict_types=1);

/** The action uses the same member-specific authority as changing a password. */
$securityIdentity = $this->request->getAttribute('identity');
if ($securityIdentity && $securityIdentity->can('changePassword', $member)) :
    $isSelf = (int)$securityIdentity->getIdentifier() === (int)$member->id;
    $shortSiteTitle = $this->KMP->getAppSetting('KMP.ShortSiteTitle');
    ?>
        <h3 class="h5" tabindex="-1">Sign out all devices?</h3>
        <p><?= $isSelf
            ? 'This signs you out everywhere, including this device.'
            : 'This signs the member out on all their devices.' ?></p>
        <ul>
            <li>Quick login PINs for this account will stop working.</li>
            <li>The <?= h($shortSiteTitle) ?> password is needed to sign in again.
                Quick login can then be set up again.</li>
            <li>Cards saved on a device without internet access may remain available for up to seven days.</li>
        </ul>
        <p>Continue only when you are ready to sign in again with a password.</p>
        <?= $this->Form->create(null, [
            'url' => ['controller' => 'Members', 'action' => 'revokeSessions', $member->id, 'plugin' => null],
            'data-turbo' => 'false',
            ]) ?>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" data-action="security-settings#home">Cancel</button>
            <?= $this->Form->button('Sign out all devices', ['class' => 'btn btn-danger']) ?>
        </div>
        <?= $this->Form->end() ?>
<?php endif; ?>
