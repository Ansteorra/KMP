<?php
declare(strict_types=1);

namespace App\Services\Storage;

use AzureOss\Storage\Common\Auth\AccessToken;
use AzureOss\Storage\Common\Auth\TokenCredential;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use RuntimeException;

/**
 * Token credential for Azure managed identity in App Service/Container Apps or IMDS.
 */
class AzureManagedIdentityTokenCredential implements TokenCredential
{
    private const RESOURCE = 'https://storage.azure.com/';

    private Client $client;

    /**
     * Constructor.
     *
     * @param string|null $clientId User-assigned managed identity client ID
     * @param \GuzzleHttp\Client|null $client HTTP client
     */
    public function __construct(private readonly ?string $clientId = null, ?Client $client = null)
    {
        $this->client = $client ?? new Client(['timeout' => 5]);
    }

    /**
     * Fetch an access token for Azure Storage.
     *
     * @return \AzureOss\Storage\Common\Auth\AccessToken
     */
    public function getToken(): AccessToken
    {
        $endpoint = getenv('IDENTITY_ENDPOINT') ?: getenv('MSI_ENDPOINT') ?: null;
        if (is_string($endpoint) && $endpoint !== '') {
            return $this->getTokenFromAppServiceEndpoint($endpoint);
        }

        return $this->getTokenFromImds();
    }

    /**
     * Fetch a token from App Service/Container Apps managed identity endpoint.
     *
     * @param string $endpoint Identity endpoint URL
     * @return \AzureOss\Storage\Common\Auth\AccessToken
     */
    private function getTokenFromAppServiceEndpoint(string $endpoint): AccessToken
    {
        $headers = ['Metadata' => 'true'];
        $identityHeader = getenv('IDENTITY_HEADER') ?: getenv('MSI_SECRET') ?: null;
        if (is_string($identityHeader) && $identityHeader !== '') {
            $headers['X-IDENTITY-HEADER'] = $identityHeader;
            $headers['secret'] = $identityHeader;
        }

        $query = [
            'api-version' => '2019-08-01',
            'resource' => self::RESOURCE,
        ];
        if ($this->clientId !== null && $this->clientId !== '') {
            $query['client_id'] = $this->clientId;
        }

        return $this->requestToken($endpoint, $headers, $query);
    }

    /**
     * Fetch a token from the Azure Instance Metadata Service endpoint.
     *
     * @return \AzureOss\Storage\Common\Auth\AccessToken
     */
    private function getTokenFromImds(): AccessToken
    {
        $query = [
            'api-version' => '2018-02-01',
            'resource' => self::RESOURCE,
        ];
        if ($this->clientId !== null && $this->clientId !== '') {
            $query['client_id'] = $this->clientId;
        }

        return $this->requestToken(
            'http://169.254.169.254/metadata/identity/oauth2/token',
            ['Metadata' => 'true'],
            $query,
        );
    }

    /**
     * @param array<string, string> $headers Request headers
     * @param array<string, string> $query Query parameters
     * @return \AzureOss\Storage\Common\Auth\AccessToken
     */
    private function requestToken(string $endpoint, array $headers, array $query): AccessToken
    {
        $response = $this->client->get($endpoint, [
            RequestOptions::HEADERS => $headers,
            RequestOptions::QUERY => $query,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        if (
            !is_array($data) ||
            !isset($data['access_token']) ||
            !is_string($data['access_token'])
        ) {
            throw new RuntimeException('Unexpected response from Azure managed identity endpoint.');
        }

        $expiresOn = $data['expires_on'] ?? $data['expiresOn'] ?? null;
        if (is_numeric($expiresOn)) {
            $expiresAt = (new DateTimeImmutable())->setTimestamp((int)$expiresOn);
        } elseif (is_string($expiresOn) && $expiresOn !== '') {
            $expiresAt = new DateTimeImmutable($expiresOn);
        } else {
            throw new RuntimeException('Azure managed identity response did not include token expiry.');
        }

        return new AccessToken($data['access_token'], $expiresAt);
    }
}
