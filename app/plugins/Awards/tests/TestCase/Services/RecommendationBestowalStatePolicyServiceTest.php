<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use Awards\Services\RecommendationBestowalStatePolicyService;
use Cake\TestSuite\TestCase;
use RuntimeException;

class RecommendationBestowalStatePolicyServiceTest extends TestCase
{
    public function testRetiredApprovalStatesCannotBeTargeted(): void
    {
        $service = new RecommendationBestowalStatePolicyService();

        foreach (['King Approved', 'Queen Approved'] as $state) {
            try {
                $service->assertUserCanTargetRecommendationState($state);
                $this->fail(sprintf('Expected %s to be rejected.', $state));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('is retired', $e->getMessage());
            }
        }
    }

    public function testRetiredApprovalStatesAreHiddenUnlessCurrentlyStored(): void
    {
        $service = new RecommendationBestowalStatePolicyService();
        $statuses = [
            'In Progress' => ['Submitted', 'King Approved', 'Queen Approved'],
            'Scheduling' => ['Need to Schedule'],
        ];

        $filtered = $service->filterUserTargetStatusList($statuses);
        $legacyCurrent = $service->filterUserTargetStatusList($statuses, 'King Approved');

        $this->assertSame(['Submitted'], $filtered['In Progress']);
        $this->assertSame(['Submitted', 'King Approved'], $legacyCurrent['In Progress']);
        $this->assertSame(['Need to Schedule'], $filtered['Scheduling']);
    }
}
