<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\KMP\PermissionsLoader;
use Cake\ORM\TableRegistry;
use App\Model\Entity\Member;
use Cake\Event\Event;
use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;

use Cake\Database\Schema\TableSchemaInterface;
use ArrayObject;

/**
 * Members Model
 *
 * @property \App\Model\Table\PendingAuthorizationsTable&\Cake\ORM\Association\HasMany $PendingAuthorizations
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsToMany $Roles
 *
 * @method \App\Model\Entity\Member newEmptyEntity()
 * @method \App\Model\Entity\Member newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Member> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Member get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Member findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Member patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Member> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Member|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Member saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member> deleteManyOrFail(iterable $entities, array $options = [])
 */
class MembersTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable("members");
        $this->setDisplayField("sca_name");
        $this->setPrimaryKey("id");

        $this->belongsToMany("Roles", [
            "through" => "MemberRoles",
        ]);

        $this->hasMany("MemberRoles", [
            "foreignKey" => "member_id",
        ]);
        $this->hasMany("CurrentMemberRoles", [
            "className" => "MemberRoles",
            "finder" => "current",
            "foreignKey" => "member_id",
        ]);
        $this->hasMany("UpcomingMemberRoles", [
            "className" => "MemberRoles",
            "finder" => "upcoming",
            "foreignKey" => "member_id",
        ]);
        $this->hasMany("PreviousMemberRoles", [
            "className" => "MemberRoles",
            "finder" => "previous",
            "foreignKey" => "member_id",
        ]);

        $this->belongsTo("Branches", [
            "className" => "Branches",
            "foreignKey" => "branch_id",
        ]);
        $this->belongsTo("Parents", [
            "className" => "Members",
            "foreignKey" => "parent_id",
        ]);
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
        $this->addBehavior("JsonField");
    }

    public function getSchema(): TableSchemaInterface
    {
        $schema = parent::getSchema();
        $schema->setColumnType('additional_info', 'json');

        return $schema;
    }


    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator->dateTime("modified")->notEmptyDateTime("modified");

        $validator
            ->scalar("password")
            ->maxLength("password", 33)
            ->requirePresence("password", "create")
            ->notEmptyString("password");

        $validator
            ->scalar("sca_name")
            ->minLength("sca_name", 3)
            ->maxLength("sca_name", 50)
            ->notEmptyString("sca_name");

        $validator
            ->scalar("first_name")
            ->maxLength("first_name", 30)
            ->requirePresence("first_name", "create")
            ->notEmptyString("first_name");

        $validator
            ->scalar("middle_name")
            ->maxLength("middle_name", 30)
            ->allowEmptyString("middle_name");

        $validator
            ->scalar("last_name")
            ->maxLength("last_name", 30)
            ->requirePresence("last_name", "create")
            ->notEmptyString("last_name");

        $validator
            ->scalar("street_address")
            ->maxLength("street_address", 75)
            ->requirePresence("street_address", "create")
            ->allowEmptyString("street_address");

        $validator
            ->scalar("city")
            ->maxLength("city", 30)
            ->requirePresence("city", "create")
            ->allowEmptyString("city");

        $validator
            ->scalar("state")
            ->maxLength("state", 2)
            ->requirePresence("state", "create")
            ->allowEmptyString("state");

        $validator
            ->scalar("zip")
            ->maxLength("zip", 5)
            ->requirePresence("zip", "create")
            ->allowEmptyString("zip");

        $validator
            ->scalar("phone_number")
            ->maxLength("phone_number", 15)
            ->requirePresence("phone_number", "create")
            ->allowEmptyString("phone_number");

        $validator
            ->scalar("email_address")
            ->maxLength("email_address", 50)
            ->requirePresence("email_address", "create")
            ->notEmptyString("email_address")
            ->add("email_address", "unique", [
                "rule" => "validateUnique",
                "provider" => "table",
            ]);

        $validator->allowEmptyString("membership_number");

        $validator
            ->date("membership_expires_on")
            ->allowEmptyDate("membership_expires_on");

        $validator
            ->scalar("parent_name")
            ->maxLength("parent_name", 50)
            ->allowEmptyString("parent_name");

        $validator
            ->date("background_check_expires_on")
            ->allowEmptyDate("background_check_expires_on");

        $validator
            ->scalar("password_token")
            ->maxLength("password_token", 255)
            ->allowEmptyString("password_token");

        $validator
            ->dateTime("password_token_expires_on")
            ->allowEmptyDateTime("password_token_expires_on");

        $validator->dateTime("last_login")->allowEmptyDateTime("last_login");

        $validator
            ->dateTime("last_failed_login")
            ->allowEmptyDateTime("last_failed_login");

        $validator
            ->integer("failed_login_attempts")
            ->allowEmptyString("failed_login_attempts");

        $validator->integer("birth_month")->notEmptyString("birth_month");

        $validator->integer("birth_year")->notEmptyString("birth_year");

        $validator
            ->dateTime("deleted_date")
            ->allowEmptyDateTime("deleted_date");

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(["email_address"]), ["errorField" => "email_address"]);

        return $rules;
    }

    public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        $entity->ageUpReview();
    }

    static function getValidationQueueCount(): int
    {
        // Get the count of pending validations  based on the members status
        $membersTable = TableRegistry::getTableLocator()->get("Members");
        return $membersTable->find()
            ->where([
                "Members.deleted IS" => null,
                'OR' => [
                    [
                        'Members.status' => Member::STATUS_ACTIVE,
                        'Members.membership_card_path IS NOT' => null
                    ],
                    ['Members.status IN' => [
                        Member::STATUS_UNVERIFIED_MINOR,
                        Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
                        Member::STATUS_MINOR_PARENT_VERIFIED,
                    ]]
                ]
            ])->count();
    }
}