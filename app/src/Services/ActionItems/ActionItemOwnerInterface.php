<?php
declare(strict_types=1);

namespace App\Services\ActionItems;

/**
 * Optional lifecycle contract for persisted entities that own ActionItems.
 */
interface ActionItemOwnerInterface
{
    /**
     * Whether the owner's ActionItems may still change state or definition.
     */
    public function allowsActionItemMutations(): bool;
}
