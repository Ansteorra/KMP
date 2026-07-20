<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Laminas\Diactoros\UploadedFile;

/**
 * App\Controller\PagesController Test Case
 *
 * @uses \App\Controller\PagesController
 */
class PagesControllerTest extends HttpIntegrationTestCase
{
    /**
     * Web manifest uses the public app-setting asset URL for its icon.
     *
     * @return void
     * @uses \App\Controller\PagesController::webmanifest()
     */
    public function testWebmanifestUsesAppSettingAssetUrl(): void
    {
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $assetValue = $appSettingsTable->assetValueFromUpload('image', $this->uploadedPngFile('manifest-logo.png'));
        $appSettingsTable->updateSetting('KMP.BannerLogo', 'image', $assetValue, true);

        $this->session([]);
        $this->get('/members/card.webmanifest');

        $this->assertResponseOk();
        $this->assertContentType('application/manifest+json');
        $this->assertResponseContains('/app-settings/asset/KMP.BannerLogo');
        $this->assertResponseNotContains('/img/badge.png');
    }

    /**
     * Create an uploaded PNG file test object.
     */
    private function uploadedPngFile(string $clientFilename): UploadedFile
    {
        $contents = $this->tinyPngBytes();
        $path = tempnam(sys_get_temp_dir(), 'kmp-manifest-upload-');
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
