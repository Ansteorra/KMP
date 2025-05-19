<?php

declare(strict_types=1);

namespace App\Test\Fixture;

/**
 * AppSettingsFixture
 */
class PermissionsFixture extends BaseTestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [];
        $this->records = array_merge($this->records, $this->getData('InitPermissionsSeed'));
        parent::init();
    }
}
