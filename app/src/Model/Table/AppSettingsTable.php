<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Datasource\EntityInterface;

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

    protected $_appSettingsCache = [];

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
        if ($result) {
            $this->clearAppSettingsCache();
        }
        return $result;
    }

    public function delete(EntityInterface $entity, array $options = []): bool
    {
        $result = parent::delete($entity, $options);
        if ($result) {
            $this->clearAppSettingsCache();
        }
        return $result;
    }
    public function getAppSetting($key, $default = null, $type = null)
    {
        $this->getAllAppSettings();
        if (isset($this->_appSettingsCache[$key])) {
            return $this->_appSettingsCache[$key];
        }
        $setting = $this->find()
            ->where(["name" => $key])
            ->first();
        if ($setting) {
            $this->_appSettingsCache[$key] = $setting->value;
            return $setting->value;
        }
        if ($default !== null) {
            $setting = $this->setAppSetting($key, $default, $type);
            return $default;
        } else {
            throw new \Exception("AppSetting $key not found");
        }
    }
    public function setAppSetting($key, $value, $type = null)
    {
        $setting = $this->find()
            ->where(["name" => $key])
            ->first();
        if (!$setting) {
            $setting = $this->newEmptyEntity();
            $setting->name = $key;
            $setting->type = $type;
        }
        $setting->value = $value;
        $this->save($setting);
        $this->_appSettingsCache[$key] = $value;
    }

    public function getAllAppSettings()
    {
        if (count($this->_appSettingsCache) > 0) {
            return $this->_appSettingsCache;
        }
        $settings = $this->find()->all();
        foreach ($settings as $setting) {
            $this->_appSettingsCache[$setting->name] = $setting->value;
        }
        //write the app settings to a local file real quick as yaml
        $yaml = yaml_emit($this->_appSettingsCache);
        file_put_contents(CONFIG . "../tmp/app_settings.yaml", $yaml);

        return $this->_appSettingsCache;
    }

    public function deleteAppSetting($key)
    {
        $setting = $this->find()
            ->where(["name" => $key])
            ->first();
        if ($setting) {
            $this->getTableLocator()->get("AppSettings")->delete($setting);
            unset($this->_appSettingsCache[$key]);
        }
    }

    public function clearAppSettingsCache()
    {
        $this->_appSettingsCache = [];
    }
}