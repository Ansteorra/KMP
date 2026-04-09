<?php
declare(strict_types=1);

namespace App\Mailer\Transport;

use Cake\Mailer\Message;
use RuntimeException;

/**
 * Email transport for the SendGrid v3 Mail Send API.
 *
 * Config keys:
 *   - apiKey: SendGrid API key (starts with "SG.")
 *   - endpoint: API URL (default: https://api.sendgrid.com/v3/mail/send)
 *   - timeout: HTTP timeout in seconds (default: 30)
 *
 * @see https://docs.sendgrid.com/api-reference/mail-send/mail-send
 */
class SendGridApiTransport extends ApiTransport
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'apiKey' => null,
        'endpoint' => 'https://api.sendgrid.com/v3/mail/send',
        'timeout' => 30,
    ];

    /**
     * @inheritDoc
     */
    public function send(Message $message): array
    {
        $this->checkRecipient($message);

        $apiKey = $this->getConfig('apiKey');
        if (empty($apiKey)) {
            throw new RuntimeException(
                'SendGridApiTransport: apiKey config is required. Set EMAIL_API_KEY environment variable.',
            );
        }

        $payload = $this->buildPayload($message);
        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $response = $this->getHttpClient()->post(
            $this->getConfig('endpoint'),
            $jsonBody,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
            ],
        );

        // SendGrid returns 202 Accepted on success
        $this->assertSuccessResponse($response, 'SendGrid', 200, 202);

        return $this->buildResult($message);
    }

    /**
     * Build the SendGrid v3 mail/send request payload.
     */
    private function buildPayload(Message $message): array
    {
        $sender = $this->getSender($message);

        $payload = [
            'from' => ['email' => $sender['email']],
            'subject' => $message->getOriginalSubject(),
            'personalizations' => [
                [
                    'to' => $this->formatSendGridAddresses($message->getTo()),
                ],
            ],
            'content' => [],
        ];

        if (!empty($sender['name'])) {
            $payload['from']['name'] = $sender['name'];
        }

        $cc = $message->getCc();
        if (!empty($cc)) {
            $payload['personalizations'][0]['cc'] = $this->formatSendGridAddresses($cc);
        }

        $bcc = $message->getBcc();
        if (!empty($bcc)) {
            $payload['personalizations'][0]['bcc'] = $this->formatSendGridAddresses($bcc);
        }

        $replyTo = $message->getReplyTo();
        if (!empty($replyTo)) {
            $email = array_key_first($replyTo);
            $payload['reply_to'] = ['email' => $email];
            if ($replyTo[$email] !== $email) {
                $payload['reply_to']['name'] = $replyTo[$email];
            }
        }

        $format = $message->getEmailFormat();
        if ($format === 'text' || $format === 'both') {
            $payload['content'][] = [
                'type' => 'text/plain',
                'value' => $message->getBodyText(),
            ];
        }
        if ($format === 'html' || $format === 'both') {
            $payload['content'][] = [
                'type' => 'text/html',
                'value' => $message->getBodyHtml(),
            ];
        }

        $attachments = $this->formatAttachments($message);
        if (!empty($attachments)) {
            $payload['attachments'] = array_map(function ($att) {
                return [
                    'content' => $att['content'],
                    'filename' => $att['name'],
                    'type' => $att['contentType'],
                    'disposition' => 'attachment',
                ];
            }, $attachments);
        }

        return $payload;
    }

    /**
     * Format addresses for SendGrid's expected structure.
     *
     * @param array<string, string> $addresses CakePHP address format
     * @return array<int, array{email: string, name?: string}>
     */
    private function formatSendGridAddresses(array $addresses): array
    {
        $result = [];
        foreach ($addresses as $email => $name) {
            $entry = ['email' => $email];
            if ($name !== $email) {
                $entry['name'] = $name;
            }
            $result[] = $entry;
        }

        return $result;
    }
}
