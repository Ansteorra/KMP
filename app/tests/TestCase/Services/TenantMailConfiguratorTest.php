<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\KMP\TenantMetadata;
use App\Mailer\Transport\AzureCommunicationTransport;
use App\Mailer\Transport\RejectedTenantMailTransport;
use App\Mailer\Transport\ResendApiTransport;
use App\Mailer\Transport\SendGridApiTransport;
use App\Services\Secrets\SecretStoreInterface;
use App\Services\TenantMailConfigurator;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\Mailer\Mailer;
use Cake\Mailer\Message;
use Cake\Mailer\TransportFactory;
use Cake\TestSuite\TestCase;
use RuntimeException;

class TenantMailConfiguratorTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $originalTransport = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $originalProfile = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->originalTransport = TransportFactory::getConfig('default');
        $this->originalProfile = Mailer::getConfig('default');
    }

    public function tearDown(): void
    {
        TransportFactory::drop('default');
        if ($this->originalTransport !== null) {
            TransportFactory::setConfig('default', $this->originalTransport);
        }
        Mailer::drop('default');
        if ($this->originalProfile !== null) {
            Mailer::setConfig('default', $this->originalProfile);
        }
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $emailConfig
     */
    private function tenant(array $emailConfig = []): TenantMetadata
    {
        return new TenantMetadata(
            'tenant-id',
            'demo',
            'Demo',
            'active',
            'db',
            'demo_db',
            'demo_role',
            null,
            $emailConfig === [] ? [] : ['email' => $emailConfig],
        );
    }

    private function configurator(array $secrets = []): TenantMailConfigurator
    {
        return new TenantMailConfigurator(new ArraySecretStore($secrets));
    }

    public function testNoEmailConfigLeavesMailUntouched(): void
    {
        $before = TransportFactory::getConfig('default');
        $restore = $this->configurator()->apply($this->tenant());

        $this->assertSame($before, TransportFactory::getConfig('default'));
        $restore();
        $this->assertSame($before, TransportFactory::getConfig('default'));
    }

    public function testUnrelatedSecretReferencesNeverReachTheTransport(): void
    {
        foreach (
            [
            'platform.master_kek',
            'tenant.other.email-api-key',
            'tenant.demo.db.password',
            'secret://platform.master_kek',
            'db://tenant.other.email-api-key',
            'env://KMP_TEST_TENANT_MAIL_SECRET',
            ] as $reference
        ) {
            $restore = $this->configurator([$reference => 'synthetic-secret'])->apply($this->tenant([
                'mode' => 'sendgrid', 'api_secret_ref' => $reference,
            ]));
            try {
                $config = TransportFactory::getConfig('default');
                $this->assertSame(RejectedTenantMailTransport::class, $config['className'], $reference);
                $this->assertArrayNotHasKey('apiKey', $config);
            } finally {
                $restore();
            }
        }
    }

    public function testBlockedConfigurationNeverLooksUpSecretsAndCannotDeliver(): void
    {
        $store = $this->createMock(SecretStoreInterface::class);
        $store->expects($this->never())->method('get');
        $restore = (new TenantMailConfigurator($store))->apply($this->tenant([
            'mode' => 'resend', 'api_secret_ref' => 'tenant.other.email-api-key',
        ]));
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('approved credential reference');
            TransportFactory::get('default')->send(new Message());
        } finally {
            $restore();
        }
    }

    public function testSequentialTenantScopesRestoreTheirOwnMailConfiguration(): void
    {
        $configurator = $this->configurator([
            'tenant.demo.email-api-key' => 'demo-key', 'tenant.other.email-api-key' => 'other-key',
        ]);
        $restoreDemo = $configurator->apply($this->tenant([
            'mode' => 'sendgrid', 'api_secret_ref' => 'tenant.demo.email-api-key',
        ]));
        try {
            $other = new TenantMetadata('other-id', 'other', 'Other', 'active', 'db', 'other_db', 'other_role', null, [
                'email' => ['mode' => 'resend', 'api_secret_ref' => 'tenant.other.email-api-key'],
            ]);
            $restoreOther = $configurator->apply($other);
            try {
                $this->assertSame('other-key', TransportFactory::getConfig('default')['apiKey']);
                $this->assertSame(ResendApiTransport::class, TransportFactory::getConfig('default')['className']);
            } finally {
                $restoreOther();
            }
            $this->assertSame('demo-key', TransportFactory::getConfig('default')['apiKey']);
        } finally {
            $restoreDemo();
        }
        $this->assertSame($this->originalTransport, TransportFactory::getConfig('default'));
    }

    public function testUnapprovedApiDestinationsCannotReceiveTenantCredentials(): void
    {
        foreach (
            [
            'https://unapproved.example.test/send',
            'https://api.sendgrid.com.unapproved.example.test/v3/mail/send',
            'https://api.sendgrid.com/v3/mail/send?token=synthetic',
            'https://api.resend.com/emails',
            ] as $endpoint
        ) {
            $restore = $this->configurator(['tenant.demo.email-api-key' => 'synthetic-secret'])->apply($this->tenant([
                'mode' => 'sendgrid', 'api_secret_ref' => 'tenant.demo.email-api-key', 'endpoint_url' => $endpoint,
            ]));
            try {
                $config = TransportFactory::getConfig('default');
                $this->assertSame(RejectedTenantMailTransport::class, $config['className'], $endpoint);
                $this->assertArrayNotHasKey('apiKey', $config);
            } finally {
                $restore();
            }
        }
    }

    public function testDefaultModeWithFromOverridesProfileOnly(): void
    {
        $transportBefore = TransportFactory::getConfig('default');
        $restore = $this->configurator()->apply($this->tenant([
            'mode' => 'default',
            'from_address' => 'crown@ansteorra.org',
            'from_name' => 'Crown of Ansteorra',
        ]));

        $this->assertSame($transportBefore, TransportFactory::getConfig('default'));
        $this->assertSame(
            ['crown@ansteorra.org' => 'Crown of Ansteorra'],
            Mailer::getConfig('default')['from'] ?? null,
        );

        $restore();
        $this->assertSame($this->originalProfile['from'] ?? null, Mailer::getConfig('default')['from'] ?? null);
    }

    public function testSmtpModeConfiguresTransportAndResolvesPassword(): void
    {
        $restore = $this->configurator(['tenant.demo.smtp-password' => 's3cret'])->apply($this->tenant([
            'mode' => 'smtp',
            'host' => 'mailhost.example.org',
            'port' => 587,
            'username' => 'mailer',
            'smtp_password_secret_ref' => 'tenant.demo.smtp-password',
            'tls' => true,
        ]));

        $config = TransportFactory::getConfig('default');
        $this->assertSame('Smtp', $config['className']);
        $this->assertSame('mailhost.example.org', $config['host']);
        $this->assertSame(587, $config['port']);
        $this->assertSame('mailer', $config['username']);
        $this->assertSame('s3cret', $config['password']);
        $this->assertTrue($config['tls']);

        $restore();
        $this->assertSame($this->originalTransport, TransportFactory::getConfig('default'));
    }

    public function testSmtpModeOmitsBlankUsernameAndUnresolvedPassword(): void
    {
        $restore = $this->configurator()->apply($this->tenant([
            'mode' => 'smtp',
            'host' => 'mailpit',
            'port' => 1025,
        ]));

        $config = TransportFactory::getConfig('default');
        $this->assertArrayNotHasKey('username', $config);
        $this->assertArrayNotHasKey('password', $config);
        $restore();
    }

    public function testDisabledModeUsesDebugTransport(): void
    {
        $restore = $this->configurator()->apply($this->tenant(['mode' => 'disabled']));

        $this->assertSame('Debug', TransportFactory::getConfig('default')['className']);
        $restore();
    }

    public function testSendGridModeResolvesApiKeyAndEndpoint(): void
    {
        $restore = $this->configurator(['tenant.demo.sendgrid-key' => 'sg-key'])->apply($this->tenant([
            'mode' => 'sendgrid',
            'api_secret_ref' => 'tenant.demo.sendgrid-key',
            'endpoint_url' => 'https://api.eu.sendgrid.com/v3/mail/send',
        ]));

        $config = TransportFactory::getConfig('default');
        $this->assertSame(SendGridApiTransport::class, $config['className']);
        $this->assertSame('sg-key', $config['apiKey']);
        $this->assertSame('https://api.eu.sendgrid.com/v3/mail/send', $config['endpoint']);
        $restore();
    }

    public function testResendModeUsesResendTransport(): void
    {
        $restore = $this->configurator(['tenant.demo.resend-key' => 're-key'])->apply($this->tenant([
            'mode' => 'resend',
            'api_secret_ref' => 'tenant.demo.resend-key',
        ]));

        $config = TransportFactory::getConfig('default');
        $this->assertSame(ResendApiTransport::class, $config['className']);
        $this->assertSame('re-key', $config['apiKey']);
        $this->assertArrayNotHasKey('endpoint', $config);
        $restore();
    }

    public function testAzureModeResolvesConnectionString(): void
    {
        $connectionString = 'endpoint=https://acs.example.org/;accesskey=abc123';
        $restore = $this->configurator(['tenant.demo.acs-connection' => $connectionString])->apply($this->tenant([
            'mode' => 'azure',
            'connection_string_secret_ref' => 'tenant.demo.acs-connection',
        ]));

        $config = TransportFactory::getConfig('default');
        $this->assertSame(AzureCommunicationTransport::class, $config['className']);
        $this->assertSame($connectionString, $config['connectionString']);
        $this->assertSame('2023-03-31', $config['apiVersion']);
        $restore();
    }

    public function testEnvSchemeDoesNotResolveArbitraryEnvironmentVariables(): void
    {
        $_ENV['KMP_TEST_TENANT_MAIL_SECRET'] = 'env-secret';
        try {
            $restore = $this->configurator()->apply($this->tenant([
                'mode' => 'smtp',
                'host' => 'mailpit',
                'smtp_password_secret_ref' => 'env://KMP_TEST_TENANT_MAIL_SECRET',
            ]));

            $this->assertSame(RejectedTenantMailTransport::class, TransportFactory::getConfig('default')['className']);
            $this->assertArrayNotHasKey('password', TransportFactory::getConfig('default'));
            $restore();
        } finally {
            unset($_ENV['KMP_TEST_TENANT_MAIL_SECRET']);
        }
    }

    public function testUnsupportedKeyVaultSchemeCannotFallBackToPlatformMail(): void
    {
        $restore = $this->configurator()->apply($this->tenant([
            'mode' => 'sendgrid',
            'api_secret_ref' => 'keyvault://vault/tenant-demo-key',
        ]));

        $config = TransportFactory::getConfig('default');
        $this->assertSame(RejectedTenantMailTransport::class, $config['className']);
        $this->assertArrayNotHasKey('apiKey', $config);
        $restore();
    }

    public function testUnknownModeKeepsPlatformTransport(): void
    {
        $before = TransportFactory::getConfig('default');
        $restore = $this->configurator()->apply($this->tenant(['mode' => 'carrier-pigeon']));

        $this->assertSame($before, TransportFactory::getConfig('default'));
        $restore();
    }
}
