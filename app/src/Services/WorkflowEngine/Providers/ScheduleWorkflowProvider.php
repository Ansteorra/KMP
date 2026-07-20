<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine\Providers;

use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;

/**
 * Registers the schedule trigger type with the workflow trigger registry.
 *
 * Makes scheduled triggers available in the workflow designer palette.
 */
class ScheduleWorkflowProvider
{
    private const SOURCE = 'Schedule';

    /**
     * Register all schedule workflow components.
     *
     * @return void
     */
    public static function register(): void
    {
        self::registerTriggers();
    }

    /**
     * @return void
     */
    private static function registerTriggers(): void
    {
        WorkflowTriggerRegistry::register(self::SOURCE, [
            [
                'event' => 'Schedule.CronTriggered',
                'label' => 'Scheduled (Cron)',
                'description' => 'Trigger workflow on a cron schedule (e.g., daily, hourly)',
                'payloadSchema' => [
                    'schedule' => [
                        'type' => 'string',
                        'label' => 'Cron Expression',
                        'required' => true,
                        'description' => 'Cron expression (e.g., "0 2 * * *" for daily at 2 AM)',
                    ],
                    'entityType' => [
                        'type' => 'string',
                        'label' => 'Entity Type',
                        'description' => 'CakePHP table alias for the entity to query',
                    ],
                    'entityQuery' => [
                        'type' => 'object',
                        'label' => 'Entity Query Conditions',
                        'description' => 'CakePHP where-clause array to find matching entities',
                    ],
                    'description' => [
                        'type' => 'string',
                        'label' => 'Schedule Description',
                        'description' => 'Human-readable description of the schedule',
                    ],
                    'scheduledAt' => [
                        'type' => 'datetime',
                        'label' => 'Scheduled At',
                        'description' => 'When this scheduled trigger fired (auto-populated)',
                    ],
                    'workflowDefinitionId' => [
                        'type' => 'integer',
                        'label' => 'Workflow Definition ID',
                        'description' => 'The workflow definition that was triggered (auto-populated)',
                    ],
                ],
            ],
        ]);
    }
}
