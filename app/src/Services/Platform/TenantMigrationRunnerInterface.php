<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;
use Cake\Console\ConsoleIo;

interface TenantMigrationRunnerInterface
{
    /**
     * Run tenant app/plugin migrations and return non-sensitive result metadata.
     *
     * @param array{target?: string|null, date?: string|null, fake?: bool, dry_run?: bool} $options Migration options
     */
    public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult;
}
