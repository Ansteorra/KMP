<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\PlatformAdminRecoveryService;
use App\Services\Platform\PlatformTotpVerifier;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

class PlatformAdminCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'platform admin';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Bootstrap and recover platform administrator access.')
            ->addArgument('action', [
                'help' => 'Action to run.',
                'choices' => ['bootstrap', 'reset-password', 'reset-mfa', 'emergency-login', 'rotate-recovery-codes'],
                'required' => true,
            ])
            ->addOption('email', [
                'help' => 'Platform admin email address.',
            ])
            ->addOption('reason', [
                'help' => 'Required operator reason for recovery actions.',
            ])
            ->addOption('approver-email', [
                'help' => 'Another platform admin email for future reset-mfa approval. Currently fail-closed.',
            ])
            ->addOption('approver-totp', [
                'help' => 'Another platform admin TOTP for future reset-mfa approval. Currently fail-closed.',
            ])
            ->addOption('totp', [
                'help' => 'TOTP code for emergency login.',
            ])
            ->addOption('recovery-code', [
                'help' => 'One-time recovery code for emergency login.',
            ])
            ->addOption('session-minutes', [
                'help' => 'Emergency session lifetime in minutes.',
                'default' => '15',
            ])
            ->addOption('print-initial-password', [
                'help' => 'Print the generated initial password once.',
                'boolean' => true,
                'default' => true,
            ])
            ->addOption('print-totp-secret', [
                'help' => 'Print the generated TOTP bootstrap secret once.',
                'boolean' => true,
                'default' => true,
            ])
            ->addOption('print-recovery-codes', [
                'help' => 'Print generated recovery codes once.',
                'boolean' => true,
                'default' => true,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $connection = ConnectionManager::get('platform');
        if (!$connection instanceof Connection) {
            throw new RuntimeException('The platform datasource must use a Cake database connection.');
        }
        $secretStore = SecretStoreFactory::fromConfig();
        $mfaConfig = (array)Configure::read('Platform.adminMfa');
        $service = new PlatformAdminRecoveryService(
            $connection,
            $secretStore,
            new PlatformTotpVerifier(
                $secretStore,
                (int)($mfaConfig['window'] ?? 1),
                (int)($mfaConfig['period'] ?? 30),
                (int)($mfaConfig['digits'] ?? 6),
                (string)($mfaConfig['algorithm'] ?? 'sha1'),
            ),
        );

        try {
            return match ((string)$args->getArgument('action')) {
                'bootstrap' => $this->bootstrap($service, $args, $io),
                'reset-password' => $this->resetPassword($service, $args, $io),
                'reset-mfa' => $this->resetMfa($service, $args, $io),
                'emergency-login' => $this->emergencyLogin($service, $args, $io),
                'rotate-recovery-codes' => $this->rotateRecoveryCodes($service, $args, $io),
                default => throw new RuntimeException('Unknown platform admin action.'),
            };
        } catch (RuntimeException $exception) {
            $io->err($exception->getMessage());
            $io->err('ON-CALL PAGE REQUIRED: platform admin recovery action failed or was refused.');

            return self::CODE_ERROR;
        }
    }

    /**
     * Run first-admin bootstrap.
     */
    private function bootstrap(PlatformAdminRecoveryService $service, Arguments $args, ConsoleIo $io): int
    {
        $email = (string)($args->getOption('email') ?? '');
        $result = $service->bootstrapFirstAdmin($email);

        $io->out('Platform admin bootstrap created the first platform user.');
        $io->out(sprintf('Platform user: %s', $result->email));
        $io->out(sprintf('Platform user id: %s', $result->platformUserId));
        $io->out(sprintf('TOTP secret reference: %s', $result->totpSecretRef));
        $io->err('ON-CALL PAGE REQUIRED: first platform admin bootstrap completed.');

        $io->out(sprintf('Initial password (shown once): %s', $result->initialPassword->reveal()));
        $io->out(sprintf('TOTP secret (shown once): %s', $result->totpSecret->reveal()));
        $io->out('Recovery codes (shown once):');
        foreach ($result->recoveryCodes as $recoveryCode) {
            $io->out('  ' . $recoveryCode->reveal());
        }

        return self::CODE_SUCCESS;
    }

    /**
     * Generate and print a new platform admin password.
     */
    private function resetPassword(PlatformAdminRecoveryService $service, Arguments $args, ConsoleIo $io): int
    {
        $email = (string)($args->getOption('email') ?? '');
        $reason = (string)($args->getOption('reason') ?? '');
        if (trim($reason) === '') {
            throw new RuntimeException('A non-empty --reason value is required for reset-password.');
        }

        $password = $service->resetPassword($email, $reason);
        $io->out(sprintf('Platform admin password reset for: %s', strtolower(trim($email))));
        $io->out(sprintf('New password (shown once): %s', $password->reveal()));
        $io->err('ON-CALL PAGE REQUIRED: platform admin password was reset.');

        return self::CODE_SUCCESS;
    }

    /**
     * Run MFA reset once another-admin TOTP approval exists.
     */
    private function resetMfa(PlatformAdminRecoveryService $service, Arguments $args, ConsoleIo $io): int
    {
        $email = (string)($args->getOption('email') ?? '');
        $reason = (string)($args->getOption('reason') ?? '');
        if (trim($reason) === '') {
            throw new RuntimeException('A non-empty --reason value is required for reset-mfa.');
        }
        $service->resetMfa($email, $reason);

        return self::CODE_SUCCESS;
    }

    /**
     * Issue an emergency login token when production TOTP verification is available.
     */
    private function emergencyLogin(PlatformAdminRecoveryService $service, Arguments $args, ConsoleIo $io): int
    {
        $email = (string)($args->getOption('email') ?? '');
        $totp = (string)($args->getOption('totp') ?? '');
        $recoveryCode = (string)($args->getOption('recovery-code') ?? '');
        $sessionMinutes = (int)$args->getOption('session-minutes');
        if ($totp === '' || $recoveryCode === '') {
            throw new RuntimeException('--totp and --recovery-code are required for emergency-login.');
        }

        $result = $service->emergencyLogin($email, $totp, $recoveryCode, $sessionMinutes);
        $io->out(sprintf('Emergency session id: %s', $result->sessionId));
        $io->out(sprintf('Expires at: %s', $result->expiresAt->format(DATE_ATOM)));
        $io->out(sprintf('One-time session token (shown once): %s', $result->sessionToken->reveal()));
        $io->err('ON-CALL PAGE REQUIRED: emergency platform admin login token issued.');

        return self::CODE_SUCCESS;
    }

    /**
     * Rotate recovery codes after a successful TOTP challenge.
     */
    private function rotateRecoveryCodes(PlatformAdminRecoveryService $service, Arguments $args, ConsoleIo $io): int
    {
        $email = (string)($args->getOption('email') ?? '');
        $totp = (string)($args->getOption('totp') ?? '');
        $reason = (string)($args->getOption('reason') ?? '');
        if ($totp === '') {
            throw new RuntimeException('--totp is required for rotate-recovery-codes.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('A non-empty --reason value is required for rotate-recovery-codes.');
        }

        $codes = $service->rotateRecoveryCodes($email, $totp, $reason);
        $io->out('Platform admin recovery codes were rotated.');
        $io->err('ON-CALL PAGE REQUIRED: platform admin recovery codes rotated.');
        $io->out('Recovery codes (shown once):');
        foreach ($codes as $recoveryCode) {
            $io->out('  ' . $recoveryCode->reveal());
        }

        return self::CODE_SUCCESS;
    }
}
