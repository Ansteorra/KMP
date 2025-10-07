<?php

/**
 * @var \App\View\AppView $this
 * @var array $item
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>
<div class="helloWorld form content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= __('Add Hello World Item') ?></h1>
        <div>
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
                <legend><?= __('Enter Item Details') ?></legend>

                <div class="mb-3">
                    <?= $this->Form->control('title', [
                        'label' => __('Title'),
                        'class' => 'form-control',
                        'required' => true,
                        'placeholder' => __('Enter a title'),
                        'value' => $item['title'] ?? ''
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
                        'value' => $item['description'] ?? ''
                    ]) ?>
                    <div class="form-text">
                        <?= __('A detailed description of the item.') ?>
                    </div>
                </div>

                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> This is a Events example. In a real implementation,
                    this form would save data to the database.
                </div>
            </fieldset>

            <div class="mt-4">
                <?= $this->Form->button(
                    '<i class="bi bi-check-circle me-1"></i>' . __('Save'),
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

    <div class="mt-4">
        <h5>Form Validation</h5>
        <p>This form demonstrates:</p>
        <ul>
            <li>Bootstrap form styling</li>
            <li>Client-side validation</li>
            <li>Form helper integration</li>
            <li>Proper error handling</li>
        </ul>
    </div>
</div>