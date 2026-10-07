<?php
declare(strict_types=1);

namespace Activities\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\ORM\TableRegistry;

class ActivitiesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
    }

    public function testApproversListRejectsMissingMemberIdWithoutServerError(): void
    {
        $this->get('/activities/activities/approvers-list/41');

        $this->assertResponseCode(400);
        $this->assertSame('[]', (string)$this->_response->getBody());
    }

    public function testApproversListOmitsSeededRidingGroupPrefix(): void
    {
        $tables = TableRegistry::getTableLocator();
        $riding = $tables->get('Branches')->find()->where(['name' => 'Riding of Marata'])->firstOrFail();
        $activities = $tables->get('Activities.Activities');
        $activity = $activities->find()->firstOrFail();
        $activities->updateAll(['permission_id' => self::SUPER_USER_PERMISSION_ID], ['id' => $activity->id]);
        $tables->get('Members')->updateAll(
            ['branch_id' => $riding->id, 'sca_name' => 'Riding Approver'],
            ['id' => self::ADMIN_MEMBER_ID],
        );

        $this->get('/activities/activities/approvers-list/' . $activity->id . '/' . self::TEST_MEMBER_AGATHA_ID);

        $this->assertResponseOk();
        $approvers = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $labels = array_column($approvers, 'sca_name', 'id');
        $this->assertSame('Riding Approver, Marata', $labels[self::ADMIN_MEMBER_ID]);
    }
}
