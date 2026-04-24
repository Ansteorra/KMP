<?php
declare(strict_types=1);

namespace App\Mailer\Transport;

use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\Log\Log;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Message;
use RuntimeException;

/**
 * Abstract base for HTTP API-based email transports.
 *
 * Provides shared helpers for extracting data from CakePHP Message objects
 * and sending HTTP requests. Concrete subclasses implement the provider-specific
 * API payload and authentication.
 */
abstract class ApiTransport extends AbstractTransport
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'timeout' => 30,
    ];

    protected ?Client $httpClient = null;

    /**
     * Get or create the HTTP client instance.
     */
    protected function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'timeout' => $this->getConfig('timeout', 30),
            ]);
        }

        return $this->httpClient;
    }

    /**
     * Set a custom HTTP client (useful for testing).
     */
    public function setHttpClient(Client $client): static
    {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * Convert Message address array to a flat list of recipient objects.
     *
     * CakePHP stores addresses as ['email@example.com' => 'Display Name'].
     *
     * @param array<string, string> $addresses
     * @return array<int, array{email: string, name: string}>
     */
    protected function formatRecipients(array $addresses): array
    {
        $recipients = [];
        foreach ($addresses as $email => $name) {
            $recipients[] = [
                'email' => $email,
                'name' => $name !== $email ? $name : '',
            ];
        }

        return $recipients;
    }

    /**
     * Validate that the message has at least one recipient.
     *
     * @param \Cake\Mailer\Message $message The email message.
     * @throws \RuntimeException If no recipients are set.
     */
    protected function checkRecipient(Message $message): void
    {
        if (empty($message->getTo()) && empty($message->getCc()) && empty($message->getBcc())) {
            throw new RuntimeException('Email message has no recipients (to, cc, or bcc).');
        }
    }

    /**
     * Get the sender address from a Message.
     *
     * @param \Cake\Mailer\Message $message The email message.
     * @return array{email: string, name: string}
     * @throws \RuntimeException If no sender is set.
     */
    protected function getSender(Message $message): array
    {
        $from = $message->getFrom();
        if (empty($from)) {
            throw new RuntimeException('Email message has no sender (from) address.');
        }
        $email = array_key_first($from);

        return [
            'email' => $email,
            'name' => $from[$email] !== $email ? $from[$email] : '',
        ];
    }

    /**
     * Build the standard return value for send().
     *
     * @return array{headers: string, message: string}
     */
    protected function buildResult(Message $message): array
    {
        return [
            'headers' => $message->getHeadersString(
                ['from', 'sender', 'replyTo', 'readReceipt', 'returnPath', 'to', 'cc', 'subject'],
            ),
            'message' => $message->getBodyString(),
        ];
    }

    /**
     * Convert Message attachments to a provider-neutral format.
     *
     * @return array<int, array{name: string, contentType: string, content: string}>
     */
    protected function formatAttachments(Message $message): array
    {
        $formatted = [];
        foreach ($message->getAttachments() as $name => $attachment) {
            if (isset($attachment['data'])) {
                $formatted[] = [
                    'name' => $name,
                    'contentType' => $attachment['mimetype'] ?? 'application/octet-stream',
                    'content' => base64_encode($attachment['data']),
                ];
            } elseif (isset($attachment['file'])) {
                $data = file_get_contents($attachment['file']);
                if ($data !== false) {
                    $formatted[] = [
                        'name' => $name,
                        'contentType' => $attachment['mimetype'] ?? 'application/octet-stream',
                        'content' => base64_encode($data),
                    ];
                }
            }
        }

        return $formatted;
    }

    /**
     * Assert a successful HTTP response or throw.
     *
     * @param \Cake\Http\Client\Response $response The HTTP response.
     * @param string $provider Provider name for error messages.
     * @param int ...$acceptedCodes HTTP status codes considered successful.
     * @throws \RuntimeException On non-success response.
     */
    protected function assertSuccessResponse(Response $response, string $provider, int ...$acceptedCodes): void
    {
        if (!in_array($response->getStatusCode(), $acceptedCodes, true)) {
            $body = $response->getStringBody();
            Log::error(sprintf(
                '%s email API error (HTTP %d): %s',
                $provider,
                $response->getStatusCode(),
                $body,
            ));
            throw new RuntimeException(sprintf(
                '%s email API returned HTTP %d: %s',
                $provider,
                $response->getStatusCode(),
                mb_substr($body, 0, 500),
            ));
        }
    }
}
