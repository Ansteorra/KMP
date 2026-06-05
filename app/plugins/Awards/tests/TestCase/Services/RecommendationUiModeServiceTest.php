<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use Awards\Model\Entity\Recommendation;
use Awards\Services\RecommendationUiModeService;
use Cake\TestSuite\TestCase;

class RecommendationUiModeServiceTest extends TestCase
{
    public function testBuildStateRulesKeepsControllerTargetShape(): void
    {
        $service = new RecommendationUiModeService();

        $rules = $service->buildStateRules();

        $this->assertArrayHasKey('Given', $rules);
        $this->assertContains('givenBlockTarget', $rules['Given']['Visible']);
        $this->assertContains('givenDateTarget', $rules['Given']['Required']);
        $this->assertArrayHasKey('No Action', $rules);
        $this->assertContains('closeReasonBlockTarget', $rules['No Action']['Visible']);
        $this->assertContains('closeReasonTarget', $rules['No Action']['Required']);
        $this->assertSame([], $rules['Submitted']);
    }

    public function testModeForRecommendationUsesBestowalLockAsUiOnlyMode(): void
    {
        $service = new RecommendationUiModeService();
        $recommendation = new Recommendation([
            'state' => 'Submitted',
            'bestowal_id' => 5,
        ]);

        $this->assertSame(
            RecommendationUiModeService::MODE_LINKED_BESTOWAL,
            $service->modeForRecommendation($recommendation),
        );
    }
}
