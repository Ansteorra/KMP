<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * LevelsFixture
 */
class LevelsFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'awards_levels';
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'Lorem ipsum dolor sit amet',
                'order' => 1,
                'modified' => '2024-06-24 21:16:26',
                'created' => '2024-06-24 21:16:26',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => '2024-06-24 21:16:26',
            ],
        ];
        parent::init();
    }
}
