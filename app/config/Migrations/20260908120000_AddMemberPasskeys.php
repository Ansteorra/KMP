<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Native credentials replace app PINs. Existing PIN hashes cannot be converted. */
class AddMemberPasskeys extends BaseMigration
{
    /** Create native authentication storage and retire PIN credentials. */
    public function up(): void
    {
        $this->table('member_passkeys')
            ->addColumn('member_id', 'integer')
            ->addColumn('credential_hash', 'string', ['limit' => 64])
            ->addColumn('credential_id', 'text')
            ->addColumn('public_key', 'text')
            ->addColumn('user_handle', 'string', ['limit' => 64])
            ->addColumn('auth_version', 'string', ['limit' => 64])
            ->addColumn('rp_id', 'string', ['limit' => 253])
            ->addColumn('signature_counter', 'biginteger', ['default' => 0])
            ->addColumn('revision', 'string', ['limit' => 64])
            ->addColumn('label', 'string', ['limit' => 80])
            ->addColumn('created_at', 'biginteger')
            ->addColumn('last_used_at', 'biginteger', ['null' => true, 'default' => null])
            ->addIndex(['credential_hash'], ['unique' => true])
            ->addIndex(['member_id'])
            ->addForeignKey('member_id', 'members', 'id', ['delete' => 'CASCADE'])
            ->create();
        $this->table('passkey_challenges', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 64])
            ->addColumn('binding', 'string', ['limit' => 64])
            ->addColumn('operation', 'string', ['limit' => 16])
            ->addColumn('member_state', 'text', ['null' => true])
            ->addColumn('expires_at', 'biginteger')
            ->addIndex(['expires_at'])
            ->create();
        // Deliberately irreversible: a rollback must not restore PIN authentication.
        $this->execute('DELETE FROM member_quick_login_devices');
    }

    /** Deleted PIN hashes must never be restored through an application rollback. */
    public function down(): void
    {
        throw new RuntimeException('Passkey retirement cannot restore revoked PIN credentials. Roll forward.');
    }
}
