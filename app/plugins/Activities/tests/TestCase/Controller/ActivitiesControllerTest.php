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

    public function testApproversListSortsByMemberScaNameAcrossBranches(): void
    {
        $tables = TableRegistry::getTableLocator();
        $activities = $tables->get('Activities.Activities');
        $activity = $activities->find()->firstOrFail();
        $activities->updateAll(['permission_id' => self::SUPER_USER_PERMISSION_ID], ['id' => $activity->id]);
        $members = $tables->get('Members');
        $requestingMember = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $roles = $tables->get('MemberRoles');
        $roles->saveOrFail($roles->newEntity([
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'role_id' => self::ADMIN_ROLE_ID,
            'start_on' => '2000-01-01',
            'approver_id' => self::ADMIN_MEMBER_ID,
        ]));
        $riding = $tables->get('Branches')->find()->where(['name' => 'Riding of Marata'])->firstOrFail();
        $members->updateAll(
            ['sca_name' => 'Zulu Approver', 'branch_id' => self::KINGDOM_BRANCH_ID],
            ['id' => self::ADMIN_MEMBER_ID],
        );
        $members->updateAll(
            ['sca_name' => 'Alpha Approver', 'branch_id' => $riding->id],
            ['id' => self::TEST_MEMBER_BRYCE_ID],
        );

        $this->get('/activities/activities/approvers-list/' . $activity->id . '/' . $requestingMember->id);

        $this->assertResponseOk();
        $approvers = json_decode((string)$this->_response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $selectedApprovers = array_values(array_filter(
            $approvers,
            fn(array $approver): bool => in_array(
                $approver['id'],
                [self::ADMIN_MEMBER_ID, self::TEST_MEMBER_BRYCE_ID],
                true,
            ),
        ));
        $this->assertSame(
            ['Alpha Approver, Marata', 'Zulu Approver, Ansteorra'],
            array_column($selectedApprovers, 'sca_name'),
        );
    }
}
