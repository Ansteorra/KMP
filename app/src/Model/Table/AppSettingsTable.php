<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use App\Model\Entity\AppSetting;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Datasource\EntityInterface;
use Cake\Cache\Cache;
use Cake\Log\Log;

/**
 * AppSettings Model
 *
 * @method \App\Model\Entity\AppSetting newEmptyEntity()
 * @method \App\Model\Entity\AppSetting newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\AppSetting> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\AppSetting get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\AppSetting findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\AppSetting patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\AppSetting> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\AppSetting|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\AppSetting saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\AppSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AppSetting>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AppSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AppSetting> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AppSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AppSetting>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\AppSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\AppSetting> deleteManyOrFail(iterable $entities, array $options = [])
 */
class AppSettingsTable extends Table
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

        $this->setTable("app_settings");
        $this->setDisplayField("name");
        $this->setPrimaryKey("id");
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar("name")
            ->maxLength("name", 255)
            ->requirePresence("name", "create")
            ->notEmptyString("name")
            ->add("name", "unique", [
                "rule" => "validateUnique",
                "provider" => "table",
            ]);

        $validator
            ->scalar("value")
            ->allowEmptyString("value");

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(["name"]), ["errorField" => "name"]);

        return $rules;
    }

    public function save(
        EntityInterface $entity,
        array $options = [],
    ): EntityInterface|false {
        $result = parent::save($entity, $options);
        return $result;
    }

    public function delete(EntityInterface $entity, array $options = []): bool
    {
        $result = parent::delete($entity, $options);
        return $result;
    }

    /**
     * Get an app setting by name, using cache.
     *
     * @param string $name The name of the setting.
     * @return mixed The value of the setting.
     */
    public function getSetting(string $name)
    {
        //Log::debug("Getting setting $name");
        $cacheKey = 'app_setting_' . $name;
        $setting = Cache::read($cacheKey, 'default');

        if ($setting == null) {
            $setting = $this->find()
                ->where(['name' => $name])
                ->first();

            if ($setting) {
                Cache::write($cacheKey, $setting->value, 'default');
                return $setting->value;
            }

            return null;
        }

        return $setting;
    }

    /**
     * Update an app setting and cache the new value.
     *
     * @param string $name The name of the setting.
     * @param ?string $type The new value of the setting.
     * @param mixed $value The new value of the setting.
     * @return bool True on success, false on failure.
     */
    public function updateSetting(string $name, ?string $type, $value, $required = false): bool
    {
        //Log::debug("Writing setting $name");
        $setting = $this->find()
            ->where(['name' => $name])
            ->first();

        if ($setting) {
            $setting->value = $value;
            $setting->type = $type;
            $setting->required = $required;
        } else {
            $setting = $this->newEmptyEntity();
            $setting->name = $name;
            $setting->type = $type;
            $setting->value = $value;
            $setting->required = $required;
        }
        if ($this->save($setting)) {
            $cacheKey = 'app_setting_' . $name;
            Cache::write($cacheKey, $setting->value, 'default');
            return true;
        }

        return false;
    }

    /**
     * Update an app setting and cache the new value.
     *
     * @param string $name The name of the setting.
     * @return bool True on success, false on failure.
     */
    public function deleteSetting(string $name, bool $forceDelete = false): bool
    {
        $setting = $this->find()
            ->where(['name' => $name])
            ->first();

        if ($setting) {
            if ($setting->required && !$forceDelete) {
                return false;
            }
            if ($this->delete($setting)) {
                $cacheKey = 'app_setting_' . $name;
                Cache::delete($cacheKey, 'default');
                return true;
            }
        }

        return false;
    }

    public function getAppSetting($key, $default = null, $type = null, $required = false)
    {
        $setting = $this->getSetting($key);
        if ($setting) {
            return $setting;
        }
        if ($default !== null) {
            $setting = $this->setAppSetting($key, $default, $type, $required);
            return $default;
        } else {
            throw new \Exception("AppSetting $key not found");
        }
    }

    public function setAppSetting($key, $value, $type = null, $required = false): bool
    {
        return $this->updateSetting($key, $type, $value, $required);
    }

    public function deleteAppSetting($key, bool $forceDelete = false): bool
    {
        return $this->deleteSetting($key, $forceDelete);
    }

    //TODO: Create a caching strategy for this
    public function getAllAppSettingsStartWith($key): array
    {
        $settings = $this->find()
            ->where(['name LIKE' => $key . '%'])
            ->all();
        $return = [];
        foreach ($settings as $setting) {
            $return[$setting->name] = $setting->value;
        }
        return $return;
    }
}