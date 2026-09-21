<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateMemberPasskeys extends BaseMigration
{
    /** Store public credentials, bound to the member's revocable authentication epoch. */
    public function change(): void
    {
        $this->table('member_passkeys')
            ->addColumn('member_id', 'integer', ['null' => false])
            ->addColumn('auth_version', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('credential_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('credential_id', 'text', ['null' => false])
            ->addColumn('public_key', 'text', ['null' => false])
            ->addColumn('origin', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('sign_count', 'biginteger', ['default' => 0, 'null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['credential_hash'], ['unique' => true])
            ->addIndex(['member_id'])
            ->addForeignKey('member_id', 'members', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
