<?php
declare(strict_types=1);

namespace App\KMP;

use App\Model\Entity\WorkflowApprovalResponse;

/**
 * Normalizes workflow-configured approval decision choices for UI and validation.
 */
class WorkflowApprovalDecisionOptions
{
    /**
     * @return array<int, string>
     */
    public static function builtInValues(): array
    {
        return [
            WorkflowApprovalResponse::DECISION_APPROVE,
            WorkflowApprovalResponse::DECISION_REJECT,
            WorkflowApprovalResponse::DECISION_ABSTAIN,
            WorkflowApprovalResponse::DECISION_REQUEST_CHANGES,
        ];
    }

    /**
     * @param array<string, mixed>|string|null $approverConfig Approval config
     * @return array<int, array{value: string, label: string}>
     */
    public static function normalizeOptions(array|string|null $approverConfig): array
    {
        $config = self::normalizeConfig($approverConfig);
        $rawOptions = $config['decision_options'] ?? $config['decisionOptions'] ?? [];
        if (!is_array($rawOptions)) {
            return [];
        }

        $options = [];
        $seen = [];
        foreach ($rawOptions as $key => $rawOption) {
            $label = null;
            $value = null;

            if (is_array($rawOption)) {
                $label = trim((string)($rawOption['label'] ?? $rawOption['name'] ?? ''));
                $value = trim((string)($rawOption['value'] ?? ''));
            } elseif (is_string($rawOption)) {
                $label = trim($rawOption);
            }

            if ($label === null || $label === '') {
                continue;
            }

            if ($value === '') {
                $value = is_string($key) && !is_numeric($key) ? $key : self::slug($label);
            } else {
                $value = self::slug($value);
            }
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed>|string|null $approverConfig Approval config
     * @return array<int, string>
     */
    public static function allowedValues(array|string|null $approverConfig): array
    {
        return array_values(array_unique(array_merge(
            self::builtInValues(),
            array_column(self::normalizeOptions($approverConfig), 'value'),
        )));
    }

    /**
     * @param array<string, mixed>|string|null $approverConfig Approval config
     */
    public static function labelForDecision(string $decision, array|string|null $approverConfig): string
    {
        foreach (self::normalizeOptions($approverConfig) as $option) {
            if ($option['value'] === $decision) {
                return $option['label'];
            }
        }

        return match ($decision) {
            WorkflowApprovalResponse::DECISION_APPROVE => __('Approve'),
            WorkflowApprovalResponse::DECISION_REJECT => __('Reject'),
            WorkflowApprovalResponse::DECISION_ABSTAIN => __('Abstain'),
            WorkflowApprovalResponse::DECISION_REQUEST_CHANGES => __('Request Changes'),
            default => ucwords(str_replace('_', ' ', $decision)),
        };
    }

    /**
     * @param array<string, mixed>|string|null $approverConfig Approval config
     * @return array<string, mixed>
     */
    private static function normalizeConfig(array|string|null $approverConfig): array
    {
        if (is_array($approverConfig)) {
            return $approverConfig;
        }
        if (is_string($approverConfig)) {
            $decoded = json_decode($approverConfig, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Convert a configured option value into the persisted decision slug.
     */
    private static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return substr($value, 0, 20);
    }
}
