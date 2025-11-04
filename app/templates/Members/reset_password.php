<?php $this->extend("/layout/TwitterBootstrap/signin");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Password Reset';
$this->KMP->endBlock(); ?>

<div class="card" style="width: 15rem;">
    <?= $this->Html->image($headerImage, [
        "class" => "card-img-top",
        "alt" => "site logo",
    ]) ?>
    <div class="card-body">
        <h5 class="card-title">Set New Password</h5>
        <div class="card-text">
            <?= $this->Form->create($passwordReset) ?>
            <?= $this->Form->control("new_password", [
                "type" => "password",
                "autocomplete" => "new-password",
                'help' => "Password must have a minimum of 12 characters"
            ]) ?>
            <?= $this->Form->control("confirm_password", [
                "type" => "password",
                "autocomplete" => "new-password"
            ]) ?>
            <?= $this->Form->button("Reset Password") ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>