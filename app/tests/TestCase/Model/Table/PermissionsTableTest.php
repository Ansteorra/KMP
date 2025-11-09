<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;

class PermissionsTableTest extends BaseTestCase
{
    /** @var \App\Model\Table\PermissionsTable */
    protected $Permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Permissions = $this->getTableLocator()->get('Permissions');
    }

    public function testValidationRequiresName(): void
    {
        $permission = $this->Permissions->newEntity(['name' => '']);
        $this->assertNotEmpty($permission->getErrors()['name']);
    }

    public function testValidationBooleanFlags(): void
    {
        $permission = $this->Permissions->newEntity([
            'name' => 'Flag Test',
            'require_active_membership' => 'not-bool',
            'require_active_background_check' => 'not-bool',
            'require_min_age' => 'abc',
            'is_system' => 'not-bool',
            'is_super_user' => 'not-bool',
        ]);
        $errors = $permission->getErrors();
        $this->assertNotEmpty($errors['require_active_membership']);
        $this->assertNotEmpty($errors['require_active_background_check']);
        $this->assertNotEmpty($errors['require_min_age']);
        $this->assertNotEmpty($errors['is_system']);
        $this->assertNotEmpty($errors['is_super_user']);
    }

    public function testAssociationsConfigured(): void
    {
        $this->assertTrue($this->Permissions->hasAssociation('Roles'));
        $this->assertTrue($this->Permissions->hasAssociation('PermissionPolicies'));
    }
}
