<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\ORM\TableRegistry;

class OfficersControllerGridDataTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testBranchGridDataSearchUsesValidBranchAssociationAlias(): void
    {
        $this->skipIfPostgres();

        $branches = TableRegistry::getTableLocator()->get('Branches');
        $branch = $branches->find()
            ->select(['id', 'name'])
            ->where(['id' => self::KINGDOM_BRANCH_ID])
            ->first();

        if ($branch === null) {
            $this->markTestSkipped('Expected seeded kingdom branch not found.');
        }

        $this->get('/officers/officers/grid-data?branch_id=' . $branch->id . '&search=' . urlencode($branch->name));

        $this->assertResponseOk();
    }
}
