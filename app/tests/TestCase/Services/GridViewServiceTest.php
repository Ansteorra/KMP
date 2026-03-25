<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\GridViewService;
use App\Test\TestCase\BaseTestCase;

class GridViewServiceTest extends BaseTestCase
{
    protected GridViewService $service;

    /**
     * @var \App\Model\Table\MembersTable
     */
    protected $Members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->service = new GridViewService();
        $this->Members = $this->getTableLocator()->get('Members');
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(GridViewService::class, $this->service);
    }

    public function testInstantiationWithExplicitTables(): void
    {
        $gridViewsTable = $this->getTableLocator()->get('GridViews');
        $preferencesTable = $this->getTableLocator()->get('GridViewPreferences');
        $service = new GridViewService($gridViewsTable, $preferencesTable);
        $this->assertInstanceOf(GridViewService::class, $service);
    }

    public function testGetEffectiveViewReturnsNullForUnknownGrid(): void
    {
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $result = $this->service->getEffectiveView('nonexistent-grid-key-xyz', $member);
        $this->assertNull($result);
    }

    public function testGetEffectiveViewReturnsNullWithNullMember(): void
    {
        $result = $this->service->getEffectiveView('nonexistent-grid-key-xyz', null);
        $this->assertNull($result);
    }

    public function testGetViewsForGridReturnsArray(): void
    {
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $views = $this->service->getViewsForGrid('Members.index', $member);
        $this->assertIsArray($views);
    }

    public function testGetViewsForGridWithNullMember(): void
    {
        $views = $this->service->getViewsForGrid('Members.index', null);
        $this->assertIsArray($views);
    }

    public function testGetUserPreferenceViewIdReturnsNullWhenNoPreference(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $result = $this->service->getUserPreferenceViewId('some-unused-grid-key', $member);
        $this->assertNull($result);
    }

    public function testCreateViewReturnsSavedEntity(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $data = [
            'grid_key' => 'Members.index',
            'name' => 'Test View ' . uniqid(),
            'config' => json_encode(['columns' => ['id', 'sca_name']]),
        ];

        $result = $this->service->createView($data, $member);
        $this->assertNotFalse($result, 'createView should return a saved entity');
        $this->assertEquals($member->id, $result->member_id);
        $this->assertFalse($result->is_system_default);
    }

    public function testCreateViewSetsSystemDefaultToFalse(): void
    {
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $data = [
            'grid_key' => 'Members.index',
            'name' => 'Admin View ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
            'is_system_default' => true, // should be overridden
        ];

        $result = $this->service->createView($data, $member);
        $this->assertNotFalse($result);
        $this->assertFalse($result->is_system_default);
    }

    public function testGetViewReturnsOwnedView(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Owned View ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $member);

        $this->assertNotFalse($created);
        $fetched = $this->service->getView($created->id, $member);
        $this->assertNotNull($fetched);
        $this->assertEquals($created->id, $fetched->id);
    }

    public function testGetViewDeniesAccessToOtherUsersView(): void
    {
        $bryce = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $devon = $this->Members->get(self::TEST_MEMBER_DEVON_ID);

        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Bryce Only View ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $bryce);

        $this->assertNotFalse($created);
        // Devon shouldn't access Bryce's view
        $fetched = $this->service->getView($created->id, $devon);
        $this->assertNull($fetched);
    }

    public function testUpdateViewModifiesEntity(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Original Name ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $member);

        $this->assertNotFalse($created);
        $newName = 'Updated Name ' . uniqid();
        $updated = $this->service->updateView($created->id, ['name' => $newName], $member);
        $this->assertNotFalse($updated);
        $this->assertEquals($newName, $updated->name);
    }

    public function testUpdateViewDeniesNonOwner(): void
    {
        $bryce = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $devon = $this->Members->get(self::TEST_MEMBER_DEVON_ID);

        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Bryce View ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $bryce);

        $this->assertNotFalse($created);
        $result = $this->service->updateView($created->id, ['name' => 'Hacked'], $devon);
        $this->assertFalse($result);
    }

    public function testUpdateViewStripsProtectedFields(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Test Protected ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $member);

        $this->assertNotFalse($created);
        $updated = $this->service->updateView($created->id, [
            'name' => 'New Name',
            'member_id' => 9999, // should be stripped
        ], $member);
        $this->assertNotFalse($updated);
        $this->assertEquals($member->id, $updated->member_id);
    }

    public function testDeleteViewRemovesOwnedView(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'To Delete ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $member);

        $this->assertNotFalse($created);
        $result = $this->service->deleteView($created->id, $member);
        $this->assertTrue($result);
    }

    public function testDeleteViewDeniesNonOwner(): void
    {
        $bryce = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $devon = $this->Members->get(self::TEST_MEMBER_DEVON_ID);

        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Bryce Delete Test ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $bryce);

        $this->assertNotFalse($created);
        $result = $this->service->deleteView($created->id, $devon);
        $this->assertFalse($result);
    }

    public function testClearUserDefaultReturnsTrue(): void
    {
        $result = $this->service->clearUserDefault(self::TEST_MEMBER_BRYCE_ID, 'nonexistent-grid');
        $this->assertTrue($result);
    }

    public function testSetUserDefaultWithStringKey(): void
    {
        $result = $this->service->setUserDefault('all', self::TEST_MEMBER_BRYCE_ID, 'Members.index');
        $this->assertTrue($result);

        // Verify preference was saved
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $preferredId = $this->service->getUserPreferenceViewId('Members.index', $member);
        $this->assertEquals('all', $preferredId);
    }

    public function testSetUserDefaultWithViewId(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Default Test ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $member);
        $this->assertNotFalse($created);

        $result = $this->service->setUserDefault($created->id, $member->id, 'Members.index');
        $this->assertTrue($result);

        $preferredId = $this->service->getUserPreferenceViewId('Members.index', $member);
        $this->assertEquals($created->id, $preferredId);
    }

    public function testSetUserDefaultRejectsWrongGridKey(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Wrong Key Test ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $member);
        $this->assertNotFalse($created);

        // Attempt to set as default for a different grid key
        $result = $this->service->setUserDefault($created->id, $member->id, 'Branches.index');
        $this->assertFalse($result);
    }

    public function testCreateSystemDefault(): void
    {
        $data = [
            'grid_key' => 'Members.index',
            'name' => 'System Default ' . uniqid(),
            'config' => json_encode(['columns' => ['id', 'sca_name']]),
        ];
        $result = $this->service->createSystemDefault($data);
        $this->assertNotFalse($result);
        $this->assertNull($result->member_id);
        $this->assertTrue($result->is_system_default);
        $this->assertFalse($result->is_default);
    }

    public function testDeleteSystemDefaultDenied(): void
    {
        $systemView = $this->service->createSystemDefault([
            'grid_key' => 'Members.index',
            'name' => 'System No Delete ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ]);
        $this->assertNotFalse($systemView);

        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $result = $this->service->deleteView($systemView->id, $member);
        $this->assertFalse($result, 'System default views should not be deletable by regular users');
    }

    public function testGetEffectiveViewWithExplicitViewId(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Explicit View ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $member);
        $this->assertNotFalse($created);

        $effective = $this->service->getEffectiveView('Members.index', $member, $created->id);
        $this->assertNotNull($effective);
        $this->assertEquals($created->id, $effective->id);
    }

    public function testGetEffectiveViewIgnoresWrongGridKeyExplicit(): void
    {
        $member = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $created = $this->service->createView([
            'grid_key' => 'Members.index',
            'name' => 'Wrong Grid Test ' . uniqid(),
            'config' => json_encode(['columns' => ['id']]),
        ], $member);
        $this->assertNotFalse($created);

        // Request with a different grid key
        $effective = $this->service->getEffectiveView('Branches.index', $member, $created->id);
        $this->assertNull($effective);
    }
}
