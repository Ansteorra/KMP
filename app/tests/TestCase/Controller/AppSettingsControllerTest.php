<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Services\PublicCalendarSettings;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\Configure;
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
        $this->assertResponseContains('href="/app-settings/public-calendar"');
        $this->assertResponseContains('Public Events Calendar');
    }

    public function testPublicCalendarSettingsSaveAndRender(): void
    {
        $this->get('/app-settings/public-calendar');
        $this->assertResponseOk();
        $this->assertResponseContains('Public Events Calendar');
        $this->assertResponseContains('/events?embed=1');
        $this->assertResponseContains('Tourneys, courts, classes, and gatherings.');

        $this->post('/app-settings/public-calendar', [
            'show_header' => '1',
            'show_footer' => '1',
            'header_title' => 'Kingdom & Friends',
            'header_subtitle' => '',
            'calendar_heading' => 'Gatherings <north>',
            'header_icon' => 'castle',
            'intro' => "**Welcome**\n\n- See [event help](https://example.test/help).\n<script>blocked()</script>",
            'footer_title' => 'Calendar contacts',
            'footer_body' => 'Contact **calendar staff**.',
            'theme' => 'dark',
            'body_font' => 'verdana',
            'heading_font' => 'playfair',
            'background' => '#101820',
        ]);
        $this->assertResponseCode(303);
        $this->assertRedirectContains('/app-settings/public-calendar');

        $this->session(['Auth' => null]);
        $this->get('/events');
        $this->assertResponseOk();
        $this->assertResponseContains('<title>Kingdom &amp; Friends</title>');
        $this->assertResponseContains('<h1>Gatherings &lt;north&gt;</h1>');
        $this->assertResponseContains('name="castle"');
        $this->assertResponseContains('<strong>Welcome</strong>');
        $this->assertResponseContains('href="https://example.test/help"');
        $this->assertResponseNotContains('<script>blocked()');
        $this->assertResponseContains('<summary>Calendar contacts</summary>');
        $this->assertResponseContains('Contact <strong>calendar staff</strong>.');
        $this->assertResponseContains('--kc-bg:#101820;');
        $this->assertResponseContains('--kc-font-body:Verdana, sans-serif;');
        $this->assertResponseContains('--kc-font-display:"Playfair Display", Georgia, serif;');

        $this->get('/events?q=forest');
        $this->assertResponseOk();
        $this->assertResponseContains('<h1>Gatherings &lt;north&gt;</h1>');
        $this->assertResponseNotContains('Event archive');
    }

    public function testPublicCalendarSettingsRejectInvalidColorsAndOrigins(): void
    {
        $table = $this->getTableLocator()->get('AppSettings');
        $saved = ['header_title' => 'Saved title'];
        $this->assertTrue($table->updateSetting(PublicCalendarSettings::KEY, 'json', json_encode($saved)));

        $this->post('/app-settings/public-calendar', [
            'header_title' => 'Invalid changes',
            'text' => '#ffffff',
            'embed_origins' => "https://kingdom.example\nhttps://*.example.test",
        ]);
        $this->assertResponseCode(422);
        $this->assertResponseContains('4.5:1 contrast');
        $this->assertResponseContains('Enter exact HTTPS origins');
        $this->assertSame($saved, $table->getSetting(PublicCalendarSettings::KEY));
    }

    public function testPublicCalendarSettingsRequireEditPermission(): void
    {
        $table = $this->getTableLocator()->get('AppSettings');
        $savedSetting = $table->find()->where(['name' => PublicCalendarSettings::KEY]);
        $before = $savedSetting->first()?->value;
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/app-settings/public-calendar');
        $this->assertRedirectContains('/pages/unauthorized');

        $this->post('/app-settings/public-calendar', ['header_title' => 'Unauthorized change']);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->assertSame($before, $savedSetting->first()?->value);
    }

    public function testPublicCalendarLogoPublishes(): void
    {
        $directory = sys_get_temp_dir() . '/kmp-calendar-logo-' . bin2hex(random_bytes(6));
        $priorPath = Configure::read('PublicAssets.localPath');
        $priorAdapter = Configure::read('PublicAssets.adapter');
        Configure::write('PublicAssets.localPath', $directory);
        Configure::write('PublicAssets.adapter', 'local');

        try {
            $this->post('/app-settings/public-calendar', [
                'show_header' => '1',
                'artwork' => 'logo',
                'logo' => $this->uploadedPngFile('calendar-logo.png'),
            ]);
            $this->assertResponseCode(303);
            $table = $this->getTableLocator()->get('AppSettings');
            $saved = $table->getSetting(PublicCalendarSettings::KEY);
            $key = $saved['logo_key'];
            $this->assertFileExists($directory . '/' . $key);

            $this->session(['Auth' => null]);
            $this->get('/events');
            $this->assertResponseOk();
            $this->assertResponseContains('/public-assets/' . $key);
            $this->assertResponseContains('alt="" class="kc-logo"');
        } finally {
            Configure::write('PublicAssets.localPath', $priorPath);
            Configure::write('PublicAssets.adapter', $priorAdapter);
            foreach (glob($directory . '/images/*/*.png') ?: [] as $file) {
                unlink($file);
                rmdir(dirname($file));
            }
            if (is_dir($directory)) {
                rmdir($directory . '/images');
                rmdir($directory);
            }
        }
    }

    public function testInvalidPublicCalendarLogoPreservesSavedSettings(): void
    {
        $table = $this->getTableLocator()->get('AppSettings');
        $saved = ['artwork' => 'logo', 'header_title' => 'Saved calendar'];
        $this->assertTrue($table->updateSetting(PublicCalendarSettings::KEY, 'json', json_encode($saved)));
        $path = tempnam(sys_get_temp_dir(), 'kmp-invalid-logo-');
        $this->assertIsString($path);
        try {
            $length = file_put_contents($path, 'Invalid PNG');
            $invalid = new UploadedFile($path, $length, UPLOAD_ERR_OK, 'invalid.png', 'image/png');
            $this->post('/app-settings/public-calendar', ['artwork' => 'logo', 'logo' => $invalid]);
            $this->assertResponseCode(422);
            $this->assertResponseContains('Upload a PNG or WebP up to 2 MB');
            $this->assertSame($saved, $table->getSetting(PublicCalendarSettings::KEY));
        } finally {
            unlink($path);
        }
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
        // Asset (fragment) requests must not record themselves in the navigation
        // history. The controller leaves pageStack untouched for these requests, so
        // the asset URL must never appear in it.
        $this->assertNotContains(
            '/app-settings/asset/' . $name,
            (array)$this->_requestSession->read('pageStack'),
            'Asset requests must not be recorded in navigation history.',
        );
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
