<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\EmailTemplate $emailTemplate
 * @var array $preview
 */

$this->extend('/layout/TwitterBootstrap/view_record');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': ' . $emailTemplate->display_name;
$this->KMP->endBlock();

echo $this->KMP->startBlock('pageTitle') ?>
<?= h($emailTemplate->display_name) ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock('recordActions') ?>
<?= $this->Html->link(
    __('Edit'),
    ['action' => 'edit', $emailTemplate->id],
    ['class' => 'btn btn-primary btn-sm']
) ?>
<?= $this->Form->postLink(
    __('Delete'),
    ['action' => 'delete', $emailTemplate->id],
    [
        'confirm' => __('Are you sure you want to delete the template for {0}::{1}?', $emailTemplate->mailer_class, $emailTemplate->action_method),
        'class' => 'btn btn-danger btn-sm'
    ]
) ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('recordDetails') ?>
<tr scope="row">
    <th class="col"><?= __('Mailer Class') ?></th>
    <td class="col-10"><code><?= h($emailTemplate->mailer_class) ?></code></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Action Method') ?></th>
    <td class="col-10"><code><?= h($emailTemplate->action_method) ?></code></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Subject Template') ?></th>
    <td class="col-10"><code><?= h($emailTemplate->subject_template) ?></code></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Status') ?></th>
    <td class="col-10">
        <?php if ($emailTemplate->is_active): ?>
            <span class="badge bg-success">Active</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark">Inactive</span>
        <?php endif; ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Created') ?></th>
    <td class="col-10"><?= $emailTemplate->created ? $this->Timezone->format($emailTemplate->created, 'F j, Y g:i A', true) : '-' ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Modified') ?></th>
    <td class="col-10"><?= $emailTemplate->modified ? $this->Timezone->format($emailTemplate->modified, 'F j, Y g:i A', true) : '-' ?></td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php // Tab Buttons 
?>
<?php $this->KMP->startBlock('tabButtons') ?>
<?php if (!empty($emailTemplate->available_vars)): ?>
    <button class="nav-link active" id="nav-variables-tab" data-bs-toggle="tab" data-bs-target="#nav-variables"
        type="button" role="tab" aria-controls="nav-variables" aria-selected="true" data-detail-tabs-target='tabBtn'
        data-tab-order="10" style="order: 10;"><?= __("Available Variables") ?>
    </button>
<?php endif; ?>

<?php if ($emailTemplate->text_template): ?>
    <button class="nav-link <?= empty($emailTemplate->available_vars) ? 'active' : '' ?>" id="nav-text-template-tab"
        data-bs-toggle="tab" data-bs-target="#nav-text-template" type="button" role="tab" aria-controls="nav-text-template"
        aria-selected="<?= empty($emailTemplate->available_vars) ? 'true' : 'false' ?>" data-detail-tabs-target='tabBtn'
        data-tab-order="20" style="order: 20;"><?= __("Text Template") ?>
    </button>
<?php endif; ?>

<?php if ($emailTemplate->html_template): ?>
    <button class="nav-link <?= empty($emailTemplate->available_vars) && !$emailTemplate->text_template ? 'active' : '' ?>"
        id="nav-html-template-tab" data-bs-toggle="tab" data-bs-target="#nav-html-template" type="button" role="tab"
        aria-controls="nav-html-template"
        aria-selected="<?= empty($emailTemplate->available_vars) && !$emailTemplate->text_template ? 'true' : 'false' ?>"
        data-detail-tabs-target='tabBtn' data-tab-order="30" style="order: 30;"><?= __("HTML Template") ?> <small
            class="text-muted">(Markdown)</small>
    </button>
<?php endif; ?>

<button
    class="nav-link <?= empty($emailTemplate->available_vars) && !$emailTemplate->text_template && !$emailTemplate->html_template ? 'active' : '' ?>"
    id="nav-preview-tab" data-bs-toggle="tab" data-bs-target="#nav-preview" type="button" role="tab"
    aria-controls="nav-preview"
    aria-selected="<?= empty($emailTemplate->available_vars) && !$emailTemplate->text_template && !$emailTemplate->html_template ? 'true' : 'false' ?>"
    data-detail-tabs-target='tabBtn' data-tab-order="40" style="order: 40;"><?= __("Preview") ?>
</button>
<?php $this->KMP->endBlock() ?>

<?php // Tab Content 
?>
<?php $this->KMP->startBlock('tabContent') ?>
<?php if (!empty($emailTemplate->available_vars)): ?>
    <div class="related tab-pane fade show active m-3" id="nav-variables" role="tabpanel"
        aria-labelledby="nav-variables-tab" data-detail-tabs-target="tabContent" data-tab-order="10" style="order: 10;">
        <div class="row">
            <?php foreach ($emailTemplate->available_vars as $var): ?>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><code>{{<?= h($var['name']) ?>}}</code></h6>
                            <p class="card-text text-muted small"><?= h($var['description'] ?? 'No description') ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($emailTemplate->text_template): ?>
    <div class="related tab-pane fade <?= empty($emailTemplate->available_vars) ? 'show active' : '' ?> m-3"
        id="nav-text-template" role="tabpanel" aria-labelledby="nav-text-template-tab" data-detail-tabs-target="tabContent"
        data-tab-order="20" style="order: 20;">
        <pre class="border p-3 bg-light"><code><?= h($emailTemplate->text_template) ?></code></pre>
    </div>
<?php endif; ?>

<?php if ($emailTemplate->html_template): ?>
    <div class="related tab-pane fade <?= empty($emailTemplate->available_vars) && !$emailTemplate->text_template ? 'show active' : '' ?> m-3"
        id="nav-html-template" role="tabpanel" aria-labelledby="nav-html-template-tab" data-detail-tabs-target="tabContent"
        data-tab-order="30" style="order: 30;">
        <pre class="border p-3 bg-light"><code><?= h($emailTemplate->html_template) ?></code></pre>
    </div>
<?php endif; ?>

<div class="related tab-pane fade <?= empty($emailTemplate->available_vars) && !$emailTemplate->text_template && !$emailTemplate->html_template ? 'show active' : '' ?> m-3"
    id="nav-preview" role="tabpanel" aria-labelledby="nav-preview-tab" data-detail-tabs-target="tabContent"
    data-tab-order="40" style="order: 40;">
    <p class="text-muted mb-3"><em>Preview with placeholder values</em></p>

    <div class="mb-4">
        <h5>Subject:</h5>
        <div class="border p-2 bg-light"><?= h($preview['subject']) ?></div>
    </div>

    <?php if ($preview['text']): ?>
        <div class="mb-4">
            <h5>Plain Text Version:</h5>
            <pre class="border p-3 bg-light"><?= h($preview['text']) ?></pre>
        </div>
    <?php endif; ?>

    <?php if ($preview['html']): ?>
        <div class="mb-4">
            <h5>HTML Version:</h5>
            <iframe srcdoc="<?= h($preview['html']) ?>" class="border w-100" style="min-height: 400px; background: white;"
                sandbox="allow-same-origin">
            </iframe>
        </div>
    <?php endif; ?>
</div>
<?php $this->KMP->endBlock() ?>