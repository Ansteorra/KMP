<?php

declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Model\Entity\EmailTemplate;
use App\Policy\EmailTemplatePolicy;
use App\Test\TestCase\BaseTestCase;

class EmailTemplatePolicyTest extends BaseTestCase
{
    protected $Members;
    protected EmailTemplatePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new EmailTemplatePolicy();
    }

    protected function getEntity(): EmailTemplate
    {
        $table = $this->getTableLocator()->get('EmailTemplates');

        return $table->newEmptyEntity();
    }

    protected function loadMember(int $id)
    {
        $member = $this->Members->get($id);
        $member->getPermissions();

        return $member;
    }

    public function testSuperUserBypassesAllChecks(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getEntity();

        $actions = ['index', 'view', 'create', 'update', 'edit', 'delete', 'discover', 'sync', 'preview'];
        foreach ($actions as $action) {
            $result = $this->policy->before($admin, $entity, $action);
            $this->assertTrue($result, "Super user before() should return true for '$action'");
        }
    }

    public function testNonPrivilegedUserCannotIndex(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getEntity();

        $beforeResult = $this->policy->before($agatha, $entity, 'index');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canIndex($agatha, $entity));
    }

    public function testNonPrivilegedUserCannotView(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getEntity();

        $beforeResult = $this->policy->before($agatha, $entity, 'view');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canView($agatha, $entity));
    }

    public function testNonPrivilegedUserCannotCreate(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getEntity();

        $beforeResult = $this->policy->before($agatha, $entity, 'create');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canCreate($agatha, $entity));
    }

    public function testNonPrivilegedUserCannotUpdate(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getEntity();

        $beforeResult = $this->policy->before($agatha, $entity, 'update');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canUpdate($agatha, $entity));
    }

    public function testCanEditDelegatesToCanUpdate(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getEntity();

        $editResult = $this->policy->canEdit($agatha, $entity);
        $updateResult = $this->policy->canUpdate($agatha, $entity);
        $this->assertSame($updateResult, $editResult, 'canEdit should delegate to canUpdate');
    }

    public function testNonPrivilegedUserCannotDelete(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getEntity();

        $beforeResult = $this->policy->before($agatha, $entity, 'delete');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canDelete($agatha, $entity));
    }

    public function testCanDiscoverDelegatesToCanView(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getEntity();

        $discoverResult = $this->policy->canDiscover($agatha, $entity);
        $viewResult = $this->policy->canView($agatha, $entity);
        $this->assertSame($viewResult, $discoverResult, 'canDiscover should delegate to canView');
    }

    public function testCanSyncDelegatesToCanCreate(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getEntity();

        $syncResult = $this->policy->canSync($agatha, $entity);
        $createResult = $this->policy->canCreate($agatha, $entity);
        $this->assertSame($createResult, $syncResult, 'canSync should delegate to canCreate');
    }

    public function testCanPreviewDelegatesToCanView(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getEntity();

        $previewResult = $this->policy->canPreview($agatha, $entity);
        $viewResult = $this->policy->canView($agatha, $entity);
        $this->assertSame($viewResult, $previewResult, 'canPreview should delegate to canView');
    }
}
