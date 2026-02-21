<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\Datasource\EntityInterface;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\Utility\Security;
use Cake\Validation\Validator;
use Exception;

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
class AppSettingsTable extends BaseTable
{
    private const PASSWORD_VALUE_PREFIX = 'enc:v1:';

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
        $this->addBehavior('Timestamp');
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', [
                'rule' => 'validateUnique',
                'provider' => 'table',
            ]);

        $validator
            ->scalar('value')
            ->allowEmptyString('value');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }

    public function save(
        EntityInterface $entity,
        array $options = [],
    ): EntityInterface|false {
        $entity->saving = true;
        $result = parent::save($entity, $options);
        $entity->saving = false;

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
    public function getSetting(string $name): mixed
    {
        $isSensitive = $this->isSensitiveSetting($name);
        $cacheKey = 'app_setting_' . $name;
        $setting = $isSensitive ? null : Cache::read($cacheKey, 'default');

        if ($setting === null) {
            $settingEntity = $this->find()
                ->where(['name' => $name])
                ->first();

            if ($settingEntity) {
                $resolvedValue = $this->resolveValueForRead($settingEntity->type ?? 'string', $settingEntity->value);
                if (!$isSensitive) {
                    Cache::write($cacheKey, $resolvedValue, 'default');
                }

                if (
                    $name === 'Backup.encryptionKey'
                    && ($settingEntity->type ?? 'string') !== 'password'
                    && is_string($resolvedValue)
                    && $resolvedValue !== ''
                ) {
                    $this->updateSetting($name, 'password', $resolvedValue, false);
                }

                return $resolvedValue;
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
    public function updateSetting(string $name, ?string $type, mixed $value, $required = false): bool
    {
        //Log::debug("Writing setting $name");
        $setting = $this->find()
            ->where(['name' => $name])
            ->first();
        $effectiveType = $type ?? ($setting?->type ?? 'string');
        $encodedValue = $this->resolveValueForWrite($effectiveType, $value, $setting !== null);

        if ($setting) {
            $setting->saving = true;
            if ($encodedValue !== null) {
                $setting->value = $encodedValue;
            }
            $setting->type = $effectiveType;
            $setting->required = $required;
        } else {
            $setting = $this->newEmptyEntity();
            $setting->saving = true;
            $setting->name = $name;
            $setting->type = $effectiveType;
            $setting->value = $encodedValue;
            $setting->required = $required;
        }
        if ($this->save($setting)) {
            if (!$this->isSensitiveSetting($name)) {
                $cacheKey = 'app_setting_' . $name;
                Cache::write($cacheKey, $this->resolveValueForRead($effectiveType, $setting->value), 'default');
            }

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
                if (!$this->isSensitiveSetting($name)) {
                    $cacheKey = 'app_setting_' . $name;
                    Cache::delete($cacheKey, 'default');
                }

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
            throw new Exception("AppSetting $key not found");
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

    private function isSensitiveSetting(string $name): bool
    {
        return $name === 'Backup.encryptionKey';
    }

    private function resolveValueForWrite(string $type, mixed $value, bool $settingExists): mixed
    {
        if ($type !== 'password') {
            return $value;
        }

        if (!is_string($value)) {
            $value = (string)$value;
        }

        if ($value === '' && $settingExists) {
            return null;
        }

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, self::PASSWORD_VALUE_PREFIX)) {
            return $value;
        }

        $encryptionKey = $this->getPasswordEncryptionKey();
        $encrypted = Security::encrypt($value, $encryptionKey);

        return self::PASSWORD_VALUE_PREFIX . base64_encode($encrypted);
    }

    private function resolveValueForRead(string $type, mixed $value): mixed
    {
        if ($type !== 'password') {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return '';
        }
        if (!str_starts_with($value, self::PASSWORD_VALUE_PREFIX)) {
            return $value;
        }

        $encoded = substr($value, strlen(self::PASSWORD_VALUE_PREFIX));
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            Log::warning('Invalid encoded password-type app setting value encountered.');

            return '';
        }

        $decrypted = Security::decrypt($decoded, $this->getPasswordEncryptionKey());
        if ($decrypted === null) {
            Log::warning('Could not decrypt password-type app setting value.');

            return '';
        }

        return $decrypted;
    }

    private function getPasswordEncryptionKey(): string
    {
        $key = (string)env('APPSETTING_PASSWORD_KEY', Security::getSalt());
        if (strlen($key) < 32) {
            $key = str_pad($key, 32, '0');
        }

        return $key;
    }
}
