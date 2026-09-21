<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
?>
<turbo-frame id="email-subscriptions">
<div tabindex="-1" data-subscription-feedback role="status">
    <?= $this->Flash->render() ?>
</div>
<p><?= __('Summaries use your current access. Empty results are skipped.') ?>
    <?= __('Subscriptions stop if your account or view is no longer available.') ?></p>
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
        <td>
            <?= $this->Form->create(null, [
                'url' => ['action' => 'delete', $subscription->id],
                'data-turbo' => 'true',
            ]) ?>
            <?= $this->Form->button(__('Cancel'), [
                'class' => 'btn btn-outline-danger btn-sm',
                'aria-label' => __('Cancel subscription {0}', $subscription->name),
                'type' => 'button',
                'data-action' => 'grid-subscriptions-dialog#requestCancellation',
                'data-cancel-subscription' => true,
            ]) ?>
            <div hidden data-cancel-confirmation>
                <p class="small mb-2"><?= h(__('Cancel {0}?', $subscription->name)) ?></p>
                <button type="button" class="btn btn-secondary btn-sm" data-keep-subscription
                    data-action="grid-subscriptions-dialog#keepSubscription"><?= __('Keep subscription') ?></button>
                <?= $this->Form->button(__('Cancel subscription'), [
                    'class' => 'btn btn-outline-danger btn-sm',
                    'aria-label' => __('Confirm cancellation of {0}', $subscription->name),
                ]) ?>
            </div>
            <?= $this->Form->end() ?>
        </td>
    </tr>
           <?php endforeach; ?></tbody>
</table>
</div>
<?php endif; ?>

</turbo-frame>
