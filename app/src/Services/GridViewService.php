<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\GridView;
use App\Model\Entity\Member;
use App\Model\Table\GridViewPreferencesTable;
use App\Model\Table\GridViewsTable;
use Cake\ORM\TableRegistry;

/**
 * Business logic for managing saved grid views.
 *
 * Handles view resolution (explicit → user default → system default), CRUD operations,
 * and ownership validation. Used by GridViewsController and DataverseGridTrait.
 */
class GridViewService
{
    /**
     * GridViews table instance
     *
     * @var \App\Model\Table\GridViewsTable
     */
    protected GridViewsTable $gridViewsTable;

    /**
     * GridView preferences table instance
     *
     * @var \App\Model\Table\GridViewPreferencesTable
     */
    protected GridViewPreferencesTable $preferencesTable;

    /**
     * Constructor
     *
     * @param \App\Model\Table\GridViewsTable|null $gridViewsTable Optional table instance for testing
     * @param mixed ...$additional Optional additional arguments (ignored unless a preferences table instance is provided)
     */
    public function __construct(?GridViewsTable $gridViewsTable = null, mixed ...$additional)
    {
        $this->gridViewsTable = $gridViewsTable ?? TableRegistry::getTableLocator()->get('GridViews');

        $preferencesTable = null;
        foreach ($additional as $arg) {
            if ($arg instanceof GridViewPreferencesTable) {
                $preferencesTable = $arg;
                break;
            }
        }

        $this->preferencesTable = $preferencesTable ?? TableRegistry::getTableLocator()->get('GridViewPreferences');
    }

    /**
     * Get the effective view for a grid based on priority resolution
     *
     * Resolution order:
     * 1. Explicit view ID (if provided and accessible)
     * 2. User's default view (if set)
     * 3. System default view (if exists)
     * 4. null (caller should use application fallback)
     *
     * @param string $gridKey Grid identifier
     * @param \App\Model\Entity\Member|null $member Current member
     * @param int|null $viewId Explicitly requested view ID
     * @return \App\Model\Entity\GridView|null
     */
    public function getEffectiveView(string $gridKey, ?Member $member, ?int $viewId = null): ?GridView
    {
        // Priority 1: Explicit view ID
        if ($viewId !== null) {
            $view = $this->getView($viewId, $member);
            if ($view && $view->grid_key === $gridKey) {
                return $view;
            }
        }

        // Priority 2: User preference stored in preferences table
        if ($member !== null) {
            $preferredId = $this->getUserPreferenceViewId($gridKey, $member);
            if ($preferredId !== null) {
                // Only try to fetch the view if we have an integer ID (not a string key like "all")
                if (is_int($preferredId)) {
                    $preferredView = $this->getView($preferredId, $member);
                    if ($preferredView) {
                        return $preferredView;
                    }
                }
                // If it's a string key (like "all"), return null and let the controller handle it
                // This allows the "All" view to work as a default while not being a stored GridView entity
            }

            // Legacy fallback: support existing is_default flags during migration
            $userDefault = $this->gridViewsTable
                ->find('userDefault', ['gridKey' => $gridKey, 'memberId' => $member->id])
                ->first();

            if ($userDefault) {
                return $userDefault;
            }
        }

        // Priority 3: System default
        $systemDefault = $this->gridViewsTable
            ->find('systemDefault', ['gridKey' => $gridKey])
            ->first();

        if ($systemDefault) {
            return $systemDefault;
        }

        // Priority 4: No view found, caller should use application fallback
        return null;
    }

    /**
     * Get all views available for a grid (system defaults + user's own)
     *
     * @param string $gridKey Grid identifier
     * @param \App\Model\Entity\Member|null $member Current member
     * @return array<\App\Model\Entity\GridView>
     */
    public function getViewsForGrid(string $gridKey, ?Member $member = null): array
    {
        $memberId = $member ? $member->id : null;

        return $this->gridViewsTable
            ->find('byGrid', ['gridKey' => $gridKey, 'memberId' => $memberId])
            ->all()
            ->toArray();
    }

    /**
     * Retrieve the preferred view id for a given member/grid key combination
     *
     * @param string $gridKey Grid identifier
     * @param \App\Model\Entity\Member $member Member to check
     * @return int|string|null Preferred view identifier if set
     */
    public function getUserPreferenceViewId(string $gridKey, Member $member): int|string|null
    {
        $preference = $this->preferencesTable
            ->find()
            ->select(['grid_view_id', 'grid_view_key'])
            ->where([
                'grid_key' => $gridKey,
                'member_id' => $member->id,
            ])
            ->first();

        if ($preference === null) {
            return null;
        }

        if ($preference->grid_view_id !== null) {
            return (int)$preference->grid_view_id;
        }

        if ($preference->grid_view_key !== null) {
            return (string)$preference->grid_view_key;
        }

        return null;
    }

    /**
     * Get a single view by ID
     *
     * Validates that the member has access to the view (owns it or it's a system default).
     *
     * @param int $viewId View ID
     * @param \App\Model\Entity\Member|null $member Current member
     * @return \App\Model\Entity\GridView|null
     */
    public function getView(int $viewId, ?Member $member = null): ?GridView
    {
        $view = $this->gridViewsTable->get($viewId);

        // System defaults are accessible to everyone
        if ($view->is_system_default && $view->member_id === null) {
            return $view;
        }

        // Member-specific views require ownership
        if ($member !== null && $view->member_id === $member->id) {
            return $view;
        }

        // No access
        return null;
    }

    /**
     * Create a new grid view
     *
     * @param array<string, mixed> $data View data
     * @param \App\Model\Entity\Member $member Owner of the view
     * @return \App\Model\Entity\GridView|false
     */
    public function createView(array $data, Member $member)
    {
        // Ensure member_id is set correctly
        $data['member_id'] = $member->id;

        // System defaults can only be created by admins (enforce elsewhere)
        // For now, regular users cannot create system defaults
        $data['is_system_default'] = false;

        $view = $this->gridViewsTable->newEntity($data);

        return $this->gridViewsTable->save($view);
    }

    /**
     * Update an existing grid view
     *
     * Validates that the member owns the view before updating.
     *
     * @param int $viewId View ID
     * @param array<string, mixed> $data Updated data
     * @param \App\Model\Entity\Member $member Member making the update
     * @return \App\Model\Entity\GridView|false
     */
    public function updateView(int $viewId, array $data, Member $member)
    {
        $view = $this->getView($viewId, $member);

        if (!$view || $view->member_id !== $member->id) {
            return false; // Not found or not owner
        }

        // Don't allow changing critical fields
        unset($data['id'], $data['member_id'], $data['created'], $data['created_by']);

        // Don't allow regular users to make system defaults
        if (!isset($data['is_system_default'])) {
            $data['is_system_default'] = false;
        }

        $view = $this->gridViewsTable->patchEntity($view, $data);

        return $this->gridViewsTable->save($view);
    }

    /**
     * Delete a grid view
     *
     * Validates that the member owns the view before deleting (soft delete).
     *
     * @param int $viewId View ID
     * @param \App\Model\Entity\Member $member Member making the deletion
     * @return bool Success
     */
    public function deleteView(int $viewId, Member $member): bool
    {
        $view = $this->getView($viewId, $member);

        if (!$view || $view->member_id !== $member->id) {
            return false; // Not found or not owner
        }

        // System defaults cannot be deleted by regular users
        if ($view->is_system_default) {
            return false;
        }

        // Remove preference referencing this view if present
        $preference = $this->preferencesTable
            ->find()
            ->where([
                'grid_key' => $view->grid_key,
                'member_id' => $member->id,
                'grid_view_id' => $view->id,
            ])
            ->first();

        if ($preference) {
            $this->preferencesTable->delete($preference);
        }

        return (bool)$this->gridViewsTable->delete($view);
    }

    /**
     * Set a view as the user's default for a grid
     *
     * Clears any existing default and sets the new one.
     *
     * @param int $viewId View ID to set as default
     * @param int $memberId Member ID
     * @param string $gridKey Grid identifier for validation
     * @return bool Success
     */
    /**
     * Set a user's default view for a grid. Accepts either int (user view) or string (system view).
     *
     * @param int|string $viewIdOrKey
     * @param int $memberId
     * @param string $gridKey
     * @return bool
     */
    public function setUserDefault($viewIdOrKey, int $memberId, string $gridKey): bool
    {
        $connection = $this->gridViewsTable->getConnection();
        return (bool)$connection->transactional(function () use ($viewIdOrKey, $memberId, $gridKey) {
            if (!$this->clearUserDefault($memberId, $gridKey)) {
                return false;
            }

            $preference = $this->preferencesTable->newEmptyEntity();
            $data = [
                'member_id' => $memberId,
                'grid_key' => $gridKey,
            ];

            // Convert numeric strings to integers (URL parameters come as strings)
            if (is_numeric($viewIdOrKey)) {
                $viewIdOrKey = (int)$viewIdOrKey;
            }

            if (is_int($viewIdOrKey)) {
                // User view
                $view = $this->gridViewsTable->get($viewIdOrKey);
                if ($view->grid_key !== $gridKey) {
                    return false;
                }
                if ($view->member_id !== null && $view->member_id !== $memberId) {
                    return false;
                }
                $data['grid_view_id'] = $view->id;
                $data['grid_view_key'] = null;
            } else {
                // System view by key
                $data['grid_view_id'] = null;
                $data['grid_view_key'] = $viewIdOrKey;
            }
            $preference = $this->preferencesTable->patchEntity($preference, $data);
            return (bool)$this->preferencesTable->save($preference);
        });
    }

    /**
     * Clear the user's default for a grid
     *
     * @param int $memberId Member ID
     * @param string $gridKey Grid identifier
     * @return bool Success
     */
    public function clearUserDefault(int $memberId, string $gridKey): bool
    {
        $preference = $this->preferencesTable
            ->find()
            ->where([
                'grid_key' => $gridKey,
                'member_id' => $memberId,
            ])
            ->first();

        if ($preference) {
            $viewId = $preference->grid_view_id;
            if (!$this->preferencesTable->delete($preference)) {
                return false;
            }

            if ($viewId) {
                $view = $this->gridViewsTable->find()
                    ->where(['id' => $viewId])
                    ->first();

                if ($view && $view->member_id === $memberId && $view->is_default) {
                    $view->is_default = false;
                    if (!$this->gridViewsTable->save($view)) {
                        return false;
                    }
                }
            }
        }

        $legacyDefault = $this->gridViewsTable
            ->find('userDefault', ['gridKey' => $gridKey, 'memberId' => $memberId])
            ->first();

        if ($legacyDefault && $legacyDefault->is_default) {
            $legacyDefault->is_default = false;
            return (bool)$this->gridViewsTable->save($legacyDefault);
        }

        return true;
    }

    /**
     * Create a system default view (admin only - enforce permissions elsewhere)
     *
     * @param array<string, mixed> $data View data
     * @return \App\Model\Entity\GridView|false
     */
    public function createSystemDefault(array $data)
    {
        // Enforce system default properties
        $data['member_id'] = null;
        $data['is_system_default'] = true;
        $data['is_default'] = false;

        $view = $this->gridViewsTable->newEntity($data);

        return $this->gridViewsTable->save($view);
    }
}
