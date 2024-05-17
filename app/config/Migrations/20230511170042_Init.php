<?php
use Migrations\AbstractMigration;

class Init extends AbstractMigration
{

    public bool $autoId = false;

    public function up()
    {

        //--------------------------- Configuration Schema -------------------------//
        $this->table('branches')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 10,
                'null' => false,
                'signed' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 40,
                'null' => false,
            ])
            ->addColumn('location', 'string', [
                'default' => null,
                'limit' => 40,
                'null' => false,
            ])
            ->addColumn('parent_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
            ])
            ->addIndex(
                [
                    'name',
                ],
                ['unique' => true]
            )
            ->create();

        $this->table('martial_groups')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->create();
            
        $this->table('roles')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addIndex(
                [
                    'name',
                ],
                ['unique' => true]
            )
            ->create();

        $this->table('authorization_types')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('length', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('martial_groups_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('minimum_age', 'integer', [
                'default' => null,
                'limit' => 2,
                'null' => true,
            ])
            ->addColumn('maximum_age', 'integer', [
                'default' => null,
                'limit' => 2,
                'null' => true,
            ])
            ->addColumn('num_required_authorizors', 'integer', [
                'default' => 1,
                'limit' => 2,
                'null' => false,
            ])
            ->addIndex(
                [
                    'name',
                ],
                ['unique' => true]
            )
            ->addIndex(
                [
                    'martial_groups_id',
                ]
            )
            ->create();

        $this->table('permissions')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('authorization_type_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
            ])            
            ->addColumn('require_active_membership', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('require_active_background_check', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('require_min_age', 'integer', [
                'default' => 0,
                'limit' => 2,
                'null' => false,
            ])
            ->addIndex(
                [
                    'authorization_type_id',
                ]
            )
            ->addColumn('system', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('is_super_user', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('roles_permissions')
            ->addColumn('permission_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('role_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(
                [
                    'permission_id','role_id'
                ]
            )
            ->create();

        $this->table('app_settings')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('value', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->create();

            //--------------------------------- Operational Tables -----------------------------------
        $this->table('Members')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('last_updated', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('password', 'string', [
                'default' => null,
                'limit' => 512,
                'null' => false,
            ])
            ->addColumn('sca_name', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('first_name', 'string', [
                'default' => null,
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('middle_name', 'string', [
                'default' => null,
                'limit' => 30,
                'null' => true,
            ])
            ->addColumn('last_name', 'string', [
                'default' => null,
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('street_address', 'string', [
                'default' => null,
                'limit' => 75,
                'null' => false,
            ])
            ->addColumn('city', 'string', [
                'default' => null,
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('state', 'string', [
                'default' => null,
                'limit' => 2,
                'null' => false,
            ])
            ->addColumn('zip', 'string', [
                'default' => null,
                'limit' => 5,
                'null' => false,
            ])
            ->addColumn('phone_number', 'string', [
                'default' => null,
                'limit' => 15,
                'null' => false,
            ])
            ->addColumn('email_address', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('membership_number', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('membership_expires_on', 'date', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('branch_name', 'string', [
                'default' => null,
                'limit' => 40,
                'null' => true,
            ])
            ->addColumn('notes', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('parent_name', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('background_check_expires_on', 'date', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('hidden', 'boolean', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('password_token', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('password_token_expires_on', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('last_login', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('last_failed_login', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('failed_login_attempts', 'integer', [
                'default' => null,
                'limit' => 2,
                'null' => true,
            ])
            ->addColumn('birth_month', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('birth_year', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
            ])
            ->addIndex(
                [
                    'branch_name',
                ]
            )
            ->addColumn('deleted_date', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('Member_authorization_types')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('Member_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('authorization_type_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('authorized_by_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('expires_on', 'date', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('start_on', 'date', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                [
                    'authorization_type_id',
                ]
            )
            ->addIndex(
                [
                    'Member_id',
                ]
            )
            ->create();

        
        

        $this->table('Member_roles')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('Member_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('role_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('ended_on', 'date', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('start_on', 'date', [
                'default' => 'CURRENT_TIMESTAMP',
                'limit' => null,
                'null' => false,
            ])            
            ->addColumn('authorized_by_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addIndex(
                [
                    'Member_id',
                ]
            )
            ->addIndex(
                [
                    'role_id',
                ]
            )
            ->addIndex(
                [
                    'authorized_by_id',
                ]
            )
            ->create();

        $this->table('pending_authorizations')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('Member_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('Member_marshal_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('authorization_type_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('authorization_token', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('requested_on', 'date', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('responded_on', 'date', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('authorization_result', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addIndex(
                [
                    'authorization_type_id',
                ]
            )
            ->addIndex(
                [
                    'Member_id',
                ]
            )
            ->addIndex(
                [
                    'Member_marshal_id',
                ]
            )
            ->create();

        
        //-------------------------------Relationships-------------------------------

        $this->table('authorization_types')
            ->addForeignKey(
                'martial_groups_id',
                'martial_groups',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'NO_ACTION'
                ]
            )
            ->update();

        $this->table('Member_authorization_types')
            ->addForeignKey(
                'authorization_type_id',
                'authorization_types',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->addForeignKey(
                'Member_id',
                'Members',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->addForeignKey(
                'authorized_by_id',
                'Members',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'NO_ACTION'
                ]
            )
            ->update();

        $this->table('Members')
            ->addForeignKey(
                'branch_name',
                'branches',
                'name',
                [
                    'update' => 'CASCADE',
                    'delete' => 'SET_NULL'
                ]
            )
            ->update();

        $this->table('Member_roles')
            ->addForeignKey(
                'Member_id',
                'Members',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->addForeignKey(
                'role_id',
                'roles',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->addForeignKey(
                'authorized_by_id',
                'Members',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->update();

        $this->table('pending_authorizations')
            ->addForeignKey(
                'authorization_type_id',
                'authorization_types',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->addForeignKey(
                'Member_id',
                'Members',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->addForeignKey(
                'Member_marshal_id',
                'Members',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->update();

        $this->table('permissions')
            ->addForeignKey(
                'authorization_type_id',
                'authorization_types',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->update();

            $this->table('roles_permissions')
            ->addForeignKey(
                'role_id',
                'roles',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->addForeignKey(
                'permission_id',
                'permissions',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->update();
    }

    public function down()
    {
        $this->table('authorization_types')
            ->dropForeignKey(
                'martial_groups_id'
            )->save();

        $this->table('Member_authorization_types')
            ->dropForeignKey(
                'authorization_type_id'
            )
            ->dropForeignKey(
                'Member_id'
            )->save();

        $this->table('Members')
            ->dropForeignKey(
                'branch_name'
            )->save();

        $this->table('Member_roles')
            ->dropForeignKey(
                'Member_id'
            )
            ->dropForeignKey(
                'role_id'
            )->save();

        $this->table('pending_authorizations')
            ->dropForeignKey(
                'authorization_type_id'
            )
            ->dropForeignKey(
                'Member_id'
            )
            ->dropForeignKey(
                'Member_marshal_id'
            )->save();
        $this->table('roles_permissions')
            ->dropForeignKey(
                'role_id'
            )->save();

        $this->table('permissions')
            ->dropForeignKey(
                'authorization_type_id'
            )->save();

        $this->table('roles_permissions')->drop()->save();
        $this->table('permissions')->drop()->save();
        $this->table('Member_roles')->drop()->save();
        $this->table('Member_authorization_types')->drop()->save();
        $this->table('pending_authorizations')->drop()->save();
        $this->table('Members')->drop()->save();
        $this->table('authorization_types')->drop()->save();
        $this->table('branches')->drop()->save();
        $this->table('martial_groups')->drop()->save();
        $this->table('roles')->drop()->save();
        $this->table('app_settings')->drop()->save();

        
    }
}
