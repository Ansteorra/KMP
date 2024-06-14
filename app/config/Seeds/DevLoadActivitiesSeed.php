<?php

declare(strict_types=1);

use Migrations\AbstractSeed;
use Cake\I18n\DateTime;

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
                'term_length' => 4,
                'activity_group_id' => 1,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => 200,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 2,
                'name' => 'Armored Combat Field Marshal',
                'term_length' => 4,
                'activity_group_id' => 1,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => 201,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 3,
                'name' => 'Rapier Combat',
                'term_length' => 4,
                'activity_group_id' => 2,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => 202,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 4,
                'name' => 'Rapier Combat Field Marshal',
                'term_length' => 4,
                'activity_group_id' => 2,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => 203,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 5,
                'name' => 'Youth Boffer 1',
                'term_length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 6,
                'maximum_age' => 12,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => 204,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 6,
                'name' => 'Youth Boffer 2',
                'term_length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 10,
                'maximum_age' => 14,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => 205,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 7,
                'name' => 'Youth Boffer 3',
                'term_length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 13,
                'maximum_age' => 18,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => 206,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 8,
                'name' => 'Youth Boffer Marshal',
                'term_length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 16,
                'maximum_age' => 200,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => 207,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 9,
                'name' => 'Youth Boffer Junior Marshal',
                'term_length' => 4,
                'activity_group_id' => 3,
                'grants_role_id' => NULL,
                'minimum_age' => 12,
                'maximum_age' => 18,
                'num_required_authorizors' => 1,
                'num_required_renewers' => 1,
                'permission_id' => 208,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 10,
                'name' => 'Authorizing Rapier Marshal',
                'term_length' => 2,
                'activity_group_id' => 2,
                'grants_role_id' => 6,
                'minimum_age' => 18,
                'maximum_age' => 200,
                'num_required_authorizors' => 2,
                'num_required_renewers' => 1,
                'permission_id' => 209,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
        ];

        $table = $this->table('activities');
        $table->insert($data)->save();
    }
}