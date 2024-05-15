<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Participant $participant
 */
$participant = [];
?>
<?php $this->extend('/layout/TwitterBootstrap/signin'); ?>

<?= $this->Form->create($participant, ['class' => 'form-signin']) ?>
<?= $this->Html->image('BootstrapUI.baked-with-cakephp.svg', ['class' => 'mb-4', 'width' => '250']) ?>
<h1 class="h3 mb-3 font-weight-normal"><?= __('Please sign in') ?></h1>
<?= $this->Flash->render(); ?>
<?= $this->Form->control('email_address', ['label' => ['floating' => true], 'autofocus']) ?>
<?= $this->Form->control('password', ['type' => 'password', 'label' => ['floating' => true]]) ?>
<?= $this->Form->submit(__('Sign in'), ['class' => 'w-100 btn btn-lg btn-primary']) ?>
<p class="mt-5 mb-3 text-muted">© <?= date('Y') ?></p>
<?= $this->Form->end() ?>
