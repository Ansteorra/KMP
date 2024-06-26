<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * RecommendationsFixture
 */
class RecommendationsFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'awards_recommendations';
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
                'requester_id' => 1,
                'member_id' => 1,
                'branch_id' => 1,
                'award_id' => 1,
                'requester_sca_name' => 'Lorem ipsum dolor sit amet',
                'member_sca_name' => 'Lorem ipsum dolor sit amet',
                'contact_number' => 'Lorem ipsum dolor sit amet',
                'reason' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'modified' => '2024-06-24 21:26:32',
                'created' => '2024-06-24 21:26:32',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => '2024-06-24 21:26:32',
            ],
        ];
        parent::init();
    }
}
