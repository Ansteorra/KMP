<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class AddRecoveryKeyExportTrackingToTenantBackups extends AbstractMigration
{
    /**
     * Track recovery-key exports so tenant self-service downloads can
     * enforce one-time key export semantics.
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('tenant_backups')
            ->addColumn('recovery_key_exported_at', 'datetime', ['null' => true])
            ->addColumn('recovery_key_exported_by', 'string', ['limit' => 160, 'null' => true])
            ->update();
    }
}
