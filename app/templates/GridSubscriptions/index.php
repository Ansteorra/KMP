<?php
/** @var \App\View\AppView $this */
$this->extend('/layout/TwitterBootstrap/dashboard');
$this->assign('title', __('Email subscriptions'));
?>
<h1><?= __('Email subscriptions') ?></h1>
<p><?= __('Summaries use your current access. Empty results are skipped.') ?>
    <?= __('Subscriptions stop if your account or view is no longer available.') ?></p>
<?= $this->Html->link(
    __('Back to my profile'),
    ['controller' => 'Members', 'action' => 'view', $user->public_id],
    ['class' => 'btn btn-outline-secondary mb-3'],
) ?>
<?php if ($subscriptions->isEmpty()) : ?>
    <p><?= __('You have no email subscriptions. Use “Email this view” in a supported grid to add one.') ?></p>
<?php else : ?>
<div class="table-responsive">
<table class="table">
    <thead><tr>
        <th scope="col"><?= __('View') ?></th><th scope="col"><?= __('Frequency') ?></th>
        <th scope="col"><?= __('Status') ?></th>
        <th scope="col"><?= __('Next check') ?></th><th scope="col"><?= __('Actions') ?></th>
    </tr></thead>
    <tbody><?php foreach ($subscriptions as $subscription) : ?>
    <tr>
        <th scope="row"><?= h($subscription->name) ?></th>
        <td><?= h([1 => __('Daily'), 3 => __('Every 3 days'), 7 => __('Weekly')][$subscription->interval_days]) ?></td>
        <td><?= $subscription->status === 'active' ? __('Active') : __('Stopped') ?>
            <?php if ($subscription->stop_reason) :
                ?><p class="small mb-0"><?= h($subscription->stop_reason) ?></p><?php
            endif; ?></td>
        <td><?= $subscription->status === 'active'
            ? $this->Timezone->format($subscription->next_run_at, $user, 'M d, Y g:i A') : '—' ?></td>
        <td><?= $this->Form->postLink(__('Cancel'), ['action' => 'delete', $subscription->id], [
            'class' => 'btn btn-outline-danger btn-sm', 'confirm' => __('Cancel {0}?', $subscription->name),
            'aria-label' => __('Cancel subscription {0}', $subscription->name),
            ]) ?></td>
    </tr>
           <?php endforeach; ?></tbody>
</table>
</div>
<?php endif; ?>
