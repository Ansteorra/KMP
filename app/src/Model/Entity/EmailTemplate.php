<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * EmailTemplate Entity
 *
 * Templates are identified by slug. Use name/description for admin display.
 *
 * @property int $id
 * @property string|null $slug
 * @property string|null $name
 * @property string|null $description
 * @property string $subject_template
 * @property string|null $html_template
 * @property string|null $text_template
 * @property array|null $available_vars
 * @property array|null $variables_schema
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
        'slug' => true,
        'name' => true,
        'description' => true,
        'subject_template' => true,
        'html_template' => true,
        'text_template' => true,
        'available_vars' => true,
        'variables_schema' => true,
        'is_active' => true,
        'created' => false,
        'modified' => false,
    ];

    /**
     * Virtual display name: prefers name > slug.
     *
     * @return string
     */
    protected function _getDisplayName(): string
    {
        if (!empty($this->name)) {
            return $this->name;
        }

        if (!empty($this->slug)) {
            return $this->slug;
        }

        return '(unnamed template)';
    }

    /**
     * Whether this template has a workflow-native slug identity.
     *
     * @return bool
     */
    protected function _getIsWorkflowNative(): bool
    {
        return !empty($this->slug);
    }

    /**
     * Getter for available_vars — always returns an array.
     *
     * @param mixed $value
     * @return array
     */
    protected function _getAvailableVars($value): array
    {
        return $this->_normaliseAvailableVarEntries($this->_decodeJsonArray($value));
    }

    /**
     * Setter for available_vars — normalises to array before JSON storage.
     *
     * @param mixed $value
     * @return array|null
     */
    protected function _setAvailableVars($value): ?array
    {
        $decoded = $this->_normaliseJsonArray($value);
        if ($decoded === null) {
            return null;
        }

        return $this->_normaliseAvailableVarEntries($decoded);
    }

    /**
     * Getter for variables_schema — always returns an array.
     *
     * @param mixed $value
     * @return array
     */
    protected function _getVariablesSchema($value): array
    {
        return $this->_normaliseVariableSchemaEntries($this->_decodeJsonArray($value));
    }

    /**
     * Setter for variables_schema — normalises to array before JSON storage.
     *
     * @param mixed $value
     * @return array|null
     */
    protected function _setVariablesSchema($value): ?array
    {
        $decoded = $this->_normaliseJsonArray($value);
        if ($decoded === null) {
            return null;
        }

        return $this->_normaliseVariableSchemaEntries($decoded);
    }

    /**
     * Normalise blank slugs to null so legacy templates do not participate in
     * slug-based uniqueness checks.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function _setSlug($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    /**
     * Decode a JSON string or passthrough an array, returning [] on failure.
     *
     * @param mixed $value
     * @return array
     */
    private function _decodeJsonArray($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Normalise a value to an array for JSON column storage, returning null for empty input.
     *
     * @param mixed $value
     * @return array|null
     */
    private function _normaliseJsonArray($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Normalize variables_schema so both supported shapes resolve to a list of
     * entries with an explicit `name` key.
     *
     * Supported inputs:
     *   - [['name' => 'foo', 'type' => 'string']]
     *   - ['foo' => ['type' => 'string']]
     *
     * @param array $entries
     * @return array
     */
    private function _normaliseVariableSchemaEntries(array $entries): array
    {
        if (array_is_list($entries)) {
            return $entries;
        }

        $normalised = [];
        foreach ($entries as $name => $entry) {
            if (!is_string($name) || !is_array($entry)) {
                continue;
            }

            $normalised[] = ['name' => $name] + $entry;
        }

        return $normalised;
    }

    /**
     * Normalize available_vars so legacy string lists and associative maps become
     * a list of entries with an explicit `name` key.
     *
     * Supported inputs:
     *   - ['memberScaName', 'memberViewUrl']
     *   - [['name' => 'memberScaName', 'description' => '...']]
     *   - ['memberScaName' => 'Member SCA Name']
     *
     * @param array $entries
     * @return array
     */
    private function _normaliseAvailableVarEntries(array $entries): array
    {
        $normalised = [];

        if (array_is_list($entries)) {
            foreach ($entries as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $normalised[] = ['name' => $entry];
                    continue;
                }
                if (is_array($entry) && !empty($entry['name'])) {
                    $normalised[] = $entry;
                }
            }

            return $normalised;
        }

        foreach ($entries as $name => $entry) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            if (is_string($entry) && $entry !== '') {
                $normalised[] = ['name' => $name, 'description' => $entry];
                continue;
            }
            if (is_array($entry)) {
                $normalised[] = ['name' => $name] + $entry;
            }
        }

        return $normalised;
    }
}
