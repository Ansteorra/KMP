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
                <?= __('Everything on this screen is stored as one JSON document on the tenant\'s platform registry row (tenants.tenant_config). On save the input is validated and normalized; keys that are not part of the safe schema are discarded. The tenant application receives the document read-only when a request or job is bound to the tenant, so changes take effect on the tenant\'s next request or worker cycle.') ?>
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
                <li><?= __('SendGrid / Resend — uses the API key secret reference, plus an optional HTTPS endpoint override.') ?></li>
            </ul>
            <p>
                <?= __('Sender precedence: the sender address and display name set here become the tenant\'s default mail identity. Template-based mail that sets an explicit sender from the tenant\'s own Email.SystemEmailFromAddress app setting still wins; the platform-wide sender is used only when neither is set. The sender address must be a full email address with a dotted domain (name@host.tld).') ?>
            </p>
            <p class="fw-semibold mb-1"><?= __('What a secret reference looks like, per secret store') ?></p>
            <p>
                <?= __('The secret reference fields (ACS connection string, SendGrid/Resend API key, SMTP password) hold the NAME of a credential, never its value. Prefer portable dotted names (tenant.&lt;slug&gt;.&lt;purpose&gt; or platform.&lt;purpose&gt;): they resolve through whichever secret backend the deployment has configured. The URI forms pin one specific backend and only resolve where that backend is wired up. If a reference cannot be resolved, tenant binding still succeeds and the failure is logged and surfaces when mail is sent.') ?>
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= __('Reference') ?></th>
                            <th scope="col"><?= __('Where the value actually lives') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>tenant.kmp.smtp-password</code></td>
                            <td><?= __('Portable name, resolved by the configured store chain. Env backend: variable KMP_SECRET_TENANT_KMP_SMTP_PASSWORD. File backend (local dev): the "tenant.kmp.smtp-password" entry in config/secrets.local.json. Database backend: an encrypted row of that name in platform_secret_values.') ?></td>
                        </tr>
                        <tr>
                            <td><code>platform.sendgrid.api-key</code></td>
                            <td><?= __('Same resolution as above, for a platform-wide (not per-tenant) secret. Env backend: KMP_SECRET_PLATFORM_SENDGRID_API_KEY.') ?></td>
                        </tr>
                        <tr>
                            <td><code>env://EMAIL_SMTP_PASSWORD</code></td>
                            <td><?= __('An environment variable literally named EMAIL_SMTP_PASSWORD on the app host (no KMP_SECRET_ prefix applied).') ?></td>
                        </tr>
                        <tr>
                            <td><code>keyvault://kmp-prod-vault/tenant-kmp-smtp-password</code></td>
                            <td><?= __('Azure Key Vault named kmp-prod-vault, secret tenant-kmp-smtp-password. Key Vault secret names allow only letters, numbers, and hyphens — no dots — so translate dotted names to hyphens. kv:// is an accepted shorthand. This deployment has no direct Key Vault resolver; cloud deployments typically surface Key Vault secrets as environment variables instead, so dotted or env:// references keep working there.') ?></td>
                        </tr>
                        <tr>
                            <td><code>db://tenant.kmp.smtp-password</code></td>
                            <td><?= __('The encrypted platform database secret store (platform_secret_values), explicitly pinned rather than reached through the chain.') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </details>
<?php endif; ?>
