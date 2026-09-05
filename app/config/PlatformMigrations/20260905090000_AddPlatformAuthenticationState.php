<?php
declare(strict_types=1);

use Migrations\BaseMigration;

// Cake migrations require a global class and a timestamped filename.
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ClassFileName.NoMatch
class AddPlatformAuthenticationState extends BaseMigration
{
    /** Add platform session epochs, TOTP replay protection and shared counters. */
    public function change(): void
    {
        $this->table('platform_users')
            ->addColumn('auth_version', 'string', ['limit' => 64, 'default' => 'initial', 'null' => false])
            ->addColumn('last_accepted_totp_counter', 'biginteger', ['null' => true])
            ->update();
        $this->table('security_rate_limits', ['id' => false, 'primary_key' => ['bucket_key']])
            ->addColumn('bucket_key', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('attempts', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('expires_at', 'biginteger', ['null' => false])
            ->addIndex(['expires_at'])
            ->create();
    }
}
