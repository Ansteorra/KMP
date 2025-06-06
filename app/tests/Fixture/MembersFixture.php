<?php

declare(strict_types=1);

namespace App\Test\Fixture;

/**
 * AppSettingsFixture
 */
class MembersFixture extends BaseTestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [];
        $this->records = array_merge($this->records, $this->getData('InitMembersSeed')['members']);
        parent::init();
    }
}
