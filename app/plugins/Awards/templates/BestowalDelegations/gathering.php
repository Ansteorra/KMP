<?php
declare(strict_types=1);
/** @var \App\View\AppView $this */
$user = $this->getRequest()->getAttribute('identity');
?>
<turbo-frame id="eventAwardDelegationFrame" data-turbo="true">
    <?= $this->Flash->render() ?>
    <h3 class="h5"><?= h($gathering->name) ?></h3>
    <p><?= __('Let another person act with your award permissions for this event and awarding group. '
        . 'This includes bestowals, court agenda work, eligible court activities and scheduling, '
        . 'and To-Dos you can perform. '
        . 'Access remains active until revoked and always depends on your current permissions. '
        . 'The recipient cannot pass your delegated access to someone else.') ?></p>
    <?php if ($branchOptions !== []) : ?>
        <section aria-labelledby="grant-delegation-title" class="card mb-4">
            <div class="card-body">
                <h3 id="grant-delegation-title" class="h5"><?= __('Delegate your event award permissions') ?></h3>
                <?= $this->Form->create(null, [
                    'url' => ['action' => 'grant', $gathering->id], 'data-turbo' => true,
                ]) ?>
                <?= $this->Form->control('branch_id', [
                    'label' => __('Awarding group'), 'options' => $branchOptions, 'required' => true,
                ]) ?>
                <?= $this->KMP->autoCompleteControl(
                    $this->Form,
                    'delegate_name',
                    'delegate_public_id',
                    $this->Url->build(['plugin' => null, 'controller' => 'Members', 'action' => 'autoComplete']),
                    __('Person receiving access'),
                    true,
                    true,
                    3,
                    [],
                ) ?>
                <?= $this->Form->button(__('Grant event access'), ['class' => 'btn btn-primary mt-3']) ?>
                <?= $this->Form->end() ?>
            </div>
        </section>
    <?php endif; ?>

    <h3 class="h5"><?= __('Delegation history') ?></h3>
    <?php if ($delegations->isEmpty()) : ?>
        <p><?= __('No event award delegations to show.') ?></p>
    <?php endif; ?>
    <?php foreach ($delegations as $grant) : ?>
        <article class="card mb-3">
            <div class="card-body">
                <h4 class="h6">
                    <?= h($grant->grantor?->sca_name ?? __('Unavailable member')) ?> →
                    <?= h($grant->delegate?->sca_name ?? __('Unavailable member')) ?>
                </h4>
                <p class="mb-1"><?= __('Awarding group: {0}', h($grant->branch?->name ?? __('Unavailable group'))) ?></p>
                <p class="mb-2"><?= __('Granted: {0}', h($this->Timezone->format($grant->created))) ?></p>
                <?php if ($grant->revoked_at !== null) : ?>
                    <p class="mb-0">
                        <?= __(
                            'Revoked by {0}: {1}',
                            h($grant->revoker?->sca_name ?? __('Unavailable member')),
                            h($this->Timezone->format($grant->revoked_at)),
                        ) ?>
                    </p>
                <?php else : ?>
                    <p><?= __('Not revoked. Effective access follows the grantor’s current permissions.') ?></p>
                    <?php if ($user->checkCan('revoke', $grant)) : ?>
                        <?= $this->Form->postButton(
                            __('Revoke access for {0}', $grant->delegate?->sca_name ?? __('Unavailable member')),
                            ['action' => 'revoke', $grant->id],
                            ['class' => 'btn btn-outline-danger', 'form' => ['data-turbo' => true]],
                        ) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</turbo-frame>
