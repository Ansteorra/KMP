<?php /** @var \App\View\AppView $this */ ?>
<turbo-frame id="passkey-settings">
    <?= $this->element('members/passkeyWizard', compact('member', 'passkeys')) ?>
</turbo-frame>
