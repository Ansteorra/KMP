<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TenantMetadata;
use App\Mailer\Transport\AzureCommunicationTransport;
use App\Mailer\Transport\ResendApiTransport;
use App\Mailer\Transport\SendGridApiTransport;
use App\Services\Secrets\SecretStoreInterface;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;

/**
 * Applies a tenant's tenant_config.email overrides to the default mail
 * profile and transport for the duration of a tenant-bound scope.
 *
 * Mode "default" (or no email config) leaves the platform transport
 * untouched. A tenant-level from_address becomes the profile default but an
 * explicit setFrom() by a mailer still wins.
 */
class TenantMailConfigurator
{
    private const TRANSPORT_NAME = 'default';
    private const MAILER_PROFILE = 'default';

    /**
     * Constructor.
     *
     * @param \App\Services\Secrets\SecretStoreInterface $secretStore Secret store for credential references
     */
    public function __construct(private readonly SecretStoreInterface $secretStore)
    {
    }

    /**
     * Apply tenant email overrides and return a restore callback.
     *
     * @param \App\KMP\TenantMetadata $tenant Tenant metadata
     * @return callable():void Restores the previous mail configuration
     */
    public function apply(TenantMetadata $tenant): callable
    {
        $email = $tenant->tenantConfig['email'] ?? null;
        if (!is_array($email) || $email === []) {
            return static function (): void {
            };
        }

        $transportConfig = $this->transportConfig($tenant, $email);
        $fromOverride = $this->fromOverride($email);
        if ($transportConfig === null && $fromOverride === null) {
            return static function (): void {
            };
        }

        $previousTransport = TransportFactory::getConfig(self::TRANSPORT_NAME);
        $previousProfile = Mailer::getConfig(self::MAILER_PROFILE);

        if ($transportConfig !== null) {
            TransportFactory::drop(self::TRANSPORT_NAME);
            TransportFactory::setConfig(self::TRANSPORT_NAME, $transportConfig);
        }
        if ($fromOverride !== null) {
            $profile = is_array($previousProfile) ? $previousProfile : [];
            $profile['from'] = $fromOverride;
            Mailer::drop(self::MAILER_PROFILE);
            Mailer::setConfig(self::MAILER_PROFILE, $profile);
        }

        return static function () use ($transportConfig, $fromOverride, $previousTransport, $previousProfile): void {
            if ($transportConfig !== null) {
                TransportFactory::drop(self::TRANSPORT_NAME);
                if ($previousTransport !== null) {
                    TransportFactory::setConfig(self::TRANSPORT_NAME, $previousTransport);
                }
            }
            if ($fromOverride !== null) {
                Mailer::drop(self::MAILER_PROFILE);
                if ($previousProfile !== null) {
                    Mailer::setConfig(self::MAILER_PROFILE, $previousProfile);
                }
            }
        };
    }

    /**
     * Build the tenant transport configuration, or null to keep the platform default.
     *
     * @param \App\KMP\TenantMetadata $tenant Tenant metadata
     * @param array<string, mixed> $email tenant_config.email section
     * @return array<string, mixed>|null
     */
    private function transportConfig(TenantMetadata $tenant, array $email): ?array
    {
        $mode = strtolower(trim((string)($email['mode'] ?? 'default')));

        return match ($mode) {
            '', 'default' => null,
            'disabled' => ['className' => 'Debug'],
            'smtp' => $this->smtpConfig($tenant, $email),
            'azure' => $this->azureConfig($tenant, $email),
            'sendgrid', 'resend' => $this->apiConfig($tenant, $email, $mode),
            default => $this->unknownMode($tenant, $mode),
        };
    }

    /**
     * @param array<string, mixed> $email tenant_config.email section
     * @return array<string, mixed>
     */
    private function smtpConfig(TenantMetadata $tenant, array $email): array
    {
        $config = [
            'className' => 'Smtp',
            'host' => (string)($email['host'] ?? 'localhost'),
            'port' => (int)($email['port'] ?? 25),
            'client' => null,
            'tls' => (bool)($email['tls'] ?? false),
        ];
        $username = trim((string)($email['username'] ?? ''));
        if ($username !== '') {
            $config['username'] = $username;
        }
        $password = $this->resolveSecret($tenant, (string)($email['smtp_password_secret_ref'] ?? ''));
        if ($password !== null) {
            $config['password'] = $password;
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $email tenant_config.email section
     * @return array<string, mixed>
     */
    private function azureConfig(TenantMetadata $tenant, array $email): array
    {
        $config = [
            'className' => AzureCommunicationTransport::class,
            'connectionString' => $this->resolveSecret(
                $tenant,
                (string)($email['connection_string_secret_ref'] ?? ''),
            ),
            'apiVersion' => trim((string)($email['api_version'] ?? '')) !== ''
                ? (string)$email['api_version']
                : '2023-03-31',
        ];

        return $config;
    }

    /**
     * @param array<string, mixed> $email tenant_config.email section
     * @return array<string, mixed>
     */
    private function apiConfig(TenantMetadata $tenant, array $email, string $mode): array
    {
        $config = [
            'className' => $mode === 'resend' ? ResendApiTransport::class : SendGridApiTransport::class,
            'apiKey' => $this->resolveSecret($tenant, (string)($email['api_secret_ref'] ?? '')),
        ];
        $endpoint = trim((string)($email['endpoint_url'] ?? ''));
        if ($endpoint !== '') {
            $config['endpoint'] = $endpoint;
        }

        return $config;
    }

    /**
     * Unknown stored mode: keep the platform default rather than break mail.
     */
    private function unknownMode(TenantMetadata $tenant, string $mode): ?array
    {
        Log::warning(sprintf('Tenant "%s" has unknown email mode "%s"; using platform default.', $tenant->slug, $mode));

        return null;
    }

    /**
     * Build the profile-level from override, or null when unset.
     *
     * @param array<string, mixed> $email tenant_config.email section
     * @return array<string, string>|string|null
     */
    private function fromOverride(array $email): array|string|null
    {
        $address = trim((string)($email['from_address'] ?? ''));
        if ($address === '') {
            return null;
        }
        $name = trim((string)($email['from_name'] ?? ''));

        return $name === '' ? $address : [$address => $name];
    }

    /**
     * Resolve a stored secret reference to its value.
     *
     * Supports the reference forms accepted by TenantConfigSchema: portable
     * dotted names via the configured secret store, env://VAR for literal
     * environment variables, and secret:// or db:// prefixes stripped down to
     * store lookups. Unresolvable references log a warning and return null so
     * the failure surfaces at send time instead of breaking tenant binding.
     *
     * @param \App\KMP\TenantMetadata $tenant Tenant metadata (for log context)
     * @param string $reference Secret reference name
     */
    private function resolveSecret(TenantMetadata $tenant, string $reference): ?string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        if (str_starts_with($reference, 'env://')) {
            $value = env(substr($reference, strlen('env://')));

            return $value === null || $value === false ? null : (string)$value;
        }

        foreach (['keyvault://', 'kv://'] as $unsupported) {
            if (str_starts_with($reference, $unsupported)) {
                Log::warning(sprintf(
                    'Tenant "%s" email secret reference "%s" needs a Key Vault resolver; none is configured.',
                    $tenant->slug,
                    $reference,
                ));

                return null;
            }
        }

        foreach (['secret://', 'db://'] as $scheme) {
            if (str_starts_with($reference, $scheme)) {
                $reference = substr($reference, strlen($scheme));
                break;
            }
        }

        $value = $this->secretStore->get($reference);
        if ($value === null) {
            Log::warning(sprintf(
                'Tenant "%s" email secret reference "%s" did not resolve; mail sending may fail.',
                $tenant->slug,
                $reference,
            ));

            return null;
        }

        return $value->reveal();
    }
}
