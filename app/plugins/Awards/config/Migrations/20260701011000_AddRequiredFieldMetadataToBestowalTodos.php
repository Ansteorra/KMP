<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add required-field metadata to bestowal to-do template items.
 *
 * The default Event Scheduled check requires a bestowal gathering assignment
 * before completion, and existing materialized action items receive the same
 * completion metadata without requiring a data reseed.
 */
class AddRequiredFieldMetadataToBestowalTodos extends BaseMigration
{
    private const GATHERING_REQUIREMENT_CONFIG = [
        'provider' => 'Awards.BestowalGathering',
        'field' => 'gathering_id',
        'label' => 'Bestowal Gathering',
        'help' => 'Choose the future event or court where this bestowal will be presented.',
        'conditional_complete_on_assign' => true,
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $items = $this->table('awards_bestowal_todo_template_items');
        if (!$items->hasColumn('required_field')) {
            $items
                ->addColumn('required_field', 'string', [
                    'after' => 'is_gating',
                    'default' => null,
                    'limit' => 100,
                    'null' => true,
                    'comment' => 'Field required before this check can be completed',
                ])
                ->addColumn('required_field_config', 'json', [
                    'after' => 'required_field',
                    'default' => null,
                    'null' => true,
                    'comment' => 'Provider metadata for completing the required field',
                ])
                ->update();
        } elseif (!$items->hasColumn('required_field_config')) {
            $items
                ->addColumn('required_field_config', 'json', [
                    'after' => 'required_field',
                    'default' => null,
                    'null' => true,
                    'comment' => 'Provider metadata for completing the required field',
                ])
                ->update();
        }

        $this->backfillEventScheduledRequirement();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $actionItems = $this->table('action_items');
        if ($actionItems->hasColumn('completion_config')) {
            $this->execute(
                "UPDATE action_items
                SET completion_config = NULL
                WHERE entity_type = 'Awards.Bestowals'
                AND source_ref = 'event_scheduled'",
            );
        }

        $items = $this->table('awards_bestowal_todo_template_items');
        if ($items->hasColumn('required_field_config')) {
            $items->removeColumn('required_field_config');
        }
        if ($items->hasColumn('required_field')) {
            $items->removeColumn('required_field');
        }
        $items->update();
    }

    /**
     * @return void
     */
    private function backfillEventScheduledRequirement(): void
    {
        $requiredConfig = json_encode(self::GATHERING_REQUIREMENT_CONFIG, JSON_THROW_ON_ERROR);
        $completionConfig = json_encode([
            'required_fields' => [
                self::GATHERING_REQUIREMENT_CONFIG,
            ],
        ], JSON_THROW_ON_ERROR);

        $this->execute(sprintf(
            "UPDATE awards_bestowal_todo_template_items
            SET required_field = 'gathering_id',
                required_field_config = '%s'
            WHERE item_key = 'event_scheduled'
            AND deleted IS NULL",
            str_replace("'", "''", $requiredConfig),
        ));

        if ($this->table('action_items')->hasColumn('completion_config')) {
            $this->execute(sprintf(
                "UPDATE action_items
                SET completion_config = '%s'
                WHERE entity_type = 'Awards.Bestowals'
                AND source_ref = 'event_scheduled'
                AND deleted IS NULL",
                str_replace("'", "''", $completionConfig),
            ));
        }
    }
}
