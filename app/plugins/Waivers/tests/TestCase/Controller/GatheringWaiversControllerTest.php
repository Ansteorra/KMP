<?php

declare(strict_types=1);

namespace Waivers\Test\TestCase\Controller;

use App\Test\TestCase\Controller\SuperUserAuthenticatedTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Waivers\Controller\GatheringWaiversController Test Case
 *
 * @uses \Waivers\Controller\GatheringWaiversController
 */
class GatheringWaiversControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use SuperUserAuthenticatedTrait;    /**
     * Test index method
     *
     * @return void
     * @uses \Waivers\Controller\GatheringWaiversController::index()
     */
    public function testIndex(): void
    {
        $this->get('/waivers/gathering-waivers?gathering_id=1');
        $this->assertResponseOk();
        $this->assertResponseContains('Gathering Waivers');
    }

    /**
     * Test index requires gathering_id parameter
     *
     * @return void
     */
    public function testIndexRequiresGatheringId(): void
    {
        $this->get('/waivers/gathering-waivers');
        $this->assertResponseError();
    }

    /**
     * Test index shows waiver counts per type
     *
     * @return void
     */
    public function testIndexShowsWaiverCounts(): void
    {
        $this->get('/waivers/gathering-waivers?gathering_id=1');
        $this->assertResponseOk();

        // Should show waiver type names
        $this->assertResponseContains('General Liability Waiver');

        // Should show count of uploaded waivers (2 waivers for gathering 1)
        $this->assertResponseContains('2');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \Waivers\Controller\GatheringWaiversController::view()
     */
    public function testView(): void
    {
        $this->get('/waivers/gathering-waivers/view/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Waiver signed by John Doe');
    }

    /**
     * Test view shows retention policy information
     *
     * @return void
     */
    public function testViewShowsRetentionPolicy(): void
    {
        $this->get('/waivers/gathering-waivers/view/1');
        $this->assertResponseOk();

        // Should show retention date
        $this->assertResponseContains('2032-03-15');

        // Should show status
        $this->assertResponseContains('active');
    }

    /**
     * Test upload method GET request shows form
     *
     * @return void
     * @uses \Waivers\Controller\GatheringWaiversController::upload()
     */
    public function testUploadGet(): void
    {
        $this->get('/waivers/gathering-waivers/upload?gathering_id=1');
        $this->assertResponseOk();
        $this->assertResponseContains('Upload Waivers');
    }

    /**
     * Test upload requires gathering_id parameter
     *
     * @return void
     */
    public function testUploadRequiresGatheringId(): void
    {
        $this->get('/waivers/gathering-waivers/upload');
        $this->assertResponseError();
    }

    /**
     * Test upload form shows required waiver types
     *
     * @return void
     */
    public function testUploadFormShowsRequiredWaiverTypes(): void
    {
        $this->get('/waivers/gathering-waivers/upload?gathering_id=1');
        $this->assertResponseOk();

        // Should display waiver types needed for this gathering
        $this->assertResponseContains('General Liability Waiver');
    }

    /**
     * Test upload form includes mobile camera capture support
     *
     * @return void
     */
    public function testUploadFormIncludesCameraCapture(): void
    {
        $this->get('/waivers/gathering-waivers/upload?gathering_id=1');
        $this->assertResponseOk();

        // Should have file input with capture attribute
        $this->assertResponseContains('capture="environment"');
        $this->assertResponseContains('accept="image/*"');
    }

    /**
     * Test download method serves PDF file
     *
     * @return void
     * @uses \Waivers\Controller\GatheringWaiversController::download()
     */
    public function testDownload(): void
    {
        // Note: Actual file serving will need mock files in test environment
        $this->get('/waivers/gathering-waivers/download/1');

        // Should redirect to download or return file
        $this->assertResponseSuccess();
    }

    /**
     * Test download requires valid waiver ID
     *
     * @return void
     */
    public function testDownloadRequiresValidId(): void
    {
        $this->get('/waivers/gathering-waivers/download/9999');
        $this->assertResponseError();
    }

    /**
     * Test delete method for expired waivers
     *
     * @return void
     * @uses \Waivers\Controller\GatheringWaiversController::delete()
     */
    public function testDelete(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        // Delete expired waiver (ID 4 is marked expired in fixture)
        $this->delete('/waivers/gathering-waivers/delete/4');
        $this->assertResponseSuccess();
        $this->assertRedirect();
    }

    /**
     * Test delete prevents deletion of active waivers
     *
     * @return void
     */
    public function testDeletePreventsActiveWaiverDeletion(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        // Try to delete active waiver (ID 1 is active)
        $this->delete('/waivers/gathering-waivers/delete/1');

        // Should fail or redirect with error
        $this->assertResponseSuccess(); // Redirects with flash error
        $this->assertFlashMessage('Only expired waivers can be deleted', 'flash');
    }

    /**
     * Test authorization - only authorized users can upload
     *
     * @return void
     */
    public function testUploadRequiresAuthorization(): void
    {
        // Test will be expanded when authorization policies are fully implemented
        $this->markTestIncomplete('Authorization testing pending policy implementation');
    }

    /**
     * Test authorization - only authorized users can delete
     *
     * @return void
     */
    public function testDeleteRequiresAuthorization(): void
    {
        // Test will be expanded when authorization policies are fully implemented
        $this->markTestIncomplete('Authorization testing pending policy implementation');
    }

    /**
     * Test authorization - only authorized users can download
     *
     * @return void
     */
    public function testDownloadRequiresAuthorization(): void
    {
        // Test will be expanded when authorization policies are fully implemented
        $this->markTestIncomplete('Authorization testing pending policy implementation');
    }
}
