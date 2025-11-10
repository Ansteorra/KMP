<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Entity;

use App\Test\TestCase\BaseTestCase;
use App\Model\Entity\Permission;
use Cake\ORM\TableRegistry;
use InvalidArgumentException;

class PermissionTest extends BaseTestCase
{
    /** @var \App\Model\Table\PermissionsTable */
    protected $Permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Permissions = TableRegistry::getTableLocator()->get('Permissions');
    }

    public function testScopingRuleValidValues(): void
    {
        $entity = $this->Permissions->newEntity([
            'name' => 'Test Global',
            'scoping_rule' => Permission::SCOPE_GLOBAL,
        ]);
        $this->assertEmpty($entity->getErrors(), 'Global scope should be valid');

        $entity = $this->Permissions->newEntity([
            'name' => 'Test Branch Only',
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
        ]);
        $this->assertEmpty($entity->getErrors(), 'Branch Only scope should be valid');

        $entity = $this->Permissions->newEntity([
            'name' => 'Test Branch and Children',
            'scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN,
        ]);
        $this->assertEmpty($entity->getErrors(), 'Branch and Children scope should be valid');
    }

    public function testScopingRuleInvalidValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->Permissions->newEntity([
            'name' => 'Invalid',
            'scoping_rule' => 'NotAValidScope',
        ]);
    }

    public function testMassAssignmentAccessibleFields(): void
    {
        $entity = $this->Permissions->newEntity([
            'name' => 'Mass Assign Test',
            'require_active_membership' => true,
            'require_active_background_check' => true,
            'require_min_age' => 21,
            'is_system' => true,
            'is_super_user' => true,
            'requires_warrant' => true,
            'scoping_rule' => Permission::SCOPE_GLOBAL,
        ]);
        $this->assertTrue($entity->require_active_membership);
        $this->assertTrue($entity->require_active_background_check);
        $this->assertSame(21, $entity->require_min_age);
        $this->assertTrue($entity->is_system);
        $this->assertTrue($entity->is_super_user);
        $this->assertTrue($entity->requires_warrant);
        $this->assertSame(Permission::SCOPE_GLOBAL, $entity->scoping_rule);
    }

    public function testSuperUserFlagPresentOnSeededPermission(): void
    {
        $superUser = $this->Permissions->find()->where(['is_super_user' => true])->first();
        $this->assertNotNull($superUser, 'Expected at least one super user permission in seed data');
        $this->assertTrue($superUser->is_super_user);
    }
}
