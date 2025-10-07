<?php

/**
 * @var \App\View\AppView $this
 * @var array $item
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/view_record"); ?>
<div class="helloWorld view content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= __('Hello World Item') ?></h1>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i>' . __('Edit'),
                ['action' => 'edit', $item['id']],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i>' . __('Delete'),
                ['action' => 'delete', $item['id']],
                [
                    'confirm' => __('Are you sure you want to delete this item?'),
                    'class' => 'btn btn-danger',
                    'escape' => false
                ]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left me-1"></i>' . __('Back to List'),
                ['action' => 'index'],
                ['class' => 'btn btn-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><?= h($item['title']) ?></h5>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3"><?= __('ID') ?></dt>
                <dd class="col-sm-9"><?= h($item['id']) ?></dd>

                <dt class="col-sm-3"><?= __('Title') ?></dt>
                <dd class="col-sm-9"><?= h($item['title']) ?></dd>

                <dt class="col-sm-3"><?= __('Description') ?></dt>
                <dd class="col-sm-9"><?= h($item['description']) ?></dd>

                <dt class="col-sm-3"><?= __('Created') ?></dt>
                <dd class="col-sm-9"><?= isset($item['created']) ? h($item['created']->format('Y-m-d H:i:s')) : 'N/A' ?>
                </dd>

                <dt class="col-sm-3"><?= __('Modified') ?></dt>
                <dd class="col-sm-9">
                    <?= isset($item['modified']) ? h($item['modified']->format('Y-m-d H:i:s')) : 'N/A' ?></dd>
            </dl>

            <?php if (isset($item['content'])): ?>
                <div class="mt-4">
                    <h6><?= __('Content') ?></h6>
                    <div class="border rounded p-3 bg-light">
                        <?= h($item['content']) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-info mt-4" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        This is a view Events example. In a real plugin, this would display actual data from the database.
    </div>
</div>