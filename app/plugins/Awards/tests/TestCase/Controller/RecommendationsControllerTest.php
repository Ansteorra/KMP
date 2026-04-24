<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Recommendation;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;

class RecommendationsControllerTest extends HttpIntegrationTestCase
{
    /**
     * @var list<int>
     */
    private array $createdPermissionPolicyIds = [];

    /**
     * @var list<int>
     */
    private array $createdMemberRoleIds = [];

    /**
     * @var list<int>
     */
    private array $createdAwardIds = [];

    /**
     * @var list<int>
     */
    private array $createdRecommendationIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->disableTransactions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    protected function tearDown(): void
    {
        if ($this->createdRecommendationIds !== []) {
            $this->getTableLocator()->get('Awards.Recommendations')->deleteAll([
                'id IN' => $this->createdRecommendationIds,
            ]);
        }

        if ($this->createdAwardIds !== []) {
            $this->getTableLocator()->get('Awards.Awards')->deleteAll([
                'id IN' => $this->createdAwardIds,
            ]);
        }

        if ($this->createdMemberRoleIds !== []) {
            $this->getTableLocator()->get('MemberRoles')->deleteAll([
                'id IN' => $this->createdMemberRoleIds,
            ]);
        }

        if ($this->createdPermissionPolicyIds !== []) {
            $this->getTableLocator()->get('PermissionPolicies')->deleteAll([
                'id IN' => $this->createdPermissionPolicyIds,
            ]);
        }

        Cache::delete('member_permissions' . self::TEST_MEMBER_AGATHA_ID, 'member_permissions');
        Cache::delete('permissions_policies' . self::TEST_MEMBER_AGATHA_ID, 'member_permissions');

        parent::tearDown();
    }

    public function testGatheringAwardsGridDataAppliesRecommendationScope(): void
    {
        $this->skipIfPostgres();

        $permissionPolicies = $this->getTableLocator()->get('PermissionPolicies');
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $members = $this->getTableLocator()->get('Members');
        $memberRoles = $this->getTableLocator()->get('MemberRoles');

        $gathering = $gatherings->find()
            ->where(['branch_id' => 27])
            ->first();
        $member = $members->get(self::TEST_MEMBER_AGATHA_ID);

        if (!$gathering) {
            $this->markTestSkipped('No branch-27 gathering found for recommendation scope test.');
        }

        $viewGatheringPolicy = $permissionPolicies->newEntity([
            'permission_id' => 1075,
            'policy_class' => 'Awards\\Policy\\RecommendationPolicy',
            'policy_method' => 'canViewGatheringRecommendations',
        ]);
        $savedPolicy = $permissionPolicies->save($viewGatheringPolicy);
        $this->assertNotFalse($savedPolicy);
        $this->createdPermissionPolicyIds[] = $savedPolicy->id;

        $activeRole = $memberRoles->newEmptyEntity();
        $activeRole->member_id = self::TEST_MEMBER_AGATHA_ID;
        $activeRole->role_id = 1117;
        $activeRole->branch_id = 27;
        $activeRole->approver_id = self::ADMIN_MEMBER_ID;
        $activeRole->start_on = DateTime::now()->modify('-1 day');
        $activeRole->expires_on = DateTime::now()->modify('+30 days');
        $savedRole = $memberRoles->save($activeRole);
        $this->assertNotFalse($savedRole);
        $this->createdMemberRoleIds[] = $savedRole->id;

        $allowedAward = $awards->newEntity([
            'name' => 'Scope Test Non-Armigerous Award',
            'abbreviation' => 'STNA-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
        ]);
        $savedAllowedAward = $awards->save($allowedAward);
        $this->assertNotFalse($savedAllowedAward);
        $this->createdAwardIds[] = $savedAllowedAward->id;

        $blockedAward = $awards->newEntity([
            'name' => 'Scope Test Armigerous Award',
            'abbreviation' => 'STAR-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 2,
            'branch_id' => 27,
        ]);
        $savedBlockedAward = $awards->save($blockedAward);
        $this->assertNotFalse($savedBlockedAward);
        $this->createdAwardIds[] = $savedBlockedAward->id;

        $allowedRecommendation = $recommendations->newEntity([
            'requester_id' => $member->id,
            'member_id' => $member->id,
            'branch_id' => 27,
            'award_id' => $savedAllowedAward->id,
            'gathering_id' => $gathering->id,
            'status' => 'To Give',
            'state' => 'Scheduled',
            'state_date' => DateTime::now(),
            'requester_sca_name' => $member->sca_name,
            'member_sca_name' => $member->sca_name,
            'contact_email' => $member->email_address,
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'Allowed gathering recommendation reason',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]);
        $savedAllowedRecommendation = $recommendations->save($allowedRecommendation);
        $this->assertNotFalse($savedAllowedRecommendation);
        $this->createdRecommendationIds[] = $savedAllowedRecommendation->id;

        $blockedRecommendation = $recommendations->newEntity([
            'requester_id' => $member->id,
            'member_id' => $member->id,
            'branch_id' => 27,
            'award_id' => $savedBlockedAward->id,
            'gathering_id' => $gathering->id,
            'status' => 'To Give',
            'state' => 'Scheduled',
            'state_date' => DateTime::now(),
            'requester_sca_name' => $member->sca_name,
            'member_sca_name' => $member->sca_name,
            'contact_email' => $member->email_address,
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'Blocked gathering recommendation reason',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]);
        $savedBlockedRecommendation = $recommendations->save($blockedRecommendation);
        $this->assertNotFalse($savedBlockedRecommendation);
        $this->createdRecommendationIds[] = $savedBlockedRecommendation->id;

        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/awards/recommendations/gathering-awards-grid-data/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Allowed gathering recommendation reason');
        $this->assertResponseNotContains('Blocked gathering recommendation reason');
    }

    public function testAwardsByDomainExcludesInactiveAwardsFromNewSelections(): void
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');

        $activeAward = $awards->save($awards->newEntity([
            'name' => 'Selection Active Award ' . uniqid(),
            'abbreviation' => 'SAA-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => true,
        ]));
        $this->assertNotFalse($activeAward);
        $this->createdAwardIds[] = $activeAward->id;

        $inactiveAward = $awards->save($awards->newEntity([
            'name' => 'Selection Inactive Award ' . uniqid(),
            'abbreviation' => 'SIA-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => false,
        ]));
        $this->assertNotFalse($inactiveAward);
        $this->createdAwardIds[] = $inactiveAward->id;

        $this->get('/awards/awards/awards-by-domain/2');

        $this->assertResponseOk();
        $response = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $awardIds = array_column($response, 'id');

        $this->assertContains($activeAward->id, $awardIds);
        $this->assertNotContains($inactiveAward->id, $awardIds);
    }

    public function testAwardsByDomainKeepsCurrentInactiveAwardAvailableForExistingRecommendation(): void
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');

        $inactiveAward = $awards->save($awards->newEntity([
            'name' => 'Existing Rec Inactive Award ' . uniqid(),
            'abbreviation' => 'ERI-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => false,
        ]));
        $this->assertNotFalse($inactiveAward);
        $this->createdAwardIds[] = $inactiveAward->id;

        $this->get('/awards/awards/awards-by-domain/2?current_award_id=' . $inactiveAward->id);

        $this->assertResponseOk();
        $response = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $awardIds = array_column($response, 'id');

        $this->assertContains($inactiveAward->id, $awardIds);
    }

    public function testInactiveAwardsCannotBeUsedForNewRecommendationsButExistingRecommendationsCanKeepThem(): void
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        $inactiveAward = $awards->save($awards->newEntity([
            'name' => 'Inactive Recommendation Award ' . uniqid(),
            'abbreviation' => 'IRA-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => false,
        ]));
        $this->assertNotFalse($inactiveAward);
        $this->createdAwardIds[] = $inactiveAward->id;

        $newRecommendation = $recommendations->newEntity($this->buildRecommendationData($inactiveAward->id, 'inactive-award-new-rec-' . uniqid()));
        $this->assertFalse($recommendations->save($newRecommendation));
        $this->assertArrayHasKey('award_id', $newRecommendation->getErrors());

        $activeAward = $awards->save($awards->newEntity([
            'name' => 'Active Recommendation Award ' . uniqid(),
            'abbreviation' => 'ARA-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => true,
        ]));
        $this->assertNotFalse($activeAward);
        $this->createdAwardIds[] = $activeAward->id;

        $existingRecommendation = $recommendations->save($recommendations->newEntity(
            $this->buildRecommendationData($activeAward->id, 'active-award-existing-rec-' . uniqid()),
        ));
        $this->assertNotFalse($existingRecommendation);
        $this->createdRecommendationIds[] = $existingRecommendation->id;

        $activeAward->is_active = false;
        $this->assertNotFalse($awards->save($activeAward));

        $existingRecommendation->reason = 'existing-inactive-award-kept-' . uniqid();
        $savedExistingRecommendation = $recommendations->save($existingRecommendation);

        $this->assertNotFalse($savedExistingRecommendation);
        $this->assertSame($activeAward->id, $savedExistingRecommendation->award_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecommendationData(int $awardId, string $reason): array
    {
        return [
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $awardId,
            'status' => 'To Give',
            'state' => Recommendation::getStatuses()['To Give'][0],
            'state_date' => DateTime::now(),
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => $reason,
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ];
    }
}
