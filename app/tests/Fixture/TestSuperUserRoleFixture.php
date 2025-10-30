<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\I18n\DateTime;

/**
 * TestSuperUserRoleFixture
 * 
 * Creates the TestSuperUser role for testing purposes.
 * This role will be assigned the "Is Super User" permission.
 */
class TestSuperUserRoleFixture extends BaseTestFixture
{
    /**
     * The table this fixture is responsible for
     *
     * @var string
     */
    public string $table = 'roles';

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'name' => 'TestSuperUser',
                'is_system' => false,
                'deleted' => null,
                'created' => DateTime::now(),
                'created_by' => 1, // Admin
            ]
        ];
        parent::init();
    }
}
