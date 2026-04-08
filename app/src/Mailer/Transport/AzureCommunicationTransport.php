<?php
declare(strict_types=1);

namespace App\Mailer\Transport;

use Cake\Log\Log;
use Cake\Mailer\Message;
use RuntimeException;

/**
 * Email transport for Azure Communication Services REST API.
 *
 * Uses HMAC-SHA256 authentication derived from the connection string.
 * The API is asynchronous (returns 202 Accepted); delivery polling is
 * not needed since CakePHP's model is fire-and-forget.
 *
 * Config keys:
 *   - connectionString: Azure Communication Services connection string
 *     (format: endpoint=https://xxx.communication.azure.com/;accesskey=BASE64KEY)
 *   - apiVersion: API version (default: 2023-03-31)
 *   - timeout: HTTP timeout in seconds (default: 30)
 */
class AzureCommunicationTransport extends ApiTransport
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'connectionString' => null,
        'apiVersion' => '2023-03-31',
        'timeout' => 30,
    ];

    /**
     * Parsed endpoint URL from connection string.
     */
    private ?string $endpoint = null;

    /**
     * Decoded access key bytes from connection string.
     */
    private ?string $accessKey = null;

    /**
     * @inheritDoc
     */
    public function send(Message $message): array
    {
        $this->checkRecipient($message);
        $this->parseConnectionString();

        $payload = $this->buildPayload($message);
        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $path = '/emails:send';
        $query = 'api-version=' . $this->getConfig('apiVersion');
        $url = rtrim($this->endpoint, '/') . $path . '?' . $query;

        $headers = $this->signRequest('POST', $path . '?' . $query, $jsonBody);

        $response = $this->getHttpClient()->post($url, $jsonBody, [
            'headers' => $headers + ['Content-Type' => 'application/json'],
        ]);

        $this->assertSuccessResponse($response, 'Azure Communication Services', 202);

        Log::info(sprintf(
            'Azure Communication Services: email queued (operation-id: %s)',
            $response->getHeaderLine('operation-id') ?: 'unknown',
        ));

        return $this->buildResult($message);
    }

    /**
     * Build the Azure Email API request payload.
     *
     * @see https://learn.microsoft.com/en-us/rest/api/communication/email/send
     */
    private function buildPayload(Message $message): array
    {
        $sender = $this->getSender($message);

        $payload = [
            'senderAddress' => $sender['email'],
            'content' => [
                'subject' => $message->getOriginalSubject(),
            ],
            'recipients' => [
                'to' => $this->formatAzureRecipients($message->getTo()),
            ],
        ];

        $format = $message->getEmailFormat();
        if ($format === 'html' || $format === 'both') {
            $payload['content']['html'] = $message->getBodyHtml();
        }
        if ($format === 'text' || $format === 'both') {
            $payload['content']['plainText'] = $message->getBodyText();
        }

        $cc = $message->getCc();
        if (!empty($cc)) {
            $payload['recipients']['cc'] = $this->formatAzureRecipients($cc);
        }

        $bcc = $message->getBcc();
        if (!empty($bcc)) {
            $payload['recipients']['bcc'] = $this->formatAzureRecipients($bcc);
        }

        $replyTo = $message->getReplyTo();
        if (!empty($replyTo)) {
            $payload['replyTo'] = $this->formatAzureRecipients($replyTo);
        }

        $attachments = $this->formatAttachments($message);
        if (!empty($attachments)) {
            $payload['attachments'] = array_map(function ($att) {
                return [
                    'name' => $att['name'],
                    'contentType' => $att['contentType'],
                    'contentInBase64' => $att['content'],
                ];
            }, $attachments);
        }

        return $payload;
    }

    /**
     * Parse the Azure connection string into endpoint and access key.
     *
     * @throws \RuntimeException If the connection string is missing or invalid.
     */
    private function parseConnectionString(): void
    {
        if ($this->endpoint !== null) {
            return;
        }

        $connStr = $this->getConfig('connectionString');
        if (empty($connStr)) {
            throw new RuntimeException(
                'AzureCommunicationTransport: connectionString config is required. '
                . 'Set AZURE_COMMUNICATION_CONNECTION_STRING environment variable.',
            );
        }

        $parts = [];
        foreach (explode(';', $connStr) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $pos = strpos($segment, '=');
            if ($pos !== false) {
                $key = strtolower(substr($segment, 0, $pos));
                $value = substr($segment, $pos + 1);
                $parts[$key] = $value;
            }
        }

        if (empty($parts['endpoint'])) {
            throw new RuntimeException(
                'AzureCommunicationTransport: connection string missing "endpoint" component.',
            );
        }
        if (empty($parts['accesskey'])) {
            throw new RuntimeException(
                'AzureCommunicationTransport: connection string missing "accesskey" component.',
            );
        }

        $this->endpoint = rtrim($parts['endpoint'], '/');
        $this->accessKey = $parts['accesskey'];
    }

    /**
     * Convert addresses to Azure's recipient format (address + displayName).
     *
     * @param array<string, string> $addresses CakePHP address map
     * @return array<int, array{address: string, displayName: string}>
     */
    private function formatAzureRecipients(array $addresses): array
    {
        $recipients = [];
        foreach ($addresses as $email => $name) {
            $recipients[] = [
                'address' => $email,
                'displayName' => $name !== $email ? $name : '',
            ];
        }

        return $recipients;
    }

    /**
     * Generate HMAC-SHA256 authentication headers for the Azure REST API.
     *
     * @param string $method HTTP method (POST, GET, etc.)
     * @param string $pathAndQuery URL path + query string
     * @param string $body Request body
     * @return array<string, string> Headers to include in the request
     * @see https://learn.microsoft.com/en-us/rest/api/communication/authentication
     */
    private function signRequest(string $method, string $pathAndQuery, string $body): array
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $utcNow = gmdate('D, d M Y H:i:s') . ' GMT';
        $contentHash = base64_encode(hash('sha256', $body, true));

        $stringToSign = strtoupper($method) . "\n"
            . $pathAndQuery . "\n"
            . $utcNow . ';' . $host . ';' . $contentHash;

        $decodedKey = base64_decode($this->accessKey);
        $signature = base64_encode(
            hash_hmac('sha256', $stringToSign, $decodedKey, true),
        );

        return [
            'x-ms-date' => $utcNow,
            'x-ms-content-sha256' => $contentHash,
            'Authorization' => sprintf(
                'HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=%s',
                $signature,
            ),
        ];
    }
}
