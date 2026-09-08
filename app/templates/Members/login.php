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
<div data-controller="login-device-auth">
    <div class="card login-card form-signin">
        <?= $this->Html->image($this->KMP->assetUrl($headerImage), [
            'class' => 'card-img-top',
            'alt' => 'site logo',
        ]) ?>
        <div class="card-body">
            <h5 class="card-title">Log in</h5>
            <div class="card-text">
                <div data-controller="passkey" class="mb-3">
                    <button type="button" class="btn btn-primary w-100 mb-2" data-action="passkey#login">Sign in with a passkey</button>
                    <p role="status" aria-live="polite" data-passkey-target="status"></p>
                </div>
                <p>Or sign in with your email and password.</p>
                <div data-login-device-auth-target="passwordExperience">
                    <?= $this->Form->create($Member, [
                        'class' => 'mb-0',
                        'data-login-device-auth-target' => 'passwordForm',
                    ]) ?>
                    <?= $this->Form->hidden('login_method', ['value' => 'password']) ?>
                    <?= $this->Form->control('email_address', [
                        'type' => 'email',
                        'label' => ['floating' => true],
                        'autofocus',
                        'autocomplete' => 'email',
                        'inputmode' => 'email',
                        'data-login-device-auth-target' => 'email',
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

                <a href="/offline" class="btn btn-outline-primary w-100 my-3">Open saved offline cards and RSVPs</a>
                <p class="small">Add a passkey from your account after signing in. Offline access must be saved on this browser before travelling.</p>
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
