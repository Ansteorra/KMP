<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\Permission;
use Awards\Policy\ApprovalProcessesTablePolicy;
use Awards\Policy\BestowalTodoTemplatesTablePolicy;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;

class AwardWorkflowSynchronizationPoliciesTest extends TestCase
{
    public function testRecommendationSyncRequiresExplicitPolicyMapping(): void
    {
        $policy = new ApprovalProcessesTablePolicy();
        $table = $this->createMock(Table::class);

        $this->assertFalse($policy->canSyncOpenRecommendations($this->identityWithPolicies([]), $table));
        $this->assertTrue($policy->canSyncOpenRecommendations(
            $this->identityWithPolicies($this->globalGrant(
                ApprovalProcessesTablePolicy::class,
                'canSyncOpenRecommendations',
            )),
            $table,
        ));
    }

    public function testBestowalSyncRequiresExplicitPolicyMapping(): void
    {
        $policy = new BestowalTodoTemplatesTablePolicy();
        $table = $this->createMock(Table::class);

        $this->assertFalse($policy->canSyncOpenBestowals($this->identityWithPolicies([]), $table));
        $this->assertTrue($policy->canSyncOpenBestowals(
            $this->identityWithPolicies($this->globalGrant(
                BestowalTodoTemplatesTablePolicy::class,
                'canSyncOpenBestowals',
            )),
            $table,
        ));
    }

    /**
     * @param array<string, mixed> $policies Policy map.
     * @return \App\KMP\KmpIdentityInterface
     */
    private function identityWithPolicies(array $policies): KmpIdentityInterface
    {
        $identity = $this->createMock(KmpIdentityInterface::class);
        $identity->method('isSuperUser')->willReturn(false);
        $identity->method('getIdentifier')->willReturn(100);
        $identity->method('getPolicies')->willReturn($policies);

        return $identity;
    }

    /**
     * @return array<string, mixed>
     */
    private function globalGrant(string $policyClass, string $policyMethod): array
    {
        return [
            $policyClass => [
                $policyMethod => (object)[
                    'scoping_rule' => Permission::SCOPE_GLOBAL,
                    'branch_ids' => [],
                    'entity_id' => null,
                    'entity_type' => 'Direct Grant',
                ],
            ],
        ];
    }
}
