<?php

declare(strict_types=1);

namespace Waivers\Services;

use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\I18n\Date;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Workflow condition evaluators for the Waivers plugin.
 *
 * Each method accepts workflow context and config, returns bool.
 */
class WaiversWorkflowConditions
{
    use WorkflowContextAwareTrait;
    use LocatorAwareTrait;

    /**
     * Check if gathering waiver collection is marked ready to close.
     *
     * @param array $context Current workflow context
     * @param array $config Config with gatheringId
     * @return bool
     */
    public function isReadyToClose(array $context, array $config): bool
    {
        try {
            $gatheringId = $this->resolveValue($config['gatheringId'] ?? null, $context);

            if (empty($gatheringId)) {
                return false;
            }

            $closuresTable = $this->fetchTable('Waivers.GatheringWaiverClosures');

            return $closuresTable->isGatheringReadyToClose((int)$gatheringId);
        } catch (\Throwable $e) {
            Log::error('Condition IsReadyToClose failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if gathering waiver collection is closed.
     *
     * @param array $context Current workflow context
     * @param array $config Config with gatheringId
     * @return bool
     */
    public function isClosed(array $context, array $config): bool
    {
        try {
            $gatheringId = $this->resolveValue($config['gatheringId'] ?? null, $context);

            if (empty($gatheringId)) {
                return false;
            }

            $closuresTable = $this->fetchTable('Waivers.GatheringWaiverClosures');

            return $closuresTable->isGatheringClosed((int)$gatheringId);
        } catch (\Throwable $e) {
            Log::error('Condition IsClosed failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if there are any outstanding undeclined waivers for a gathering.
     *
     * @param array $context Current workflow context
     * @param array $config Config with gatheringId
     * @return bool
     */
    public function hasUndeclinedWaivers(array $context, array $config): bool
    {
        try {
            $gatheringId = $this->resolveValue($config['gatheringId'] ?? null, $context);

            if (empty($gatheringId)) {
                return false;
            }

            $waiversTable = $this->fetchTable('Waivers.GatheringWaivers');

            $count = $waiversTable->find('validByGathering', gatheringId: (int)$gatheringId)
                ->count();

            return $count > 0;
        } catch (\Throwable $e) {
            Log::error('Condition HasUndeclinedWaivers failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if waiver is past its retention date.
     *
     * @param array $context Current workflow context
     * @param array $config Config with waiverId
     * @return bool
     */
    public function isPastRetentionDate(array $context, array $config): bool
    {
        try {
            $waiverId = $this->resolveValue($config['waiverId'] ?? null, $context);

            if (empty($waiverId)) {
                return false;
            }

            $waiversTable = $this->fetchTable('Waivers.GatheringWaivers');
            $waiver = $waiversTable->find()
                ->where(['GatheringWaivers.id' => (int)$waiverId])
                ->select(['GatheringWaivers.retention_date'])
                ->first();

            if (!$waiver || empty($waiver->retention_date)) {
                return false;
            }

            return $waiver->retention_date->isPast();
        } catch (\Throwable $e) {
            Log::error('Condition IsPastRetentionDate failed: ' . $e->getMessage());
            return false;
        }
    }
}
