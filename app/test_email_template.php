<?php

declare(strict_types=1);

// Simulate a password reset email send
require 'config/bootstrap.php';

use App\Mailer\KMPMailer;
use Cake\Log\Log;
use Cake\Mailer\TransportFactory;

// Clear the log
file_put_contents('logs/debug.log', '');

echo "Configuring Debug transport...\n";
TransportFactory::drop('default');
TransportFactory::setConfig('default', [
    'className' => 'Debug',
]);

echo "Creating mailer...\n";
$mailer = new KMPMailer();
$mailer->setTransport('default');

echo "Calling resetPassword method...\n";
try {
    // Call the mailer method directly with proper arguments
    $result = $mailer->send('resetPassword', [
        'test@example.com',
        'https://example.com/reset/token123'
    ]);

    echo "Email sent successfully!\n\n";

    // Show debug output
    echo "=== Debug Transport Output ===\n";
    echo "Headers:\n";
    print_r($result->getHeaders());
    echo "\nBody (HTML):\n";
    echo $result->getBodyHtml() . "\n";
    echo "\nBody (Text):\n";
    echo $result->getBodyText() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Log ===\n";
$logs = file_get_contents('logs/debug.log');
echo $logs;
