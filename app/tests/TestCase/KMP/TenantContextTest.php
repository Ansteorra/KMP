<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\KMP\MissingTenantContextException;
use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use Cake\TestSuite\TestCase;

class TenantContextTest extends TestCase
{
    public function testCurrentThrowsWhenMissing(): void
    {
        $this->expectException(MissingTenantContextException::class);
        TenantContext::current();
    }

    public function testWithRestoresPreviousContext(): void
    {
        $outer = $this->tenant('outer');
        $inner = $this->tenant('inner');

        $result = TenantContext::with($outer, function () use ($inner): string {
            $this->assertSame('outer', TenantContext::slug());

            return TenantContext::with($inner, function (): string {
                $this->assertSame('inner', TenantContext::slug());

                return TenantContext::id();
            });
        });

        $this->assertSame('inner-id', $result);
        $this->assertNull(TenantContext::tryCurrent());
    }

    private function tenant(string $slug): TenantMetadata
    {
        return new TenantMetadata(
            $slug . '-id',
            $slug,
            ucfirst($slug),
            'active',
            'db',
            $slug . '_db',
            $slug . '_role',
        );
    }
}
