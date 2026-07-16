<?php
declare(strict_types=1);

namespace App\Services\Platform;

use InvalidArgumentException;

/**
 * Validates the safe, non-secret tenant_config surface exposed to platform admins.
 */
class TenantConfigSchema
{
    private const ALLOWED_FIELDS = [
        'documents_blob_container',
        'documents_blob_prefix',
        'email_mode',
        'email_from_address',
        'email_from_name',
        'email_endpoint_url',
        'email_azure_connection_string_secret_ref',
        'email_azure_api_version',
        'email_api_secret_ref',
        'email_smtp_host',
        'email_smtp_port',
        'email_smtp_username',
        'email_smtp_password_secret_ref',
        'email_smtp_tls',
    ];

    private const IGNORED_FIELDS = ['_csrfToken', '_Token'];

    /**
     * @return array<string, mixed>
     */
    public function buildFromFormData(array $data): array
    {
        $this->rejectUnknownFields($data);

        $config = [];
        $documents = [];
        $container = $this->optionalString($data['documents_blob_container'] ?? null);
        if ($container !== null) {
            $documents['blob_container'] = $this->validateContainer($container);
        }
        $prefix = $this->optionalString($data['documents_blob_prefix'] ?? null);
        if ($prefix !== null) {
            $documents['blob_prefix'] = $this->validateBlobPrefix($prefix);
        }
        if ($documents !== []) {
            $config['documents'] = $documents;
        }

        $email = [];
        $emailMode = $this->normalizeEmailMode($this->optionalString($data['email_mode'] ?? null) ?? 'default');
        if ($emailMode !== 'default') {
            $email['mode'] = $emailMode;
        }
        $this->addEmailString($email, 'from_address', $data['email_from_address'] ?? null, true);
        $this->addEmailString($email, 'from_name', $data['email_from_name'] ?? null, false);
        if ($emailMode === 'azure') {
            $this->addSecretRef(
                $email,
                'connection_string_secret_ref',
                $data['email_azure_connection_string_secret_ref'] ?? null,
            );
            $this->addEmailProviderString($email, 'api_version', $data['email_azure_api_version'] ?? null, 32);
        }
        if ($emailMode === 'sendgrid' || $emailMode === 'resend') {
            $this->addUrl($email, 'endpoint_url', $data['email_endpoint_url'] ?? null);
            $this->addSecretRef($email, 'api_secret_ref', $data['email_api_secret_ref'] ?? null);
        }
        if ($emailMode === 'smtp') {
            $this->addEmailProviderString($email, 'host', $data['email_smtp_host'] ?? null, 255);
            $this->addPort($email, 'port', $data['email_smtp_port'] ?? null);
            $this->addEmailProviderString($email, 'username', $data['email_smtp_username'] ?? null, 255);
            $this->addSecretRef($email, 'smtp_password_secret_ref', $data['email_smtp_password_secret_ref'] ?? null);
            $this->addBoolean($email, 'tls', $data['email_smtp_tls'] ?? null);
        }
        if ($email !== []) {
            $config['email'] = $email;
        }

        return $this->sortConfig($config);
    }

    /**
     * Return only safe, known config keys from existing tenant_config.
     *
     * @return array<string, mixed>
     */
    public function safeConfigFromJson(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        try {
            return $this->safeConfig($decoded);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, string>
     */
    public function toFormData(array $config): array
    {
        try {
            $safe = $this->safeConfig($config);
        } catch (InvalidArgumentException) {
            $safe = [];
        }

        return [
            'documents_blob_container' => (string)($safe['documents']['blob_container'] ?? ''),
            'documents_blob_prefix' => (string)($safe['documents']['blob_prefix'] ?? ''),
            'email_mode' => (string)($safe['email']['mode'] ?? 'default'),
            'email_from_address' => (string)($safe['email']['from_address'] ?? ''),
            'email_from_name' => (string)($safe['email']['from_name'] ?? ''),
            'email_endpoint_url' => (string)($safe['email']['endpoint_url'] ?? ''),
            'email_azure_connection_string_secret_ref' => (string)(
                $safe['email']['connection_string_secret_ref'] ?? ''
            ),
            'email_azure_api_version' => (string)($safe['email']['api_version'] ?? ''),
            'email_api_secret_ref' => (string)($safe['email']['api_secret_ref'] ?? ''),
            'email_smtp_host' => (string)($safe['email']['host'] ?? ''),
            'email_smtp_port' => (string)($safe['email']['port'] ?? ''),
            'email_smtp_username' => (string)($safe['email']['username'] ?? ''),
            'email_smtp_password_secret_ref' => (string)($safe['email']['smtp_password_secret_ref'] ?? ''),
            'email_smtp_tls' => !empty($safe['email']['tls']) ? '1' : '0',
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function safeConfig(array $config): array
    {
        $formData = $this->toRawFormData($config);

        return $this->buildFromFormData($formData);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function rejectUnknownFields(array $data): void
    {
        $allowed = array_merge(self::ALLOWED_FIELDS, self::IGNORED_FIELDS);
        foreach (array_keys($data) as $field) {
            if (!in_array((string)$field, $allowed, true)) {
                throw new InvalidArgumentException(sprintf('Unknown tenant configuration field "%s".', (string)$field));
            }
        }
    }

    /**
     * Normalize the email transport driver value.
     */
    private function normalizeEmailMode(string $mode): string
    {
        $mode = strtolower($mode);
        if ($mode === 'api') {
            $mode = 'sendgrid';
        }
        if (!in_array($mode, ['default', 'disabled', 'azure', 'smtp', 'resend', 'sendgrid'], true)) {
            throw new InvalidArgumentException(
                'Email mode must be default, disabled, azure, smtp, resend, or sendgrid.',
            );
        }

        return $mode;
    }

    /**
     * Normalize an optional scalar form value.
     */
    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Tenant configuration values must be scalar strings.');
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $this->rejectInlineSecret($value);

        return $value;
    }

    /**
     * Validate an Azure blob container name.
     */
    private function validateContainer(string $container): string
    {
        $container = strtolower($container);
        if (
            !preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/', $container) ||
            str_contains($container, '--')
        ) {
            throw new InvalidArgumentException('Document storage container must be a valid Azure blob container name.');
        }

        return $container;
    }

    /**
     * Validate an Azure blob object prefix.
     */
    private function validateBlobPrefix(string $prefix): string
    {
        $prefix = trim($prefix, '/');
        if (
            $prefix === '' ||
            strlen($prefix) > 200 ||
            str_contains($prefix, '..') ||
            str_contains($prefix, '//') ||
            !preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $prefix)
        ) {
            throw new InvalidArgumentException('Document storage prefix must be a safe relative blob path prefix.');
        }

        return $prefix;
    }

    /**
     * @param array<string, mixed> $email
     */
    private function addEmailString(array &$email, string $key, mixed $value, bool $emailAddress): void
    {
        $value = $this->optionalString($value);
        if ($value === null) {
            return;
        }
        if ($emailAddress && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email sender address must be a valid email address.');
        }
        if (!$emailAddress && strlen($value) > 120) {
            throw new InvalidArgumentException('Email sender name must be 120 characters or fewer.');
        }

        $email[$key] = $value;
    }

    /**
     * @param array<string, mixed> $email
     */
    private function addEmailProviderString(array &$email, string $key, mixed $value, int $maxLength): void
    {
        $value = $this->optionalString($value);
        if ($value === null) {
            return;
        }
        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException(sprintf('Email %s must be %d characters or fewer.', $key, $maxLength));
        }

        $email[$key] = $value;
    }

    /**
     * @param array<string, mixed> $email
     */
    private function addPort(array &$email, string $key, mixed $value): void
    {
        $value = $this->optionalString($value);
        if ($value === null) {
            return;
        }
        if (!ctype_digit($value) || (int)$value < 1 || (int)$value > 65535) {
            throw new InvalidArgumentException('SMTP port must be a number from 1 to 65535.');
        }

        $email[$key] = (int)$value;
    }

    /**
     * @param array<string, mixed> $email
     */
    private function addBoolean(array &$email, string $key, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $email[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string, mixed> $target
     */
    private function addUrl(array &$target, string $key, mixed $value): void
    {
        $value = $this->optionalString($value);
        if ($value !== null) {
            $target[$key] = $this->validateHttpsUrl($value, 'Endpoint URL');
        }
    }

    /**
     * @param array<string, mixed> $target
     */
    private function addSecretRef(array &$target, string $key, mixed $value): void
    {
        $value = $this->optionalString($value);
        if ($value !== null) {
            $target[$key] = $this->validateSecretReference($value);
        }
    }

    /**
     * Validate HTTPS URLs and reject secret-bearing query values.
     */
    private function validateHttpsUrl(string $url, string $label): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) {
            throw new InvalidArgumentException(sprintf('%s must be an HTTPS URL.', $label));
        }
        $this->rejectInlineSecret($url);

        return $url;
    }

    /**
     * Validate a secret reference name without accepting plaintext values.
     */
    private function validateSecretReference(string $reference): string
    {
        if (
            !preg_match(
                '/^(tenant|platform|secret:\/\/|keyvault:\/\/|kv:\/\/|env:\/\/|db:\/\/)[A-Za-z0-9._:\/@-]{2,255}$/',
                $reference,
            ) ||
            preg_match('/^(raw|plain|plaintext|value):/i', $reference)
        ) {
            throw new InvalidArgumentException(
                'Secret fields must contain a secret reference name, not a plaintext secret value.',
            );
        }

        return $reference;
    }

    /**
     * Reject inline secrets embedded in otherwise non-secret fields.
     */
    private function rejectInlineSecret(string $value): void
    {
        if (preg_match('/(password|secret|token|api[_-]?key)=/i', $value)) {
            throw new InvalidArgumentException('Configuration values must not include inline secrets.');
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function sortConfig(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->sortConfig($value);
            }
        }
        ksort($config);

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function toRawFormData(array $config): array
    {
        $documents = isset($config['documents']) && is_array($config['documents']) ? $config['documents'] : [];
        $email = isset($config['email']) && is_array($config['email']) ? $config['email'] : [];

        return [
            'documents_blob_container' => $documents['blob_container'] ?? '',
            'documents_blob_prefix' => $documents['blob_prefix'] ?? '',
            'email_mode' => $email['mode'] ?? 'default',
            'email_from_address' => $email['from_address'] ?? '',
            'email_from_name' => $email['from_name'] ?? '',
            'email_endpoint_url' => $email['endpoint_url'] ?? '',
            'email_azure_connection_string_secret_ref' => $email['connection_string_secret_ref'] ?? '',
            'email_azure_api_version' => $email['api_version'] ?? '',
            'email_api_secret_ref' => $email['api_secret_ref'] ?? '',
            'email_smtp_host' => $email['host'] ?? '',
            'email_smtp_port' => $email['port'] ?? '',
            'email_smtp_username' => $email['username'] ?? '',
            'email_smtp_password_secret_ref' => $email['smtp_password_secret_ref'] ?? '',
            'email_smtp_tls' => !empty($email['tls']) ? '1' : '0',
        ];
    }
}
