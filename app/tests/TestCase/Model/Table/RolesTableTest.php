<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;

class RolesTableTest extends BaseTestCase
{
    /** @var \App\Model\Table\RolesTable */
    protected $Roles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Roles = $this->getTableLocator()->get('Roles');
    }

    public function testValidationRequiresName(): void
    {
        $role = $this->Roles->newEntity(['name' => '']);
        $this->assertNotEmpty($role->getErrors()['name']);
    }

    public function testValidationUniqueName(): void
    {
        $existing = $this->Roles->find()->first();
        if (!$existing) {
            $this->markTestSkipped('No existing role found in seed data');
        }
        $dup = $this->Roles->newEntity(['name' => $existing->name]);
        $this->Roles->save($dup); // triggers rules checker
        $this->assertNotEmpty($dup->getErrors()['name']);
    }

    public function testAssociationsConfigured(): void
    {
        $this->assertTrue($this->Roles->hasAssociation('Members'));
        $this->assertTrue($this->Roles->hasAssociation('Permissions'));
        $this->assertTrue($this->Roles->hasAssociation('MemberRoles'));
    }

    public function testPermissionsAssociation(): void
    {
        // Find a role that has at least one permission via the join table
        $role = $this->Roles->find()
            ->contain(['Permissions'])
            ->matching('Permissions')
            ->first();

        if (!$role) {
            $this->markTestSkipped('No role with permissions found in seed data');
        }

        $this->assertNotEmpty($role->permissions, 'Role should have at least one permission linked');
        foreach ($role->permissions as $permission) {
            $this->assertNotEmpty($permission->id);
            $this->assertNotEmpty($permission->name);
        }
    }
}
