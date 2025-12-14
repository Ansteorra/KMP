<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * Table-level authorization policy for Recommendations in the Awards plugin.
 *
 * Implements query scoping based on user approval authority and organizational scope.
 * Supports open recommendation submission and export authorization.
 *
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @see \App\Policy\BasePolicy Base table authorization functionality
 * @see \Awards\Model\Table\RecommendationsTable Recommendation data management
 * @see /docs/5.2.16-awards-recommendations-table-policy.md Full documentation
 */
class RecommendationsTablePolicy extends BasePolicy
{
    /**
     * Apply authorization scoping to recommendation queries.
     *
     * Filters recommendations by branch access and approval authority levels.
     * Discovers approval levels from canApproveLevel* permission methods.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user requesting data access
     * @param \Cake\ORM\Query $query The base query to scope
     * @return \Cake\ORM\Query The scoped query with authorization filtering
     */
    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        $table = $query->getRepository();
        $branchIds = $this->_getBranchIdsForPolicy($user, "canIndex");

        // Extract user policies for approval authority discovery
        $branchPolicies = $user->getPolicies($branchIds);
        $approvalLevels = [];
        $recommendationPolicies = $branchPolicies["Awards\Policy\RecommendationPolicy"] ?? [];

        // Discover approval levels from dynamic permission methods
        foreach ($recommendationPolicies as $method => $policy) {
            if (strpos($method, 'canApproveLevel') === 0) {
                $level = str_replace("canApproveLevel", "", $method);
                $approvalLevels[] = $level;
            }
        }

        // Apply branch scoping if branch restrictions exist
        if (!empty($branchIds)) {
            if ($branchIds[0] != -10000000) {  // Global access check
                $query = $table->addBranchScopeQuery($query, $branchIds);
            }
        }

        // Filter by approval levels if user has specific approval authority
        if (!empty($approvalLevels)) {
            return $query->contain(['Awards.Levels'])->where(['Levels.name in' => $approvalLevels]);
        }

        return $query;
    }

    /**
     * Authorize recommendation creation (open access).
     *
     * @param \App\KMP\KmpIdentityInterface $user The user requesting creation access
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The target entity or table
     * @param mixed ...$optionalArgs Additional authorization arguments
     * @return bool Always true for open recommendation submission
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Authorize recommendation export to CSV.
     *
     * Delegates to canIndex - users who can list can export.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user requesting export access
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The target entity or table
     * @param mixed ...$optionalArgs Additional authorization arguments
     * @return bool True if user has index permission
     */
    public function canExport(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canIndex($user, $entity, ...$optionalArgs);
    }
}
