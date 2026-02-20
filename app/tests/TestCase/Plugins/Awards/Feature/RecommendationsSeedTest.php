<?php

declare(strict_types=1);

namespace App\Test\TestCase\Plugins\Awards\Feature;

use App\Test\TestCase\Support\PluginIntegrationTestCase;

/**
 * Validates Awards plugin tables are accessible with seeded data.
 */
final class RecommendationsSeedTest extends PluginIntegrationTestCase
{
    protected const PLUGIN_NAME = 'Awards';

    public function testSeededRecommendationIsReachable(): void
    {
        $this->skipIfPostgres();
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        $record = $recommendations->get(579);

        $this->assertSame('Closed', $record->status);
        $this->assertSame('Bryce Demoer', $record->member_sca_name);
    }
}
