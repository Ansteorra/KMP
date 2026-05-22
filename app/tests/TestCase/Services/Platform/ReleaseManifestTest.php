<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\ReleaseCompatibilityChecker;
use App\Services\Platform\ReleaseManifest;
use Cake\TestSuite\TestCase;
use RuntimeException;

class ReleaseManifestTest extends TestCase
{
    public function testValidManifestPassesTenantSchemaWithinRange(): void
    {
        $manifest = ReleaseManifest::fromArray($this->validManifest());

        (new ReleaseCompatibilityChecker())->assertTenantCompatible('20260516005000', $manifest, 'alpha');

        $this->assertSame('2026.05.16-test', $manifest->appVersion);
    }

    public function testTenantSchemaBelowMinimumFailsClearly(): void
    {
        $manifest = ReleaseManifest::fromArray($this->validManifest());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant "alpha" schema 20260514000000 is below release minimum 20260516000000');

        (new ReleaseCompatibilityChecker())->assertTenantCompatible('20260514000000', $manifest, 'alpha');
    }

    public function testTenantSchemaAboveMaximumFailsClearly(): void
    {
        $manifest = ReleaseManifest::fromArray($this->validManifest());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant "alpha" schema 20260517000000 is above release maximum 20260516009999');

        (new ReleaseCompatibilityChecker())->assertTenantCompatible('20260517000000', $manifest, 'alpha');
    }

    public function testCompatiblePreviousSchemaIsAccepted(): void
    {
        $manifest = ReleaseManifest::fromArray($this->validManifest());

        (new ReleaseCompatibilityChecker())->assertTenantCompatible('20260515000000', $manifest, 'alpha');

        $this->addToAssertionCount(1);
    }

    public function testInvalidManifestFailsClosed(): void
    {
        $data = $this->validManifest();
        unset($data['tenant_schema']['max']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Release manifest field "max" must be a non-empty string.');

        ReleaseManifest::fromArray($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function validManifest(): array
    {
        return [
            'format_version' => 1,
            'app' => [
                'version' => '2026.05.16-test',
                'image' => 'ghcr.io/example/kmp:2026.05.16-test',
                'digest' => 'sha256:1111111111111111111111111111111111111111111111111111111111111111',
            ],
            'tenant_schema' => [
                'min' => '20260516000000',
                'max' => '20260516009999',
                'compatible_previous' => ['20260515000000'],
            ],
            'migration_policy' => [
                'mode' => 'expand-contract',
                'online' => true,
            ],
            'rollback_notes' => 'Rollback image before contract migrations.',
        ];
    }
}
