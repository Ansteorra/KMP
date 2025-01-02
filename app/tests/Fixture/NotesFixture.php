<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * NotesFixture
 */
class NotesFixture extends TestFixture
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
                "author_id" => 1,
                "created_on" => 1716308713,
                "entity_type" => "Lorem ipsum dolor sit amet",
                "entity_id" => 1,
                "subject" => "Lorem ipsum dolor sit amet",
                "body" =>
                "Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.",
                "private" => 1,
            ],
        ];
        parent::init();
    }
}
