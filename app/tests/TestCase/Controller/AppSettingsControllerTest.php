<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Laminas\Diactoros\UploadedFile;

/**
 * App\Controller\AppSettingsController Test Case
 *
 * @uses \App\Controller\AppSettingsController
 */
class AppSettingsControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::index()
     */
    public function testIndex(): void
    {
        $this->get('/app-settings');
        $this->assertResponseOk();
        $this->assertResponseContains('App Settings');
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::add()
     */
    public function testAdd(): void
    {
        $uniqueName = 'Test.Setting.' . time();
        $data = [
            'name' => $uniqueName,
            'value' => 'Test Value',
        ];

        $this->post('/app-settings/add', $data);
        $this->assertRedirect(['controller' => 'AppSettings', 'action' => 'index']);

        // Check the record was saved to the database
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $query = $appSettingsTable->find()->where(['name' => $uniqueName]);
        $this->assertEquals(1, $query->count());
    }

    /**
     * Test add image setting with upload.
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::add()
     */
    public function testAddImageSettingUpload(): void
    {
        $uniqueName = 'Test.Image.Setting.' . time();
        $this->post('/app-settings/add', [
            'name' => $uniqueName,
            'type' => 'image',
            'asset_file' => $this->uploadedPngFile('new-image.png'),
        ]);
        $this->assertRedirect(['controller' => 'AppSettings', 'action' => 'index']);

        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $setting = $appSettingsTable->find()->where(['name' => $uniqueName])->firstOrFail();
        $this->assertSame('image', $setting->type);
        $this->assertStringContainsString('"storage":"database"', (string)$setting->value);
        $this->assertStringContainsString('new-image.png', (string)$setting->value);
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::edit()
     */
    public function testEdit(): void
    {
        // Use an existing setting from dev_seed_clean.sql
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $appSetting = $appSettingsTable->find()->where(['name' => 'KMP.ShortSiteTitle'])->first();

        $data = [
            'id' => $appSetting->id,
            'raw_value' => 'EDITED',
        ];

        $this->post('/app-settings/edit/' . $appSetting->id, $data);
        $this->assertResponseOk();

        // Check the record was saved to the database
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $updatedAppSetting = $appSettingsTable->find()->where(['name' => 'KMP.ShortSiteTitle'])->first();
        $this->assertEquals($data['raw_value'], $updatedAppSetting->value);
    }

    /**
     * Test editing an image setting with an uploaded file.
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::edit()
     */
    public function testEditImageSettingUpload(): void
    {
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $appSetting = $appSettingsTable->newEntity([
            'name' => 'test.controller.image.' . time(),
            'value' => 'legacy.png',
            'type' => 'image',
            'required' => false,
        ]);
        $appSettingsTable->saveOrFail($appSetting);

        $this->post('/app-settings/edit/' . $appSetting->id, [
            'asset_file' => $this->uploadedPngFile('custom-login.png'),
        ]);
        $this->assertResponseOk();

        $updated = $appSettingsTable->find()->where(['name' => $appSetting->name])->firstOrFail();
        $this->assertSame('image', $updated->type);
        $this->assertStringContainsString('"storage":"database"', (string)$updated->value);
        $this->assertStringContainsString('custom-login.png', (string)$updated->value);
    }

    /**
     * Empty image uploads leave the existing app setting asset unchanged.
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::edit()
     */
    public function testEditImageSettingWithoutUploadKeepsExistingValue(): void
    {
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $appSetting = $appSettingsTable->newEntity([
            'name' => 'test.controller.image.nochange.' . time(),
            'value' => 'legacy.png',
            'type' => 'image',
            'required' => false,
        ]);
        $appSettingsTable->saveOrFail($appSetting);

        $this->post('/app-settings/edit/' . $appSetting->id, [
            'asset_file' => new UploadedFile('php://temp', 0, UPLOAD_ERR_NO_FILE, '', 'application/octet-stream'),
        ]);
        $this->assertResponseOk();

        $updated = $appSettingsTable->find()->where(['name' => $appSetting->name])->firstOrFail();
        $this->assertSame('legacy.png', $updated->value);
    }

    /**
     * Public app setting assets can be read without authentication.
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::asset()
     */
    public function testAssetIsPubliclyReadable(): void
    {
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $name = 'test.public.asset.' . time();
        $assetValue = $appSettingsTable->assetValueFromUpload('image', $this->uploadedPngFile('public.png'));
        $appSettingsTable->updateSetting($name, 'image', $assetValue, false);

        $this->session([]);
        $this->get('/app-settings/asset/' . $name);

        $this->assertResponseOk();
        $this->assertHeader('Content-Type', 'image/png');
        $this->assertHeaderContains('Cache-Control', 'public');
        $this->assertResponseEquals($this->tinyPngBytes());
    }

    /**
     * App setting asset requests must not pollute navigation history.
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::asset()
     */
    public function testAssetDoesNotUpdatePageStack(): void
    {
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $name = 'test.public.asset.stack.' . time();
        $assetValue = $appSettingsTable->assetValueFromUpload('image', $this->uploadedPngFile('stack.png'));
        $appSettingsTable->updateSetting($name, 'image', $assetValue, false);

        $this->session(['pageStack' => ['/members/view/1']]);
        $this->get('/app-settings/asset/' . $name);

        $this->assertResponseOk();
        $this->assertSession(['/members/view/1'], 'pageStack');
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::delete()
     */
    public function testEditFromGridReturnsRowReplaceStreamOnMainIndex(): void
    {
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $appSetting = $appSettingsTable->find()->where(['name' => 'KMP.ShortSiteTitle'])->firstOrFail();

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/app-settings/edit/' . $appSetting->id, [
            'raw_value' => 'RowSync' . time(),
            'page_context_url' => '/app-settings',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains(
            '<turbo-stream action="replace" target="app-settings-grid-row-' . $appSetting->id . '"',
        );
        $this->assertResponseNotContains('target="app-settings-grid-table"');
    }

    public function testDeleteFromGridReturnsRowRemoveStreamOnMainIndex(): void
    {
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $testSetting = $appSettingsTable->newEntity([
            'name' => 'test.delete.grid.turbo.' . time(),
            'value' => 'delete-me',
            'required' => false,
        ]);
        $appSettingsTable->saveOrFail($testSetting);

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/app-settings/delete/' . $testSetting->id, [
            'page_context_url' => '/app-settings',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains(
            '<turbo-stream action="remove" target="app-settings-grid-row-' . $testSetting->id . '"',
        );
        $this->assertResponseNotContains('target="app-settings-grid-table"');
    }

    public function testDelete(): void
    {
        // Create a test setting to delete
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $testSetting = $appSettingsTable->newEntity([
            'name' => 'test.delete.controller.' . time(),
            'value' => 'delete-me',
            'required' => false,
        ]);
        $appSettingsTable->save($testSetting);

        $this->post('/app-settings/delete/' . $testSetting->id);
        $this->assertResponseSuccess();

        // Check the record was deleted from the database
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $query = $appSettingsTable->find()->where(['name' => $testSetting->name]);
        $this->assertEquals(0, $query->count());
    }

    /**
     * Create an uploaded PNG file test object.
     */
    private function uploadedPngFile(string $clientFilename): UploadedFile
    {
        $contents = $this->tinyPngBytes();
        $path = tempnam(sys_get_temp_dir(), 'kmp-controller-upload-');
        $this->assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, strlen($contents), UPLOAD_ERR_OK, $clientFilename, 'image/png');
    }

    /**
     * Tiny valid PNG bytes.
     */
    private function tinyPngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            true,
        ) ?: '';
    }
}
