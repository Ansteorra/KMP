<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var \App\View\AppView $this
 * @var array<string, mixed> $tenant
 * @var array<string, string> $formData
 * @var list<string> $errors
 */
$this->assign('title', __('Tenant Config: {0}', $tenant['slug']));
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1"><?= __('Tenant Configuration') ?></h1>
        <p class="text-muted mb-0"><?= __('Tenant: {0}', h($tenant['display_name'] ?? $tenant['slug'])) ?></p>
    </div>
    <?= $this->Html->link(__('Back to tenant'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'view', $tenant['slug']], ['class' => 'btn btn-outline-secondary']) ?>
</div>

<?php if ($errors !== []) : ?>
    <div class="alert alert-danger" role="alert">
        <h2 class="h6"><?= __('Configuration was not saved') ?></h2>
        <ul class="mb-0">
            <?php foreach ($errors as $error) : ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="alert alert-info" role="note">
    <?= __('Only non-secret settings are stored in tenant_config. For passwords, API keys, tokens, and client secrets, enter the secret reference name only (for example, tenant.example.email-api-key or keyvault://vault/secret).') ?>
</div>

<?= $this->Form->create(null, ['url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'config', $tenant['slug']]]) ?>
<section class="card mb-4" aria-labelledby="documents-config-heading">
    <div class="card-body">
        <h2 id="documents-config-heading" class="h5"><?= __('Document Storage') ?></h2>
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <?= $this->Form->control('documents_blob_container', [
                    'label' => __('Azure blob container'),
                    'value' => $formData['documents_blob_container'],
                    'help' => __('Lowercase Azure container name. Leave blank to use the platform default/prefix.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-6">
                <?= $this->Form->control('documents_blob_prefix', [
                    'label' => __('Blob object prefix'),
                    'value' => $formData['documents_blob_prefix'],
                    'help' => __('Safe relative prefix only, such as tenants/example.'),
                ]) ?>
            </div>
        </div>
    </div>
</section>

<section class="card mb-4" aria-labelledby="email-config-heading">
    <div class="card-body">
        <h2 id="email-config-heading" class="h5"><?= __('Email Integration') ?></h2>
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('email_mode', [
                    'type' => 'select',
                    'label' => __('Email mode'),
                    'value' => $formData['email_mode'],
                    'options' => [
                        'default' => __('Platform default'),
                        'disabled' => __('Disabled'),
                        'azure' => __('Azure Communication Services'),
                        'smtp' => __('SMTP'),
                        'sendgrid' => __('SendGrid'),
                        'resend' => __('Resend'),
                    ],
                    'help' => __('Choose the concrete mail transport used for this tenant.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('email_from_address', [
                    'label' => __('Sender email address'),
                    'value' => $formData['email_from_address'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('email_from_name', [
                    'label' => __('Sender display name'),
                    'value' => $formData['email_from_name'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-6">
                <?= $this->Form->control('email_endpoint_url', [
                    'label' => __('SendGrid or Resend endpoint URL'),
                    'value' => $formData['email_endpoint_url'],
                    'help' => __('Optional HTTPS override for SendGrid or Resend; leave blank for the transport default.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-6">
                <?= $this->Form->control('email_api_secret_ref', [
                    'label' => __('SendGrid or Resend API key secret reference'),
                    'value' => $formData['email_api_secret_ref'],
                    'help' => __('Reference/name only, never the secret value.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-6">
                <?= $this->Form->control('email_azure_connection_string_secret_ref', [
                    'label' => __('Azure Communication Services connection string secret reference'),
                    'value' => $formData['email_azure_connection_string_secret_ref'],
                    'help' => __('Reference/name only for the ACS connection string secret.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-3">
                <?= $this->Form->control('email_azure_api_version', [
                    'label' => __('Azure Communication Services API version'),
                    'value' => $formData['email_azure_api_version'],
                    'help' => __('Optional API version override; leave blank for the transport default.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-3">
                <?= $this->Form->control('email_smtp_host', [
                    'label' => __('SMTP host'),
                    'value' => $formData['email_smtp_host'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-2">
                <?= $this->Form->control('email_smtp_port', [
                    'label' => __('SMTP port'),
                    'value' => $formData['email_smtp_port'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('email_smtp_username', [
                    'label' => __('SMTP username'),
                    'value' => $formData['email_smtp_username'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('email_smtp_password_secret_ref', [
                    'label' => __('SMTP password secret reference'),
                    'value' => $formData['email_smtp_password_secret_ref'],
                    'help' => __('Reference/name only, never the password.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-2 d-flex align-items-center">
                <?= $this->Form->control('email_smtp_tls', [
                    'type' => 'checkbox',
                    'label' => __('Use SMTP TLS'),
                    'checked' => $formData['email_smtp_tls'] === '1',
                ]) ?>
            </div>
        </div>
    </div>
</section>

<section class="card mb-4" aria-labelledby="integrations-config-heading">
    <div class="card-body">
        <h2 id="integrations-config-heading" class="h5"><?= __('Feature Flags and Integrations') ?></h2>
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('features_json', [
                    'type' => 'textarea',
                    'label' => __('Feature flags JSON'),
                    'value' => $formData['features_json'],
                    'rows' => 8,
                    'help' => __('JSON object of safe feature names to true/false.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('integration_endpoints_json', [
                    'type' => 'textarea',
                    'label' => __('Integration endpoints JSON'),
                    'value' => $formData['integration_endpoints_json'],
                    'rows' => 8,
                    'help' => __('JSON object of safe endpoint names to HTTPS URLs.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('integration_secret_refs_json', [
                    'type' => 'textarea',
                    'label' => __('Integration secret references JSON'),
                    'value' => $formData['integration_secret_refs_json'],
                    'rows' => 8,
                    'help' => __('JSON object of safe names to secret reference names only.'),
                ]) ?>
            </div>
        </div>
    </div>
</section>

<div class="d-flex gap-2">
    <?= $this->Form->button(__('Save configuration'), ['class' => 'btn btn-primary']) ?>
    <?= $this->Html->link(__('Cancel'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'view', $tenant['slug']], ['class' => 'btn btn-outline-secondary']) ?>
</div>
<?= $this->Form->end() ?>
