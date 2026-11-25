<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Model\Table\AppSettingsTable;
use App\Services\PublicCalendarSettings;
use App\Test\TestCase\BaseTestCase;
use Cake\Datasource\ConnectionManager;

class PublicCalendarSettingsTest extends BaseTestCase
{
    public function testSettingsAndLogoReferencesUseTheActiveTenant(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->execute('CREATE TABLE calendar_settings_a (LIKE app_settings INCLUDING ALL)');
        $connection->execute('CREATE TABLE calendar_settings_b (LIKE app_settings INCLUDING ALL)');
        $tableA = new AppSettingsTable(['connection' => $connection]);
        $tableA->setTable('calendar_settings_a');
        $tableB = new AppSettingsTable(['connection' => $connection]);
        $tableB->setTable('calendar_settings_b');

        $tenantA = new TenantMetadata('calendar-a', 'calendar-a', 'A', 'active', 'db', 'calendar_a', 'role');
        $tenantB = new TenantMetadata('calendar-b', 'calendar-b', 'B', 'active', 'db', 'calendar_b', 'role');
        $logo = 'images/' . substr(hash('sha256', $tenantA->id), 0, 32) . '/' . str_repeat('a', 64) . '.png';
        $saved = [
            'header_title' => 'Tenant A calendar',
            'logo_key' => $logo,
            'embed_origins' => 'https://calendar-a.example',
        ];
        TenantContext::with($tenantA, function () use ($tableA, $saved): void {
            $this->assertTrue($tableA->updateSetting(PublicCalendarSettings::KEY, 'json', json_encode($saved)));
            $loaded = (new PublicCalendarSettings($tableA))->load();
            $this->assertSame($saved['header_title'], $loaded['header_title']);
            $this->assertSame(['https://calendar-a.example'], PublicCalendarSettings::frameOrigins($loaded['embed_origins']));
            $this->assertNotNull(PublicCalendarSettings::logoUrl($loaded));
        });
        TenantContext::with($tenantB, function () use ($tableB, $saved): void {
            $loaded = (new PublicCalendarSettings($tableB))->load();
            $this->assertSame('', $loaded['header_title']);
            $this->assertSame([], PublicCalendarSettings::frameOrigins($loaded['embed_origins']));
            $this->assertNull(PublicCalendarSettings::logoUrl($saved));
        });
    }
}
