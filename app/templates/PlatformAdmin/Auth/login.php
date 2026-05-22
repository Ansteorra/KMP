<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var string $redirect
 */

$this->assign('title', __('Platform Admin Login'));
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5 col-xl-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-3 text-center"><?= __('Platform Admin') ?></h1>
                <p class="text-muted text-center">
                    <?= __('Sign in with your platform administrator password and MFA code.') ?>
                </p>
                <?= $this->Form->create(null, [
                    'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Auth', 'action' => 'login'],
                ]) ?>
                <?= $this->Form->hidden('redirect', ['value' => $redirect]) ?>
                <?= $this->Form->control('email', [
                    'type' => 'email',
                    'label' => __('Email'),
                    'autocomplete' => 'username',
                    'required' => true,
                ]) ?>
                <?= $this->Form->control('password', [
                    'type' => 'password',
                    'label' => __('Password'),
                    'autocomplete' => 'current-password',
                    'required' => true,
                ]) ?>
                <?= $this->Form->control('totp', [
                    'type' => 'text',
                    'label' => __('MFA code'),
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'maxlength' => 8,
                    'required' => true,
                ]) ?>
                <?= $this->Form->button(__('Sign in'), ['class' => 'btn btn-primary w-100 mt-2']) ?>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
</div>
