<?php
declare(strict_types=1);

namespace App\Log\Engine;

use App\KMP\Telemetry\SqlRedactor;
use App\Log\LogPrivacy;
use Cake\Log\Engine\FileLog;
use Stringable;

/** Sanitize before FileLog interpolates context; restrict files containing security diagnostics. */
class PrivateFileLog extends FileLog
{
    /** @inheritDoc */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $context = LogPrivacy::context($context);
        if (
            is_string($message) && (in_array($this->getConfig('file'), ['queries', 'queries.log'], true)
            || in_array('cake.database.queries', (array)($context['scope'] ?? []), true))
        ) {
            $message = SqlRedactor::redact($message);
        }
        parent::log($level, LogPrivacy::message($message), $context);
    }
}
