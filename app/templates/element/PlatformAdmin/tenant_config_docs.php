<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * Embedded field guide for the safe tenant_config surface.
 *
 * Renders a collapsible "How this works" block for one tenant configuration
 * section. Keep this copy in sync with the validation rules enforced by
 * \App\Services\Platform\TenantConfigSchema and the runtime behavior of
 * \App\Services\TenantMailConfigurator.
 *
 * @var \App\View\AppView $this
 * @var string $section One of: overview, documents, email
 */
?>
<?php if ($section === 'overview') : ?>
    <details class="border rounded p-3 bg-body-tertiary mb-3">
        <summary class="fw-semibold"><?= __('How tenant configuration works') ?></summary>
        <div class="mt-2 small">
            <p>
                <?= __('Everything on this screen is stored as one JSON document on the tenant\'s platform registry row (tenants.tenant_config). On save the input is validated and normalized; keys that are not part of the safe schema are discarded. The tenant application receives the document read-only when a request or job is bound to the tenant, so changes take effect after commit, within 30 seconds across replicas.') ?>
            </p>
            <p class="mb-0">
                <?= __('Secrets never live here. Fields marked "secret reference" hold the NAME of a secret; the value itself stays in the platform secret store.') ?>
            </p>
        </div>
    </details>
<?php elseif ($section === 'documents') : ?>
    <details class="border rounded p-3 bg-body-tertiary mb-3">
        <summary class="fw-semibold"><?= __('How document storage settings work') ?></summary>
        <div class="mt-2 small">
            <p>
                <?= __('Controls where this tenant\'s uploaded files and documents are stored. These values are read live by the tenant runtime: leave both blank and the platform default container and per-tenant prefix convention is used.') ?>
            </p>
            <ul class="mb-0">
                <li><?= __('Container: a valid Azure Blob container name — 3 to 63 characters, lowercase letters, numbers, and single hyphens; it must start and end with a letter or number and cannot contain consecutive hyphens (input is lowercased for you).') ?></li>
                <li><?= __('Prefix: a relative path inside the container, such as tenants/kmp — up to 200 characters; ".." and "//" are rejected and surrounding slashes are trimmed.') ?></li>
            </ul>
        </div>
    </details>
<?php elseif ($section === 'email') : ?>
    <details class="border rounded p-3 bg-body-tertiary mb-3">
        <summary class="fw-semibold"><?= __('How email settings work') ?></summary>
        <div class="mt-2 small">
            <p>
                <?= __('Selects the mail transport this tenant sends through and the identity mail is sent as. These settings are applied live whenever the tenant is bound — web requests and background queue jobs alike. Only the fields for the selected mode are kept on save; fields for other modes are dropped.') ?>
            </p>
            <ul>
                <li><?= __('Platform default — no per-tenant override is stored; the tenant sends through the platform-wide transport.') ?></li>
                <li><?= __('Disabled — the tenant sends no mail: messages are accepted and discarded. Not allowed while onboarding a new tenant, because the initial super user claims access by email.') ?></li>
                <li><?= __('Azure Communication Services — uses the ACS connection string secret reference, plus an optional API version override.') ?></li>
                <li><?= __('SMTP — uses host, port (1-65535), optional username, an SMTP password secret reference, and the TLS toggle.') ?></li>
                <li><?= __('SendGrid / Resend — uses the tenant API key secret reference. Leave the endpoint blank for the provider default, or use the official global/EU SendGrid mail endpoint or Resend mail endpoint.') ?></li>
            </ul>
            <p>
                <?= __('Sender precedence: the sender address and display name set here become the tenant\'s default mail identity. Template-based mail that sets an explicit sender from the tenant\'s own Email.SystemEmailFromAddress app setting still wins; the platform-wide sender is used only when neither is set. The sender address must be a full email address with a dotted domain (name@host.tld).') ?>
            </p>
            <p class="fw-semibold mb-1"><?= __('Approved email credential references') ?></p>
            <p>
                <?= __('Use only the current tenant slug in these names. SMTP accepts tenant.&lt;slug&gt;.smtp-password or tenant.&lt;slug&gt;.email.smtp_password. SendGrid accepts tenant.&lt;slug&gt;.email-api-key, tenant.&lt;slug&gt;.sendgrid-key or tenant.&lt;slug&gt;.email.sendgrid.api_key. Resend accepts tenant.&lt;slug&gt;.email-api-key, tenant.&lt;slug&gt;.resend-key or tenant.&lt;slug&gt;.email.resend.api_key. Azure accepts tenant.&lt;slug&gt;.acs-connection or tenant.&lt;slug&gt;.email.azure.connection_string.') ?>
            </p>
            <p>
                <?= __('Names resolve through the configured secret store; secret:// and db:// prefixes may precede an approved name. Platform secrets, other tenants, database credentials, raw env:// and direct Key Vault references are rejected. Choose Platform default to use the shared platform transport. Invalid stored overrides block email delivery until corrected; other tenant pages remain available.') ?>
            </p>
            <p class="mb-0">
                <?= __('Changing email settings requires the EMAIL &lt;slug&gt; confirmation, a reason of at least 10 characters, and a fresh single-use authenticator code. The reason is recorded in the platform audit log. Unchanged email settings do not require confirmation when editing other tenant fields.') ?>
            </p>
        </div>
    </details>
<?php endif; ?>
