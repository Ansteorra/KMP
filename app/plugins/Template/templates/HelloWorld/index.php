<?php

/**
 * @var \App\View\AppView $this
 * @var array $items
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>
<div class="helloWorld index content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= __('Hello World') ?></h1>
        <div>
            <?= $this->Html->link(
                __(' Add New'),
                ['action' => 'add'],
                ['class' => 'btn btn-primary  bi bi-plus-circle', 'escape' => false]
            ) ?>
        </div>
    </div>

    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Template Plugin</strong> - This is a demonstration of the KMP plugin system.
        Use this template as a starting point for your own plugins!
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Hello World Items</h5>
        </div>
        <div class="card-body">
            <?php if (empty($items)): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No items found.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?= __('ID') ?></th>
                                <th><?= __('Title') ?></th>
                                <th><?= __('Description') ?></th>
                                <th><?= __('Created') ?></th>
                                <th class="actions"><?= __('Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= h($item['id']) ?></td>
                                    <td><?= h($item['title']) ?></td>
                                    <td><?= h($item['description']) ?></td>
                                    <td><?= isset($item['created']) ? h($item['created']->format('Y-m-d H:i')) : 'N/A' ?></td>
                                    <td class="actions">
                                        <?= $this->Html->link(
                                            '<i class="bi bi-eye"></i>',
                                            ['action' => 'view', $item['id']],
                                            ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false, 'title' => __('View')]
                                        ) ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-pencil"></i>',
                                            ['action' => 'edit', $item['id']],
                                            ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => __('Edit')]
                                        ) ?>
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-trash"></i>',
                                            ['action' => 'delete', $item['id']],
                                            [
                                                'confirm' => __('Are you sure you want to delete # {0}?', $item['id']),
                                                'class' => 'btn btn-sm btn-outline-danger',
                                                'escape' => false,
                                                'title' => __('Delete')
                                            ]
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-4">
        <h3>About This Template</h3>
        <p>This Template plugin demonstrates:</p>
        <ul>
            <li><strong>Controller Pattern</strong>: Standard CRUD operations</li>
            <li><strong>Authorization</strong>: Policy-based access control</li>
            <li><strong>Navigation</strong>: Menu integration with the main application</li>
            <li><strong>Templates</strong>: Bootstrap-styled views</li>
            <li><strong>Best Practices</strong>: Following KMP conventions</li>
        </ul>
        <p class="text-muted">
            To create your own plugin, copy this template and customize it for your needs.
            See the <code>README.md</code> file for detailed instructions.
        </p>
    </div>
</div>