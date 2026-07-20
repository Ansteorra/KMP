<?php

return [
    'Queue' => [
        'sleeptime' => (int)env('QUEUE_SLEEP_TIME', 30),
        'gcprob' => (int)env('QUEUE_GC_PROB', 2),
        'defaultworkertimeout' => 1800,
        'workermaxruntime' => (int)env('QUEUE_WORKER_MAX_RUNTIME', 110),
        'workertimeout' => 120 * 100,
        'exitwhennothingtodo' => filter_var(env('QUEUE_EXIT_WHEN_NOTHING_TO_DO', false), FILTER_VALIDATE_BOOLEAN),
        'cleanuptimeout' => 604800,
        'maxworkers' => (int)env('QUEUE_MAX_WORKERS', 1),
        'ignoredTasks' => [],
        'plugins' => ['Queue'],
    ],
];