<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Persist revocable, single-hop event authority without changing role assignments. */
class CreateBestowalDelegations extends BaseMigration
{
    /** @inheritDoc */
    public function change(): void
    {
        $this->table('awards_bestowal_delegations')
            ->addColumn('gathering_id', 'integer', ['null' => false])
            ->addColumn('branch_id', 'integer', ['null' => false])
            ->addColumn('grantor_id', 'integer', ['null' => false])
            ->addColumn('delegate_id', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addColumn('created_by', 'integer', ['null' => false])
            ->addColumn('modified_by', 'integer', ['null' => true])
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addColumn('revoked_by', 'integer', ['null' => true])
            ->addIndex(['delegate_id', 'revoked_at', 'gathering_id'])
            ->addIndex(['grantor_id', 'gathering_id'])
            ->addForeignKey('gathering_id', 'gatherings', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('branch_id', 'branches', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('grantor_id', 'members', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('delegate_id', 'members', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('revoked_by', 'members', 'id', ['delete' => 'RESTRICT'])
            ->create();
    }
}
