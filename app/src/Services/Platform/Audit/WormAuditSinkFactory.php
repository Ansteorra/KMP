<?php
declare(strict_types=1);

namespace App\Services\Platform\Audit;

use Cake\Core\Configure;
use RuntimeException;

class WormAuditSinkFactory
{
    /**
     * Build the configured WORM audit sink.
     *
     * @param array<string, mixed>|null $config Optional config override
     * @return \App\Services\Platform\Audit\WormAuditSinkInterface
     */
    public static function fromConfig(?array $config = null): WormAuditSinkInterface
    {
        $config ??= self::defaultConfig();
        $sink = strtolower(trim((string)($config['sink'] ?? 'disabled')));

        return match ($sink) {
            '', 'disabled', 'null', 'none' => new NullWormAuditSink(),
            'file' => new FileWormAuditSink((string)($config['filePath'] ?? '')),
            'azure_blob' => throw new RuntimeException(
                'Azure Blob WORM audit sink is not implemented in this slice; configure immutable storage externally.',
            ),
            default => throw new RuntimeException(sprintf('Unsupported WORM audit sink "%s".', $sink)),
        };
    }

    /**
     * Return config from Configure, falling back to environment variables.
     *
     * @return array<string, mixed>
     */
    private static function defaultConfig(): array
    {
        $configured = (array)Configure::read('PlatformAudit.worm', []);
        if ($configured !== []) {
            return $configured;
        }

        return [
            'sink' => env('PLATFORM_AUDIT_WORM_SINK', 'disabled'),
            'filePath' => env('PLATFORM_AUDIT_WORM_FILE_PATH', ROOT . DS . 'tmp' . DS . 'platform_audit_worm.jsonl'),
        ];
    }
}
