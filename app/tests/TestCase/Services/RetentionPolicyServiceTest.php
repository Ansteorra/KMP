<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\RetentionPolicyService;
use Cake\I18n\Date;
use App\Test\TestCase\BaseTestCase;

/**
 * App\Services\RetentionPolicyService Test Case
 */
class RetentionPolicyServiceTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \App\Services\RetentionPolicyService
     */
    protected $RetentionPolicyService;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->RetentionPolicyService = new RetentionPolicyService();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->RetentionPolicyService);
        parent::tearDown();
    }

    /**
     * Test calculateRetentionDate with gathering_end_date anchor
     *
     * @return void
     */
    public function testCalculateRetentionDateWithGatheringEndDate(): void
    {
        $policy = '{"anchor":"gathering_end_date","duration":{"years":7}}';
        $gatheringEndDate = new Date('2025-06-15');

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, $gatheringEndDate, null);

        $this->assertTrue($result->isSuccess(), 'Should successfully calculate retention date');
        $this->assertInstanceOf(Date::class, $result->getData());

        $expected = new Date('2032-06-15'); // 7 years later
        $this->assertEquals($expected->format('Y-m-d'), $result->getData()->format('Y-m-d'));
    }

    /**
     * Test calculateRetentionDate with upload_date anchor
     *
     * @return void
     */
    public function testCalculateRetentionDateWithUploadDate(): void
    {
        $policy = '{"anchor":"upload_date","duration":{"months":6}}';
        $uploadDate = new Date('2025-01-01');

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, null, $uploadDate);

        $this->assertTrue($result->isSuccess(), 'Should successfully calculate retention date');

        $expected = new Date('2025-07-01'); // 6 months later
        $this->assertEquals($expected->format('Y-m-d'), $result->getData()->format('Y-m-d'));
    }

    /**
     * Test calculateRetentionDate with permanent anchor
     *
     * @return void
     */
    public function testCalculateRetentionDateWithPermanent(): void
    {
        $policy = '{"anchor":"permanent"}';

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, null, null);

        $this->assertTrue($result->isSuccess(), 'Should successfully handle permanent retention');
        $this->assertNull($result->getData(), 'Permanent retention should return null date');
    }

    /**
     * Test calculateRetentionDate with multiple duration units
     *
     * @return void
     */
    public function testCalculateRetentionDateWithMultipleDurations(): void
    {
        $policy = '{"anchor":"gathering_end_date","duration":{"years":2,"months":6,"days":15}}';
        $gatheringEndDate = new Date('2025-01-01');

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, $gatheringEndDate, null);

        $this->assertTrue($result->isSuccess());

        // 2 years + 6 months + 15 days from 2025-01-01
        $expected = new Date('2027-07-16');
        $this->assertEquals($expected->format('Y-m-d'), $result->getData()->format('Y-m-d'));
    }

    /**
     * Test calculateRetentionDate with invalid JSON
     *
     * @return void
     */
    public function testCalculateRetentionDateWithInvalidJson(): void
    {
        $policy = 'not-valid-json';

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, null, null);

        $this->assertFalse($result->isSuccess(), 'Should fail with invalid JSON');
        $this->assertStringContainsString('Invalid JSON', $result->getError());
    }

    /**
     * Test calculateRetentionDate with missing anchor
     *
     * @return void
     */
    public function testCalculateRetentionDateWithMissingAnchor(): void
    {
        $policy = '{"duration":{"years":7}}';

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, null, null);

        $this->assertFalse($result->isSuccess(), 'Should fail with missing anchor');
        $this->assertStringContainsString('anchor', $result->getError());
    }

    /**
     * Test calculateRetentionDate with invalid anchor value
     *
     * @return void
     */
    public function testCalculateRetentionDateWithInvalidAnchor(): void
    {
        $policy = '{"anchor":"invalid_anchor","duration":{"years":7}}';

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, null, null);

        $this->assertFalse($result->isSuccess(), 'Should fail with invalid anchor');
        $this->assertStringContainsString('Invalid anchor', $result->getError());
    }

    /**
     * Test calculateRetentionDate with missing required date for anchor
     *
     * @return void
     */
    public function testCalculateRetentionDateWithMissingRequiredDate(): void
    {
        // gathering_end_date anchor requires $gatheringEndDate
        $policy = '{"anchor":"gathering_end_date","duration":{"years":7}}';

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, null, null);

        $this->assertFalse($result->isSuccess(), 'Should fail when required date is missing');
        $this->assertStringContainsString('required', $result->getError());
    }

    /**
     * Test calculateRetentionDate with missing duration for non-permanent anchor
     *
     * @return void
     */
    public function testCalculateRetentionDateWithMissingDuration(): void
    {
        $policy = '{"anchor":"gathering_end_date"}';
        $gatheringEndDate = new Date('2025-06-15');

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, $gatheringEndDate, null);

        $this->assertFalse($result->isSuccess(), 'Should fail when duration is missing for non-permanent anchor');
        $this->assertStringContainsString('duration', $result->getError());
    }

    /**
     * Test validatePolicy with valid policy
     *
     * @return void
     */
    public function testValidatePolicyWithValidPolicy(): void
    {
        $policy = '{"anchor":"gathering_end_date","duration":{"years":7}}';

        $result = $this->RetentionPolicyService->validatePolicy($policy);

        $this->assertTrue($result->isSuccess(), 'Should validate correct policy');
    }

    /**
     * Test validatePolicy with valid permanent policy
     *
     * @return void
     */
    public function testValidatePolicyWithPermanent(): void
    {
        $policy = '{"anchor":"permanent"}';

        $result = $this->RetentionPolicyService->validatePolicy($policy);

        $this->assertTrue($result->isSuccess(), 'Should validate permanent policy without duration');
    }

    /**
     * Test validatePolicy with invalid JSON
     *
     * @return void
     */
    public function testValidatePolicyWithInvalidJson(): void
    {
        $policy = 'not-valid-json';

        $result = $this->RetentionPolicyService->validatePolicy($policy);

        $this->assertFalse($result->isSuccess(), 'Should fail validation with invalid JSON');
    }

    /**
     * Test validatePolicy with all duration types
     *
     * @return void
     */
    public function testValidatePolicyWithAllDurationTypes(): void
    {
        $policy = '{"anchor":"upload_date","duration":{"years":1,"months":2,"days":3}}';

        $result = $this->RetentionPolicyService->validatePolicy($policy);

        $this->assertTrue($result->isSuccess(), 'Should validate policy with years, months, and days');
    }

    /**
     * Test getHumanReadableDescription
     *
     * @return void
     */
    public function testGetHumanReadableDescription(): void
    {
        // Test gathering_end_date with years
        $policy = '{"anchor":"gathering_end_date","duration":{"years":7}}';
        $description = $this->RetentionPolicyService->getHumanReadableDescription($policy);
        $this->assertEquals('Retain for 7 years after gathering end date', $description);

        // Test upload_date with months
        $policy = '{"anchor":"upload_date","duration":{"months":6}}';
        $description = $this->RetentionPolicyService->getHumanReadableDescription($policy);
        $this->assertEquals('Retain for 6 months after upload date', $description);

        // Test permanent
        $policy = '{"anchor":"permanent"}';
        $description = $this->RetentionPolicyService->getHumanReadableDescription($policy);
        $this->assertEquals('Retain permanently', $description);

        // Test multiple durations
        $policy = '{"anchor":"gathering_end_date","duration":{"years":2,"months":6,"days":15}}';
        $description = $this->RetentionPolicyService->getHumanReadableDescription($policy);
        $this->assertEquals('Retain for 2 years, 6 months, 15 days after gathering end date', $description);

        // Test invalid JSON
        $policy = 'invalid';
        $description = $this->RetentionPolicyService->getHumanReadableDescription($policy);
        $this->assertEquals('Invalid retention policy', $description);
    }

    /**
     * Test calculateRetentionDate with days only
     *
     * @return void
     */
    public function testCalculateRetentionDateWithDaysOnly(): void
    {
        $policy = '{"anchor":"upload_date","duration":{"days":90}}';
        $uploadDate = new Date('2025-01-01');

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, null, $uploadDate);

        $this->assertTrue($result->isSuccess());

        $expected = new Date('2025-04-01'); // 90 days later
        $this->assertEquals($expected->format('Y-m-d'), $result->getData()->format('Y-m-d'));
    }

    /**
     * Test calculateRetentionDate handles edge cases
     *
     * @return void
     */
    public function testCalculateRetentionDateEdgeCases(): void
    {
        // Test leap year calculation
        $policy = '{"anchor":"gathering_end_date","duration":{"years":1}}';
        $gatheringEndDate = new Date('2024-02-29'); // Leap year

        $result = $this->RetentionPolicyService->calculateRetentionDate($policy, $gatheringEndDate, null);

        $this->assertTrue($result->isSuccess());
        $expected = new Date('2025-02-28'); // Non-leap year
        $this->assertEquals($expected->format('Y-m-d'), $result->getData()->format('Y-m-d'));
    }
}
