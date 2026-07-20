<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var array<string, mixed> $tenant
 * @var array<string, string> $tenantForm
 * @var array<string, string> $formData
 * @var list<string> $errors
 */
$this->assign('title', __('Edit Tenant: {0}', $tenant['slug']));
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1"><?= __('Edit Tenant') ?></h1>
        <p class="text-muted mb-0"><?= __('Tenant: {0}', h($tenant['display_name'] ?? $tenant['slug'])) ?></p>
    </div>
    <?= $this->Html->link(
        __('Back to tenant'),
        ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'view', $tenant['slug']],
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
    'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'edit', $tenant['slug']],
]) ?>
<?= $this->element('PlatformAdmin/tenant_form', [
    'tenantForm' => $tenantForm,
    'formData' => $formData,
    'isEdit' => true,
]) ?>
<div class="d-flex gap-2">
    <?= $this->Form->button(__('Save tenant'), ['class' => 'btn btn-primary']) ?>
    <?= $this->Html->link(
        __('Cancel'),
        ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'view', $tenant['slug']],
        ['class' => 'btn btn-outline-secondary'],
    ) ?>
</div>
<?= $this->Form->end() ?>
