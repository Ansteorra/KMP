<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Document Entity
 *
 * Generic polymorphic document storage entity. Follows the same pattern as Notes.
 * Used for storing uploaded files with metadata and integrity verification.
 *
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $original_filename
 * @property string $stored_filename
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 * @property string $checksum
 * @property string $storage_adapter
 * @property string|null $metadata
 * @property int $uploaded_by
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property int $created_by
 * @property int|null $modified_by
 *
 * @property \App\Model\Entity\Member $uploader
 * @property \App\Model\Entity\Member $creator
 * @property \App\Model\Entity\Member $modifier
 */
class Document extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'entity_type' => true,
        'entity_id' => true,
        'original_filename' => true,
        'stored_filename' => true,
        'file_path' => true,
        'mime_type' => true,
        'file_size' => true,
        'checksum' => true,
        'storage_adapter' => true,
        'metadata' => true,
        'uploaded_by' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'uploader' => true,
        'creator' => true,
        'modifier' => true,
    ];

    /**
     * Virtual field for metadata as array
     *
     * @return array|null
     */
    protected function _getMetadataArray(): ?array
    {
        if (empty($this->metadata)) {
            return null;
        }

        $decoded = json_decode($this->metadata, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Virtual field for human-readable file size
     *
     * @return string
     */
    protected function _getFileSizeFormatted(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
