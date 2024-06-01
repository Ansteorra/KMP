<?php $this->extend("/layout/TwitterBootstrap/signin"); ?>

<div class="card" style="width: 15rem;">
    <?= $this->Html->image($headerImage, [
        "class" => "card-img-top",
        "alt" => "site logo",
    ]) ?>
    <div class="card-body">
        <h5 class="card-title">Set New Password</h5>
        <div class="card-text">
            <?= $this->Form->create($passwordReset) ?>
            <?= $this->Form->control("new_password", ["type" => "password"]) ?>
            <?= $this->Form->control("confirm_password", ["type" => "password"]) ?>
            <?= $this->Form->button("Reset Password") ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>