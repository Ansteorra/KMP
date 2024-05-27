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
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('app_settings');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('value')
            ->maxLength('value', 255)
            ->allowEmptyString('value');

        return $validator;
    }

    public function save (EntityInterface $entity, array $options = []): EntityInterface|false{
        $result = parent::save($entity, $options);
        if($result){
            $this->clearAppSettingsCache();
        }
        return $result;
    }

    public function delete(EntityInterface $entity, array $options = []): bool{
        $result = parent::delete($entity, $options);
        if($result){
            $this->clearAppSettingsCache();
        }
        return $result;
    }

    protected $_appSettingsCachs = [];
    public function getAppSetting($key,$default = '')
    {
        if (isset($this->_appSettingsCachs[$key])) {
            return $this->_appSettingsCachs[$key];
        }
        $setting = $this->find()->where(['name' => $key])->first();
        if (!$setting) {
            $setting = $this->setAppSetting($key, $default);
            return $default;
        }else{
            $this->_appSettingsCachs[$key] = $setting->value;
            return $setting->value;
        }
        
    }   
    public function setAppSetting($key, $value)
    {
        $setting = $this->find()->where(['name' => $key])->first();
        if (!$setting) {
            $setting = $this->newEmptyEntity();
            $setting->name = $key;
        }
        $setting->value = $value;
        $this->save($setting);
        $this->appSettingsCachs[$key] = $value;
    }

    public function getAllAppSettings(){
        if($this->appSettingsCachs->count() > 0){
            return $this->appSettingsCachs;
        }
        $settings = $this->find()->all();
        foreach ($settings as $setting) {
            $this->appSettingsCachs[$setting->name] = $setting->value;
        }
        return $this->appSettingsCachs;
        
    }

    public function deleteAppSetting($key){
        $setting =$this->find()->where(['name' => $key])->first();
        if ($setting) {
            $this->getTableLocator()->get('AppSettings')->delete($setting);
            unset($this->appSettingsCachs[$key]);
        }
    }

    public function clearAppSettingsCache(){
        $this->appSettingsCachs = [];
    }
}
