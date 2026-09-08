<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $Member
 */
$Member = []; ?>
<?php $this->extend('/layout/TwitterBootstrap/signin');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Login';
$this->KMP->endBlock(); ?>
<div data-controller="login-device-auth passkey" data-passkey-autofill-value="true"
    data-login-device-auth-retry-value="<?= $this->getRequest()->is('post') ? 'true' : 'false' ?>"
    data-action="login-device-auth:submit->passkey#cancelLogin">
    <div class="card login-card form-signin">
        <?= $this->Html->image($this->KMP->assetUrl($headerImage), [
            'class' => 'card-img-top',
            'alt' => 'site logo',
        ]) ?>
        <div class="card-body">
            <h5 class="card-title">Log in</h5>
            <div class="card-text">
                <div data-login-device-auth-target="passwordExperience">
                    <?= $this->Form->create($Member, [
                        'class' => 'mb-0',
                        'data-login-device-auth-target' => 'passwordForm',
                        'data-action' => 'submit->login-device-auth#submit',
                    ]) ?>
                    <?= $this->Form->hidden('login_method', ['value' => 'password']) ?>
                    <?= $this->Form->control('email_address', [
                        'type' => 'email',
                        'label' => ['floating' => true],
                        'autofocus',
                        'autocomplete' => 'username webauthn',
                        'required' => true,
                        'data-action' => 'focus->passkey#autofill',
                        'inputmode' => 'email',
                        'data-login-device-auth-target' => 'email',
                        'container' => ['class' => 'form-group'],
                    ]) ?>
                    <button type="submit" class="w-100 btn btn-lg btn-primary mb-3" hidden
                        data-login-device-auth-target="continue">Continue</button>
                    <div data-login-device-auth-target="passwordStep">
                        <button type="button" class="btn btn-link mb-2" hidden
                            data-login-device-auth-target="changeEmail" data-action="login-device-auth#back">
                            Change email address
                        </button>
                        <?= $this->Form->control('password', [
                            'type' => 'password',
                            'autocomplete' => 'current-password',
                            'required' => true,
                            'data-login-device-auth-target' => 'password',
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
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-secondary w-100" data-passkey-target="work"
                                data-action="passkey#login">Use a passkey</button>
                            <p class="small mt-2">If you have saved a KMP passkey, use your device to sign in.
                            You can also choose a passkey from another device.</p>
                        </div>
                    </div>
                    <p role="status" aria-live="polite" data-passkey-target="status"></p>
                    <?= $this->Form->end() ?>
                </div>

                <a href="/offline" class="btn btn-outline-primary w-100 my-3">Open saved offline cards and RSVPs</a>
                <p class="small">Offline access must be saved on this browser before travelling.</p>
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
