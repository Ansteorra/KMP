<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\AppController;
use App\Model\Table\AppSettingsTable;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\ORM\Locator\TableLocator;

class AppControllerBrandingTest extends BaseTestCase
{
    private AppController $controller;
    private AppSettingsTable $settings;
    private string $imageDirectory;
    private string $outsideImage;
    private string $imageData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = $this->getMockBuilder(AppSettingsTable::class)
            ->onlyMethods(['getAssetPayload'])->getMock();
        $locator = new TableLocator();
        $locator->set('AppSettings', $this->settings);
        $this->controller = new class (new ServerRequest()) extends AppController {
            public function brandingImage(): ?string
            {
                return $this->appSettingImageDataUri('Test.BrandingImage');
            }
        };
        $this->controller->setTableLocator($locator);
        $this->outsideImage = tempnam(WWW_ROOT, 'branding-test-');
        $this->imageDirectory = WWW_ROOT . 'img' . DS . basename($this->outsideImage);
        mkdir($this->imageDirectory);
        $this->imageData = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        file_put_contents($this->outsideImage, $this->imageData);
        file_put_contents($this->imageDirectory . DS . 'valid.gif', $this->imageData);
    }

    protected function tearDown(): void
    {
        Configure::delete('Test.BrandingImage');
        if (is_link($this->imageDirectory . DS . 'escape.gif')) {
            unlink($this->imageDirectory . DS . 'escape.gif');
        }
        unlink($this->imageDirectory . DS . 'valid.gif');
        rmdir($this->imageDirectory);
        unlink($this->outsideImage);
        parent::tearDown();
    }

    public function testLegacyImageInsideBrandingDirectoryIsEmbedded(): void
    {
        $this->settings->method('getAssetPayload')->willReturn(null);
        Configure::write('Test.BrandingImage', basename($this->imageDirectory) . '/valid.gif');
        $this->assertSame('data:image/gif;base64,' . base64_encode($this->imageData), $this->controller->brandingImage());
    }

    public function testTraversalOutsideBrandingDirectoryIsRejected(): void
    {
        $this->settings->method('getAssetPayload')->willReturn(null);
        Configure::write('Test.BrandingImage', '../' . basename($this->outsideImage));
        $this->assertNull($this->controller->brandingImage());
    }

    public function testSymlinkOutsideBrandingDirectoryIsRejected(): void
    {
        $this->settings->method('getAssetPayload')->willReturn(null);
        symlink($this->outsideImage, $this->imageDirectory . DS . 'escape.gif');
        Configure::write('Test.BrandingImage', basename($this->imageDirectory) . '/escape.gif');
        $this->assertNull($this->controller->brandingImage());
    }

    public function testStoredAssetPayloadIsStillUsed(): void
    {
        $this->settings->method('getAssetPayload')->willReturn([
            'mime' => 'image/gif', 'data' => base64_encode($this->imageData),
        ]);
        $this->assertSame('data:image/gif;base64,' . base64_encode($this->imageData), $this->controller->brandingImage());
    }
}
