<?php

declare(strict_types=1);

namespace Waivers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * WaiverType Entity
 *
 * Defines types of waivers with retention policies.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $document_id FK to documents table for uploaded templates
 * @property string|null $template_path External URL to template (for SCA.org hosted files)
 * @property string $retention_policy JSON-encoded retention policy
 * @property string|null $exemption_reasons JSON array of reasons why waiver might not be needed
 * @property bool $convert_to_pdf
 * @property bool $is_active
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * 
 * @property array|null $retention_policy_parsed Virtual field - parsed JSON
 * @property string $retention_description Virtual field - human readable description
 * @property array|null $exemption_reasons_parsed Virtual field - parsed exemption reasons array
 *
 * @property \App\Model\Entity\Document|null $document
 * @property \Waivers\Model\Entity\GatheringActivityWaiver[] $gathering_activity_waivers
 * @property \Waivers\Model\Entity\GatheringWaiver[] $gathering_waivers
 */
class WaiverType extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'document_id' => true,
        'template_path' => true,
        'retention_policy' => true,
        'exemption_reasons' => true,
        'convert_to_pdf' => true,
        'is_active' => true,
        'document' => true,
        'gathering_activity_waivers' => true,
        'gathering_waivers' => true,
        'gathering_waiver_exemptions' => true,
    ];

    /**
     * Virtual fields that should be included in array/JSON representations
     *
     * @var array<string>
     */
    protected array $_virtual = [
        'retention_policy_parsed',
        'retention_description',
        'exemption_reasons_parsed',
    ];

    /**
     * Virtual field for parsed retention policy
     *
     * @return array|null
     */
    protected function _getRetentionPolicyParsed(): ?array
    {
        if (empty($this->retention_policy)) {
            return null;
        }

        $decoded = json_decode($this->retention_policy, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Virtual field for human-readable retention policy description
     *
     * @return string
     */
    protected function _getRetentionDescription(): string
    {
        $policy = $this->retention_policy_parsed;

        if (!$policy || !isset($policy['anchor'])) {
            return 'Invalid retention policy';
        }

        // Handle permanent retention
        if ($policy['anchor'] === 'permanent') {
            return 'Retain permanently';
        }

        // Build duration description
        $parts = [];
        $duration = $policy['duration'] ?? [];

        if (!empty($duration['years'])) {
            $parts[] = $duration['years'] . ' year' . ($duration['years'] != 1 ? 's' : '');
        }
        if (!empty($duration['months'])) {
            $parts[] = $duration['months'] . ' month' . ($duration['months'] != 1 ? 's' : '');
        }
        if (!empty($duration['days'])) {
            $parts[] = $duration['days'] . ' day' . ($duration['days'] != 1 ? 's' : '');
        }

        if (empty($parts)) {
            return 'Invalid retention policy';
        }

        $durationText = implode(', ', $parts);

        // Build anchor description
        $anchorText = match ($policy['anchor']) {
            'gathering_end_date' => 'after gathering end date',
            'upload_date' => 'after upload date',
            default => 'after ' . $policy['anchor'],
        };

        return "Retain for {$durationText} {$anchorText}";
    }

    /**
     * Virtual field for parsed exemption reasons
     *
     * @return array
     */
    protected function _getExemptionReasonsParsed(): array
    {
        if (empty($this->exemption_reasons)) {
            return [];
        }

        $decoded = json_decode($this->exemption_reasons, true);
        return is_array($decoded) ? $decoded : [];
    }
}
