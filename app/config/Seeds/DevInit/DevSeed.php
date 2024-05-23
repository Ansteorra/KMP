<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Role seed.
 */
class DevSeed extends AbstractSeed
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

        $authorization_groups = [
            ["name" => "Armored"],
            ["name" => "Rapier"],
            ["name" => "Youth Armored"],
        ];

        $this->table("authorization_groups")->insert($authorization_groups)->save();

        $roles = [
            ["name" => "Admin"],
            ["name" => "Secretary"],
            ["name" => "Kingdom Earl Marshal"],
            ["name" => "Kingdom Rapier Marshal"],
            ["name" => "Kingdom Armored Marshal"],
            ["name" => "Authorizing Rapier Marshal"],
            ["name" => "Authorizing Armored Marshal"],
            ["name" => "Authorizing Youth Armored Marshal"]
        ];
        $this->table("roles")->insert($roles)->save();

        $authtypes = [
            ["name"=>"Armored Combat", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "authorization_groups_id"=>1],
            ["name"=>"Armored Combat Field Marshal", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "authorization_groups_id"=>1],
            ["name"=>"Rapier Combat", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "authorization_groups_id"=>2],
            ["name"=>"Rapier Combat Field Marshal", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "authorization_groups_id"=>2],
            ["name"=>"Youth Boffer 1", "length"=>4, "minimum_age"=>6, "maximum_age"=>12, "authorization_groups_id"=>3],
            ["name"=>"Youth Boffer 2", "length"=>4, "minimum_age"=>10, "maximum_age"=>14, "authorization_groups_id"=>3],
            ["name"=>"Youth Boffer 3", "length"=>4, "minimum_age"=>13, "maximum_age"=>18, "authorization_groups_id"=>3],
            ["name"=>"Youth Boffer Marshal", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "authorization_groups_id"=>3],
            ["name"=>"Youth Boffer Junior Marshal", "length"=>4, "minimum_age"=>12, "maximum_age"=>18, "authorization_groups_id"=>3],
        ];
        $this->table("authorization_types")->insert($authtypes)->save();

        $permissions = [
            ["name" => "Is Super User", 'authorization_type_id' => NULL, 'system' => true, 'is_super_user' => true, 'require_active_membership' => true],
            ["name" => "Can Manage Roles", 'authorization_type_id' => NULL, 'system' => true, 'require_active_membership' => true],
            ["name" => "Can Manage Permissions", 'authorization_type_id' => NULL, 'system' => true, 'require_active_membership' => true],
            ["name" => "Can Manage Authorization Types", 'authorization_type_id' => NULL, 'system' => true, 'require_active_membership' => true],
            ["name" => "Can Manage Branches", 'authorization_type_id' => NULL, 'system' => true, 'require_active_membership' => true],
            ["name" => "Can Manage Settings", 'authorization_type_id' => NULL, 'system' => true, 'require_active_membership' => true],
            ["name" => "Can Manage members", 'authorization_type_id' => NULL, 'system' => true, 'require_active_membership' => true]
        ];

        $this->table("permissions")->insert($permissions)->save();

        $insertStatement = "INSERT INTO permissions (name, authorization_type_id,system) SELECT CONCAT('Can Authorize ', authorization_types.name) AS name, authorization_types.id as authorization_type_id, false as system FROM authorization_types";

        $count = $this->execute($insertStatement);

        $role_permissions = [
            ["role_id" => 1, "permission_id" => 1],
            ["role_id" => 1, "permission_id" => 2],
            ["role_id" => 1, "permission_id" => 3]
        ];
        $this->table("roles_permissions")->insert($role_permissions)->save();

        $branches = [
            ["name" => "Kingdom", "location"=>"Kingdom"],
            ["name" => "Region 1", "location"=> "Part of Kingdom","parent_id"=>1],
            ["name" => "Barony 1", "location"=> "A Local group","parent_id"=>2],
            ["name" => "Barony 2", "location"=> "A Local group 2","parent_id"=>2],
            ["name" => "Region 2", "location"=> "Part of Kingdom 2","parent_id"=>1],
            ["name" => "Barony 3", "location"=> "A Local group 2","parent_id"=>5],
            ["name" => "Shire 1", "location"=> "A sub local group 2","parent_id"=>6],
            ["name" => "Region 1 Kingdom Land", "location"=> "Part of Kingdom","parent_id"=>2],
            ["name" => "Region 2 Kingdom Land", "location"=> "Part of Kingdom","parent_id"=>5],
            ["name" => "Out of Kingdom", "location"=>"Out of Kingdom"],
        ];
        $this->table("branches")->insert($branches)->save();

        $dev_pass = md5("Password123");
        $members = [
            [
                'last_updated' => '2021-11-18 20:02:26',
                'password' => $dev_pass,
                'sca_name' => 'Admin von Admin',
                'first_name' => 'Addy',
                'middle_name' => '',
                'last_name' => 'Min',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '',
                'email_address' => 'admin@test.com',
                'membership_number' => '0',
                'membership_expires_on' => '2030-01-01',
                'branch_id' => '3',
                'parent_name' => '',
                'background_check_expires_on' => NULL,
                'hidden' => '0',
                'password_token' => NULL,
                'password_token_expires_on' => NULL,
                'last_login' => NULL,
                'last_failed_login' => NULL,
                'failed_login_attempts' => NULL,
                'birth_month' => 4,
                'birth_year' => 1977,
            ],
            [
                'last_updated' => '2021-11-18 20:02:26',
                'password' => $dev_pass,
                'sca_name' => 'Earl Realm',
                'first_name' => 'Kingdom',
                'middle_name' => '',
                'last_name' => 'Marshal',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '',
                'email_address' => 'Earl@test.com',
                'membership_number' => '0',
                'membership_expires_on' => NULL,
                'branch_id' => '4',
                'parent_name' => '',
                'background_check_expires_on' => NULL,
                'hidden' => '0',
                'password_token' => NULL,
                'password_token_expires_on' => NULL,
                'last_login' => NULL,
                'last_failed_login' => NULL,
                'failed_login_attempts' => NULL,
                'birth_month' => 4,
                'birth_year' => 1977,
            ],
            [
                'last_updated' => '2021-11-18 20:02:26',
                'password' => $dev_pass,
                'sca_name' => 'Stabby McStab',
                'first_name' => 'Stan',
                'middle_name' => '',
                'last_name' => 'Rapier',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '',
                'email_address' => 'Stan@test.com',
                'membership_number' => '0',
                'membership_expires_on' => NULL,
                'branch_id' => '7',
                'parent_name' => '',
                'background_check_expires_on' => NULL,
                'hidden' => '0',
                'password_token' => NULL,
                'password_token_expires_on' => NULL,
                'last_login' => NULL,
                'last_failed_login' => NULL,
                'failed_login_attempts' => NULL,
                'birth_month' => 4,
                'birth_year' => 1977,
            ]
        ];
        $table = $this->table('members');
        $table->insert($members)->save();        
        $member_roles = [
            ["member_id" => 1,"role_id" => 1, 'approver_id'=>1],
            ["member_id" => 2,"role_id" => 3, 'approver_id'=>1],
        ];
        $this->table("member_roles")->insert($member_roles)->save();

        $member_notes = [
            [   
                "topic_model"  => "Members",
                "topic_id" => 1,
                "body" => "This is a note",
                "author_id" => 1,
                "private" => 0,
                "subject" => "Note Subject"
            ],
            [   
                "topic_model"  => "Members",
                "topic_id" => 1,
                "body" => "This is a note 2",
                "author_id" => 1,
                "private" => 0,
                "subject" => "Note Subject 2"
            ],
        ];
        $this->table("notes")->insert($member_notes)->save();


    }
}
