<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddMemberAuthenticationState extends BaseMigration
{
    /** Add credential revocation epochs and shared request counters. */
    public function change(): void
    {
        $this->table('members')
            ->addColumn('auth_version', 'string', ['limit' => 64, 'default' => 'initial', 'null' => false])
            ->addColumn('password_reset_requested_at', 'datetime', ['null' => true])
            ->update();
        $this->table('member_quick_login_devices')
            ->addColumn('auth_version', 'string', ['limit' => 64, 'null' => true])
            ->update();
        $this->table('security_rate_limits', ['id' => false, 'primary_key' => ['bucket_key']])
            ->addColumn('bucket_key', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('attempts', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('expires_at', 'biginteger', ['null' => false])
            ->addIndex(['expires_at'])
            ->create();
    }
}
