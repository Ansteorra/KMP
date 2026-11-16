<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AllowWaiverLossReversal extends BaseMigration
{
    /** Preserve loss reports while permitting audited reversals and subsequent reports. */
    public function up(): void
    {
        $this->table('waivers_gathering_waiver_losses')
            ->addColumn('reversed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('reversed_by', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['reversed_by'])
            ->addForeignKey('reversed_by', 'members', 'id', ['delete' => 'RESTRICT'])
            ->removeIndexByName('uq_waiver_loss_type')
            ->update();
        $this->execute('CREATE UNIQUE INDEX uq_active_waiver_loss_type ON waivers_gathering_waiver_losses '
            . '(gathering_id, waiver_type_id) WHERE reversed_at IS NULL');
    }

    /** Refuse to discard reversal history on rollback. */
    public function down(): void
    {
        $row = $this->fetchRow('SELECT COUNT(*) AS total FROM waivers_gathering_waiver_losses '
            . 'WHERE reversed_at IS NOT NULL');
        if ((int)$row['total'] > 0) {
            throw new RuntimeException('Cannot remove waiver loss reversal history.');
        }
        $this->execute('DROP INDEX uq_active_waiver_loss_type');
        $this->table('waivers_gathering_waiver_losses')
            ->dropForeignKey('reversed_by')
            ->removeColumn('reversed_by')->removeColumn('reversed_at')
            ->addIndex(['gathering_id', 'waiver_type_id'], ['unique' => true, 'name' => 'uq_waiver_loss_type'])
            ->update();
    }
}
