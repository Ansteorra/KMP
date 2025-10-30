<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * EmailTemplate Entity
 *
 * @property int $id
 * @property string $mailer_class
 * @property string $action_method
 * @property string $subject_template
 * @property string|null $html_template
 * @property string|null $text_template
 * @property array|null $available_vars
 * @property bool $is_active
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class EmailTemplate extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'mailer_class' => true,
        'action_method' => true,
        'subject_template' => true,
        'html_template' => true,
        'text_template' => true,
        'available_vars' => true,
        'is_active' => true,
        'created' => false,
        'modified' => false,
    ];

    /**
     * Virtual field for display name
     *
     * @return string
     */
    protected function _getDisplayName(): string
    {
        $className = substr(strrchr($this->mailer_class, '\\'), 1);

        return $className . '::' . $this->action_method . '()';
    }

    /**
     * Getter for available_vars to ensure it's always an array
     *
     * @param mixed $value
     * @return array
     */
    protected function _getAvailableVars($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        // Shouldn't happen with JSON type, but handle string just in case
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Setter for available_vars to ensure proper format
     * The JSON database type will handle the actual JSON encoding
     *
     * @param mixed $value
     * @return array|null
     */
    protected function _setAvailableVars($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        // If it's a JSON string, decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
