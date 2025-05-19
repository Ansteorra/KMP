<?php

declare(strict_types=1);

require_once __DIR__ . '/Lib/SeedHelpers.php';

use Migrations\BaseSeed;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Branches seed.
 */
class DevLoadBranchesSeed extends BaseSeed
{

    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        $createdByMemberId = SeedHelpers::getMemberId('admin@test.com'); // Assuming '1' referred to this member
        $kingdomBranchId = SeedHelpers::getBranchIdByName('Kingdom'); // Assuming this is the name of branch with ID 1

        return [
            [
                // 'id' => 2, // Removed
                'name' => 'Region 1',
                'location' => 'Part of Kingdom',
                'parent_id' => $kingdomBranchId,
                'lft' => 2, // Lft/rght values might need recalculation by the TreeBehavior after insert if not managed carefully
                'rght' => 9,
                'type' => 'Region',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 5, // Removed
                'name' => 'Region 2',
                'location' => 'Part of Kingdom 2',
                'parent_id' => $kingdomBranchId,
                'lft' => 10,
                'rght' => 17,
                'type' => 'Region',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 3, // Removed
                'name' => 'Barony 1',
                'location' => 'A Local group',
                'parent_id_lookup_name' => 'Region 1', // Relies on 'Region 1' being inserted first or existing
                'lft' => 3,
                'rght' => 4,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 4, // Removed
                'name' => 'Barony 2',
                'location' => 'A Local group 2',
                'parent_id_lookup_name' => 'Region 1',
                'lft' => 5,
                'rght' => 6,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 6, // Removed
                'name' => 'Barony 3',
                'location' => 'A Local group 2',
                'parent_id_lookup_name' => 'Region 2',
                'lft' => 11,
                'rght' => 14,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 7, // Removed
                'name' => 'Shire 1',
                'location' => 'A sub local group 2',
                'parent_id_lookup_name' => 'Barony 3',
                'lft' => 12,
                'rght' => 13,
                'type' => 'Sponsored Local Group',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 8, // Removed
                'name' => 'Region 1 Kingdom Land',
                'location' => 'Part of Kingdom',
                'parent_id_lookup_name' => 'Region 1',
                'lft' => 7,
                'rght' => 8,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 9, // Removed
                'name' => 'Region 2 Kingdom Land',
                'location' => 'Part of Kingdom',
                'parent_id_lookup_name' => 'Region 2',
                'lft' => 15,
                'rght' => 16,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 10, // Removed
                'name' => 'Out of Kingdom',
                'location' => 'Out of Kingdom',
                'parent_id' => NULL, // This remains NULL
                'lft' => 19,
                'rght' => 20,
                'type' => 'N/A',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
        ];
    }

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
        $table = $this->table('branches');
        // $options = $table->getAdapter()->getOptions(); // Removed
        // $options['identity_insert'] = true; // Removed
        // $table->getAdapter()->setOptions($options); // Removed

        foreach ($data as $record) {
            // If parent_id is based on a name that is also being inserted in this same seed run,
            // we need to ensure the parent is inserted first.
            // This simple loop might fail if `getBranchIdByName` is called for a branch not yet inserted.
            // A more robust solution would involve ordering $data or multiple insert passes.
            if (isset($record['parent_id_lookup_name'])) { // A temporary key to hold the name for lookup
                $record['parent_id'] = SeedHelpers::getBranchIdByName($record['parent_id_lookup_name']);
                unset($record['parent_id_lookup_name']);
            }
            $table->insert($record)->save(); // Insert one by one to allow TreeBehavior to work if applicable
        }
        // $table->insert($data)->save(); // Original batch insert, might be problematic with TreeBehavior and lookups

        //update kingdom to add type
        $branchTbl = TableRegistry::getTableLocator()->get('Branches');
        $kingdomBranch = $branchTbl->find()->where(['name' => 'Kingdom of Ansteorra'])->first();
        if ($kingdomBranch) {
            $kingdomBranch->type = 'Kingdom';
            $branchTbl->saveOrFail($kingdomBranch);
        }
    }
}