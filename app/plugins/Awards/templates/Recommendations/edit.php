<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $recommendation
 * @var \App\Model\Entity\Requester[]|\Cake\Collection\CollectionInterface $requesters
 * @var \App\Model\Entity\Member[]|\Cake\Collection\CollectionInterface $members
 * @var \App\Model\Entity\Branch[]|\Cake\Collection\CollectionInterface $branches
 * @var \App\Model\Entity\Award[]|\Cake\Collection\CollectionInterface $awards
 * @var \App\Model\Entity\Event[]|\Cake\Collection\CollectionInterface $events
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $recommendation->id], ['confirm' => __('Are you sure you want to delete # {0}?', $recommendation->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Recommendations'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Requesters'), ['controller' => 'Members', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Requester'), ['controller' => 'Members', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Branches'), ['controller' => 'Branches', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Branch'), ['controller' => 'Branches', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Awards'), ['controller' => 'Awards', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Award'), ['controller' => 'Awards', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Events'), ['controller' => 'Events', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Event'), ['controller' => 'Events', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="recommendations form content">
    <?= $this->Form->create($recommendation) ?>
    <fieldset>
        <legend><?= __('Edit Recommendation') ?></legend>
        <?php
            echo $this->Form->control('requester_id', ['options' => $requesters]);
            echo $this->Form->control('member_id', ['options' => $members, 'empty' => true]);
            echo $this->Form->control('branch_id', ['options' => $branches, 'empty' => true]);
            echo $this->Form->control('award_id', ['options' => $awards]);
            echo $this->Form->control('requester_sca_name');
            echo $this->Form->control('member_sca_name');
            echo $this->Form->control('contact_number');
            echo $this->Form->control('reason');
            echo $this->Form->control('created_by');
            echo $this->Form->control('modified_by');
            echo $this->Form->control('deleted', ['empty' => true]);
            echo $this->Form->control('events._ids', ['options' => $events]);
                ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
