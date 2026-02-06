<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add public_id column to Branches table.
 *
 * Extends the public ID pattern (see 20251103140000) to branches so the
 * public API can expose a non-sequential identifier instead of the
 * internal auto-increment ID.
 */
class AddPublicIdToBranches extends AbstractMigration
{
    protected const TABLE = 'branches';
    protected const PUBLIC_ID_LENGTH = 8;
    protected const CHARSET = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        $table = $this->table(self::TABLE);

        if ($table->hasColumn('public_id')) {
            return;
        }

        $table->addColumn('public_id', 'string', [
            'limit' => 8,
            'null' => true,
            'default' => null,
            'after' => 'id',
            'comment' => 'Non-sequential public identifier safe for client exposure',
        ]);

        $table->addIndex(['public_id'], [
            'unique' => true,
            'name' => 'idx_branches_public_id',
        ]);

        $table->update();

        // Backfill existing rows
        $charsetLength = strlen(self::CHARSET);
        $rows = $this->fetchAll(
            "SELECT id FROM branches WHERE public_id IS NULL OR public_id = ''"
        );

        foreach ($rows as $row) {
            $publicId = $this->_generateUniquePublicId($charsetLength);
            $this->execute(sprintf(
                "UPDATE branches SET public_id = '%s' WHERE id = %d",
                $publicId,
                $row['id']
            ));
        }

        // Make NOT NULL now that every row has a value
        $table->changeColumn('public_id', 'string', [
            'limit' => 8,
            'null' => false,
            'comment' => 'Non-sequential public identifier safe for client exposure',
        ]);

        $table->update();
    }

    protected function _generateUniquePublicId(int $charsetLength): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $publicId = '';
            for ($i = 0; $i < self::PUBLIC_ID_LENGTH; $i++) {
                $publicId .= self::CHARSET[random_int(0, $charsetLength - 1)];
            }

            $exists = $this->fetchRow(sprintf(
                "SELECT id FROM branches WHERE public_id = '%s'",
                $publicId
            ));

            $attempt++;
            if ($attempt >= $maxAttempts) {
                throw new \RuntimeException(
                    'Failed to generate unique public ID for branches after ' . $maxAttempts . ' attempts'
                );
            }
        } while ($exists);

        return $publicId;
    }

    public function down(): void
    {
        $table = $this->table(self::TABLE);

        if (!$table->hasColumn('public_id')) {
            return;
        }

        $table->removeIndexByName('idx_branches_public_id');
        $table->removeColumn('public_id');
        $table->update();
    }
}
