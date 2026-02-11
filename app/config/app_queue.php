<?php

return [
    'Queue' => [
        'sleeptime' => 10,
        'gcprob' => 10,
        'defaultworkertimeout' => 1800,
        'workermaxruntime' => 110,
        'workertimeout' => 120 * 100,
        'exitwhennothingtodo' => false,
        'cleanuptimeout' => 604800,
        'maxworkers' => 1,
        'ignoredTasks' => [],
    ],
];