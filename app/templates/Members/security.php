<?php /** @var \App\View\AppView $this */ ?>
<turbo-frame id="passkey-settings">
    <?= $this->element('members/securityWizard', compact('member', 'isSelf', 'canManagePasskeys', 'passkeys', 'passwordReset')) ?>
</turbo-frame>
