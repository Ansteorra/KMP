<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Services\Cache\TenantAwareCache;
use Cake\Cache\Cache;
use Cake\Database\Exception\DatabaseException;
use Cake\Database\Exception\QueryException;
use Cake\Datasource\EntityInterface;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\Utility\Security;
use Cake\Validation\Validator;
use Exception;
use finfo;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\UploadedFileInterface;

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
    private const MAX_ASSET_BYTES = 2097152;
    private const ALL_SETTINGS_CACHE_KEY = 'app_settings_all';
    private const IMAGE_MIME_TYPES = [
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const FILE_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
    ];

    /**
     * Request-local sensitive settings keyed by tenant-aware cache key.
     *
     * @var array<string, mixed>
     */
    private static array $sensitiveSettingRequestCache = [];

    /**
     * Request-local all-settings payload keyed by tenant-aware cache key.
     *
     * @var array<string, array{values: array<string, mixed>, raw: array<string, mixed>}>
     */
    private static array $settingsPayloadRequestCache = [];

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
                'rule' => ['validateUnique'],
                'provider' => 'table',
            ]);

        $validator
            ->scalar('value')
            ->allowEmptyString('value');

        return $validator;
    }

    /**
     * Define application-level rules.
     *
     * @param \Cake\ORM\RulesChecker $rules
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(
            $rules->isUnique(['name']),
            ['errorField' => 'name'],
        );

        return $rules;
    }

    /**
     * Save.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array $options
     * @return \Cake\Datasource\EntityInterface|false
     */
    public function save(
        EntityInterface $entity,
        array $options = [],
    ): EntityInterface|false {
        $entity->saving = true;
        $result = parent::save($entity, $options);
        $entity->saving = false;

        return $result;
    }

    /**
     * Delete.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array $options
     * @return bool
     */
    public function delete(EntityInterface $entity, array $options = []): bool
    {
        $result = parent::delete($entity, $options);

        return $result;
    }

    /**
     * Clear app setting caches after direct saves.
     *
     * @param mixed $event Event
     * @param \Cake\Datasource\EntityInterface $entity Saved setting
     * @param mixed $options Save options
     * @return void
     */
    public function afterSave($event, $entity, $options): void
    {
        parent::afterSave($event, $entity, $options);
        $this->clearSettingCaches((string)$entity->name);
    }

    /**
     * Clear app setting caches after direct deletes.
     *
     * @param mixed $event Event
     * @param \Cake\Datasource\EntityInterface $entity Deleted setting
     * @param mixed $options Delete options
     * @return void
     */
    public function afterDelete($event, $entity, $options): void
    {
        parent::afterDelete($event, $entity, $options);
        $this->clearSettingCaches((string)$entity->name);
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
        if (!$isSensitive) {
            $settings = $this->getCachedSettingsPayload();

            if (array_key_exists($name, $settings['values'])) {
                return $settings['values'][$name];
            }

            return null;
        }

        $cacheKey = $this->cacheKey($name);
        if (array_key_exists($cacheKey, self::$sensitiveSettingRequestCache)) {
            return self::$sensitiveSettingRequestCache[$cacheKey];
        }

        $settingEntity = $this->find()
            ->where(['name' => $name])
            ->first();

        if (!$settingEntity) {
            self::$sensitiveSettingRequestCache[$cacheKey] = null;

            return null;
        }

        $resolvedValue = $this->resolveValueForRead(
            $settingEntity->type ?? 'string',
            $settingEntity->value,
            $name,
        );

        if (
            $name === 'Backup.encryptionKey'
            && ($settingEntity->type ?? 'string') !== 'password'
            && is_string($resolvedValue)
            && $resolvedValue !== ''
        ) {
            $this->updateSetting($name, 'password', $resolvedValue, false);
        }

        self::$sensitiveSettingRequestCache[$cacheKey] = $resolvedValue;

        return $resolvedValue;
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
        $isNewSetting = $setting === null;
        $effectiveType = $type ?? ($setting?->type ?? 'string');
        $encodedValue = $this->resolveValueForWrite($effectiveType, $value, $setting !== null);

        if ($setting) {
            $this->assignSettingValues($setting, $effectiveType, $encodedValue, $required);
        } else {
            $setting = $this->newEmptyEntity();
            $setting->name = $name;
            $this->assignSettingValues($setting, $effectiveType, $encodedValue, $required);
        }

        try {
            $savedSetting = $this->save($setting);
        } catch (DatabaseException | QueryException $exception) {
            if (!$isNewSetting || !$this->isUniqueSettingConflict($exception)) {
                throw $exception;
            }

            $setting = $this->find()
                ->where(['name' => $name])
                ->first();
            if (!$setting) {
                throw $exception;
            }
            $this->assignSettingValues($setting, $effectiveType, $encodedValue, $required);
            $savedSetting = $this->save($setting);
        }

        if ($savedSetting) {
            if (!$this->isSensitiveSetting($name)) {
                $cacheKey = $this->cacheKey($name);
                Cache::delete($this->assetCacheKey($name), 'default');
                Cache::write($cacheKey, $this->resolveValueForRead($effectiveType, $setting->value, $name), 'default');
            }

            return true;
        }

        return false;
    }

    /**
     * Assign mutable setting fields before saving.
     *
     * @param \Cake\Datasource\EntityInterface $setting App setting entity
     * @param string $type Effective setting type
     * @param string|null $encodedValue Encoded value to persist
     * @param bool $required Required flag
     * @return void
     */
    private function assignSettingValues(
        EntityInterface $setting,
        string $type,
        ?string $encodedValue,
        bool $required,
    ): void {
        $setting->saving = true;
        if ($encodedValue !== null) {
            $setting->value = $encodedValue;
        }
        $setting->type = $type;
        $setting->required = $required;
    }

    /**
     * Detect duplicate-name conflicts from read-then-insert setting creation.
     *
     * @param \Cake\Database\Exception\DatabaseException $exception Database exception
     * @return bool
     */
    private function isUniqueSettingConflict(DatabaseException|QueryException $exception): bool
    {
        $messages = [$exception->getMessage()];
        if ($exception->getPrevious()) {
            $messages[] = $exception->getPrevious()->getMessage();
        }
        $message = strtolower(implode(' ', $messages));

        return str_contains($message, 'app_settings_name')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint');
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
                    $this->clearSettingCaches($name);
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Get app setting.
     *
     * @param mixed $key
     * @param mixed $default
     * @param mixed $type
     * @param mixed $required
     */
    public function getAppSetting($key, $default = null, $type = null, $required = false)
    {
        $setting = $this->getSetting($key);
        if ($setting) {
            return $setting;
        }
        if ($default !== null) {
            $this->setAppSetting($key, $default, $type, $required);

            return $default;
        } else {
            throw new Exception("AppSetting $key not found");
        }
    }

    /**
     * Set app setting.
     *
     * @param mixed $key
     * @param mixed $value
     * @param mixed $type
     * @param mixed $required
     * @return bool
     */
    public function setAppSetting($key, $value, $type = null, $required = false): bool
    {
        return $this->updateSetting($key, $type, $value, $required);
    }

    /**
     * Build a safe database asset payload from an uploaded app setting file.
     *
     * @param string $type App setting type: image or file
     * @param \Psr\Http\Message\UploadedFileInterface $file Uploaded file
     * @return string JSON payload for app_settings.value
     */
    public function assetValueFromUpload(string $type, UploadedFileInterface $file): string
    {
        if (!$this->isAssetType($type)) {
            throw new InvalidArgumentException('Only image and file app setting types can store uploaded assets.');
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($this->uploadErrorMessage($file->getError()));
        }

        $size = $file->getSize();
        if ($size === null || $size <= 0) {
            throw new InvalidArgumentException('The uploaded file was empty.');
        }
        if ($size > self::MAX_ASSET_BYTES) {
            throw new InvalidArgumentException('The uploaded file must be 2 MB or smaller.');
        }

        $contents = $file->getStream()->getContents();
        if ($contents === '') {
            throw new InvalidArgumentException('The uploaded file was empty.');
        }

        $mimeType = $this->detectMimeType($contents);
        $extension = $this->allowedAssetTypes($type)[$mimeType] ?? null;
        if ($extension === null) {
            throw new InvalidArgumentException($this->unsupportedAssetMessage($type));
        }
        if ($type === 'image' && getimagesizefromstring($contents) === false) {
            throw new InvalidArgumentException('File content does not match an allowed image type.');
        }

        $filename = $this->assetFilename((string)$file->getClientFilename(), $extension);
        $payload = [
            'storage' => 'database',
            'filename' => $filename,
            'mime' => $mimeType,
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'data' => base64_encode($contents),
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Read a decoded app setting asset payload for public delivery.
     *
     * @param string $name App setting name
     * @return array<string, mixed>|null
     */
    public function getAssetPayload(string $name): ?array
    {
        $cacheKey = $this->assetCacheKey($name);
        $cached = Cache::read($cacheKey, 'default');
        if (is_array($cached)) {
            return $cached;
        }

        $setting = $this->find()
            ->select(['name', 'type', 'value'])
            ->where(['name' => $name])
            ->first();
        if (!$setting || !$this->isAssetType((string)$setting->type)) {
            return null;
        }

        $payload = $this->decodeAssetPayload($setting->value);
        if ($payload === null) {
            return null;
        }

        Cache::write($cacheKey, $payload, 'default');

        return $payload;
    }

    /**
     * Delete app setting.
     *
     * @param mixed $key
     * @param bool $forceDelete
     * @return bool
     */
    public function deleteAppSetting($key, bool $forceDelete = false): bool
    {
        return $this->deleteSetting($key, $forceDelete);
    }

    /**
     * Get all app settings start with.
     *
     * @param mixed $key
     * @return array
     */
    public function getAllAppSettingsStartWith($key): array
    {
        $settings = $this->getCachedSettingsPayload()['raw'];
        $return = [];
        foreach ($settings as $name => $value) {
            if (str_starts_with($name, (string)$key)) {
                $return[$name] = $value;
            }
        }

        return $return;
    }

    /**
     * Clear request-local setting memoization.
     *
     * Useful for tests and long-running workers that need to simulate a fresh
     * request after directly manipulating the shared cache or database.
     *
     * @return void
     */
    public static function clearRequestCaches(): void
    {
        self::$sensitiveSettingRequestCache = [];
        self::$settingsPayloadRequestCache = [];
    }

    /**
     * Check if sensitive setting.
     *
     * @param string $name
     * @return bool
     */
    private function isSensitiveSetting(string $name): bool
    {
        return $name === 'Backup.encryptionKey';
    }

    /**
     * Read all non-sensitive settings from cache or database.
     *
     * @return array{values: array<string, mixed>, raw: array<string, mixed>}
     */
    private function getCachedSettingsPayload(): array
    {
        $cacheKey = $this->allSettingsCacheKey();
        if (isset(self::$settingsPayloadRequestCache[$cacheKey])) {
            return self::$settingsPayloadRequestCache[$cacheKey];
        }

        $payload = Cache::read($cacheKey, 'default');
        if (is_array($payload) && isset($payload['values'], $payload['raw'])) {
            self::$settingsPayloadRequestCache[$cacheKey] = $payload;

            return self::$settingsPayloadRequestCache[$cacheKey];
        }

        $payload = ['values' => [], 'raw' => []];
        $settings = $this->find()
            ->select(['name', 'type', 'value'])
            ->all();
        foreach ($settings as $setting) {
            $name = (string)$setting->name;
            if ($this->isSensitiveSetting($name)) {
                continue;
            }
            $payload['raw'][$name] = $setting->value;
            $payload['values'][$name] = $this->resolveValueForRead(
                $setting->type ?? 'string',
                $setting->value,
                $name,
            );
        }
        Cache::write($cacheKey, $payload, 'default');
        self::$settingsPayloadRequestCache[$cacheKey] = $payload;

        return self::$settingsPayloadRequestCache[$cacheKey];
    }

    /**
     * Clear tenant-scoped caches affected by setting mutation.
     */
    private function clearSettingCaches(?string $name = null): void
    {
        $allSettingsCacheKey = $this->allSettingsCacheKey();
        Cache::delete($allSettingsCacheKey, 'default');
        unset(self::$settingsPayloadRequestCache[$allSettingsCacheKey]);
        if ($name !== null) {
            Cache::delete($this->cacheKey($name), 'default');
            Cache::delete($this->assetCacheKey($name), 'default');
            unset(self::$sensitiveSettingRequestCache[$this->cacheKey($name)]);
        }
    }

    /**
     * Build a tenant-safe cache key for a setting name.
     */
    private function cacheKey(string $name): string
    {
        return TenantAwareCache::tenantScopedKey('app_setting_' . $name);
    }

    /**
     * Build a tenant-safe cache key for all non-sensitive settings.
     */
    private function allSettingsCacheKey(): string
    {
        return TenantAwareCache::tenantScopedKey(self::ALL_SETTINGS_CACHE_KEY);
    }

    /**
     * Build a tenant-safe cache key for decoded asset payloads.
     */
    private function assetCacheKey(string $name): string
    {
        return TenantAwareCache::tenantScopedKey('app_setting_asset_' . $name);
    }

    /**
     * Resolve value for write.
     *
     * @param string $type
     * @param mixed $value
     * @param bool $settingExists
     * @return mixed
     */
    private function resolveValueForWrite(string $type, mixed $value, bool $settingExists): mixed
    {
        if ($this->isAssetType($type)) {
            return is_string($value) ? $value : '';
        }
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

    /**
     * Resolve value for read.
     *
     * @param string $type
     * @param mixed $value
     * @return mixed
     */
    private function resolveValueForRead(string $type, mixed $value, ?string $name = null): mixed
    {
        if ($this->isAssetType($type)) {
            $payload = $this->decodeAssetPayload($value);
            if ($payload === null || $name === null) {
                return $value;
            }

            return $this->assetUrl($name, $payload);
        }
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

    /**
     * Get password encryption key.
     *
     * @return string
     */
    private function getPasswordEncryptionKey(): string
    {
        $key = (string)env('APPSETTING_PASSWORD_KEY', Security::getSalt());
        if (strlen($key) < 32) {
            $key = str_pad($key, 32, '0');
        }

        return $key;
    }

    /**
     * Check if a setting type is a stored public asset.
     */
    private function isAssetType(string $type): bool
    {
        return in_array($type, ['file', 'image'], true);
    }

    /**
     * Decode and validate a stored database asset payload.
     *
     * @param mixed $value
     * @return array<string, mixed>|null
     */
    private function decodeAssetPayload(mixed $value): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            $payload = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($payload) || ($payload['storage'] ?? null) !== 'database') {
            return null;
        }
        foreach (['filename', 'mime', 'sha256', 'data'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field])) {
                return null;
            }
        }

        return $payload;
    }

    /**
     * Build a public URL for a stored app setting asset.
     *
     * @param string $name App setting name
     * @param array<string, mixed> $payload Decoded asset payload
     * @return string
     */
    private function assetUrl(string $name, array $payload): string
    {
        return '/app-settings/asset/' . rawurlencode($name) . '?v=' . substr((string)$payload['sha256'], 0, 12);
    }

    /**
     * Detect MIME type from file content.
     */
    private function detectMimeType(string $contents): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents);

        return is_string($mimeType) ? $mimeType : 'application/octet-stream';
    }

    /**
     * Get allowed MIME types for a public asset setting type.
     *
     * @return array<string, string>
     */
    private function allowedAssetTypes(string $type): array
    {
        return $type === 'image' ? self::IMAGE_MIME_TYPES : self::FILE_MIME_TYPES;
    }

    /**
     * Build a safe filename with the detected extension.
     */
    private function assetFilename(string $clientFilename, string $extension): string
    {
        $basename = pathinfo($clientFilename, PATHINFO_FILENAME);
        $basename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $basename) ?? 'asset';
        $basename = trim($basename, '.-');
        if ($basename === '') {
            $basename = 'asset';
        }

        return substr($basename, 0, 80) . '.' . $extension;
    }

    /**
     * Build upload error message.
     */
    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the maximum upload size.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            default => 'The uploaded file could not be processed.',
        };
    }

    /**
     * Build unsupported asset message.
     */
    private function unsupportedAssetMessage(string $type): string
    {
        if ($type === 'image') {
            return 'Images must be PNG, JPEG, GIF, or WebP files.';
        }

        return 'Files must be PNG, JPEG, GIF, WebP, PDF, or plain text files.';
    }
}
