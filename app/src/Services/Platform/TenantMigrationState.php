<?php
declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Immutable comparison of a tenant database against the migrations in this release.
 */
final class TenantMigrationState
{
    /**
     * @param array<string, list<string>> $pendingVersions Missing versions keyed by migration scope
     * @param array<string, list<string>> $unexpectedVersions Applied versions absent from this release
     */
    public function __construct(
        public readonly string $targetVersion,
        public readonly ?string $currentVersion,
        public readonly array $pendingVersions,
        public readonly array $unexpectedVersions,
    ) {
    }

    /**
     * Whether every expected migration is applied without history drift.
     */
    public function isCurrent(): bool
    {
        return $this->pendingVersions === [] && $this->unexpectedVersions === [];
    }

    /**
     * Safe metadata for platform job records and operator output.
     *
     * @return array<string, mixed>
     */
    public function toMetadata(): array
    {
        return [
            'target_version' => $this->targetVersion,
            'current_version' => $this->currentVersion,
            'pending_versions' => $this->pendingVersions,
            'unexpected_versions' => $this->unexpectedVersions,
            'is_current' => $this->isCurrent(),
        ];
    }
}
