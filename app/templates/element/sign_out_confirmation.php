<?php
declare(strict_types=1);

?>
<section class="card mx-auto my-4" aria-labelledby="sign-out-heading">
    <div class="card-body">
        <h1 id="sign-out-heading" class="h4"><?= __('Sign out of this device') ?></h1>
        <p><?= __('Confirm to end your current sign-in on this device.') ?></p>
        <?= $this->Form->create(null, ['data-turbo' => 'false']) ?>
        <?= $this->Form->button(__('Sign out'), ['type' => 'submit', 'class' => 'btn btn-primary']) ?>
        <?= $this->Form->end() ?>
    </div>
</section>
