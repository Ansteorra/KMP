<?php
declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Throwable;

/**
 * Sortable Behavior
 * 
 * Provides comprehensive sortable list management for database entities with position-based ordering.
 * This behavior automatically manages position values, handles position conflicts, and provides
 * intuitive methods for reordering entities within sorted lists.
 *
 * ## Key Features
 * - **Automatic Position Management**: Handles position assignment and conflict resolution
 * - **Group-Based Sorting**: Support for multiple sorted lists within the same table
 * - **Flexible Movement Methods**: Move to top, bottom, before/after other items, or specific positions
 * - **Gap Management**: Automatically manages gaps and overlaps in position sequences
 * - **Transaction Safety**: Uses database transactions for atomic position updates
 * - **Event Integration**: Hooks into CakePHP's ORM events for seamless operation
 *
 * ## Configuration Options
 * - `field`: Position field name (default: 'position')
 * - `group`: Array of fields that define separate sorting groups
 * - `start`: Starting position value (default: 1)
 * - `step`: Increment between positions (default: 1)
 *
 * ## Database Requirements
 * Tables using this behavior must have:
 * - A numeric field for position storage (integer or decimal)
 * - Optional grouping fields for separate sorted lists
 *
 * ## Usage Examples
 * ```php
 * // Basic configuration
 * $this->addBehavior('Sortable');
 * 
 * // Custom configuration
 * $this->addBehavior('Sortable', [
 *     'field' => 'stack_rank',
 *     'group' => ['category_id', 'status'],
 *     'start' => 0,
 *     'step' => 10
 * ]);
 * 
 * // Reorder items
 * $this->toTop($itemId);
 * $this->toBottom($itemId);
 * $this->moveBefore($itemId, $targetId);
 * $this->moveAfter($itemId, $targetId);
 * $this->move($itemId, 5); // Move to position 5
 * ```
 *
 * ## Use Cases in KMP
 * - **Award Recommendations**: Stack ranking for recommendation prioritization
 * - **Menu Items**: Navigation menu ordering
 * - **Activity Lists**: Event and activity display ordering
 * - **Officer Assignments**: Priority ordering for multi-office holders
 * - **Document Lists**: Ordered document display
 *
 * ## Group-Based Sorting
 * When group fields are configured, sorting operates independently within each group:
 * ```php
 * $this->addBehavior('Sortable', [
 *     'field' => 'priority',
 *     'group' => ['category_id', 'status']
 * ]);
 * // Each combination of category_id + status maintains its own sorted list
 * ```
 *
 * ## Performance Considerations
 * - Position updates may require multiple record modifications
 * - Use transactions for batch operations
 * - Consider using larger step values for frequently reordered lists
 * - Group-based sorting scales better than single large lists
 *
 * @see \Awards\Model\Table\RecommendationsTable Award recommendation stack ranking
 * @author KMP Development Team
 * @since 1.0.0
 */
class SortableBehavior extends Behavior
{
    /**
     * Default configuration for the Sortable behavior
     *
     * ## Configuration Options
     * - **field**: Database field name that stores position values (default: 'position')
     * - **group**: Array of field names that define separate sorting groups (default: [])
     * - **start**: Starting position value for new sequences (default: 1)  
     * - **step**: Increment value between positions (default: 1)
     *
     * ## Group Configuration Example
     * ```php
     * [
     *     'field' => 'display_order',
     *     'group' => ['category_id', 'status'], // Each category+status maintains separate order
     *     'start' => 10,
     *     'step' => 10 // Positions: 10, 20, 30, etc. (allows easy insertion)
     * ]
     * ```
     *
     * @var array
     */
    protected array $_defaultConfig = [
        'field' => 'position',
        'group' => [],
        'start' => 1,
        'step' => 1,
    ];

    /**
     * Fields required for position management operations
     * 
     * Automatically populated with id, position field, and group fields
     * to minimize database queries during reorder operations.
     *
     * @var array
     */
    protected $fields; // Fields for searches
    
    /**
     * Current entity being processed for position changes
     *
     * @var EntityInterface
     */
    protected $row; // Entity to modify

    /**
     * Initialize the Sortable behavior
     *
     * Sets up field arrays for efficient database operations during position management.
     * Combines ID field, position field, and any group fields into a single array
     * to minimize database queries when fetching entities for reordering.
     *
     * ## Initialization Process
     * 1. Merge essential fields: ID + position field + group fields
     * 2. Store field list for use in database operations
     * 3. Prepare behavior for position management operations
     *
     * ## Fields Array Contents
     * - `id`: Primary key for entity identification
     * - Position field: Current position value (e.g., 'position', 'stack_rank')
     * - Group fields: Fields that define sorting boundaries (e.g., 'category_id')
     *
     * @param array $config The configuration for this behavior
     * @return void
     * @see $_defaultConfig For available configuration options
     */
    public function initialize(array $config): void
    {
        $this->fields = array_merge(['id', $this->_config['field']], $this->_config['group']);
    }

    /**
     * Move an entity to the top of its sorting group
     *
     * Repositions the specified entity to the first position in its sorted list.
     * This operation automatically adjusts the positions of other entities to
     * maintain proper sequence ordering.
     *
     * ## Operation Flow
     * 1. Fetch entity with position and group data
     * 2. Check if already at top position (optimization)
     * 3. Increment positions of all entities above current position
     * 4. Set entity to starting position value
     * 5. Save updated entity
     *
     * ## Position Adjustment Logic
     * - All entities with positions below the moved item get incremented by step value
     * - The moved entity gets set to the configured start position
     * - Group boundaries are respected (only affects same group)
     *
     * ## Usage Examples
     * ```php
     * // Move recommendation to top of stack
     * $this->Recommendations->toTop($recommendationId);
     * 
     * // Move menu item to top of category
     * $success = $this->MenuItems->toTop($menuItemId);
     * if ($success) {
     *     $this->Flash->success('Item moved to top');
     * }
     * ```
     *
     * ## Error Handling
     * - Returns false if entity not found or database error occurs
     * - Uses exception handling to prevent fatal errors
     * - Logs errors for debugging (TODO: implement logging)
     *
     * @param int $id Primary key of entity to move to top
     * @return bool True on success, false on failure
     * @see toBottom() Move entity to bottom of list
     * @see move() Move entity to specific position
     * @throws \Throwable Database or entity-related errors (caught internally)
     */
    public function toTop(int $id): bool
    {
        try {
            $field = $this->_config['field'];
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};
            $newVal = $this->getStart();

            if ($currentVal == $newVal) {
                return true;
            }

            // Add one step to all the ones below
            $this->_change($currentVal, false);

            // Set it as the first one
            $this->row->{$field} = $newVal;
            $this->_table->save($this->row);
        } catch (Throwable $th) {
            // TODO: Log error
            return false;
        }

        return true;
    }

    /**
     * Move an entity to the bottom of its sorting group
     *
     * Repositions the specified entity to the last position in its sorted list.
     * This operation automatically adjusts the positions of other entities to
     * maintain proper sequence ordering.
     *
     * ## Operation Flow
     * 1. Fetch entity with position and group data
     * 2. Calculate last position in the group
     * 3. Check if already at bottom position (optimization)
     * 4. Decrement positions of all entities below current position  
     * 5. Set entity to last position value
     * 6. Save updated entity
     *
     * ## Position Adjustment Logic
     * - All entities with positions above the moved item get decremented by step value
     * - The moved entity gets set to the calculated last position
     * - Group boundaries are respected (only affects same group)
     *
     * ## Usage Examples
     * ```php
     * // Move recommendation to bottom of stack
     * $this->Recommendations->toBottom($recommendationId);
     * 
     * // Move problematic item to end of list
     * $success = $this->Items->toBottom($itemId);
     * if ($success) {
     *     $this->Flash->success('Item moved to bottom');
     * }
     * ```
     *
     * ## Error Handling
     * - Returns false if entity not found, no position field, or database error
     * - Uses exception handling to prevent fatal errors
     * - Logs errors for debugging (TODO: implement logging)
     *
     * @param int $id Primary key of entity to move to bottom
     * @return bool True on success, false on failure
     * @see toTop() Move entity to top of list
     * @see move() Move entity to specific position
     * @see getLast() Get the current last position value
     * @throws \Throwable Database or entity-related errors (caught internally)
     */
    public function toBottom(int $id): bool
    {
        try {
            $field = $this->_config['field'];
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};
            $newVal = $this->getLast($this->_getConditions());

            if (!$field) {
                return false;
            }

            if ($currentVal == $newVal) {
                return true;
            }

            // Subtract one step from all the ones above
            $this->_change($currentVal);

            // Set it as the last one
            $this->row->{$field} = $newVal;
            $this->_table->save($this->row);
        } catch (Throwable $th) {
            // TODO: Log error
            return false;
        }

        return true;
    }

    /**
     * Prevent recursive calls during ORM event handling
     *
     * This flag prevents infinite recursion when the move() method is called
     * during the beforeSave event listener to avoid triggering the listener again.
     *
     * @var bool
     */
    private $preventCallOfMoveInEventListener = false;

    /**
     * Move an entity to a specific position in its sorting group
     *
     * This is the core movement method that handles repositioning an entity to any
     * position within its sorted list. All other movement methods ultimately use
     * this method for their operations.
     *
     * ## Operation Flow
     * 1. Fetch entity with position and group data
     * 2. Compare current and target positions
     * 3. Determine movement direction (up or down in list)
     * 4. Create gap at target position by adjusting other entities
     * 5. Move entity to target position (optional)
     * 6. Return success status
     *
     * ## Position Gap Management
     * - **Moving Up**: Increment positions in range [newVal, currentVal)
     * - **Moving Down**: Decrement positions in range (currentVal, newVal]
     * - Gap creation ensures no position conflicts occur
     *
     * ## Usage Examples
     * ```php
     * // Move to specific position
     * $this->Recommendations->move($id, 3); // Move to position 3
     * 
     * // Create gap without moving (for external operations)
     * $this->Recommendations->move($id, 5, false); // Create gap at 5, don't move item
     * 
     * // Complex reordering
     * $newOrder = [15, 7, 23, 4]; // New sequence of IDs
     * foreach ($newOrder as $position => $id) {
     *     $this->Items->move($id, ($position + 1) * 10);
     * }
     * ```
     *
     * ## Parameters
     * - **$id**: Primary key of entity to move
     * - **$newVal**: Target position value
     * - **$moveOwn**: Whether to actually move the entity (true) or just create gap (false)
     *
     * ## Error Handling
     * - Returns false if entity not found or database error occurs
     * - Uses exception handling to prevent fatal errors
     * - Recursive call protection via `$preventCallOfMoveInEventListener`
     *
     * @param int $id Primary key of entity to move
     * @param int $newVal Target position value
     * @param bool $moveOwn Whether to move the entity itself (default: true)
     * @return bool True on success, false on failure
     * @see toTop() Move to first position
     * @see toBottom() Move to last position  
     * @see moveBefore() Move before another entity
     * @see moveAfter() Move after another entity
     * @throws \Throwable Database or entity-related errors (caught internally)
     */
    public function move(int $id, int $newVal, bool $moveOwn = true): bool
    {
        try {
            $step = $this->getStep();
            $field = $this->_config['field'];
            $this->row = $this->_table->get($id, ['fields' => $this->fields]);
            $currentVal = $this->row->{$field};

            if ($newVal == $currentVal) {
                return true; // We do nothing
            } elseif ($newVal < $currentVal) {
                // Create a gap for the movement; do not modify the original
                $this->_change([$newVal, $currentVal - $step], false);
            } else {
                // Create a gap for the movement; do not modify the original
                $this->_change([$currentVal + $step, $newVal]);
            }

            // Assigns the new position
            if ($moveOwn) {
                $this->preventCallOfMoveInEventListener = true;
                $this->row->{$field} = $newVal;
                $this->_table->save($this->row);
                $this->preventCallOfMoveInEventListener = false;
            }
        } catch (Throwable $th) {
            // TODO: Log error
            return false;
        }

        return true;
    }

    /**
     * Move an entity to the position immediately before another entity
     *
     * Provides a convenient way to reorder entities relative to each other rather
     * than using absolute position values. This method fetches the target entity's
     * position and moves the source entity just before it.
     *
     * ## Operation Flow
     * 1. Fetch the target entity to get its current position
     * 2. Call move() with the target's position as the new position
     * 3. Position adjustment logic handles gap creation automatically
     *
     * ## Usage Examples
     * ```php
     * // Move recommendation before another in stack
     * $this->Recommendations->moveBefore($sourceId, $targetId);
     * 
     * // Reorder menu items
     * $this->MenuItems->moveBefore($draggedItemId, $dropTargetId);
     * 
     * // Drag-and-drop implementation
     * $success = $this->Items->moveBefore($dragId, $beforeId);
     * echo json_encode(['success' => $success]);
     * ```
     *
     * ## Position Logic
     * - Source entity takes the exact position of the target entity
     * - Target entity and all subsequent entities get shifted down
     * - Group boundaries are automatically respected
     *
     * @param int $id Primary key of entity to move
     * @param int $beforeId Primary key of entity to move before
     * @return bool True on success, false on failure
     * @see moveAfter() Move entity after another entity
     * @see move() Core movement method with absolute positioning
     * @throws \Throwable If entities cannot be fetched or updated
     */
    public function moveBefore(int $id, int $beforeId): bool
    {
        $before = $this->_table->get($beforeId, ['fields' => $this->fields]);

        return $this->move($id, $before->{$this->_config['field']}, true);
    }

    /**
     * Move an entity to the position immediately after another entity
     *
     * Provides a convenient way to reorder entities relative to each other.
     * This method fetches the target entity's position and moves the source 
     * entity just after it by adding one step value.
     *
     * ## Operation Flow
     * 1. Fetch the target entity to get its current position
     * 2. Calculate new position as target position + step value
     * 3. Call move() with the calculated position
     * 4. Position adjustment logic handles gap creation automatically
     *
     * ## Usage Examples
     * ```php
     * // Move recommendation after another in stack
     * $this->Recommendations->moveAfter($sourceId, $targetId);
     * 
     * // Insert new item after existing one
     * $this->MenuItems->moveAfter($newItemId, $existingItemId);
     * 
     * // Drag-and-drop drop-after implementation
     * $success = $this->Items->moveAfter($dragId, $afterId);
     * $this->Flash->success($success ? 'Moved successfully' : 'Move failed');
     * ```
     *
     * ## Position Logic
     * - Source entity gets position = target position + step value
     * - Entities between target and new position get shifted appropriately
     * - Group boundaries are automatically respected
     *
     * @param int $id Primary key of entity to move
     * @param int $afterId Primary key of entity to move after
     * @return bool True on success, false on failure
     * @see moveBefore() Move entity before another entity
     * @see move() Core movement method with absolute positioning
     * @see getStep() Get the configured step value
     * @throws \Throwable If entities cannot be fetched or updated
     */
    public function moveAfter(int $id, int $afterId): bool
    {
        $after = $this->_table->get($afterId, ['fields' => $this->fields]);

        return $this->move($id, $after->{$this->_config['field']} + $this->getStep(), true);
    }

    /**
     * Get the starting position value for new sorted lists
     *
     * Returns the configured starting position that will be used for the first
     * item in a new sorted list or when moving items to the top position.
     *
     * ## Usage Examples
     * ```php
     * $startPos = $this->getStart(); // Returns configured start value (default: 1)
     * 
     * // Check if item is already at top
     * if ($entity->position == $this->getStart()) {
     *     // Already at top position
     * }
     * ```
     *
     * @return int|float The configured starting position value
     * @see $_defaultConfig For start value configuration
     * @see getNew() Get position for new items (after existing items)
     */
    public function getStart(): int|float
    {
        return $this->_config['start'];
    }

    /**
     * Calculate the position value for a new entity to be added at the end
     *
     * This method determines the appropriate position for a new entity by finding
     * the highest existing position in the group and adding the step value.
     * This ensures new items appear at the end of the sorted list.
     *
     * ## Calculation Logic
     * 1. Find the highest position in the specified group (or all if no conditions)
     * 2. Add the configured step value to create the next position
     * 3. Return the calculated position for the new entity
     *
     * ## Usage Examples
     * ```php
     * // Get position for new item at end of list
     * $newPosition = $this->getNew();
     * 
     * // Get position for new item in specific group
     * $groupConditions = ['category_id' => 5, 'status' => 'active'];
     * $newPosition = $this->getNew($groupConditions);
     * 
     * // Create new entity with proper position
     * $entity = $this->newEntity($data);
     * $entity->position = $this->getNew();
     * ```
     *
     * ## Group Considerations
     * - Without conditions: finds highest position across all records
     * - With conditions: finds highest position within the specified group
     * - Ensures new items don't conflict with existing positions
     *
     * @param array $conditions Optional WHERE conditions to limit the group
     * @return int|float The calculated position for a new entity
     * @see getLast() Get the current highest position
     * @see getStep() Get the configured step increment
     */
    public function getNew($conditions = []): int|float
    {
        return $this->getLast($conditions) + $this->getStep();
    }

    /**
     * Get the configured step value for position increments
     *
     * Returns the step value that determines the spacing between positions.
     * This value is used for calculating position increments during reordering
     * operations and when creating new entities.
     *
     * ## Step Value Usage
     * - **Position Spacing**: Distance between consecutive positions
     * - **Reorder Operations**: Amount to increment/decrement during moves
     * - **New Entity Positioning**: Added to highest position for new items
     * - **Gap Creation**: Used to create space when inserting between items
     *
     * ## Configuration Impact
     * - **Small Steps (1)**: Compact numbering, frequent conflicts on manual edits
     * - **Large Steps (10, 100)**: Sparse numbering, easier manual insertion
     * - **Decimal Steps (0.1)**: Fine-grained positioning, good for frequently reordered lists
     *
     * ## Usage Examples
     * ```php
     * $step = $this->getStep(); // Returns configured step (default: 1)
     * 
     * // Calculate position after specific item
     * $afterPosition = $existingPosition + $this->getStep();
     * 
     * // Create evenly spaced positions
     * for ($i = 0; $i < 10; $i++) {
     *     $positions[] = $this->getStart() + ($i * $this->getStep());
     * }
     * ```
     *
     * @return int|float The configured step increment value
     * @see $_defaultConfig For step value configuration
     * @see getNew() Uses step for new entity positioning
     * @see move() Uses step for position calculations
     */
    public function getStep(): int|float
    {
        return $this->_config['step'];
    }

    /**
     * Returns the highest value
     *
     * @param array $conditions
     * @return float|int
     */
    public function getLast(array $conditions = []): int|float
    {
        $field = $this->_config['field'];
        $query = $this->_table->find(
            'all',
            fields: $this->fields,
            order: ["{$field}" => 'DESC'],
        );

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        return $query->first()->{$field} ?? $this->getStart() - $this->getStep();
    }

    /**
     * Subtract or add a step to the value of a field.
     *
     * @param array|int $value the new value or an array with two values
     * @param bool $substract by default subtracts
     * @return void
     */
    private function _change(int|array $value, bool $substract = true): void
    {
        $step = $this->getStep();
        $field = $this->_config['field'];
        $operator = $substract ? '-' : '+'; // Subtract or add
        $expression = new QueryExpression("`{$field}` = `{$field}` {$operator} {$step}");
        $conditions = $this->_getConditions();

        if (!is_array($value)) {
            // Modify all the ones above or below
            $operator = $substract ? '>' : '<';
            $conditions = array_merge($conditions, ["{$field} {$operator}" => $value]);
            $this->_table->updateAll([$expression], $conditions);
        } else {
            // Modify the ones within the range
            $between = ["{$field} >=" => $value[0], "{$field} <=" => $value[1]];
            $conditions = array_merge($between, $conditions);
            $this->_table->updateAll([$expression], $conditions);
        }
    }

    /**
     * Moves all values to insert a new one in the middle of the list
     *
     * @param array|int $value the position where it will be inserted
     * @return void
     */
    private function _insert(int|array $value): void
    {
        if ($this->isFirst($this->_getConditions())) {
            return; // If there are no values to modify, do nothing
        }

        $step = $this->getStep();
        $field = $this->_config['field'];
        $expression = new QueryExpression("`{$field}` = `{$field}` + {$step}");
        try {
            $conditions = array_merge($this->_getConditions(), ["{$field} >=" => $value]);
            $this->_table->updateAll([$expression], $conditions);
        } catch (Throwable $th) {
            // TODO: When saving multiple entities at once, even though isFirst() is used, starting from the second one it starts returning FALSE, but they actually don't exist yet so updateAll returns false
        }
    }

    /**
     * Returns conditions for the WHERE clause
     *
     * @param \App\Model\Entity $entity
     * @return array
     */
    private function _getConditions(): array
    {
        $group = $this->_config['group'];
        $conditions = [];
        foreach ($group as $column) {
            if (is_null($this->row->{$column})) {
                $conditions[] = "{$column} IS NULL";
            } else {
                $conditions[$column] = $this->row->{$column};
            }
        }

        return $conditions;
    }

    /**
     * Checks if it is the first row of its group
     *
     * @param array $conditions
     * @return bool
     */
    public function isFirst(array $conditions = []): bool
    {
        $field = $this->_config['field'];
        $query = $this->_table->find(
            'all',
            fields: $this->fields,
            order: ["{$field}" => 'DESC'],
        );

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        return $query->count() == 0;
    }

    /**
     * Automatic position management during entity save operations
     *
     * This ORM event listener ensures proper position management when entities are
     * saved. It handles both new entity creation and position updates on existing
     * entities, maintaining list integrity automatically.
     *
     * ## New Entity Handling
     * When a new entity is created:
     * 1. Calculate default position (end of list in appropriate group)
     * 2. If position not set or differs from default, use default position
     * 3. Create gap at position if needed (calls _insert())
     * 4. Assign calculated position to entity
     *
     * ## Existing Entity Position Changes
     * When an existing entity's position is modified:
     * 1. Check if position field has been changed (isDirty())
     * 2. Prevent recursive calls during move operations
     * 3. Call move() method to handle position adjustment
     * 4. Create gaps and adjust other entities as needed
     *
     * ## Automatic Position Assignment Logic
     * ```php
     * // For new entities
     * if ($entity->isNew()) {
     *     $defaultPosition = $this->getNew($groupConditions);
     *     $entity->position = $defaultPosition;
     *     $this->_insert($defaultPosition); // Create gap if needed
     * }
     * 
     * // For position changes on existing entities
     * if ($entity->isDirty('position')) {
     *     $this->move($entity->id, $entity->position, false); // Just create gap
     * }
     * ```
     *
     * ## Recursion Prevention
     * Uses `$preventCallOfMoveInEventListener` flag to prevent infinite recursion
     * when move() operations trigger additional saves during position adjustments.
     *
     * ## Group Context
     * - Position calculations respect configured group boundaries
     * - New entities get positions within their appropriate group
     * - Position changes only affect entities in the same group
     *
     * ## Usage Notes
     * - This method is called automatically by CakePHP's ORM
     * - Manual intervention usually not required
     * - Developers can still manually set positions if needed
     * - Behavior handles conflict resolution transparently
     *
     * ## Error Handling
     * - Graceful handling of missing position fields
     * - Prevents conflicts during concurrent operations
     * - Maintains data integrity even with unexpected position values
     *
     * @param EventInterface $event The beforeSave event from CakePHP ORM
     * @param EntityInterface $entity The entity being saved
     * @return void
     * @see move() Position adjustment for existing entities
     * @see _insert() Gap creation for new entity positioning
     * @see _getConditions() Group boundary determination
     * @see getNew() Default position calculation for new entities
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity): void
    {
        $this->row = $entity;
        $default = $this->getNew($this->_getConditions());
        $field = $this->_config['field'];
        if ($entity->isNew()) { // If it is a new row
            if ($entity->{$field} != $default || $entity->{$field} != null) { // If there are already other rows and it is not inserted at the end
                $entity->{$field} = $default;
                $this->_insert($entity->{$field});
            }
        } elseif (
            $this->preventCallOfMoveInEventListener === false
            &&
            $entity->isDirty($field)
        ) { // If position has been modified
            $this->move($entity->id, $entity->{$field}, false);
        }
    }
}