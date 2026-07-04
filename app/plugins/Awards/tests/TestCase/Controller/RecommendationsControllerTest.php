<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Controller\RecommendationsController;
use Awards\Model\Entity\Recommendation;
use Cake\Cache\Cache;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use ReflectionMethod;

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
            $feedbackItems = $this->getTableLocator()->get('Awards.RecommendationFeedbackRequestItems');
            $feedbackRequestIds = $feedbackItems->find()
                ->select(['feedback_request_id'])
                ->where(['recommendation_id IN' => $this->createdRecommendationIds])
                ->all()
                ->extract('feedback_request_id')
                ->toList();
            if ($feedbackRequestIds !== []) {
                $this->getTableLocator()->get('Awards.RecommendationFeedbackRequestRecipients')->deleteAll([
                    'feedback_request_id IN' => $feedbackRequestIds,
                ]);
                $feedbackItems->deleteAll([
                    'feedback_request_id IN' => $feedbackRequestIds,
                ]);
                $this->getTableLocator()->get('Awards.RecommendationFeedbackRequests')->deleteAll([
                    'id IN' => $feedbackRequestIds,
                ]);
            }

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

    public function testFeedbackRecipientParserAcceptsMemberPublicIds(): void
    {
        $members = $this->getTableLocator()->get('Members');
        $bryce = $members->get(self::TEST_MEMBER_BRYCE_ID);

        $controller = new RecommendationsController(new ServerRequest());
        $method = new ReflectionMethod($controller, 'parseMemberIdList');
        $method->setAccessible(true);

        $ids = $method->invoke($controller, $bryce->public_id . ', ' . self::TEST_MEMBER_AGATHA_ID);

        sort($ids);
        $this->assertSame([self::TEST_MEMBER_AGATHA_ID, self::TEST_MEMBER_BRYCE_ID], $ids);
    }

    public function testRequestFeedbackDetailOriginIgnoresStaleMemberPageContext(): void
    {
        $this->authenticateAsSuperUser();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->select(['id'])->firstOrFail();
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $recommendation = $recommendations->save($recommendations->newEntity(
            $this->buildRecommendationData((int)$award->id, 'feedback-detail-origin-' . uniqid()),
        ));
        $this->assertNotFalse($recommendation);
        $this->createdRecommendationIds[] = (int)$recommendation->id;

        $this->post('/awards/recommendations/request-feedback', [
            'ids' => (string)$recommendation->id,
            'recipient_ids' => (string)self::TEST_MEMBER_BRYCE_ID,
            'message' => 'Please provide context.',
            'feedback_origin' => 'detail',
            'page_context_url' => '/members/view/' . self::TEST_MEMBER_BRYCE_ID,
        ]);

        $this->assertRedirect('/awards/recommendations/view/' . $recommendation->id);
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

    public function testMemberSubmittedRecsGridDataUsesAuthenticatedUserForVisibility(): void
    {
        $this->authenticateAsSuperUser();
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $award = $awards->find()->select(['id'])->firstOrFail();
        $reason = 'member-submitted-grid-' . uniqid();

        $recommendation = $recommendations->save($recommendations->newEntity(
            $this->buildRecommendationData((int)$award->id, $reason),
        ));
        $this->assertNotFalse($recommendation);
        $this->createdRecommendationIds[] = (int)$recommendation->id;

        $this->get('/awards/recommendations/member-submitted-recs-grid-data/' . self::ADMIN_MEMBER_ID . '?' . http_build_query([
            'search' => $reason,
            'limit' => 10,
            'ignore_default' => 1,
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains($reason);
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
