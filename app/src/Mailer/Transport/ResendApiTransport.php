<?php
declare(strict_types=1);

namespace App\Mailer\Transport;

use Cake\Mailer\Message;
use RuntimeException;

/**
 * Email transport for the Resend API.
 *
 * Config keys:
 *   - apiKey: Resend API key (starts with "re_")
 *   - endpoint: API URL (default: https://api.resend.com/emails)
 *   - timeout: HTTP timeout in seconds (default: 30)
 *
 * @see https://resend.com/docs/api-reference/emails/send-email
 */
class ResendApiTransport extends ApiTransport
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'apiKey' => null,
        'endpoint' => 'https://api.resend.com/emails',
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
                'ResendApiTransport: apiKey config is required. Set EMAIL_API_KEY environment variable.',
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

        // Resend returns 200 on success
        $this->assertSuccessResponse($response, 'Resend', 200);

        return $this->buildResult($message);
    }

    /**
     * Build the Resend API request payload.
     */
    private function buildPayload(Message $message): array
    {
        $sender = $this->getSender($message);
        $fromStr = !empty($sender['name'])
            ? sprintf('%s <%s>', $sender['name'], $sender['email'])
            : $sender['email'];

        $payload = [
            'from' => $fromStr,
            'to' => array_keys($message->getTo()),
            'subject' => $message->getOriginalSubject(),
        ];

        $cc = $message->getCc();
        if (!empty($cc)) {
            $payload['cc'] = array_keys($cc);
        }

        $bcc = $message->getBcc();
        if (!empty($bcc)) {
            $payload['bcc'] = array_keys($bcc);
        }

        $replyTo = $message->getReplyTo();
        if (!empty($replyTo)) {
            $payload['reply_to'] = array_key_first($replyTo);
        }

        $format = $message->getEmailFormat();
        if ($format === 'html' || $format === 'both') {
            $payload['html'] = $message->getBodyHtml();
        }
        if ($format === 'text' || $format === 'both') {
            $payload['text'] = $message->getBodyText();
        }

        $attachments = $this->formatAttachments($message);
        if (!empty($attachments)) {
            $payload['attachments'] = array_map(function ($att) {
                return [
                    'filename' => $att['name'],
                    'content' => $att['content'],
                    'content_type' => $att['contentType'],
                ];
            }, $attachments);
        }

        return $payload;
    }
}
