<?php $this->extend("/layout/TwitterBootstrap/signin"); ?>
<h1>Fogot Password</h1>
<?= $this->Form->create($passwordReset) ?>
<?= $this->Form->control("new_password", ["type" => "password"]) ?>
<?= $this->Form->control("confirm_password", ["type" => "password"]) ?>
<?= $this->Form->button("Reset Password") ?>
<?= $this->Form->end() ?>