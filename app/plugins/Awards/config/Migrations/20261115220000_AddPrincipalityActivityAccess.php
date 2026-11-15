<?php
declare(strict_types=1);

use Awards\Services\PrincipalityActivityAccessService;
use Migrations\BaseMigration;

/** Restore scoped Coronet Presence and Principality Court activity assignment. */
class AddPrincipalityActivityAccess extends BaseMigration
{
    /** @return void */
    public function up(): void
    {
        (new PrincipalityActivityAccessService())->ensure();
    }

    /** @return void */
    public function down(): void
    {
        throw new RuntimeException('Principality activity access is irreversible; grants may be in active use.');
    }
}
