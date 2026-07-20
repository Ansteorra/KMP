<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddAutoCloseToBestowalRequiredFieldTodos extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->upsertRequirementConfig(
            'event_scheduled',
            [
                'provider' => 'Awards.BestowalGathering',
                'field' => 'gathering_id',
                'label' => 'Bestowal Gathering',
                'help' => 'Choose the gathering where this bestowal will be presented.',
                'conditional_complete_on_assign' => true,
                'auto_complete_when_satisfied' => true,
            ],
        );
        $this->upsertRequirementConfig(
            'added_to_agenda',
            [
                'provider' => 'Awards.BestowalCourtSlot',
                'field' => 'court_slot',
                'label' => 'Court Assignment',
                'help' => 'Choose Roaming Court or a scheduled court activity that can give this award.',
                'conditional_complete_on_assign' => true,
                'auto_complete_when_satisfied' => true,
            ],
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->upsertRequirementConfig(
            'event_scheduled',
            [
                'provider' => 'Awards.BestowalGathering',
                'field' => 'gathering_id',
                'label' => 'Bestowal Gathering',
                'help' => 'Choose the gathering where this bestowal will be presented.',
                'conditional_complete_on_assign' => true,
            ],
            false,
        );
        $this->upsertRequirementConfig(
            'added_to_agenda',
            [
                'provider' => 'Awards.BestowalCourtSlot',
                'field' => 'court_slot',
                'label' => 'Court Assignment',
                'help' => 'Choose Roaming Court or a scheduled court activity that can give this award.',
                'conditional_complete_on_assign' => true,
            ],
            false,
        );
    }

    /**
     * @param string $itemKey Template/action item source key.
     * @param array<string, mixed> $fieldConfig Required-field configuration.
     * @param bool $autoComplete Whether generated action items can auto-close.
     * @return void
     */
    private function upsertRequirementConfig(string $itemKey, array $fieldConfig, bool $autoComplete = true): void
    {
        $requiredConfig = json_encode($fieldConfig, JSON_THROW_ON_ERROR);
        $completionConfig = json_encode([
            'auto_complete_when_satisfied' => $autoComplete,
            'required_fields' => [
                array_diff_key($fieldConfig, ['auto_complete_when_satisfied' => true]),
            ],
        ], JSON_THROW_ON_ERROR);

        $this->execute(sprintf(
            "UPDATE awards_bestowal_todo_template_items
            SET required_field = '%s',
                required_field_config = '%s'
            WHERE item_key = '%s'
            AND deleted IS NULL",
            str_replace("'", "''", (string)$fieldConfig['field']),
            str_replace("'", "''", $requiredConfig),
            str_replace("'", "''", $itemKey),
        ));

        if ($this->table('action_items')->hasColumn('completion_config')) {
            $this->execute(sprintf(
                "UPDATE action_items
                SET completion_config = '%s'
                WHERE entity_type = 'Awards.Bestowals'
                AND source_ref = '%s'
                AND deleted IS NULL",
                str_replace("'", "''", $completionConfig),
                str_replace("'", "''", $itemKey),
            ));
        }
    }
}
