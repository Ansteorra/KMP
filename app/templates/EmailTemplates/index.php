<?php

/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\EmailTemplate> $emailTemplates
 * @var array $mailerClasses
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Email Templates';
$this->KMP->endBlock(); ?>

<div class="row align-items-start">
    <div class="col">
        <h3>Email Templates</h3>
    </div>
    <div class="col text-end">
        <?php
        if ($user->checkCan("add", "EmailTemplates")) :
        ?>
            <?= $this->Html->link(
                ' Add Email Template',
                ['action' => 'add'],
                ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>

<?php // Filters 
?>
<div class="card mb-3">
    <div class="card-body">
        <?= $this->Form->create(null, ['type' => 'get']) ?>
        <div class="row">
            <div class="col-md-5">
                <?= $this->Form->control('mailer_class', [
                    'options' => ['' => '-- All Mailers --'] + $mailerClasses,
                    'label' => 'Mailer Class',
                ]) ?>
            </div>
            <div class="col-md-4">
                <?= $this->Form->control('is_active', [
                    'options' => ['' => '-- All Status --', '1' => 'Active', '0' => 'Inactive'],
                    'label' => 'Status',
                ]) ?>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <?= $this->Form->button(__('Filter'), ['class' => 'btn btn-primary me-2']) ?>
                <?= $this->Html->link(__('Clear'), ['action' => 'index'], ['class' => 'btn btn-secondary']) ?>
            </div>
        </div>
        <?= $this->Form->end() ?>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('mailer_class', 'Mailer') ?></th>
                <th scope="col"><?= $this->Paginator->sort('action_method', 'Action') ?></th>
                <th scope="col"><?= $this->Paginator->sort('subject_template', 'Subject') ?></th>
                <th scope="col">Templates</th>
                <th scope="col"><?= $this->Paginator->sort('is_active', 'Status') ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified') ?></th>
                <th scope="col" class="actions"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($emailTemplates as $emailTemplate): ?>
                <tr>
                    <td><code><?= h($emailTemplate->mailer_class) ?></code></td>
                    <td><code><?= h($emailTemplate->action_method) ?></code></td>
                    <td class="text-truncate" style="max-width: 250px;" title="<?= h($emailTemplate->subject_template) ?>">
                        <?= h($emailTemplate->subject_template) ?>
                    </td>
                    <td>
                        <?php if ($emailTemplate->html_template): ?>
                            <span class="badge bg-info">HTML</span>
                        <?php endif; ?>
                        <?php if ($emailTemplate->text_template): ?>
                            <span class="badge bg-secondary">Text</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($emailTemplate->is_active): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($emailTemplate->modified->format('Y-m-d H:i')) ?></td>
                    <td class="actions text-end text-nowrap">
                        <?= $this->Html->link(
                            '',
                            ['action' => 'view', $emailTemplate->id],
                            ['title' => __('View'), 'class' => 'btn-sm btn btn-secondary bi bi-binoculars-fill']
                        ) ?>
                        <?= $this->Html->link(
                            '',
                            ['action' => 'edit', $emailTemplate->id],
                            ['title' => __('Edit'), 'class' => 'btn-sm btn btn-primary bi bi-pencil-fill']
                        ) ?>
                        <?= $this->Form->postLink(
                            '',
                            ['action' => 'delete', $emailTemplate->id],
                            [
                                'confirm' => __('Are you sure you want to delete the template for {0}::{1}?', $emailTemplate->mailer_class, $emailTemplate->action_method),
                                'title' => __('Delete'),
                                'class' => 'btn-sm btn btn-danger bi bi-trash-fill'
                            ]
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first('«') ?>
        <?= $this->Paginator->prev('‹') ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next('›') ?>
        <?= $this->Paginator->last('»') ?>
    </ul>
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?>
    </p>
</div>