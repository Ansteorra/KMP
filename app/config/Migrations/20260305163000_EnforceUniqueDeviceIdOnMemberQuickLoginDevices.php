<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class EnforceUniqueDeviceIdOnMemberQuickLoginDevices extends AbstractMigration
{
    private const TABLE = 'member_quick_login_devices';

    public function up(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            return;
        }

        $this->deduplicateByDeviceId();

        $table = $this->table(self::TABLE);
        $table->removeIndexByName('idx_mqld_member_device');
        $table->removeIndexByName('idx_mqld_device_id');
        $table->addIndex(['device_id'], [
            'name' => 'idx_mqld_device_id',
            'unique' => true,
        ]);
        $table->update();
    }

    public function down(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            return;
        }

        $table = $this->table(self::TABLE);
        $table->removeIndexByName('idx_mqld_device_id');
        $table->addIndex(['device_id'], [
            'name' => 'idx_mqld_device_id',
        ]);
        $table->addIndex(['member_id', 'device_id'], [
            'name' => 'idx_mqld_member_device',
            'unique' => true,
        ]);
        $table->update();
    }

    private function deduplicateByDeviceId(): void
    {
        $duplicateDevices = $this->fetchAll(
            sprintf(
                'SELECT device_id FROM %s GROUP BY device_id HAVING COUNT(*) > 1',
                self::TABLE,
            ),
        );
        foreach ($duplicateDevices as $row) {
            $deviceId = (string)($row['device_id'] ?? '');
            if ($deviceId === '') {
                continue;
            }

            $escapedDeviceId = str_replace("'", "''", $deviceId);
            $records = $this->fetchAll(
                sprintf(
                    "SELECT id FROM %s WHERE device_id = '%s' ORDER BY COALESCE(modified, created) DESC, created DESC, id DESC",
                    self::TABLE,
                    $escapedDeviceId,
                ),
            );
            if (count($records) <= 1) {
                continue;
            }

            $idsToDelete = array_map(
                static fn(array $record): int => (int)$record['id'],
                array_slice($records, 1),
            );
            if (count($idsToDelete) === 0) {
                continue;
            }

            $this->execute(
                sprintf(
                    'DELETE FROM %s WHERE id IN (%s)',
                    self::TABLE,
                    implode(', ', $idsToDelete),
                ),
            );
        }
    }
}
