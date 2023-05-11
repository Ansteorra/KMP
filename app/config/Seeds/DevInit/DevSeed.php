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

        $martial_groups = [
            ["name" => "Armored"],
            ["name" => "Rapier"],
            ["name" => "Youth Armored"],
        ];

        $this->table("martial_groups")->insert($martial_groups)->save();

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
            ["name"=>"Armored Combat", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "martial_groups_id"=>1],
            ["name"=>"Armored Combat Field Marshal", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "martial_groups_id"=>1],
            ["name"=>"Rapier Combat", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "martial_groups_id"=>2],
            ["name"=>"Rapier Combat Field Marshal", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "martial_groups_id"=>2],
            ["name"=>"Youth Boffer 1", "length"=>4, "minimum_age"=>6, "maximum_age"=>12, "martial_groups_id"=>3],
            ["name"=>"Youth Boffer 2", "length"=>4, "minimum_age"=>10, "maximum_age"=>14, "martial_groups_id"=>3],
            ["name"=>"Youth Boffer 3", "length"=>4, "minimum_age"=>13, "maximum_age"=>18, "martial_groups_id"=>3],
            ["name"=>"Youth Boffer Marshal", "length"=>4, "minimum_age"=>16, "maximum_age"=>200, "martial_groups_id"=>3],
            ["name"=>"Youth Boffer Junior Marshal", "length"=>4, "minimum_age"=>12, "maximum_age"=>18, "martial_groups_id"=>3],
        ];
        $this->table("authorization_types")->insert($authtypes)->save();

        $insertStatement = "INSERT INTO roles_authorization_types (authorization_type_id, role_id) SELECT authorization_types.id as authorization_type_id, roles.id as role_id FROM authorization_types, roles where authorization_types.name in (%s) and roles.name = '%s'";
        $authRoles = 
        [
            ["role" => "Admin", "auths" => "'Armored Combat', 'Armored Combat Field Marshal', 'Rapier Combat','Rapier Combat Field Marshal','Youth Boffer 1','Youth Boffer 2','Youth Boffer 3','Youth Boffer Marshal','Youth Boffer Junior Marshal'"],
            ["role" => "Kingdom Earl Marshal", "auths" => "'Armored Combat', 'Armored Combat Field Marshal', 'Rapier Combat','Rapier Combat Field Marshal','Youth Boffer 1','Youth Boffer 2','Youth Boffer 3','Youth Boffer Marshal','Youth Boffer Junior Marshal'"],
            ["role" => "Secretary", "auths" => "'Armored Combat', 'Armored Combat Field Marshal', 'Rapier Combat','Rapier Combat Field Marshal','Youth Boffer 1','Youth Boffer 2','Youth Boffer 3','Youth Boffer Marshal','Youth Boffer Junior Marshal'"],
            ["role" => "Authorizing Armored Marshal", "auths" => "'Armored Combat', 'Armored Combat Field Marshal'"],
            ["role" => "Authorizing Rapier Marshal", "auths" => " 'Rapier Combat','Rapier Combat Field Marshal'"],
            ["role" => "Authorizing Youth Armored Marshal", "auths" => "'Youth Boffer 1','Youth Boffer 2','Youth Boffer 3','Youth Boffer Marshal','Youth Boffer Junior Marshal'"],
        ];
        foreach($authRoles as $auths){
            $exeStatement = sprintf($insertStatement,$auths["auths"],$auths["role"]);
            $count = $this->execute($exeStatement);
        }

        $branches = [
            ["name" => "Kingdom", "location"=>"Kingdom"],
            ["name" => "Region", "location"=> "Part of Kingdom","branch_id"=>1],
            ["name" => "Barony", "location"=> "A Local group","branch_id"=>2],
        ];
        $this->table("branches")->insert($branches)->save();

        $dev_pass = md5("Password123");
        $participants = [
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
                'membership_expires_on' => NULL,
                'branch_name' => 'Barony',
                'notes' => '',
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
                'branch_name' => 'Barony',
                'notes' => '',
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
                'branch_name' => 'Barony',
                'notes' => '',
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

        $table = $this->table('participants');
        $table->insert($participants)->save();        
        $participant_roles = [
            ["participant_id" => 1,"role_id" => 1],
            ["participant_id" => 2,"role_id" => 3],
        ];
        $this->table("participants_roles")->insert($participant_roles)->save();

    }
}
