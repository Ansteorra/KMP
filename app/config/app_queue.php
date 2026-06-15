<?php

return [
    'Queue' => [
        'sleeptime' => 10,
        'gcprob' => 10,
        'defaultworkertimeout' => 1800,
        'workermaxruntime' => 110,
        'workertimeout' => 120 * 100,
        'exitwhennothingtodo' => filter_var(env('QUEUE_EXIT_WHEN_NOTHING_TO_DO', false), FILTER_VALIDATE_BOOLEAN),
        'cleanuptimeout' => 604800,
        'maxworkers' => (int)env('QUEUE_MAX_WORKERS', 1),
        'ignoredTasks' => [],
    ],
];