<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\IconCatalog;
use Cake\TestSuite\TestCase;

class IconCatalogTest extends TestCase
{
    public function testAllAliasesResolveToRegisteredCanonicalEntries(): void
    {
        $data = IconCatalog::data();
        $this->assertGreaterThan(9000, count($data['entries']));
        foreach ($data['aliases'] as $alias => $id) {
            $this->assertTrue(isset($data['entries'][$id]), $alias);
            $this->assertSame($id, IconCatalog::canonical($alias), $alias);
        }
        $this->assertSame('search', IconCatalog::canonical('bi-search'));
        $this->assertSame('archery', IconCatalog::canonical('tabler:bow'));
        $this->assertSame('armored-combat', IconCatalog::canonical('hugeicons:BodyArmorIcon'));
        $this->assertSame('target', IconCatalog::canonical('bi-bullseye'));
        $this->assertSame('device-ipad-horizontal', IconCatalog::canonical('bi-tablet-landscape'));
        $this->assertNull(IconCatalog::canonical('bi-heptagon'));
        $this->assertNull(IconCatalog::canonical('bi-anthropic'));
    }

    public function testExactAliasesStaySeparateFromKeywordsAndUnknownValues(): void
    {
        $this->assertSame('search', IconCatalog::canonical('bi bi-search text-muted'));
        $this->assertNull(IconCatalog::canonical('bi-search bi-trash'));
        $this->assertNull(IconCatalog::canonical('heavy combat'));
        $this->assertNull(IconCatalog::canonical('find'));
        $this->assertNull(IconCatalog::canonical('../../private.svg'));
        $this->assertStringContainsString('/help-circle.', IconCatalog::url('unregistered-icon'));
    }

    public function testRenderingEscapesNamesAndUsesOnlyTrustedAssetPaths(): void
    {
        $decorative = IconCatalog::render('bi-search', ['class' => 'text-danger', 'size' => 20]);
        $this->assertStringContainsString('name="search"', $decorative);
        $this->assertStringContainsString('aria-hidden="true"', $decorative);
        $this->assertStringContainsString('--kmp-icon-size:20px', $decorative);
        $this->assertStringContainsString('class="text-danger"', $decorative);
        $meaningful = IconCatalog::render('crown', ['label' => 'Crown "on progress"', 'onclick' => 'alert(1)']);
        $this->assertStringContainsString('role="img"', $meaningful);
        $this->assertStringContainsString('aria-label="Crown &quot;on progress&quot;"', $meaningful);
        $this->assertStringNotContainsString('aria-hidden', $meaningful);
        $this->assertStringNotContainsString('onclick', $meaningful);
        $unknown = IconCatalog::render('<svg onload="alert(1)">');
        $this->assertStringContainsString('name="help-circle"', $unknown);
        $this->assertStringNotContainsString('<svg', $unknown);
    }

    public function testPersistedHeaderAndFooterClassesKeepTheirControlsAndUtilityStyles(): void
    {
        $this->assertSame(['class' => 'btn btn-warning', 'icon' => 'bi-discord'], IconCatalog::controlOptions('btn bi bi-discord btn-warning'));
        $this->assertSame(['class' => 'btn', 'icon' => 'crown'], IconCatalog::controlOptions('btn', 'crown'));
        $this->assertSame(['class' => 'btn', 'icon' => 'help-circle'], IconCatalog::controlOptions('bi bi-trash bi-search btn'));
    }
}
