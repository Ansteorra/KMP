<?php
declare(strict_types=1);

namespace App\Services\Secrets;

use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

class FileSecretStore implements WritableSecretStoreInterface
{
    /**
     * @param list<string> $allowInEnvironments
     */
    public function __construct(
        private readonly string $path,
        private readonly string $environment = 'local',
        private readonly array $allowInEnvironments = [
            'local',
            'development',
            'dev',
            'test',
            'ci',
        ],
    ) {
        if (!in_array(strtolower($this->environment), array_map('strtolower', $this->allowInEnvironments), true)) {
            throw new RuntimeException(sprintf(
                'FileSecretStore is not allowed in "%s" environment.',
                $this->environment,
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $name): ?SensitiveString
    {
        $record = $this->readRecords()[$name] ?? null;
        if (!is_array($record) || !array_key_exists('value', $record)) {
            return null;
        }

        return new SensitiveString((string)$record['value']);
    }

    /**
     * @inheritDoc
     */
    public function put(string $name, SensitiveString $value): void
    {
        $records = $this->readRecords();
        $records[$name] = [
            'value' => $value->reveal(),
            'rotated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ];
        $this->writeRecords($records);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $name): void
    {
        $records = $this->readRecords();
        unset($records[$name]);
        $this->writeRecords($records);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->readRecords());
    }

    /**
     * @inheritDoc
     */
    public function list(string $prefix = ''): array
    {
        $names = array_keys($this->readRecords());
        if ($prefix !== '') {
            $names = array_values(array_filter(
                $names,
                static fn(string $name): bool => str_starts_with($name, $prefix),
            ));
        }
        sort($names);

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function rotatedAt(string $name): ?DateTimeImmutable
    {
        $record = $this->readRecords()[$name] ?? null;
        if (!is_array($record) || empty($record['rotated_at'])) {
            return null;
        }

        return new DateTimeImmutable((string)$record['rotated_at']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readRecords(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }
        $this->assertFilePermissions();

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read secrets file "%s".', $this->path));
        }
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Secrets file "%s" does not contain valid JSON.', $this->path));
        }
        $records = $decoded['secrets'] ?? [];
        if (!is_array($records)) {
            throw new RuntimeException(sprintf('Secrets file "%s" has an invalid secrets section.', $this->path));
        }

        return $records;
    }

    /**
     * @param array<string, array<string, mixed>> $records
     */
    private function writeRecords(array $records): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create secrets directory "%s".', $directory));
        }
        $this->assertDirectoryPermissions($directory);

        ksort($records);
        $payload = json_encode(['secrets' => $records], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('Unable to encode secrets file JSON.');
        }

        $tempPath = tempnam($directory, '.secrets.');
        if ($tempPath === false) {
            throw new RuntimeException(sprintf('Unable to create temporary secrets file in "%s".', $directory));
        }
        chmod($tempPath, 0600);
        if (file_put_contents($tempPath, $payload . PHP_EOL, LOCK_EX) === false) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw new RuntimeException(sprintf('Unable to write temporary secrets file "%s".', $tempPath));
        }
        chmod($tempPath, 0600);
        if (!rename($tempPath, $this->path)) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw new RuntimeException(sprintf('Unable to replace secrets file "%s".', $this->path));
        }
    }

    /**
     * Ensure the secrets file itself is not readable by group/world users.
     *
     * @return void
     */
    private function assertFilePermissions(): void
    {
        $this->assertDirectoryPermissions(dirname($this->path));
        $permissions = fileperms($this->path);
        if ($permissions !== false && ($permissions & 0077) !== 0) {
            throw new RuntimeException(sprintf('Secrets file "%s" must not be group/world-readable.', $this->path));
        }
    }

    /**
     * Ensure the containing directory cannot be replaced by arbitrary users.
     *
     * @param string $directory Directory path
     * @return void
     */
    private function assertDirectoryPermissions(string $directory): void
    {
        $permissions = fileperms($directory);
        if ($permissions !== false && ($permissions & 0002) !== 0) {
            throw new RuntimeException(sprintf('Secrets directory "%s" must not be world-writable.', $directory));
        }
    }
}
