<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Activities seed.
 */
class DevLoadActivitiesSeed extends AbstractSeed
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
        $data = [
            [
                'id' => 1,
                'name' => 'Armored Combat',
                'length' => 4,
                'activity_group_id' => 1,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 2,
                'name' => 'Armored Combat Field Marshal',
                'length' => 4,
                'activity_group_id' => 1,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 3,
                'name' => 'Rapier Combat',
                'length' => 4,
                'activity_group_id' => 2,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 4,
                'name' => 'Rapier Combat Field Marshal',
                'length' => 4,
                'activity_group_id' => 2,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 5,
                'name' => 'Youth Boffer 1',
                'length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 6,
                'maximum_age' => 12,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 6,
                'name' => 'Youth Boffer 2',
                'length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 10,
                'maximum_age' => 14,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 7,
                'name' => 'Youth Boffer 3',
                'length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 13,
                'maximum_age' => 18,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 8,
                'name' => 'Youth Boffer Marshal',
                'length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 9,
                'name' => 'Youth Boffer Junior Marshal',
                'length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 12,
                'maximum_age' => 18,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 10,
                'name' => 'Authorizing Rapier Marshal',
                'length' => 2,
                'activity_group_id' => 2,
                'grants_role_id' => 6,
                'minimum_age' => 18,
                'maximum_age' => 200,
                'num_required_authorizors' => 2,
                'num_required_renewers' => 1,
                'deleted' => NULL,
            ],
        ];

        $table = $this->table('activities');
        $table->insert($data)->save();
    }
}
