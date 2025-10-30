<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * DocumentsFixture
 */
class DocumentsFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'documents';

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'entity_type' => 'GatheringWaiver',
                'entity_id' => 1,
                'file_path' => 'gathering_waivers/2025/01/waiver_001.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 524288, // 512 KB
                'checksum' => hash('sha256', 'test_document_1'),
                'storage_adapter' => 'local',
                'metadata' => '{"original_filename":"signed_waiver_john.jpg","original_size":3145728,"conversion_date":"2025-01-15 10:30:00"}',
                'uploaded_by' => 1,
                'created' => '2025-01-15 10:30:00',
                'modified' => '2025-01-15 10:30:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 2,
                'entity_type' => 'GatheringWaiver',
                'entity_id' => 2,
                'file_path' => 'gathering_waivers/2025/01/waiver_002.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 786432, // 768 KB
                'checksum' => hash('sha256', 'test_document_2'),
                'storage_adapter' => 'local',
                'metadata' => '{"original_filename":"combat_waiver_jane.png","original_size":4194304,"conversion_date":"2025-01-20 14:15:00"}',
                'uploaded_by' => 1,
                'created' => '2025-01-20 14:15:00',
                'modified' => '2025-01-20 14:15:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 3,
                'entity_type' => 'GatheringWaiver',
                'entity_id' => 3,
                'file_path' => 'gathering_waivers/2025/02/waiver_003.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 1048576, // 1 MB
                'checksum' => hash('sha256', 'test_document_3'),
                'storage_adapter' => 'local',
                'metadata' => '{"original_filename":"minor_consent_parent.tiff","original_size":5242880,"conversion_date":"2025-02-10 09:45:00"}',
                'uploaded_by' => 2,
                'created' => '2025-02-10 09:45:00',
                'modified' => '2025-02-10 09:45:00',
                'created_by' => 2,
                'modified_by' => 2,
            ],
            [
                'id' => 4,
                'entity_type' => 'WaiverType',
                'entity_id' => 1,
                'file_path' => 'waiver_templates/general_liability_template.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 2097152, // 2 MB
                'checksum' => hash('sha256', 'template_document_1'),
                'storage_adapter' => 'local',
                'metadata' => '{"is_template":true}',
                'uploaded_by' => 1,
                'created' => '2024-12-01 10:00:00',
                'modified' => '2024-12-01 10:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
        ];
        parent::init();
    }
}
