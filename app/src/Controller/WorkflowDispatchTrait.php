<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\WorkflowDefinition;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowDefinitionFinderTrait;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use RuntimeException;
use Throwable;

/**
 * Workflow dispatch helpers for controllers.
 *
 * Resolves the current kingdom from the authenticated member's branch
 * hierarchy and includes it as workflow event context.
 */
trait WorkflowDispatchTrait
{
    use WorkflowDefinitionFinderTrait;

    /**
     * Dispatch to the workflow engine and fail when no active definition is available.
     *
     * Resolves the current kingdom from the authenticated user's branch hierarchy
     * and includes it in the dispatch context.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $dispatcher Workflow trigger dispatcher
     * @param string $slug Workflow definition slug
     * @param string $triggerEvent Event name for the workflow engine
     * @param array $context Event data / context for the workflow
     * @return array<int, mixed>
     */
    protected function dispatchWorkflowOrFail(
        TriggerDispatcher $dispatcher,
        string $slug,
        string $triggerEvent,
        array $context,
    ): array {
        $kingdomId = $this->resolveKingdomId($context);
        $def = $this->findActiveDefinition($slug);

        if (!$def || !$def->current_version) {
            throw new RuntimeException("No active workflow definition found for {$slug}.");
        }

        $triggeredBy = $this->request->getAttribute('identity')?->getIdentifier();
        $context['kingdom_id'] = $kingdomId;

        $results = $dispatcher->dispatch($triggerEvent, $context, $triggeredBy);
        if ($results === []) {
            throw new RuntimeException("Workflow dispatch for {$triggerEvent} started no workflows.");
        }

        return $results;
    }

    /**
     * Fire a workflow event without fallback. Silently logs on failure.
     *
     * Includes kingdom context from the authenticated user.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $dispatcher Workflow trigger dispatcher
     * @param string $triggerEvent Event name for the workflow engine
     * @param array $context Event data / context for the workflow
     * @return void
     */
    protected function dispatchWorkflowEvent(
        TriggerDispatcher $dispatcher,
        string $triggerEvent,
        array $context,
    ): void {
        try {
            $triggeredBy = $this->request->getAttribute('identity')?->getIdentifier();
            $kingdomId = $this->resolveKingdomId($context);
            $context['kingdom_id'] = $kingdomId;

            $dispatcher->dispatch($triggerEvent, $context, $triggeredBy);
        } catch (Throwable $e) {
            Log::warning("Workflow dispatch failed for {$triggerEvent}: " . $e->getMessage());
        }
    }

    /**
     * Resolve the kingdom ID from the authenticated member or workflow context.
     *
     * Authenticated requests still prefer the actor's branch ancestry. Anonymous
     * requests can derive kingdom from explicit branch or member identifiers in
     * the workflow context so public forms can dispatch to kingdom-specific flows.
     *
     * @param array<string, mixed> $context Workflow trigger context.
     * @return int|null Kingdom branch ID, or null if unavailable
     */
    protected function resolveKingdomId(array $context = []): ?int
    {
        $explicitKingdomId = null;
        foreach (['kingdom_id', 'kingdomId'] as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $explicitKingdomId = $this->extractNumericContextValue([$key => $context[$key]], [$key]);
            break;
        }

        if ($explicitKingdomId !== null) {
            return $explicitKingdomId;
        }

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            $memberBranchId = $this->resolveBranchIdFromContextMember($context);
            if ($memberBranchId !== null) {
                return $this->resolveKingdomIdFromBranch($memberBranchId);
            }

            $branchId = $this->extractNumericContextValue($context, ['branch_id', 'branchId']);
            if ($branchId !== null) {
                return $this->resolveKingdomIdFromBranch($branchId);
            }

            return null;
        }

        $branchId = isset($identity['branch_id']) ? (int)$identity['branch_id'] : null;
        if (!$branchId) {
            return null;
        }

        return $this->resolveKingdomIdFromBranch($branchId);
    }

    /**
     * Resolve a branch ID from member identifiers carried in workflow context.
     *
     * Supports both direct member IDs and member public IDs from public forms.
     *
     * @param array<string, mixed> $context Workflow trigger context.
     * @return int|null
     */
    protected function resolveBranchIdFromContextMember(array $context): ?int
    {
        $memberId = $this->extractNumericContextValue($context, ['member_id', 'memberId']);
        if ($memberId !== null) {
            $member = TableRegistry::getTableLocator()
                ->get('Members')
                ->find()
                ->select(['branch_id'])
                ->where(['id' => $memberId])
                ->first();

            if ($member?->branch_id !== null) {
                return (int)$member->branch_id;
            }
        }

        $memberPublicId = $this->extractStringContextValue($context, ['member_public_id', 'memberPublicId']);
        if ($memberPublicId === null) {
            return null;
        }

        $member = TableRegistry::getTableLocator()
            ->get('Members')
            ->find('byPublicId', [$memberPublicId])
            ->select(['branch_id'])
            ->first();

        return $member?->branch_id !== null ? (int)$member->branch_id : null;
    }

    /**
     * Extract a numeric value from top-level or nested workflow context arrays.
     *
     * @param array<string, mixed> $context Workflow trigger context.
     * @param array<int, string> $keys Keys to search for.
     * @return int|null
     */
    protected function extractNumericContextValue(array $context, array $keys): ?int
    {
        $value = $this->extractContextValue($context, $keys);
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = preg_replace('/\\|.*$/', '', trim($value));
            if ($normalized !== null && is_numeric($normalized)) {
                return (int)$normalized;
            }
        }

        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * Extract a string value from top-level or nested workflow context arrays.
     *
     * @param array<string, mixed> $context Workflow trigger context.
     * @param array<int, string> $keys Keys to search for.
     * @return string|null
     */
    protected function extractStringContextValue(array $context, array $keys): ?string
    {
        $value = $this->extractContextValue($context, $keys);
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Extract a matching value from the workflow context or its nested payload arrays.
     *
     * @param array<string, mixed> $context Workflow trigger context.
     * @param array<int, string> $keys Keys to search for.
     * @return mixed
     */
    protected function extractContextValue(array $context, array $keys): mixed
    {
        $candidates = [$context];
        foreach (['data', 'eventData', 'event'] as $nestedKey) {
            $nested = $context[$nestedKey] ?? null;
            if (is_array($nested)) {
                $candidates[] = $nested;
            }
        }

        foreach ($candidates as $candidate) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $candidate)) {
                    return $candidate[$key];
                }
            }
        }

        return null;
    }

    /**
     * Walk the branch parent chain to find the kingdom-type ancestor.
     *
     * @param int $branchId Starting branch ID
     * @return int|null Kingdom branch ID, or null if no kingdom found
     */
    protected function resolveKingdomIdFromBranch(int $branchId): ?int
    {
        $branchesTable = TableRegistry::getTableLocator()->get('Branches');
        $parents = $branchesTable->getAllParents($branchId);
        $candidateIds = array_merge([$branchId], $parents);

        $kingdom = $branchesTable->find()
            ->select(['id'])
            ->where([
                'id IN' => $candidateIds,
                'type' => 'Kingdom',
            ])
            ->first();

        return $kingdom ? (int)$kingdom->id : null;
    }

    /**
     * Find an active workflow definition by slug.
     *
     * @param string $slug Workflow definition slug
     * @return \App\Model\Entity\WorkflowDefinition|null
     */
    private function findActiveDefinition(string $slug): ?WorkflowDefinition
    {
        $table = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $this->findWorkflowDefinitionBySlug($slug);

        if (!$def || !$def->is_active || !$def->current_version_id) {
            return null;
        }

        return $table->find()
            ->where(['WorkflowDefinitions.id' => $def->id])
            ->contain(['CurrentVersion'])
            ->first();
    }
}
