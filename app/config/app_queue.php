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
        'ignoredTasks' => [
            'Queue\Queue\Task\CostsExampleTask',
            'Queue\Queue\Task\EmailTask',
            'Queue\Queue\Task\ExceptionExampleTask',
            'Queue\Queue\Task\ExecuteTask',
            'Queue\Queue\Task\MonitorExampleTask',
            'Queue\Queue\Task\ProgressExampleTask',
            'Queue\Queue\Task\RetryExampleTask',
            'Queue\Queue\Task\SuperExampleTask',
            'Queue\Queue\Task\UniqueExampleTask',
        ],
    ],
];