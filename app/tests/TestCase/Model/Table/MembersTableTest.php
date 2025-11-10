<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Entity\Member;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\Table;
use Cake\Datasource\EntityInterface;

/**
 * MembersTableTest
 *
 * Focuses on table-level concerns (validation, rules, beforeSave side-effects, static helpers).
 * Entity virtuals & authorization helpers will be covered in Member entity tests separately.
 */
class MembersTableTest extends BaseTestCase
{
    /** @var \App\Model\Table\MembersTable */
    protected Table $Members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Members = $this->getTableLocator()->get('Members');
    }

    protected function tearDown(): void
    {
        unset($this->Members);
        parent::tearDown();
    }

    /**
     * Provide a fully valid member data array (adult, verified) for baseline tests.
     */
    protected function validMemberData(array $overrides = []): array
    {
        $data = [
            'password' => 'VerySecurePass123!',
            'sca_name' => 'Test SCA Name',
            'first_name' => 'First',
            'last_name' => 'Last',
            'street_address' => '123 Main St',
            'city' => 'City',
            'state' => 'TX',
            'zip' => '75001',
            'phone_number' => '5551234567',
            'email_address' => 'unique' . microtime(true) . '@example.com',
            'birth_month' => 6,
            'birth_year' => 1990,
            'status' => Member::STATUS_VERIFIED_MEMBERSHIP,
            'branch_id' => self::KINGDOM_BRANCH_ID,
        ];
        return array_replace($data, $overrides);
    }

    public function testValidationFailsMissingRequiredFields(): void
    {
        $entity = $this->Members->newEntity([]); // empty create scenario
        $this->assertTrue($entity->hasErrors(), 'Entity should have validation errors');
        $errors = $entity->getErrors();
        // Only fields with requirePresence(...) will appear as missing
        foreach (['password', 'first_name', 'last_name', 'email_address', 'street_address', 'city', 'state', 'zip', 'phone_number'] as $requiredField) {
            $this->assertArrayHasKey($requiredField, $errors, "Expected validation error for {$requiredField}");
        }
    }

    public function testValidationPassesWithValidData(): void
    {
        $entity = $this->Members->newEntity($this->validMemberData());
        $this->assertFalse($entity->hasErrors(), 'Valid data should have no errors');
    }

    public function testPasswordIsHashedOnEntityCreation(): void
    {
        $plain = 'VerySecurePass123!';
        $entity = $this->Members->newEntity($this->validMemberData(['password' => $plain]));
        // Hashing occurs in setter before save
        $this->assertNotSame($plain, $entity->password, 'Password should be hashed at assignment');
        $this->assertStringStartsWith('$2y$', $entity->password, 'Expect bcrypt hash prefix');
    }

    public function testUniqueEmailRulePreventsDuplicate(): void
    {
        // Use existing seed email (admin@amp.ansteorra.org) to trigger uniqueness rule
        $entity = $this->Members->newEntity($this->validMemberData(['email_address' => 'admin@amp.ansteorra.org']));
        $saved = $this->Members->save($entity);
        $this->assertFalse($saved, 'Duplicate email save should fail');
        $this->assertArrayHasKey('email_address', $entity->getErrors(), 'Error should be on email_address');
    }

    public function testInvalidStatusThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $entity = $this->Members->newEntity($this->validMemberData(['status' => 'not_a_valid_status']));
        // Access status to ensure setter executed during patching
        $entity->status;
    }

    public function testAgeUpReviewTransitionsMinorToAdultWithoutSave(): void
    {
        $entity = $this->Members->newEntity($this->validMemberData([
            'birth_year' => (int)date('Y') - 18,
            'birth_month' => (int)date('n'),
            'status' => Member::STATUS_VERIFIED_MINOR,
            'parent_id' => 10,
        ]));
        $this->assertSame(Member::STATUS_VERIFIED_MINOR, $entity->status);
        $entity->ageUpReview();
        $this->assertSame(Member::STATUS_VERIFIED_MEMBERSHIP, $entity->status, 'Status should transition to verified membership');
        $this->assertNull($entity->parent_id, 'Parent should be cleared');
    }

    public function testWarrantableReviewReasonGenerationWithoutSave(): void
    {
        $entity = $this->Members->newEntity($this->validMemberData([
            'membership_expires_on' => new \Cake\I18n\Date('+1 year'),
        ]));
        $entity->warrantableReview();
        $this->assertTrue($entity->warrantable, 'Should be warrantable with valid data');
        $this->assertSame([], $entity->non_warrantable_reasons);

        // Break criteria
        $entity->birth_year = (int)date('Y') - 10; // under 18
        $entity->membership_expires_on = new \Cake\I18n\Date('-1 day');
        $entity->warrantableReview();
        $this->assertFalse($entity->warrantable, 'Should not be warrantable now');
        $this->assertContains('Member is under 18', $entity->non_warrantable_reasons);
        $this->assertContains('Membership is expired', $entity->non_warrantable_reasons);
    }

    public function testTimezoneValidationAcceptsValidAndRejectsInvalid(): void
    {
        $valid = $this->Members->newEntity($this->validMemberData(['timezone' => 'America/Chicago']));
        $this->assertFalse($valid->hasErrors(), 'Valid timezone should pass');

        $invalid = $this->Members->newEntity($this->validMemberData(['timezone' => 'Not/AZone']));
        $this->assertTrue($invalid->hasErrors(), 'Invalid timezone should have errors');
        $this->assertArrayHasKey('timezone', $invalid->getErrors());
    }

    public function testGetValidationQueueCountMatchesManualQuery(): void
    {
        $manualCount = $this->Members->find()
            ->where([
                'Members.deleted IS' => null,
                'OR' => [
                    ['Members.membership_card_path IS NOT' => null],
                    ['Members.status IN' => [
                        Member::STATUS_UNVERIFIED_MINOR,
                        Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
                    ]],
                ],
            ])->count();
        $staticCount = \App\Model\Table\MembersTable::getValidationQueueCount();
        $this->assertSame($manualCount, $staticCount, 'Static method count should match manual query');
    }
}
