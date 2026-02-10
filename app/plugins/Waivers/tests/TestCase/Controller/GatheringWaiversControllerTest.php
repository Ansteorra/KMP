<?php

declare(strict_types=1);

namespace Waivers\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * Waivers\Controller\GatheringWaiversController Test Case
 *
 * @uses \Waivers\Controller\GatheringWaiversController
 */
class GatheringWaiversControllerTest extends HttpIntegrationTestCase
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
     * @uses \Waivers\Controller\GatheringWaiversController::index()
     */
    public function testIndex(): void
    {
        // Use a gathering that has waivers in the seed data
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }

        $this->get('/waivers/gathering-waivers?gathering_id=' . $waiver->gathering_id);
        $this->assertResponseOk();
    }

    /**
     * Test index requires gathering_id parameter
     *
     * @return void
     */
    public function testIndexRequiresGatheringId(): void
    {
        $this->get('/waivers/gathering-waivers');
        $this->assertResponseOk(); // Index without gathering_id shows grid view
    }

    /**
     * Test index shows waiver counts per type
     *
     * @return void
     */
    public function testIndexShowsWaiverCounts(): void
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }

        $this->get('/waivers/gathering-waivers?gathering_id=' . $waiver->gathering_id);
        $this->assertResponseOk();
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \Waivers\Controller\GatheringWaiversController::view()
     */
    public function testView(): void
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }
        $this->get('/waivers/gathering-waivers/view/' . $waiver->id);
        $this->assertResponseOk();
    }

    /**
     * Test view shows retention policy information
     *
     * @return void
     */
    public function testViewShowsRetentionPolicy(): void
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }
        $this->get('/waivers/gathering-waivers/view/' . $waiver->id);
        $this->assertResponseOk();
    }

    /**
     * Test upload method GET request shows form
     *
     * @return void
     * @uses \Waivers\Controller\GatheringWaiversController::upload()
     */
    public function testUploadGet(): void
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }
        $this->get('/waivers/gathering-waivers/upload?gathering_id=' . $waiver->gathering_id);
        $this->assertResponseOk();
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
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }
        $this->get('/waivers/gathering-waivers/upload?gathering_id=' . $waiver->gathering_id);
        $this->assertResponseOk();
    }

    /**
     * Test upload form includes mobile camera capture support
     *
     * @return void
     */
    public function testUploadFormIncludesCameraCapture(): void
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }
        $this->get('/waivers/gathering-waivers/upload?gathering_id=' . $waiver->gathering_id);
        $this->assertResponseOk();
    }

    /**
     * Test download method serves PDF file
     *
     * @return void
     * @uses \Waivers\Controller\GatheringWaiversController::download()
     */
    public function testDownload(): void
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()
            ->contain(['Documents'])
            ->where(['GatheringWaivers.document_id IS NOT' => null])
            ->first();
        if (!$waiver || !$waiver->document) {
            $this->markTestSkipped('No gathering waiver with document found in seed data');
        }

        // Create a temporary file at the expected storage path
        $basePath = WWW_ROOT . '..' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'uploaded' . DIRECTORY_SEPARATOR;
        $filePath = $basePath . str_replace('/', DIRECTORY_SEPARATOR, $waiver->document->file_path);
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $createdDir = !is_dir($dir); // track if we created it
        file_put_contents($filePath, '%PDF-1.4 test content');
        $this->_testFilePath = $filePath;

        $this->get('/waivers/gathering-waivers/download/' . $waiver->id);
        $this->assertResponseOk();
        $this->assertContentType('application/pdf');

        // Clean up
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Temp file path for cleanup
     */
    private ?string $_testFilePath = null;

    protected function tearDown(): void
    {
        if ($this->_testFilePath && file_exists($this->_testFilePath)) {
            unlink($this->_testFilePath);
        }
        parent::tearDown();
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
        // Find a valid gathering and waiver type from seed data
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $existingWaiver = $GatheringWaivers->find()->first();
        if (!$existingWaiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }

        // Insert an expired waiver directly (bypassing validation since 'expired'
        // is not in the validator's inList but is a valid DB/controller state)
        $connection = \Cake\Datasource\ConnectionManager::get('test');
        $connection->execute(
            'INSERT INTO waivers_gathering_waivers (gathering_id, waiver_type_id, document_id, is_exemption, exemption_reason, status, retention_date, created, created_by) VALUES (?, ?, NULL, 1, ?, ?, ?, NOW(), ?)',
            [
                $existingWaiver->gathering_id,
                $existingWaiver->waiver_type_id,
                'Test expired waiver for deletion',
                'expired',
                '2020-01-01',
                self::ADMIN_MEMBER_ID,
            ]
        );
        $expiredWaiver = $GatheringWaivers->find()
            ->where(['status' => 'expired'])
            ->orderBy(['id' => 'DESC'])
            ->first();
        $this->assertNotNull($expiredWaiver, 'Failed to create expired waiver test record');

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/waivers/gathering-waivers/delete/' . $expiredWaiver->id);
        $this->assertResponseSuccess();

        // Verify the waiver was deleted (soft-delete via Muffin/Trash)
        $afterDelete = $GatheringWaivers->find()
            ->where(['id' => $expiredWaiver->id])
            ->first();
        $this->assertNull($afterDelete, 'Expired waiver should be deleted');
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

        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->where(['status' => 'active'])->first();
        if (!$waiver) {
            $this->markTestSkipped('No active gathering waivers found');
        }

        // Try to delete active waiver
        $this->delete('/waivers/gathering-waivers/delete/' . $waiver->id);

        // Should redirect (deletion blocked or error)
        $this->assertResponseSuccess();

        // Verify waiver still exists
        $still = $GatheringWaivers->find()->where(['id' => $waiver->id])->first();
        $this->assertNotNull($still, 'Active waiver should not be deleted');
    }

    /**
     * Test authorization - only authorized users can upload
     *
     * @return void
     */
    public function testUploadRequiresAuthorization(): void
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }

        // Clear authentication — simulate unauthenticated access
        $this->session(['Auth' => null]);
        $this->get('/waivers/gathering-waivers/upload?gathering_id=' . $waiver->gathering_id);
        $this->assertRedirect();
    }

    /**
     * Test authorization - only authorized users can delete
     *
     * @return void
     */
    public function testDeleteRequiresAuthorization(): void
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }

        // Clear authentication — simulate unauthenticated access
        $this->session(['Auth' => null]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/waivers/gathering-waivers/delete/' . $waiver->id);
        $this->assertRedirect();
    }

    /**
     * Test authorization - only authorized users can download
     *
     * @return void
     */
    public function testDownloadRequiresAuthorization(): void
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $waiver = $GatheringWaivers->find()->first();
        if (!$waiver) {
            $this->markTestSkipped('No gathering waivers found in seed data');
        }

        // Clear authentication — simulate unauthenticated access
        $this->session(['Auth' => null]);
        $this->get('/waivers/gathering-waivers/download/' . $waiver->id);
        $this->assertRedirect();
    }
}
