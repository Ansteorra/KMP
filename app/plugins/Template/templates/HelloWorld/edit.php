<?php

/**
 * @var \App\View\AppView $this
 * @var array $item
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>
<div class="helloWorld form content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= __('Edit Hello World Item') ?></h1>
        <div>
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
        <div class="card-body">
            <?= $this->Form->create(null, ['class' => 'needs-validation', 'novalidate' => true]) ?>
            <fieldset>
                <legend><?= __('Edit Item #') . h($item['id']) ?></legend>

                <div class="mb-3">
                    <?= $this->Form->control('title', [
                        'label' => __('Title'),
                        'class' => 'form-control',
                        'required' => true,
                        'placeholder' => __('Enter a title'),
                        'value' => $item['title']
                    ]) ?>
                    <div class="form-text">
                        <?= __('A descriptive title for the hello world item.') ?>
                    </div>
                </div>

                <div class="mb-3">
                    <?= $this->Form->control('description', [
                        'label' => __('Description'),
                        'type' => 'textarea',
                        'class' => 'form-control',
                        'rows' => 4,
                        'required' => true,
                        'placeholder' => __('Enter a description'),
                        'value' => $item['description']
                    ]) ?>
                    <div class="form-text">
                        <?= __('A detailed description of the item.') ?>
                    </div>
                </div>

                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> This is a template example. In a real implementation,
                    this form would update data in the database.
                </div>
            </fieldset>

            <div class="mt-4">
                <?= $this->Form->button(
                    '<i class="bi bi-check-circle me-1"></i>' . __('Save Changes'),
                    ['type' => 'submit', 'class' => 'btn btn-primary', 'escape' => false]
                ) ?>
                <?= $this->Html->link(
                    __('Cancel'),
                    ['action' => 'index'],
                    ['class' => 'btn btn-secondary']
                ) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>