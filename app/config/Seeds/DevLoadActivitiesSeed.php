<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

require_once __DIR__ . '/Lib/SeedHelpers.php';

/**
 * Activities seed.
 */
class DevLoadActivitiesSeed extends BaseSeed
{

    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        $createdByMemberId = SeedHelpers::getMemberId('admin@test.com'); // Assuming '1' referred to this member

        return [
            [
                // 'id' => 1, // Removed
                'name' => 'Armored Combat',
                'term_length' => 48,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Armored'),
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Armored Combat'),
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 2, // Removed
                'name' => 'Armored Combat Field Marshal',
                'term_length' => 48,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Armored'),
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Armored Combat Field Marshal'),
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 3, // Removed
                'name' => 'Rapier Combat',
                'term_length' => 48,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Rapier'),
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Rapier Combat'),
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 4, // Removed
                'name' => 'Rapier Combat Field Marshal',
                'term_length' => 48,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Rapier'),
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Rapier Combat Field Marshal'),
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 5, // Removed
                'name' => 'Youth Boffer 1',
                'term_length' => 48,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Youth Armored'),
                'grants_role_id' => NULL,
                'minimum_age' => 6,
                'maximum_age' => 12,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Youth Boffer 1'),
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 6, // Removed
                'name' => 'Youth Boffer 2',
                'term_length' => 48,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Youth Armored'),
                'grants_role_id' => NULL,
                'minimum_age' => 10,
                'maximum_age' => 14,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Youth Boffer 2'),
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 7, // Removed
                'name' => 'Youth Boffer 3',
                'term_length' => 48,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Youth Armored'),
                'grants_role_id' => NULL,
                'minimum_age' => 13,
                'maximum_age' => 18,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Youth Boffer 3'),
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 8, // Removed
                'name' => 'Youth Boffer Marshal',
                'term_length' => 48,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Youth Armored'),
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Youth Boffer Marshal'),
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 9, // Removed
                'name' => 'Youth Boffer Junior Marshal',
                'term_length' => 48,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Youth Armored'),
                'grants_role_id' => NULL,
                'minimum_age' => 12,
                'maximum_age' => 18,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Youth Boffer Junior Marshal'),
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                // 'id' => 10, // Removed
                'name' => 'Authorizing Rapier Marshal',
                'term_length' => 24,
                'activity_group_id' => SeedHelpers::getActivityGroupId('Rapier'),
                'grants_role_id' => SeedHelpers::getRoleId('Authorizing Rapier Marshal'),
                'minimum_age' => 18,
                'maximum_age' => 200,
                'num_required_authorizors' => 2,
                'num_required_renewers' => 1,
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Authorizing Rapier Marshal'),
                'deleted' => NULL,
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
        $table = $this->table('activities_activities');
        // $options = $table->getAdapter()->getOptions(); // Removed
        // $options['identity_insert'] = true; // Removed
        // $table->getAdapter()->setOptions($options); // Removed
        $table->insert($data)->save();
    }
}
