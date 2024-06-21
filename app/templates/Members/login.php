<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $Member
 */
$Member = []; ?>
<?php $this->extend("/layout/TwitterBootstrap/signin");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Login';
$this->KMP->endBlock(); ?>

<?= $this->Flash->render() ?>
<?= $this->Form->create($Member, ["class" => "form-signin"]) ?>

<div class="card" style="width: 15rem;">
    <?= $this->Html->image($headerImage, [
        "class" => "card-img-top",
        "alt" => "site logo",
    ]) ?>
    <div class="card-body">
        <h5 class="card-title">Log In</h5>
        <div class="card-text">
            <?= $this->Form->control("email_address", [
                "label" => ["floating" => true],
                "autofocus",
            ]) ?>
            <?= $this->Form->control("password", [
                "type" => "password",
                "label" => ["floating" => true],
            ]) ?>
            <?= $this->Form->submit(__("Sign in"), [
                "class" => "w-100 btn btn-lg btn-primary",
            ]) ?>
            <?= $this->html->link(
                __("Forgot Password?"),
                ["action" => "forgotPassword"],
                ["class" => "btn btn-sm btn-link"],
            ) ?>
            <? if ($allowRegistration == strtolower("yes")) : ?>
            <?= $this->html->link(
                    __("New User? Register Here"),
                    ["action" => "register"],
                    ["class" => "btn btn-sm btn-link"],
                ) ?>
            <? endif; ?>
        </div>
    </div>
</div>
<?= $this->Form->end() ?>