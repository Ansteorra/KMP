<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * Backup entity — tracks backup metadata.
 *
 * @property int $id
 * @property string $filename
 * @property int|null $size_bytes
 * @property int|null $table_count
 * @property int|null $row_count
 * @property string $storage_type
 * @property string $status
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class Backup extends BaseEntity
{
    protected array $_accessible = [
        'filename' => true,
        'size_bytes' => true,
        'table_count' => true,
        'row_count' => true,
        'storage_type' => true,
        'status' => true,
        'notes' => true,
    ];
}
