<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GridView Entity - Represents a saved grid view configuration
 *
 * GridView entities encapsulate user-specific or system-wide grid configurations,
 * including filters, sorting, column visibility, and pagination preferences.
 * These views enable a Dataverse-style user experience where grid states can be
 * saved, shared, and reused across sessions.
 *
 * ## Core Properties
 *
 * ### Identity and Ownership
 * - **id**: Primary key
 * - **grid_key**: Unique identifier for the grid (e.g., 'Members.index.main')
 * - **member_id**: Owner of the view (NULL for system defaults)
 * - **name**: User-friendly display name
 *
 * ### View Behavior
 * - **is_default**: Whether this is the user's preferred default for this grid
 * - **is_system_default**: Whether this is the application-wide default (requires member_id = NULL)
 * - **config**: JSON containing the complete view configuration
 *
 * ### Audit Trail
 * - **created/modified**: Timestamps for tracking changes
 * - **created_by/modified_by**: Member references for audit
 * - **deleted**: Soft delete support via Trash behavior
 *
 * ## Config Structure
 *
 * The `config` property contains a JSON object with the following structure:
 *
 * ```json
 * {
 *   "filters": [
 *     {"field": "status", "operator": "eq", "value": "active"},
 *     {"field": "branch_id", "operator": "in", "value": [1, 2, 3]}
 *   ],
 *   "sort": [
 *     {"field": "last_name", "direction": "asc"}
 *   ],
 *   "columns": [
 *     {"key": "sca_name", "visible": true},
 *     {"key": "email_address", "visible": true},
 *     {"key": "phone", "visible": false}
 *   ],
 *   "pageSize": 50
 * }
 * ```
 *
 * ## Validation Rules
 *
 * - Only one `is_system_default = true` per `grid_key`
 * - System defaults must have `member_id = NULL`
 * - Only one `is_default = true` per (`grid_key`, `member_id`) combination
 * - `config` must be valid JSON matching the expected schema
 *
 * ## Usage Examples
 *
 * ### Creating a User View
 * ```php
 * $view = $gridViewsTable->newEntity([
 *     'grid_key' => 'Members.index.main',
 *     'member_id' => $currentUser->id,
 *     'name' => 'Active Officers',
 *     'is_default' => false,
 *     'config' => json_encode([
 *         'filters' => [
 *             ['field' => 'status', 'operator' => 'eq', 'value' => 'active']
 *         ],
 *         'sort' => [
 *             ['field' => 'last_name', 'direction' => 'asc']
 *         ],
 *         'columns' => [
 *             ['key' => 'sca_name', 'visible' => true],
 *             ['key' => 'email_address', 'visible' => true]
 *         ],
 *         'pageSize' => 25
 *     ])
 * ]);
 * ```
 *
 * ### Creating a System Default
 * ```php
 * $systemDefault = $gridViewsTable->newEntity([
 *     'grid_key' => 'Members.index.main',
 *     'member_id' => null,
 *     'name' => 'System Default',
 *     'is_system_default' => true,
 *     'config' => json_encode([...])
 * ]);
 * ```
 *
 * ### Accessing Config as Array
 * ```php
 * $configArray = $view->getConfigArray();
 * $filters = $configArray['filters'] ?? [];
 * $sort = $configArray['sort'] ?? [];
 * ```
 *
 * @property int $id
 * @property string $grid_key
 * @property int|null $member_id
 * @property string $name
 * @property bool $is_default
 * @property bool $is_system_default
 * @property string $config
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\Member $creator
 * @property \App\Model\Entity\Member $modifier
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
