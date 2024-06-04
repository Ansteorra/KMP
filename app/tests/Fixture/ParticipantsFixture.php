<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * MembersFixture
 */
class MembersFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                "id" => 1,
                "last_updated" => 1715772334,
                "password" => "Lorem ipsum dolor sit amet",
                "sca_name" => "Lorem ipsum dolor sit amet",
                "first_name" => "Lorem ipsum dolor sit amet",
                "middle_name" => "Lorem ipsum dolor sit amet",
                "last_name" => "Lorem ipsum dolor sit amet",
                "street_address" => "Lorem ipsum dolor sit amet",
                "city" => "Lorem ipsum dolor sit amet",
                "state" => "Lo",
                "zip" => "Lor",
                "phone_number" => "Lorem ipsum d",
                "email_address" => "Lorem ipsum dolor sit amet",
                "membership_number" => 1,
                "membership_expires_on" => "2024-05-15",
                "branch_name" => "Lorem ipsum dolor sit amet",
                "notes" =>
                "Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.",
                "parent_name" => "Lorem ipsum dolor sit amet",
                "background_check_expires_on" => "2024-05-15",
                "password_token" => "Lorem ipsum dolor sit amet",
                "password_token_expires_on" => "2024-05-15 11:25:34",
                "last_login" => "2024-05-15 11:25:34",
                "last_failed_login" => "2024-05-15 11:25:34",
                "failed_login_attempts" => 1,
                "birth_month" => 1,
                "birth_year" => 1,
                "deleted_date" => "2024-05-15 11:25:34",
            ],
        ];
        parent::init();
    }
}