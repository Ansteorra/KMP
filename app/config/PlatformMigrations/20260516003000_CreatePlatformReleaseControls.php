<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreatePlatformReleaseControls extends AbstractMigration
{
    public function change(): void
    {
        $this->table('feature_flags', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('flag_key', 'string', ['limit' => 120])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('default_enabled', 'boolean', ['default' => false])
            ->addColumn('rules', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['flag_key'], ['unique' => true])
            ->create();

        $this->table('releases', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('image_tag', 'string', ['limit' => 255])
            ->addColumn('git_sha', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('min_schema', 'string', ['limit' => 64])
            ->addColumn('max_schema', 'string', ['limit' => 64])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'draft'])
            ->addColumn('manifest', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['image_tag'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['max_schema'])
            ->create();
    }
}
