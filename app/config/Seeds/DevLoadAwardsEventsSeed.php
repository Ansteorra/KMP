<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

require_once __DIR__ . '/Lib/SeedHelpers.php';

/**
 * AwardsEvents seed.
 */
class DevLoadAwardsEventsSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = $this->getData();
        $table = $this->table('awards_events');
        $table->insert($data)->save();
    }

    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        $adminId = SeedHelpers::getMemberId('admin@test.com');
        return [
            [
                'name' => 'Twilight Dreams Baronial',
                'description' => 'A cool Baronial Event',
                'branch_id' => SeedHelpers::getBranchIdByName('Barony 1'),
                'start_date' => DateTime::now()->addMonths(1),
                'end_date' => DateTime::now()->addMonths(1)->addDays(2),
                'modified' => '2024-06-25 22:03:26',
                'created' => '2024-06-25 22:03:26',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
            [
                'name' => 'Midsomer Mysteries ',
                'description' => 'a murder mystery event',
                'branch_id' => SeedHelpers::getBranchIdByName('Shire 1'),
                'start_date' => DateTime::now()->addMonths(2),
                'end_date' => DateTime::now()->addMonths(2)->addDays(2),
                'modified' => '2024-06-25 22:18:32',
                'created' => '2024-06-25 22:18:32',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
        ];
    }
}
