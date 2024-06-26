<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * DomainsFixture
 */
class DomainsFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'awards_domains';
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
                'modified' => '2024-06-24 21:15:03',
                'created' => '2024-06-24 21:15:03',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => '2024-06-24 21:15:03',
            ],
        ];
        parent::init();
    }
}
