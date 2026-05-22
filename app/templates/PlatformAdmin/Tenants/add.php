<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var array<string, string> $tenantForm
 * @var array<string, string> $formData
 * @var list<string> $errors
 */
$this->assign('title', __('Create Tenant'));
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1"><?= __('Create Tenant') ?></h1>
        <p class="text-muted mb-0"><?= __('Create the platform registry and safe tenant-level configuration.') ?></p>
    </div>
    <?= $this->Html->link(
        __('Back to tenants'),
        ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'index'],
        ['class' => 'btn btn-outline-secondary'],
    ) ?>
</div>

<?php if ($errors !== []) : ?>
    <div class="alert alert-danger" role="alert">
        <h2 class="h6"><?= __('Tenant was not saved') ?></h2>
        <ul class="mb-0">
            <?php foreach ($errors as $error) : ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?= $this->Form->create(null, [
    'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'add'],
]) ?>
<?= $this->element('PlatformAdmin/tenant_form', [
    'tenantForm' => $tenantForm,
    'formData' => $formData,
    'isEdit' => false,
]) ?>
<div class="d-flex gap-2">
    <?= $this->Form->button(__('Create tenant'), ['class' => 'btn btn-primary']) ?>
    <?= $this->Html->link(
        __('Cancel'),
        ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'index'],
        ['class' => 'btn btn-outline-secondary'],
    ) ?>
</div>
<?= $this->Form->end() ?>
