<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreatePlatformIdentity extends AbstractMigration
{
    public function change(): void
    {
        $this->table('platform_users', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending_enrollment'])
            ->addColumn('totp_secret_ref', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('totp_enrolled_at', 'datetime', ['null' => true])
            ->addColumn('failed_login_count', 'integer', ['default' => 0])
            ->addColumn('locked_until', 'datetime', ['null' => true])
            ->addColumn('last_login_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['status'])
            ->create();

        $this->table('platform_user_recovery_codes', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('platform_user_id', 'uuid')
            ->addColumn('code_hash', 'string', ['limit' => 255])
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['platform_user_id'])
            ->addForeignKey('platform_user_id', 'platform_users', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('platform_auth_sessions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('platform_user_id', 'uuid')
            ->addColumn('selector_hash', 'string', ['limit' => 128])
            ->addColumn('verifier_hash', 'string', ['limit' => 255])
            ->addColumn('ip_address', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('user_agent', 'text', ['null' => true])
            ->addColumn('last_seen_at', 'datetime', ['null' => true])
            ->addColumn('expires_at', 'datetime')
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['platform_user_id'])
            ->addIndex(['selector_hash'], ['unique' => true])
            ->addIndex(['expires_at'])
            ->addForeignKey('platform_user_id', 'platform_users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
