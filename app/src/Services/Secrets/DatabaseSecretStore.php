<?php
declare(strict_types=1);

namespace App\Services\Secrets;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Text;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

class DatabaseSecretStore implements WritableSecretStoreInterface
{
    private const BINARY_ENCODING_PREFIX = 'base64:';
    private const DRIVER_NAME = 'database';
    private const SODIUM_CIPHER = 'xchacha20poly1305-ietf';
    private const OPENSSL_CIPHER = 'aes-256-gcm';

    /**
     * Constructor.
     *
     * @param \App\Services\Secrets\SecretStoreInterface $masterStore Store that provides the master KEK
     * @param string $masterKeyName Master KEK secret name
     * @param string $connectionName CakePHP connection name for platform metadata
     * @param string $namespace Logical secret namespace
     * @param string|null $tenantId Optional tenant scope
     * @param string $keyName Logical KEK name recorded with encrypted rows
     * @param string $keyVersion Logical KEK version recorded with encrypted rows
     * @param \Cake\Database\Connection|null $connection Optional injected connection for tests
     */
    public function __construct(
        private readonly SecretStoreInterface $masterStore,
        private readonly string $masterKeyName,
        private readonly string $connectionName = 'platform',
        private readonly string $namespace = 'platform',
        private readonly ?string $tenantId = null,
        private readonly string $keyName = 'platform-secrets',
        private readonly string $keyVersion = 'v1',
        private ?Connection $connection = null,
    ) {
        if ($this->masterStore instanceof self) {
            throw new InvalidArgumentException('DatabaseSecretStore cannot use itself as its master key store.');
        }
        $required = [
            'masterKeyName' => $this->masterKeyName,
            'namespace' => $this->namespace,
            'keyName' => $this->keyName,
            'keyVersion' => $this->keyVersion,
        ];
        foreach ($required as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('DatabaseSecretStore %s cannot be empty.', $field));
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $name): ?SensitiveString
    {
        $row = $this->activeRow($name);
        if ($row === null) {
            return null;
        }

        $aad = $this->associatedData($row);
        if (!hash_equals((string)$row['associated_data_hash'], hash('sha256', $aad))) {
            throw new RuntimeException(sprintf('Secret metadata authentication failed for "%s".', $name));
        }

        $dek = $this->decryptWithKey(
            (string)$row['dek_cipher'],
            $this->decodeBinaryPayload($row['dek_nonce']),
            $this->decodeBinaryPayload($row['dek_tag'] ?? ''),
            $this->decodeBinaryPayload($row['wrapped_dek']),
            $this->dekAssociatedData($aad),
            $this->masterKey(),
        );
        $plaintext = $this->decryptWithKey(
            (string)$row['cipher'],
            $this->decodeBinaryPayload($row['nonce']),
            $this->decodeBinaryPayload($row['tag'] ?? ''),
            $this->decodeBinaryPayload($row['ciphertext']),
            $aad,
            $dek,
        );

        return new SensitiveString($plaintext);
    }

    /**
     * @inheritDoc
     */
    public function put(string $name, SensitiveString $value): void
    {
        $this->assertValidName($name);
        $now = $this->now();
        $this->platform()->transactional(function () use ($name, $value, $now): void {
            $this->ensureKekRow($now);
            $existing = $this->rowByName($name);
            $id = $existing !== null ? (string)$existing['id'] : Text::uuid();
            $base = [
                'id' => $id,
                'tenant_id' => $this->tenantId,
                'name' => $name,
                'namespace' => $this->namespace,
                'key_name' => $this->keyName,
                'key_version' => $this->keyVersion,
            ];
            $aad = $this->associatedData($base);
            $dek = random_bytes(32);
            $encrypted = $this->encryptWithKey($value->reveal(), $aad, $dek);
            $wrappedDek = $this->encryptWithKey($dek, $this->dekAssociatedData($aad), $this->masterKey());
            $data = $base + [
                'dek_cipher' => $wrappedDek['cipher'],
                'dek_nonce' => $this->encodeBinaryPayload($wrappedDek['nonce']),
                'dek_tag' => $wrappedDek['tag'] === null ? null : $this->encodeBinaryPayload($wrappedDek['tag']),
                'wrapped_dek' => $this->encodeBinaryPayload($wrappedDek['ciphertext']),
                'cipher' => $encrypted['cipher'],
                'nonce' => $this->encodeBinaryPayload($encrypted['nonce']),
                'tag' => $encrypted['tag'] === null ? null : $this->encodeBinaryPayload($encrypted['tag']),
                'ciphertext' => $this->encodeBinaryPayload($encrypted['ciphertext']),
                'associated_data_hash' => hash('sha256', $aad),
                'status' => 'active',
                'modified_at' => $now,
                'rotated_at' => $now,
                'deleted_at' => null,
            ];

            if ($existing === null) {
                $data['created_at'] = $now;
                $this->platform()->insert('platform_secret_values', $data);
            } else {
                $this->platform()->update('platform_secret_values', $data, ['id' => $id]);
            }
            $this->upsertIndexRow($name, $now);
        });
    }

    /**
     * @inheritDoc
     */
    public function delete(string $name): void
    {
        $now = $this->now();
        $this->platform()->transactional(function () use ($name, $now): void {
            $row = $this->activeRow($name);
            if ($row === null) {
                return;
            }
            $this->platform()->update('platform_secret_values', [
                'status' => 'deleted',
                'wrapped_dek' => $this->encodeBinaryPayload(random_bytes(32)),
                'dek_nonce' => $this->encodeBinaryPayload(random_bytes($this->nonceLength((string)$row['dek_cipher']))),
                'dek_tag' => null,
                'ciphertext' => $this->encodeBinaryPayload(random_bytes(32)),
                'nonce' => $this->encodeBinaryPayload(random_bytes($this->nonceLength((string)$row['cipher']))),
                'tag' => null,
                'associated_data_hash' => hash('sha256', random_bytes(32)),
                'modified_at' => $now,
                'deleted_at' => $now,
            ], ['id' => (string)$row['id']]);
            $this->platform()->delete('tenant_secrets_index', ['name' => $name]);
        });
    }

    /**
     * @inheritDoc
     */
    public function exists(string $name): bool
    {
        return $this->activeRow($name) !== null;
    }

    /**
     * @inheritDoc
     */
    public function list(string $prefix = ''): array
    {
        $conditions = ['active'];
        $tenantSql = $this->tenantConditionSql($conditions);
        $prefixSql = '';
        if ($prefix !== '') {
            $prefixSql = ' AND name LIKE ?';
            $conditions[] = $prefix . '%';
        }
        $rows = $this->platform()->execute(
            'SELECT name FROM platform_secret_values WHERE status = ?' . $tenantSql . $prefixSql . ' ORDER BY name',
            $conditions,
        )->fetchAll('assoc');

        return array_map(static fn(array $row): string => (string)$row['name'], $rows);
    }

    /**
     * @inheritDoc
     */
    public function rotatedAt(string $name): ?DateTimeImmutable
    {
        $row = $this->activeRow($name);
        if ($row === null || empty($row['rotated_at'])) {
            return null;
        }

        return new DateTimeImmutable((string)$row['rotated_at']);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function associatedData(array $row): string
    {
        $encoded = json_encode([
            'schema' => 1,
            'id' => (string)$row['id'],
            'tenant_id' => $row['tenant_id'] === null ? null : (string)$row['tenant_id'],
            'name' => (string)$row['name'],
            'namespace' => (string)$row['namespace'],
            'key_name' => (string)$row['key_name'],
            'key_version' => (string)$row['key_version'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return $encoded;
    }

    /**
     * Derive distinct associated data for wrapping the per-secret DEK.
     */
    private function dekAssociatedData(string $secretAssociatedData): string
    {
        return 'platform-secret-dek-wrap:' . $secretAssociatedData;
    }

    /**
     * @return array{cipher: string, nonce: string, tag: ?string, ciphertext: string}
     */
    private function encryptWithKey(string $plaintext, string $aad, string $key): array
    {
        if (strlen($key) !== 32) {
            throw new RuntimeException('Platform secret encryption keys must be 32 bytes.');
        }
        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

            return [
                'cipher' => self::SODIUM_CIPHER,
                'nonce' => $nonce,
                'tag' => null,
                'ciphertext' => sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key),
            ];
        }

        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::OPENSSL_CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
            16,
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt platform secret.');
        }

        return [
            'cipher' => self::OPENSSL_CIPHER,
            'nonce' => $nonce,
            'tag' => $tag,
            'ciphertext' => $ciphertext,
        ];
    }

    /**
     * Decrypt a stored value and fail closed on authentication failure.
     */
    private function decryptWithKey(
        string $cipher,
        string $nonce,
        string $tag,
        string $ciphertext,
        string $aad,
        string $key,
    ): string {
        if ($cipher === self::SODIUM_CIPHER) {
            if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
                throw new RuntimeException('Sodium is required to decrypt this platform secret.');
            }
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $aad, $nonce, $key);
            if ($plaintext === false) {
                throw new RuntimeException('Unable to decrypt platform secret.');
            }

            return $plaintext;
        }
        if ($cipher !== self::OPENSSL_CIPHER) {
            throw new RuntimeException(sprintf('Unsupported platform secret cipher "%s".', $cipher));
        }
        $plaintext = openssl_decrypt($ciphertext, self::OPENSSL_CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt platform secret.');
        }

        return $plaintext;
    }

    /**
     * Store binary ciphertext in a PostgreSQL-safe ASCII representation.
     */
    private function encodeBinaryPayload(string $payload): string
    {
        return self::BINARY_ENCODING_PREFIX . base64_encode($payload);
    }

    /**
     * Decode binary ciphertext while still accepting legacy raw BLOB values.
     */
    private function decodeBinaryPayload(mixed $payload): string
    {
        if ($payload === null) {
            return '';
        }
        if (is_resource($payload)) {
            $payload = stream_get_contents($payload);
        }
        $payload = (string)$payload;
        if (!str_starts_with($payload, self::BINARY_ENCODING_PREFIX)) {
            return $payload;
        }
        $decoded = base64_decode(substr($payload, strlen(self::BINARY_ENCODING_PREFIX)), true);
        if ($decoded === false) {
            throw new RuntimeException('Stored platform secret payload is not valid base64.');
        }

        return $decoded;
    }

    /**
     * Load and normalize the master KEK to 32 raw bytes.
     */
    private function masterKey(): string
    {
        $secret = $this->masterStore->get($this->masterKeyName);
        if ($secret === null || $secret->isEmpty()) {
            throw new RuntimeException(sprintf('Master KEK secret "%s" is missing.', $this->masterKeyName));
        }
        $value = $secret->reveal();
        if (strlen($value) === 32) {
            return $value;
        }
        if (strlen($value) === 64 && ctype_xdigit($value)) {
            $decoded = hex2bin($value);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }
        $decoded = base64_decode($value, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }

        throw new RuntimeException(sprintf('Master KEK secret "%s" must decode to 32 bytes.', $this->masterKeyName));
    }

    /**
     * Ensure metadata exists for the active logical KEK version.
     */
    private function ensureKekRow(string $now): void
    {
        $row = $this->platform()->execute(
            'SELECT id FROM platform_secret_keks WHERE key_name = ? AND key_version = ?',
            [$this->keyName, $this->keyVersion],
        )->fetch('assoc');
        if ($row !== false) {
            return;
        }
        $this->platform()->insert('platform_secret_keks', [
            'id' => Text::uuid(),
            'key_name' => $this->keyName,
            'key_version' => $this->keyVersion,
            'master_secret_name' => $this->masterKeyName,
            'algorithm' => $this->preferredCipher(),
            'status' => 'active',
            'metadata' => json_encode(['master_store' => $this->masterStore::class], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'rotated_at' => $now,
            'retired_at' => null,
        ]);
    }

    /**
     * Keep the non-secret platform index synchronized.
     */
    private function upsertIndexRow(string $name, string $now): void
    {
        $row = $this->platform()->execute(
            'SELECT id FROM tenant_secrets_index WHERE name = ?',
            [$name],
        )->fetch('assoc');
        $data = [
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'namespace' => $this->namespace,
            'driver' => self::DRIVER_NAME,
            'purpose' => null,
            'rotated_at' => $now,
            'modified_at' => $now,
        ];
        if ($row === false) {
            $data['id'] = Text::uuid();
            $data['created_at'] = $now;
            $this->platform()->insert('tenant_secrets_index', $data);

            return;
        }
        $this->platform()->update('tenant_secrets_index', $data, ['id' => (string)$row['id']]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeRow(string $name): ?array
    {
        $conditions = ['active', $name];
        $tenantSql = $this->tenantConditionSql($conditions);
        $row = $this->platform()->execute(
            'SELECT * FROM platform_secret_values WHERE status = ? AND name = ?' . $tenantSql . ' LIMIT 1',
            $conditions,
        )->fetch('assoc');

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rowByName(string $name): ?array
    {
        $conditions = [$name];
        $tenantSql = $this->tenantConditionSql($conditions);
        $row = $this->platform()->execute(
            'SELECT * FROM platform_secret_values WHERE name = ?' . $tenantSql . ' LIMIT 1',
            $conditions,
        )->fetch('assoc');

        return $row === false ? null : $row;
    }

    /**
     * @param list<mixed> $conditions
     */
    private function tenantConditionSql(array &$conditions): string
    {
        if ($this->tenantId === null) {
            return ' AND tenant_id IS NULL';
        }
        $conditions[] = $this->tenantId;

        return ' AND tenant_id = ?';
    }

    /**
     * Return nonce size for a supported cipher.
     */
    private function nonceLength(string $cipher): int
    {
        return $cipher === self::SODIUM_CIPHER ? SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES : 12;
    }

    /**
     * Return the cipher used for new encryptions.
     */
    private function preferredCipher(): string
    {
        return function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
            ? self::SODIUM_CIPHER
            : self::OPENSSL_CIPHER;
    }

    /**
     * Return the platform metadata connection.
     */
    private function platform(): Connection
    {
        return $this->connection ??= ConnectionManager::get($this->connectionName);
    }

    /**
     * Return an audit timestamp in UTC-compatible ATOM format.
     */
    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    /**
     * Validate a logical secret name.
     */
    private function assertValidName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Secret name cannot be empty.');
        }
    }
}
