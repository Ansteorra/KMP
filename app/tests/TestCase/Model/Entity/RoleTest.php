<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Entity;

use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;

class RoleTest extends BaseTestCase
{
    /** @var \App\Model\Table\RolesTable */
    protected $Roles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Roles = TableRegistry::getTableLocator()->get('Roles');
    }

    public function testMassAssignmentAccessibleFields(): void
    {
        $entity = $this->Roles->newEntity([
            'name' => 'Mass Assign Role',
            'permissions' => [
                ['id' => $this->Roles->Permissions->find()->select('id')->first()->id]
            ],
        ]);
        $this->assertSame('Mass Assign Role', $entity->name);
        $this->assertNotEmpty($entity->permissions);
    }

    public function testLazyLoadPermissions(): void
    {
        $role = $this->Roles->find()->contain([])->first();
        $this->assertNotNull($role);
        // Accessing permissions should trigger lazy load
        $perms = $role->permissions;
        $this->assertIsIterable($perms);
    }

    public function testUniqueNameConstraint(): void
    {
        $existing = $this->Roles->find()->first();
        $dup = $this->Roles->newEntity(['name' => $existing->name]);
        $this->Roles->save($dup);
        $this->assertNotEmpty($dup->getErrors()['name']);
    }
}
