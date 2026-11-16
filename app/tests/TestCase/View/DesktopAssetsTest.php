<?php
declare(strict_types=1);

namespace App\Test\TestCase\View;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use App\View\Helper\ViteHelper;
use Cake\View\View;

/** Desktop page shells must emit the assets resolved from the build manifest. */
class DesktopAssetsTest extends HttpIntegrationTestCase
{
    public function testLoginIncludesCompiledAssets(): void
    {
        $this->get('/members/login');
        $this->assertResponseOk();
        $this->assertDesktopAssets();
        $vite = new ViteHelper(new View());
        $this->assertResponseContains('href="' . $vite->getStyleUrl('signin') . '"');
    }

    public function testAuthenticatedPageIncludesCompiledAssets(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/members');
        $this->assertResponseOk();
        $this->assertDesktopAssets();
    }

    private function assertDesktopAssets(): void
    {
        $vite = new ViteHelper(new View());
        $this->assertResponseContains('src="' . $vite->getScriptUrl('index') . '"');
        $this->assertResponseContains('src="' . $vite->getScriptUrl('controllers') . '"');
        $this->assertResponseContains('href="' . $vite->getStyleUrl('app') . '"');
    }
}
