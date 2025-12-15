<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * AppSetting Entity
 *
 * @property int $id
 * @property string $name
 * @property string|null $value
 */
class AppSetting extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'value' => true,
        'type' => true,
        'raw_value' => true,
        'required' => true,
    ];

    protected function _getRawValue()
    {
        switch ($this->type) {
            case 'json':
                return json_encode($this->value);
            case 'yaml':
                return yaml_emit($this->value);
            default:
                return $this->value;
        }
    }

    protected function _setRawValue($value): void
    {
        switch ($this->type) {
            case 'json':
                $this->value = json_decode($value);
            case 'yaml':
                $this->value = yaml_parse($value);
            default:
                $this->value = $value;
        }
    }

    protected function _setValue($value)
    {
        if ($this->saving) {
            return $value;
        }
        switch ($this->type) {
            case 'json':
                return json_encode($value);
            case 'yaml':
                return yaml_emit($value);
            default:
                return $value;
        }
    }

    protected function _getValue($value)
    {
        if ($this->saving) {
            return $value;
        }
        switch ($this->type) {
            case 'json':
                if (is_string($value)) {
                    return json_decode($value, true);
                }
            case 'yaml':
                if (is_string($value)) {
                    return yaml_parse($value);
                }
            default:
                return $value;
        }

        return $value;
    }

    /**
     * Get a preview of the value for display in grids
     * Shows truncated text for long values or indicates complex types
     *
     * @return string|null
     */
    protected function _getValuePreview(): ?string
    {
        $value = $this->value;

        // For complex types (yaml/json that decode to arrays), show indicator
        if (is_array($value)) {
            return '[Complex Value]';
        }

        // For null/empty values
        if ($value === null || $value === '') {
            return null;
        }

        // For string values, truncate if too long
        $stringValue = (string)$value;
        if (strlen($stringValue) > 100) {
            return substr($stringValue, 0, 100) . '...';
        }

        return $stringValue;
    }
}
