<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
?>
<turbo-frame id="security-settings">
    <?= $this->element('members/securityWizard', compact('member', 'isSelf', 'passwordReset')) ?>
</turbo-frame>
