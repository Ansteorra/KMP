<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $Member
 * @var bool $quickLoginDisabled
 * @var string $quickLoginDisabledEmail
 */
$Member = []; ?>
<?php $this->extend("/layout/TwitterBootstrap/signin");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Login';
$this->KMP->endBlock(); ?>
<div data-controller="login-device-auth">
    <div class="card login-card form-signin">
        <?= $this->Html->image($headerImage, [
            "class" => "card-img-top",
            "alt" => "site logo",
        ]) ?>
        <div class="card-body">
            <h5 class="card-title">Log in</h5>
            <div class="card-text">
                <?= $this->Form->hidden("quick_login_disabled", [
                    "value" => !empty($quickLoginDisabled) ? "1" : "0",
                    "data-login-device-auth-target" => "quickDisabled",
                ]) ?>
                <?= $this->Form->hidden("quick_login_disabled_email", [
                    "value" => (string)($quickLoginDisabledEmail ?? ""),
                    "data-login-device-auth-target" => "quickDisabledEmail",
                ]) ?>
                <ul class="nav nav-tabs nav-fill login-mode-tabs mb-3 d-none" role="tablist"
                    data-login-device-auth-target="modeTabs">
                    <li class="nav-item" role="presentation">
                        <button type="button"
                            class="nav-link"
                            role="tab"
                            data-login-device-auth-target="quickTabButton"
                            data-action="click->login-device-auth#switchToQuick">
                            <?= __("Quick login") ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button"
                            class="nav-link"
                            role="tab"
                            data-login-device-auth-target="passwordTabButton"
                            data-action="click->login-device-auth#switchToPassword">
                            <?= __("Email + Password") ?>
                        </button>
                    </li>
                </ul>

                <div class="border rounded p-3 mb-3 d-none text-start" data-login-device-auth-target="quickExperience">
                    <h6 class="mb-1"><?= __("Quick login") ?></h6>
                    <p class="text-muted small mb-2" data-login-device-auth-target="quickLoginLabel">
                        <?= __("Enter your PIN to use quick login on this device.") ?>
                    </p>
                    <?php
                    $quickLoginUrl = ["action" => "login"];
                    $redirectTarget = trim((string)$this->request->getQuery("redirect", ""));
                    if ($redirectTarget !== "") {
                        $quickLoginUrl["?"] = ["redirect" => $redirectTarget];
                    }
                    ?>
                    <?= $this->Form->create(null, [
                        "url" => $quickLoginUrl,
                        "class" => "mb-2",
                        "data-login-device-auth-target" => "quickForm",
                    ]) ?>
                    <?= $this->Form->hidden("login_method", ["value" => "quick_pin"]) ?>
                    <?= $this->Form->hidden("email_address", [
                        "data-login-device-auth-target" => "quickEmail",
                    ]) ?>
                    <?= $this->Form->hidden("quick_login_device_id", [
                        "data-login-device-auth-target" => "quickDeviceId",
                    ]) ?>
                    <?= $this->Form->control("quick_login_pin", [
                        "type" => "password",
                        "label" => __("PIN"),
                        "autocomplete" => "current-password",
                        "inputmode" => "numeric",
                        "pattern" => "[0-9]*",
                        "required" => true,
                        "maxlength" => 10,
                        "minlength" => 4,
                        "container" => ["class" => "form-group mb-2"],
                    ]) ?>
                    <?= $this->Form->button(__("Quick login"), [
                        "class" => "w-100 btn btn-outline-primary",
                    ]) ?>
                    <?= $this->Form->end() ?>
                </div>

                <div data-login-device-auth-target="passwordExperience">
                    <?= $this->Form->create($Member, [
                        "class" => "mb-0",
                        "data-login-device-auth-target" => "passwordForm",
                    ]) ?>
                    <?= $this->Form->hidden("login_method", ["value" => "password"]) ?>
                    <?= $this->Form->hidden("quick_login_device_id", [
                        "data-login-device-auth-target" => "passwordDeviceId",
                    ]) ?>
                    <?= $this->Form->control("email_address", [
                        "type" => "email",
                        "label" => ["floating" => true],
                        "autofocus",
                        "autocomplete" => "email",
                        "inputmode" => "email",
                        "data-login-device-auth-target" => "email",
                        "data-action" => "input->login-device-auth#syncEmail",
                        "container" => ["class" => "form-group"],
                    ]) ?>
                    <?= $this->Form->control("password", [
                        "type" => "password",
                        "autocomplete" => "current-password",
                        "label" => ["floating" => true],
                        "container" => ["class" => "form-group"],
                    ]) ?>

                    <div class="form-check text-start mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="remember-my-id"
                            name="remember_my_id" data-login-device-auth-target="rememberId">
                        <label class="form-check-label" for="remember-my-id">
                            <?= __("Remember my ID") ?>
                        </label>
                    </div>

                    <div class="form-check text-start mb-1">
                        <input class="form-check-input" type="checkbox" value="1" id="quick-login-enable"
                            name="quick_login_enable" data-login-device-auth-target="quickEnable"
                            data-action="change->login-device-auth#syncQuickPreference">
                        <label class="form-check-label" for="quick-login-enable">
                            <?= __("Quick login on this device") ?>
                        </label>
                    </div>
                    <small class="text-muted d-block mb-2 text-start">
                        <?= __("After you sign in, you'll set your quick login PIN on this device.") ?>
                    </small>

                    <?= $this->Form->submit(__("Sign in"), [
                        "class" => "w-100 btn btn-lg btn-primary",
                    ]) ?>
                    <?= $this->Form->end() ?>
                </div>

                <?= $this->Html->link(
                    __("Forgot Password?"),
                    ["action" => "forgotPassword"],
                    ["class" => "btn btn-sm btn-link"],
                ) ?>
                <?php if ($allowRegistration == strtolower("yes")) : ?>
                    <?= $this->Html->link(
                        __("New User? Register Here"),
                        ["action" => "register"],
                        ["class" => "btn btn-sm btn-link"],
                    ) ?>
                <?php endif; ?>

                <a href="<?= $this->Url->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'SubmitRecommendation']) ?>"
                    class="mt-3 btn fs-6 bi bi-megaphone-fill mb-2 <?= $this->KMP->getAppSetting("Awards.RecButtonClass") ?>">
                    Submit Award Rec.</a>
            </div>
        </div>
    </div>
</div>
