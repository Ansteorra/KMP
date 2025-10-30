<?php

declare(strict_types=1);

use App\Application;
use Cake\Http\Server;

// for built-in server
if (php_sapi_name() === 'cli-server') {
    $_SERVER['PHP_SELF'] = '/' . basename(__FILE__);

    $url = parse_url(urldecode($_SERVER['REQUEST_URI']));
    $file = __DIR__ . $url['path'];
    if (strpos($url['path'], '..') === false && strpos($url['path'], '.') !== false && is_file($file)) {
        return false;
    }
}
require __DIR__ . '/vendor/autoload.php';

use App\Mailer\KMPMailer;
use Cake\Core\Configure;
use Cake\Mailer\TransportFactory;
use Cake\Log\Log;

// Bootstrap the application
$server = new Server(new Application(__DIR__ . '/config'));

// Configure debug transport
TransportFactory::drop('default');
TransportFactory::setConfig('default', [
    'className' => 'Debug',
]);

// Clear the log first
file_put_contents('logs/debug.log', '');

echo "Sending password reset email...\n";

try {
    $mailer = new KMPMailer();
    $mailer->setTransport('default');

    $result = $mailer->resetPassword(
        'test@example.com',
        'https://example.com/reset/token123'
    );

    echo "Email queued successfully!\n";
    echo "\nCheck logs/debug.log for details\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n--- Recent Debug Logs ---\n";
$logs = file_get_contents('logs/debug.log');
if (empty($logs)) {
    echo "No debug logs found\n";
} else {
    echo $logs;
}
