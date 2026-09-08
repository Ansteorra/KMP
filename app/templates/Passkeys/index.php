<?php
/** @var \App\View\AppView $this */
if (!$this->request->getHeaderLine('Turbo-Frame')) :
    if (!$mobile) {
        $this->extend('/layout/TwitterBootstrap/dashboard');
    }
?>
<section class="p-3" aria-labelledby="security-title">
    <h1 id="security-title">Sign-in settings</h1>
    <p>Use your device to sign in more easily. We will guide you through each step.</p>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#passkeyModal">Manage passkeys</button>
    <p class="mt-3"><a href="/members/profile">Back to my account</a></p>
</section>
<?php else : ?>
<turbo-frame id="passkey-settings">
    <?= $this->element('members/passkeyWizard', compact('member', 'passkeys')) ?>
</turbo-frame>
<?php endif; ?>
