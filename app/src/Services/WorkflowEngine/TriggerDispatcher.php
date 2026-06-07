<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\Log\Log;
use Throwable;

/**
 * Dispatches trigger events to find and start matching workflows.
 *
 * Supports two dispatch patterns:
 *  1. Explicit: call `dispatch()` directly from service/controller code.
 *  2. Event-driven: call `attachToEventManager()` during bootstrap, then fire
 *     CakePHP events with subject 'Workflow.trigger' and data keys
 *     'eventName', 'eventData', 'triggeredBy'.
 *
 * The explicit pattern is preferred for clarity and testability.
 * Event-driven dispatch is useful for decoupling plugins that don't want
 * a direct dependency on TriggerDispatcher.
 */
class TriggerDispatcher implements EventListenerInterface
{
    /**
     * Tracks automatic listener registration for event-driven model triggers.
     *
     * @var self|null
     */
    private static ?self $eventManagerListener = null;

    private WorkflowEngineInterface $engine;

    /**
     * Constructor.
     *
     * @param \App\Services\WorkflowEngine\WorkflowEngineInterface $engine Workflow engine
     */
    public function __construct(WorkflowEngineInterface $engine)
    {
        $this->engine = $engine;

        if (self::$eventManagerListener !== $this) {
            if (self::$eventManagerListener !== null) {
                EventManager::instance()->off(self::$eventManagerListener);
            }

            $this->attachToEventManager();
            self::$eventManagerListener = $this;
        }
    }

    /**
     * Return the engine backing this dispatcher for follow-up workflow operations.
     *
     * @return \App\Services\WorkflowEngine\WorkflowEngineInterface
     */
    public function getEngine(): WorkflowEngineInterface
    {
        return $this->engine;
    }

    /**
     * Register this dispatcher as a CakePHP event listener.
     *
     * Call during Application::bootstrap() or plugin bootstrap to enable
     * automatic workflow dispatch from CakePHP events:
     *
     *   $triggerDispatcher->attachToEventManager();
     *
     * Then fire events anywhere:
     *   EventManager::instance()->dispatch(new Event('Workflow.trigger', $this, [
     *       'eventName' => 'Officers.HireRequested',
     *       'eventData' => ['officerId' => 42],
     *       'triggeredBy' => $memberId,
     *   ]));
     */
    public function attachToEventManager(?EventManager $eventManager = null): void
    {
        $manager = $eventManager ?? EventManager::instance();
        $manager->on($this);
    }

    /**
     * CakePHP EventListenerInterface — events this listener handles.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Workflow.trigger' => 'handleWorkflowEvent',
        ];
    }

    /**
     * Handle a CakePHP event by dispatching to the workflow engine.
     */
    public function handleWorkflowEvent(EventInterface $event): void
    {
        $data = (array)$event->getData();
        $eventName = $data['eventName'] ?? $event->getName();
        $eventData = $data['eventData'] ?? [];
        $triggeredBy = $data['triggeredBy'] ?? null;

        $this->dispatch($eventName, $eventData, $triggeredBy);
    }

    /**
     * Dispatch a trigger event to find and start matching workflows.
     *
     * @param string $eventName Event identifier (e.g., 'Officers.HireRequested')
     * @param array $eventData Data associated with the event
     * @param int|null $triggeredBy Member who triggered
     * @return array Array of ServiceResult from started workflows
     */
    public function dispatch(string $eventName, array $eventData = [], ?int $triggeredBy = null): array
    {
        try {
            return $this->engine->dispatchTrigger($eventName, $eventData, $triggeredBy);
        } catch (Throwable $e) {
            Log::error("TriggerDispatcher failed for {$eventName}: " . $e->getMessage());

            return [];
        }
    }
}
