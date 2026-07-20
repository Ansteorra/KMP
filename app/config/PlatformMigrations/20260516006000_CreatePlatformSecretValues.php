<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch
class CreatePlatformSecretValues extends AbstractMigration
{
    /**
     * Create envelope-encrypted platform secret tables.
     */
    public function change(): void
    {
        $this->table('platform_secret_keks', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('key_name', 'string', ['limit' => 255])
            ->addColumn('key_version', 'string', ['limit' => 120])
            ->addColumn('master_secret_name', 'string', ['limit' => 255])
            ->addColumn('algorithm', 'string', ['limit' => 80])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'active'])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('rotated_at', 'datetime', ['null' => true])
            ->addColumn('retired_at', 'datetime', ['null' => true])
            ->addIndex(['key_name', 'key_version'], ['unique' => true])
            ->addIndex(['status'])
            ->create();

        $this->table('platform_secret_values', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('tenant_id', 'uuid', ['null' => true])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('namespace', 'string', ['limit' => 80])
            ->addColumn('key_name', 'string', ['limit' => 255])
            ->addColumn('key_version', 'string', ['limit' => 120])
            ->addColumn('dek_cipher', 'string', ['limit' => 80])
            ->addColumn('dek_nonce', 'binary', ['limit' => 64])
            ->addColumn('dek_tag', 'binary', ['limit' => 32, 'null' => true])
            ->addColumn('wrapped_dek', 'binary', ['limit' => 512])
            ->addColumn('cipher', 'string', ['limit' => 80])
            ->addColumn('nonce', 'binary', ['limit' => 64])
            ->addColumn('tag', 'binary', ['limit' => 32, 'null' => true])
            ->addColumn('ciphertext', 'binary', ['limit' => 16777215])
            ->addColumn('associated_data_hash', 'string', ['limit' => 128])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'active'])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addColumn('rotated_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addIndex(['tenant_id'])
            ->addIndex(['namespace'])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['key_name', 'key_version'])
            ->addIndex(['status'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey(['key_name', 'key_version'], 'platform_secret_keks', ['key_name', 'key_version'])
            ->create();
    }
}
