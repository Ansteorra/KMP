#!/usr/bin/env php
<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

// Load the application
require dirname(__FILE__) . '/config/bootstrap.php';

use App\Mailer\KMPMailer;
use Cake\Mailer\TransportFactory;
use Cake\Log\Log;

echo "Setting up Debug transport...\n";
TransportFactory::drop('default');
TransportFactory::setConfig('default', [
    'className' => 'Debug',
]);

echo "Creating KMPMailer instance...\n";
$mailer = new KMPMailer();
$mailer->setTransport('default');

echo "Sending resetPassword email...\n";
try {
    $result = $mailer->send('resetPassword', [
        'admin@example.com',
        'https://example.com/reset/token123'
    ]);

    echo "\n✓ Email sent successfully!\n\n";

    // The debug transport doesn't return a message, it just logs
    // Let's check what was set on the mailer
    echo "=== Email Details ===\n";
    echo "To: " . implode(', ', array_keys($mailer->getTo())) . "\n";
    echo "From: " . implode(', ', array_keys($mailer->getFrom())) . "\n";
    echo "Subject: " . $mailer->getSubject() . "\n";
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Log Contents ===\n";
$logContents = file_get_contents('logs/debug.log');
if (empty($logContents)) {
    echo "(empty)\n";
} else {
    echo $logContents;
}
