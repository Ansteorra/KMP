<?php
declare(strict_types=1);

namespace App\Mailer\Transport;

use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Message;
use RuntimeException;

/** Fail closed at delivery without blocking unrelated tenant requests. */
class RejectedTenantMailTransport extends AbstractTransport
{
    /** Reject delivery without resolving credentials or contacting a provider. */
    public function send(Message $message): array
    {
        throw new RuntimeException(
            'Tenant email configuration requires an approved credential reference and destination.',
        );
    }
}
