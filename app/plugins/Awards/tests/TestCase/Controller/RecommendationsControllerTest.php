<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;

class RecommendationsControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
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

        $activeRole = $memberRoles->newEmptyEntity();
        $activeRole->member_id = self::TEST_MEMBER_AGATHA_ID;
        $activeRole->role_id = 1117;
        $activeRole->branch_id = 27;
        $activeRole->approver_id = self::ADMIN_MEMBER_ID;
        $activeRole->start_on = DateTime::now()->modify('-1 day');
        $activeRole->expires_on = DateTime::now()->modify('+30 days');
        $savedRole = $memberRoles->save($activeRole);
        $this->assertNotFalse($savedRole);

        $allowedAward = $awards->newEntity([
            'name' => 'Scope Test Non-Armigerous Award',
            'abbreviation' => 'STNA-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
        ]);
        $savedAllowedAward = $awards->save($allowedAward);
        $this->assertNotFalse($savedAllowedAward);

        $blockedAward = $awards->newEntity([
            'name' => 'Scope Test Armigerous Award',
            'abbreviation' => 'STAR-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 2,
            'branch_id' => 27,
        ]);
        $savedBlockedAward = $awards->save($blockedAward);
        $this->assertNotFalse($savedBlockedAward);

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

        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/awards/recommendations/gathering-awards-grid-data/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Allowed gathering recommendation reason');
        $this->assertResponseNotContains('Blocked gathering recommendation reason');
    }
}
