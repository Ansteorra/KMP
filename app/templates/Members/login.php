<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $Member
 * @var bool $quickLoginDisabled
 * @var string $quickLoginDisabledEmail
 */
$Member = [];
$shortSiteTitle = $this->KMP->getAppSetting('KMP.ShortSiteTitle');
?>
<?php $this->extend('/layout/TwitterBootstrap/signin');

echo $this->KMP->startBlock('title');
echo $shortSiteTitle . ': Login';
$this->KMP->endBlock(); ?>
<div data-controller="login-device-auth"
    data-action="kmp:device-login-availability->login-device-auth#deviceAvailability
        kmp:password-login->login-device-auth#showPasswordLogin">
    <div class="card login-card form-signin">
        <?= $this->Html->image($this->KMP->assetUrl($headerImage), [
            'class' => 'card-img-top',
            'alt' => 'site logo',
        ]) ?>
        <div class="card-body">
            <h1 class="card-title h5">Log in</h1>
            <div class="alert alert-info text-start" role="status"
                data-login-device-auth-target="migrationNotice" hidden>
                <h2 class="h6">A fresh start for PIN login</h2>
                <p class="mb-0"><?= h($shortSiteTitle) ?> has updated device unlock. Your old PIN no longer works.
                    Connect to the internet and sign in with your email and password once.
                    Then we’ll help you set up a new PIN or passkey for online and offline access.</p>
            </div>
            <p class="small" role="status" tabindex="-1" data-login-device-auth-target="offlineNotice" hidden></p>
            <div id="device-login" data-login-device-auth-target="deviceExperience">
                <?= $this->element('offline_access') ?>
            </div>
            <div class="card-text" id="password-login" data-trusted-password-login
                data-login-device-auth-target="passwordLogin">
                <button type="button" class="btn btn-outline-secondary w-100 py-2 mb-3"
                    aria-controls="device-login" data-login-device-auth-target="deviceSwitch"
                    data-action="login-device-auth#switchToDevice" hidden>Use device unlock</button>
                <?= $this->Form->hidden('quick_login_disabled', [
                    'value' => !empty($quickLoginDisabled) ? '1' : '0',
                    'data-login-device-auth-target' => 'quickDisabled',
                ]) ?>
                <?= $this->Form->hidden('quick_login_disabled_email', [
                    'value' => (string)($quickLoginDisabledEmail ?? ''),
                    'data-login-device-auth-target' => 'quickDisabledEmail',
                ]) ?>
                <ul class="nav nav-tabs nav-fill login-mode-tabs mb-3 d-none" role="tablist"
                    data-login-device-auth-target="modeTabs">
                    <li class="nav-item" role="presentation">
                        <button type="button"
                            class="nav-link"
                            role="tab"
                            data-login-device-auth-target="quickTabButton"
                            data-action="click->login-device-auth#switchToQuick">
                            <?= __('Quick login') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button"
                            class="nav-link"
                            role="tab"
                            data-login-device-auth-target="passwordTabButton"
                            data-action="click->login-device-auth#switchToPassword">
                            <?= __('Email + Password') ?>
                        </button>
                    </li>
                </ul>

                <div class="border rounded p-3 mb-3 d-none text-start" data-login-device-auth-target="quickExperience">
                    <h6 class="mb-1"><?= __('Quick login') ?></h6>
                    <p class="text-muted small mb-2" data-login-device-auth-target="quickLoginLabel">
                        <?= __('Enter your PIN to use quick login on this device.') ?>
                    </p>
                    <?php
                    $quickLoginUrl = ['action' => 'login'];
                    $redirectTarget = trim((string)$this->request->getQuery('redirect', ''));
                    if ($redirectTarget !== '') {
                        $quickLoginUrl['?'] = ['redirect' => $redirectTarget];
                    }
                    ?>
                    <?= $this->Form->create(null, [
                        'url' => $quickLoginUrl,
                        'class' => 'mb-2',
                        'data-login-device-auth-target' => 'quickForm',
                    ]) ?>
                    <?= $this->Form->hidden('login_method', ['value' => 'quick_pin']) ?>
                    <?= $this->Form->hidden('email_address', [
                        'data-login-device-auth-target' => 'quickEmail',
                    ]) ?>
                    <?= $this->Form->hidden('quick_login_device_id', [
                        'data-login-device-auth-target' => 'quickDeviceId',
                    ]) ?>
                    <?= $this->Form->control('quick_login_pin', [
                        'type' => 'password',
                        'label' => __('PIN'),
                        'autocomplete' => 'current-password',
                        'inputmode' => 'numeric',
                        'pattern' => '[0-9]*',
                        'required' => false,
                        'disabled' => true,
                        'data-login-device-auth-target' => 'quickPin',
                        'maxlength' => 10,
                        'minlength' => 4,
                        'container' => ['class' => 'form-group mb-2'],
                    ]) ?>
                    <?= $this->Form->button(__('Quick login'), [
                        'class' => 'w-100 btn btn-outline-primary',
                    ]) ?>
                    <?= $this->Form->end() ?>
                </div>

                <div data-login-device-auth-target="passwordExperience">
                    <?= $this->Form->create($Member, [
                        'class' => 'mb-0',
                        'data-login-device-auth-target' => 'passwordForm',
                    ]) ?>
                    <?= $this->Form->hidden('login_method', ['value' => 'password']) ?>
                    <?= $this->Form->hidden('quick_login_device_id', [
                        'data-login-device-auth-target' => 'passwordDeviceId',
                    ]) ?>
                    <?= $this->Form->control('email_address', [
                        'type' => 'email',
                        'label' => ['floating' => true],
                        'autofocus',
                        'autocomplete' => 'email',
                        'inputmode' => 'email',
                        'data-login-device-auth-target' => 'email',
                        'data-action' => 'input->login-device-auth#syncEmail',
                        'container' => ['class' => 'form-group'],
                    ]) ?>
                    <?= $this->Form->control('password', [
                        'type' => 'password',
                        'autocomplete' => 'current-password',
                        'label' => ['floating' => true],
                        'container' => ['class' => 'form-group'],
                    ]) ?>

                    <div class="form-check text-start mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="remember-my-id"
                            name="remember_my_id" data-login-device-auth-target="rememberId">
                        <label class="form-check-label" for="remember-my-id">
                            <?= __('Remember my ID') ?>
                        </label>
                    </div>

                    <?= $this->Form->submit(__('Sign in'), [
                        'class' => 'w-100 btn btn-lg btn-primary',
                    ]) ?>
                    <?= $this->Form->end() ?>
                </div>

                <?= $this->Html->link(
                    __('Forgot Password?'),
                    ['action' => 'forgotPassword'],
                    ['class' => 'btn btn-sm btn-link'],
                ) ?>
                <?php if (strtolower((string)$allowRegistration) === 'yes') : ?>
                    <?= $this->Html->link(
                        __('New User? Register Here'),
                        ['action' => 'register'],
                        ['class' => 'btn btn-sm btn-link'],
                    ) ?>
                <?php endif; ?>

                <a href="<?= $this->Url->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'SubmitRecommendation']) ?>"
                    class="mt-3 btn fs-6 bi bi-megaphone-fill mb-2 <?= $this->KMP->getAppSetting('Awards.RecButtonClass') ?>">
                    Submit Award Rec.</a>
            </div>
        </div>
    </div>
</div>
