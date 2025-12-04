<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GridView Entity - Saved grid view configuration.
 *
 * Stores user-specific or system-wide grid configurations including
 * filters, sorting, column visibility, and pagination.
 *
 * @property int $id
 * @property string $grid_key Grid identifier (e.g., 'Members.index.main')
 * @property int|null $member_id Owner (NULL for system defaults)
 * @property string $name Display name
 * @property bool $is_default User's preferred default
 * @property bool $is_system_default Application-wide default
 * @property string $config JSON configuration
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\Member $member
 */
class GridView extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'grid_key' => true,
        'member_id' => true,
        'name' => true,
        'is_default' => true,
        'is_system_default' => true,
        'config' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'member' => true,
        'creator' => true,
        'modifier' => true,
    ];

    /**
     * Get the config as a decoded array
     *
     * Convenience method for accessing the JSON config as a PHP array.
     *
     * @return array<string, mixed>
     */
    public function getConfigArray(): array
    {
        if (empty($this->config)) {
            return [];
        }

        $decoded = json_decode($this->config, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $decoded ?: [];
    }

    /**
     * Set the config from an array
     *
     * Convenience method for setting the config from a PHP array.
     *
     * @param array<string, mixed> $configArray Configuration array
     * @return void
     */
    public function setConfigArray(array $configArray): void
    {
        $this->config = json_encode($configArray);
    }

    /**
     * Check if this is a system-wide default view
     *
     * @return bool
     */
    public function isSystemDefault(): bool
    {
        return $this->is_system_default && $this->member_id === null;
    }

    /**
     * Check if this is a user's default view
     *
     * @return bool
     */
    public function isUserDefault(): bool
    {
        return $this->is_default && $this->member_id !== null;
    }

    /**
     * Get a human-readable description of the view type
     *
     * @return string
     */
    public function getViewType(): string
    {
        if ($this->isSystemDefault()) {
            return 'System Default';
        }

        if ($this->isUserDefault()) {
            return 'User Default';
        }

        return 'Saved View';
    }
}
