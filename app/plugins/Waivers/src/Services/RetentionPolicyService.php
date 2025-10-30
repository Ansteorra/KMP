<?php

declare(strict_types=1);

namespace Waivers\Services;

use App\Services\ServiceResult;
use Cake\I18n\Date;
use Cake\Log\Log;

/**
 * Retention Policy Service
 *
 * Calculates waiver retention dates based on JSON retention policy definitions.
 * Interprets retention rules stored in WaiverTypes and applies them to specific
 * gatherings and upload dates to determine when waivers should be expired/purged.
 * 
 * ## Retention Policy Structure
 * 
 * Policies are stored as JSON in the waiver_types.retention_periods column:
 * 
 * ```json
 * {
 *   "anchor": "gathering_end_date",
 *   "years": 2,
 *   "months": 0,
 *   "days": 0
 * }
 * ```
 * 
 * ## Anchor Types
 * 
 * - **gathering_end_date**: Count from the gathering's end date
 * - **upload_date**: Count from when the waiver was uploaded
 * - **permanent**: Waiver is never expired (returns far future date)
 * 
 * ## Features
 * 
 * - **Flexible Duration**: Supports years, months, and days combinations
 * - **Multiple Anchors**: Different starting points for retention calculation
 * - **Validation**: Validates policy structure before calculation
 * - **Error Handling**: Returns ServiceResult for consistent error reporting
 * - **Permanent Retention**: Special handling for waivers that never expire
 * 
 * ## Usage Examples
 * 
 * ```php
 * $service = new RetentionPolicyService();
 * 
 * // Calculate retention from policy JSON and gathering end date
 * $policy = '{"anchor":"gathering_end_date","years":2,"months":0,"days":0}';
 * $gatheringEndDate = new Date('2024-12-31');
 * $result = $service->calculateRetentionDate($policy, $gatheringEndDate);
 * 
 * if ($result->success) {
 *     $retentionDate = $result->data; // Date object: 2026-12-31
 * }
 * 
 * // Calculate from upload date
 * $policy = '{"anchor":"upload_date","years":1,"months":6,"days":0}';
 * $uploadDate = new Date('2024-01-01');
 * $result = $service->calculateRetentionDate($policy, null, $uploadDate);
 * // Returns: 2025-07-01
 * 
 * // Permanent retention
 * $policy = '{"anchor":"permanent"}';
 * $result = $service->calculateRetentionDate($policy);
 * // Returns: Date far in future (e.g., 2099-12-31)
 * ```
 * 
 * ## Validation
 * 
 * The service validates:
 * - JSON structure is valid
 * - Required 'anchor' field is present
 * - Anchor type is supported
 * - Duration fields (years/months/days) are present for non-permanent policies
 * - Required date parameters are provided for the anchor type
 * 
 * @see \Waivers\Model\Entity\WaiverType Source of retention policy JSON
 * @see \App\Services\ServiceResult Standard service result pattern
 */
class RetentionPolicyService
{
    /**
     * Valid anchor types for retention policies
     */
    private const VALID_ANCHORS = ['gathering_end_date', 'upload_date', 'permanent'];

    /**
     * Far future date for permanent retention (100 years from now)
     */
    private const PERMANENT_RETENTION_YEARS = 100;

    /**
     * Calculate retention date based on policy and anchor date
     *
     * @param string $policyJson JSON-encoded retention policy
     * @param \Cake\I18n\Date|null $gatheringEndDate Gathering end date (required for gathering_end_date anchor)
     * @param \Cake\I18n\Date|null $uploadDate Upload date (required for upload_date anchor, defaults to today)
     * @return \App\Services\ServiceResult Success with Date object, or failure with error message
     */
    public function calculateRetentionDate(
        string $policyJson,
        ?Date $gatheringEndDate = null,
        ?Date $uploadDate = null
    ): ServiceResult {
        // Parse and validate policy JSON
        $validationResult = $this->validatePolicy($policyJson);
        if (!$validationResult->success) {
            return $validationResult;
        }

        $policy = $validationResult->data;

        // Handle permanent retention
        if ($policy['anchor'] === 'permanent') {
            // Permanent retention returns null to indicate no expiration
            return new ServiceResult(true, null, null);
        }

        // Determine anchor date
        $anchorDate = $this->getAnchorDate($policy['anchor'], $gatheringEndDate, $uploadDate);
        if ($anchorDate === null) {
            return new ServiceResult(
                false,
                "Missing required date for anchor type '{$policy['anchor']}'"
            );
        }

        // Extract duration - support both nested duration object and flat format
        $duration = $policy['duration'] ?? $policy;
        $years = $duration['years'] ?? 0;
        $months = $duration['months'] ?? 0;
        $days = $duration['days'] ?? 0;

        // Calculate retention date from anchor
        $retentionDate = $this->addDurationToDate(
            $anchorDate,
            $years,
            $months,
            $days
        );

        return new ServiceResult(true, null, $retentionDate);
    }

    /**
     * Validate retention policy JSON structure
     *
     * @param string $policyJson JSON string to validate
     * @return \App\Services\ServiceResult Success with parsed policy array, or failure
     */
    public function validatePolicy(string $policyJson): ServiceResult
    {
        // Parse JSON
        $policy = json_decode($policyJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new ServiceResult(false, 'Invalid JSON in retention policy: ' . json_last_error_msg());
        }

        if (!is_array($policy)) {
            return new ServiceResult(false, 'Retention policy must be a JSON object');
        }

        // Validate anchor field
        if (!isset($policy['anchor'])) {
            return new ServiceResult(false, 'Retention policy missing required "anchor" field');
        }

        if (!in_array($policy['anchor'], self::VALID_ANCHORS, true)) {
            return new ServiceResult(
                false,
                'Invalid anchor type. Must be one of: ' . implode(', ', self::VALID_ANCHORS)
            );
        }

        // For non-permanent policies, require at least one duration field
        if ($policy['anchor'] !== 'permanent') {
            // Support both nested duration object and flat format
            $duration = $policy['duration'] ?? $policy;
            $hasDuration = !empty($duration['years']) || !empty($duration['months']) || !empty($duration['days']);
            if (!$hasDuration) {
                return new ServiceResult(
                    false,
                    'Retention policy must specify at least one duration (years, months, or days)'
                );
            }
        }

        return new ServiceResult(true, null, $policy);
    }

    /**
     * Get the anchor date based on anchor type
     *
     * @param string $anchorType Type of anchor
     * @param \Cake\I18n\Date|null $gatheringEndDate Gathering end date
     * @param \Cake\I18n\Date|null $uploadDate Upload date
     * @return \Cake\I18n\Date|null The anchor date or null if not available
     */
    private function getAnchorDate(string $anchorType, ?Date $gatheringEndDate, ?Date $uploadDate): ?Date
    {
        return match ($anchorType) {
            'gathering_end_date' => $gatheringEndDate,
            'upload_date' => $uploadDate ?? Date::now(),
            default => null,
        };
    }

    /**
     * Add duration to a date
     *
     * @param \Cake\I18n\Date $date Starting date
     * @param int $years Years to add
     * @param int $months Months to add
     * @param int $days Days to add
     * @return \Cake\I18n\Date New date with duration added
     */
    private function addDurationToDate(Date $date, int $years, int $months, int $days): Date
    {
        $result = clone $date;

        if ($years > 0) {
            $result = $result->addYears($years);
        }
        if ($months > 0) {
            $result = $result->addMonths($months);
        }
        if ($days > 0) {
            $result = $result->addDays($days);
        }

        return $result;
    }

    /**
     * Check if a waiver is expired based on retention date
     *
     * @param \Cake\I18n\Date $retentionDate The calculated retention date
     * @param \Cake\I18n\Date|null $checkDate Date to check against (defaults to today)
     * @return bool True if expired (retention date has passed)
     */
    public function isExpired(Date $retentionDate, ?Date $checkDate = null): bool
    {
        $checkDate = $checkDate ?? Date::now();
        return $retentionDate->lessThan($checkDate);
    }

    /**
     * Get human-readable description of a retention policy
     *
     * @param string $policyJson JSON-encoded retention policy
     * @return string Human-readable description
     */
    public function getDescription(string $policyJson): string
    {
        return $this->getHumanReadableDescription($policyJson);
    }

    /**
     * Get human-readable description of a retention policy
     *
     * Parses the retention policy JSON and generates a user-friendly description
     * suitable for display in UI or documentation.
     *
     * @param string $policyJson JSON-encoded retention policy
     * @return string Human-readable description following the format:
     *                "Retain for X years, Y months, Z days after [anchor]"
     *                or "Retain permanently" for permanent retention
     */
    public function getHumanReadableDescription(string $policyJson): string
    {
        $result = $this->validatePolicy($policyJson);
        if (!$result->success) {
            return 'Invalid retention policy';
        }

        $policy = $result->data;

        if ($policy['anchor'] === 'permanent') {
            return 'Retain permanently';
        }

        // Support both nested duration object and flat format
        $duration = $policy['duration'] ?? $policy;

        $parts = [];
        if (!empty($duration['years'])) {
            $parts[] = $duration['years'] . ' year' . ($duration['years'] != 1 ? 's' : '');
        }
        if (!empty($duration['months'])) {
            $parts[] = $duration['months'] . ' month' . ($duration['months'] != 1 ? 's' : '');
        }
        if (!empty($duration['days'])) {
            $parts[] = $duration['days'] . ' day' . ($duration['days'] != 1 ? 's' : '');
        }

        $durationText = !empty($parts) ? implode(', ', $parts) : '0 days';

        $anchorText = match ($policy['anchor']) {
            'gathering_end_date' => 'gathering end date',
            'upload_date' => 'upload date',
            default => $policy['anchor'],
        };

        return 'Retain for ' . $durationText . ' after ' . $anchorText;
    }
}
