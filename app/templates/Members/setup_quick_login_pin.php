<?php

/**
 * @var \App\View\AppView $this
 * @var string $headerImage
 * @var string $quickLoginEmail
 * @var string $quickLoginDeviceId
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/signin");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Setup Quick Login';
$this->KMP->endBlock(); ?>
<div data-controller="login-device-auth">
    <div class="card login-card form-signin">
        <?= $this->Html->image($headerImage, [
            "class" => "card-img-top",
            "alt" => "site logo",
        ]) ?>
        <div class="card-body">
            <h5 class="card-title"><?= __("Set quick login PIN") ?></h5>
            <p class="text-start text-muted small mb-3">
                <?= __("Signed in as") ?> <strong><?= h($quickLoginEmail) ?></strong>.
                <?= __("Choose a 4-10 digit PIN to enable quick login and offline privacy protection on this device.") ?>
            </p>

            <?= $this->Form->create(null, [
                "class" => "mb-0",
                "data-login-device-auth-target" => "pinSetupForm",
            ]) ?>
            <?= $this->Form->hidden("email_address", [
                "value" => $quickLoginEmail,
                "data-login-device-auth-target" => "pinSetupEmail",
            ]) ?>
            <?= $this->Form->hidden("quick_login_device_id", [
                "value" => $quickLoginDeviceId,
                "data-login-device-auth-target" => "pinSetupDeviceId",
            ]) ?>
            <?= $this->Form->control("quick_login_pin", [
                "type" => "password",
                "label" => __("Quick login PIN"),
                "autocomplete" => "new-password",
                "inputmode" => "numeric",
                "pattern" => "[0-9]*",
                "required" => true,
                "maxlength" => 10,
                "minlength" => 4,
                "data-login-device-auth-target" => "pinSetupPin",
                "container" => ["class" => "form-group"],
            ]) ?>
            <?= $this->Form->control("quick_login_pin_confirm", [
                "type" => "password",
                "label" => __("Confirm quick login PIN"),
                "autocomplete" => "new-password",
                "inputmode" => "numeric",
                "pattern" => "[0-9]*",
                "required" => true,
                "maxlength" => 10,
                "minlength" => 4,
                "data-login-device-auth-target" => "pinSetupConfirm",
                "container" => ["class" => "form-group mb-2"],
            ]) ?>
            <?= $this->Form->submit(__("Save PIN"), [
                "class" => "w-100 btn btn-lg btn-primary",
            ]) ?>
            <?= $this->Form->end() ?>

            <?= $this->Html->link(
                __("Skip for now"),
                [
                    "action" => "setupQuickLoginPin",
                    "?" => ["skip" => 1],
                ],
                ["class" => "btn btn-link btn-sm mt-2"],
            ) ?>
        </div>
    </div>
</div>
