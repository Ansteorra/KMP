<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var \App\View\AppView $this
 * @var array<string, string> $tenantForm
 * @var array<string, string> $formData
 * @var bool $isEdit
 */
$statusOptions = [
    'provisioning' => __('Provisioning'),
    'suspended' => __('Suspended'),
    'archived' => __('Archived'),
];
if (($tenantForm['status'] ?? '') === 'active') {
    $statusOptions = ['active' => __('Active')] + $statusOptions;
}
?>
<section class="card mb-4" aria-labelledby="tenant-registry-heading">
    <div class="card-body">
        <h2 id="tenant-registry-heading" class="h5"><?= __('Tenant Registry') ?></h2>
        <div class="row g-3">
            <div class="col-12 col-lg-3">
                <?= $this->Form->control('slug', [
                    'label' => __('Tenant slug'),
                    'value' => $tenantForm['slug'],
                    'readonly' => $isEdit,
                    'help' => __('Lowercase unique identifier used for routing and defaults.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-5">
                <?= $this->Form->control('display_name', [
                    'label' => __('Display name'),
                    'value' => $tenantForm['display_name'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-2">
                <?php if ($isEdit) : ?>
                    <?= $this->Form->control('status', [
                        'type' => 'select',
                        'label' => __('Status'),
                        'value' => $tenantForm['status'],
                        'options' => $statusOptions,
                        'help' => __('Activation is set by the provisioning worker after migrations complete.'),
                    ]) ?>
                <?php else : ?>
                    <?= $this->Form->hidden('status', ['value' => 'provisioning']) ?>
                    <div class="form-group">
                        <label class="form-label"><?= __('Status') ?></label>
                        <p class="form-control-plaintext mb-0"><?= __('Provisioning') ?></p>
                        <div class="form-text">
                            <?= __('The tenant becomes active after the provisioning worker creates the database and runs migrations.') ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-lg-2">
                <?= $this->Form->control('region', [
                    'label' => __('Region'),
                    'value' => $tenantForm['region'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-5">
                <?= $this->Form->control('primary_host', [
                    'label' => __('Primary host'),
                    'value' => $tenantForm['primary_host'],
                    'help' => __('Hostname used to route requests to this tenant.'),
                ]) ?>
            </div>
            <?php if (!$isEdit) : ?>
                <div class="col-12 col-lg-4">
                    <?= $this->Form->control('initial_super_user_email', [
                        'type' => 'email',
                        'label' => __('Tenant super user email'),
                        'value' => $tenantForm['initial_super_user_email'],
                        'required' => true,
                        'maxlength' => 50,
                        'help' => __('Provisioning creates this active tenant super-user account. The user sets their password through Forgot Password.'),
                    ]) ?>
                </div>
            <?php endif; ?>
            <div class="col-12 col-lg-3">
                <?= $this->Form->control('db_server', [
                    'label' => __('Database server'),
                    'value' => $tenantForm['db_server'],
                    'readonly' => $isEdit,
                    'help' => $isEdit
                        ? __('Database identity fields are immutable after tenant creation.')
                        : null,
                ]) ?>
            </div>
            <div class="col-12 col-lg-2">
                <?= $this->Form->control('db_name', [
                    'label' => __('Database name'),
                    'value' => $tenantForm['db_name'],
                    'readonly' => $isEdit,
                    'help' => $isEdit
                        ? __('Database identity fields are immutable after tenant creation.')
                        : __('Leave blank on create to use the slug default.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-2">
                <?= $this->Form->control('db_role', [
                    'label' => __('Database role'),
                    'value' => $tenantForm['db_role'],
                    'readonly' => $isEdit,
                    'help' => $isEdit
                        ? __('Database identity fields are immutable after tenant creation.')
                        : __('Leave blank on create to use the database-name default.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-2">
                <?= $this->Form->control('queue_concurrency_limit', [
                    'type' => 'number',
                    'label' => __('Queue limit'),
                    'value' => $tenantForm['queue_concurrency_limit'],
                    'min' => 1,
                    'max' => 100,
                ]) ?>
            </div>
        </div>
    </div>
</section>

<section class="card mb-4" aria-labelledby="tenant-storage-heading">
    <div class="card-body">
        <h2 id="tenant-storage-heading" class="h5"><?= __('Document Storage') ?></h2>
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

<section class="card mb-4" aria-labelledby="tenant-email-heading">
    <div class="card-body">
        <h2 id="tenant-email-heading" class="h5"><?= __('Email Delivery') ?></h2>
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
                <?= $this->Form->control('email_azure_connection_string_secret_ref', [
                    'label' => __('ACS connection string secret reference'),
                    'value' => $formData['email_azure_connection_string_secret_ref'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-3">
                <?= $this->Form->control('email_azure_api_version', [
                    'label' => __('ACS API version'),
                    'value' => $formData['email_azure_api_version'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-3">
                <?= $this->Form->control('email_endpoint_url', [
                    'label' => __('SendGrid/Resend endpoint URL'),
                    'value' => $formData['email_endpoint_url'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('email_api_secret_ref', [
                    'label' => __('SendGrid/Resend API key secret reference'),
                    'value' => $formData['email_api_secret_ref'],
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
            <div class="col-12 col-lg-3">
                <?= $this->Form->control('email_smtp_username', [
                    'label' => __('SMTP username'),
                    'value' => $formData['email_smtp_username'],
                ]) ?>
            </div>
            <div class="col-12 col-lg-4">
                <?= $this->Form->control('email_smtp_password_secret_ref', [
                    'label' => __('SMTP password secret reference'),
                    'value' => $formData['email_smtp_password_secret_ref'],
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

<section class="card mb-4" aria-labelledby="tenant-integrations-heading">
    <div class="card-body">
        <h2 id="tenant-integrations-heading" class="h5"><?= __('Integration References') ?></h2>
        <div class="alert alert-info" role="note">
            <?= __('Enter HTTPS endpoint URLs and secret reference names only. Do not paste passwords, API keys, or tokens into tenant configuration.') ?>
        </div>
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <?= $this->Form->control('integration_endpoints_json', [
                    'type' => 'textarea',
                    'label' => __('Integration endpoints JSON'),
                    'value' => $formData['integration_endpoints_json'],
                    'rows' => 6,
                    'help' => __('JSON object of safe endpoint names to HTTPS URLs.'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-6">
                <?= $this->Form->control('integration_secret_refs_json', [
                    'type' => 'textarea',
                    'label' => __('Integration secret references JSON'),
                    'value' => $formData['integration_secret_refs_json'],
                    'rows' => 6,
                    'help' => __('JSON object of safe names to secret reference names only.'),
                ]) ?>
            </div>
        </div>
        <?= $this->Form->hidden('features_json', ['value' => $formData['features_json']]) ?>
    </div>
</section>
