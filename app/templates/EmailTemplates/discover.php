<?php

/**
 * @var \App\View\AppView $this
 * @var array $allMailers
 * @var array $existingTemplates
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Discover Email Templates';
$this->KMP->endBlock(); ?>

<div class="row align-items-start">
    <div class="col">
        <h3>Discover Mailer Methods</h3>
    </div>
    <div class="col text-end">
        <?= $this->Form->postLink(
            ' Sync All',
            ['action' => 'sync'],
            [
                'confirm' => __('This will create active database templates for all methods that don\'t have templates yet. Continue?'),
                'class' => 'btn btn-warning btn-sm bi bi-arrow-repeat',
                'data-turbo-frame' => '_top'
            ]
        ) ?>
        <?= $this->Html->link(
            ' Back to Templates',
            ['action' => 'index'],
            ['class' => 'btn btn-secondary btn-sm bi bi-arrow-left', 'data-turbo-frame' => '_top']
        ) ?>
    </div>
</div>

<p class="text-muted mb-4">
    This page shows all discovered mailer classes and their methods.
    Green badges indicate templates exist in the database.
</p>

<?php if (empty($allMailers)): ?>
    <div class="alert alert-warning">
        <strong>No mailers discovered!</strong>
        <p class="mb-0">Make sure you have Mailer classes in your application or plugins.</p>
    </div>
<?php else: ?>

    <?php foreach ($allMailers as $mailer): ?>
        <div class="card mb-3">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">
                            <code><?= h($mailer['class']) ?></code>
                        </h5>
                        <small class="text-muted"><?= h($mailer['filePath']) ?></small>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-info"><?= count($mailer['methods']) ?> method<?= count($mailer['methods']) !== 1 ? 's' : '' ?></span>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($mailer['methods'])): ?>
                    <p class="text-muted p-3 mb-0">No public methods found</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Method</th>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Variables</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mailer['methods'] as $method): ?>
                                    <?php
                                    // Check if template exists using composite key
                                    $templateKey = $mailer['class'] . '::' . $method['name'];
                                    $hasTemplate = isset($existingTemplates[$templateKey]);
                                    $templateId = $hasTemplate ? $existingTemplates[$templateKey]->id : null;
                                    $isActive = $hasTemplate ? $existingTemplates[$templateKey]->is_active : false;
                                    ?>
                                    <tr>
                                        <td><code><?= h($method['name']) ?>()</code></td>
                                        <td>
                                            <?php if (!empty($method['defaultSubject'])): ?>
                                                <small><?= h($method['defaultSubject']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($method['availableVars'])): ?>
                                                <small>
                                                    <?php foreach (array_slice($method['availableVars'], 0, 3) as $var): ?>
                                                        <code class="small">{{<?= h($var['name']) ?>}}</code>
                                                    <?php endforeach; ?>
                                                    <?php if (count($method['availableVars']) > 3): ?>
                                                        <span class="text-muted">+<?= count($method['availableVars']) - 3 ?> more</span>
                                                    <?php endif; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted small">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($hasTemplate): ?>
                                                <?php if ($isActive): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Inactive</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Template</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <?php if ($hasTemplate): ?>
                                                <?= $this->Html->link(
                                                    '',
                                                    ['action' => 'view', $templateId],
                                                    ['title' => __('View'), 'class' => 'btn btn-sm btn-secondary bi bi-binoculars-fill']
                                                ) ?>
                                                <?= $this->Html->link(
                                                    '',
                                                    ['action' => 'edit', $templateId],
                                                    ['title' => __('Edit'), 'class' => 'btn btn-sm btn-primary bi bi-pencil-fill']
                                                ) ?>
                                            <?php else: ?>
                                                <?= $this->Html->link(
                                                    ' Create',
                                                    [
                                                        'action' => 'add',
                                                        '?' => [
                                                            'mailer_class' => $mailer['class'],
                                                            'action_method' => $method['name'],
                                                        ],
                                                    ],
                                                    ['class' => 'btn btn-sm btn-success bi bi-plus-circle']
                                                ) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>