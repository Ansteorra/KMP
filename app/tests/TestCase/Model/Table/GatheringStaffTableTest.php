<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\GatheringStaffTable;
use App\Test\TestCase\BaseTestCase;

/**
 * App\Model\Table\GatheringStaffTable Test Case
 */
class GatheringStaffTableTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\GatheringStaffTable
     */
    protected $GatheringStaff;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('GatheringStaff') ? [] : ['className' => GatheringStaffTable::class];
        $this->GatheringStaff = $this->getTableLocator()->get('GatheringStaff', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->GatheringStaff);

        parent::tearDown();
    }

    /**
     * Test validation for member_id or sca_name requirement
     *
     * @return void
     */
    public function testValidationRequiresMemberOrScaName(): void
    {
        $staff = $this->GatheringStaff->newEntity([
            'gathering_id' => 1,
            'role' => 'Steward',
            'is_steward' => true,
            'email' => 'test@example.com',
            // Missing both member_id and sca_name
        ]);

        $this->assertFalse($this->GatheringStaff->save($staff));
        $this->assertNotEmpty($staff->getErrors());
    }

    /**
     * Test that stewards must have contact info
     *
     * @return void
     */
    public function testStewardRequiresContactInfo(): void
    {
        $staff = $this->GatheringStaff->newEntity([
            'gathering_id' => 1,
            'member_id' => 1,
            'role' => 'Steward',
            'is_steward' => true,
            // Missing email and phone
        ]);

        $this->assertFalse($this->GatheringStaff->save($staff));
        $this->assertArrayHasKey('email', $staff->getErrors());
    }

    /**
     * Test that steward with email saves successfully
     *
     * @return void
     */
    public function testStewardWithEmailSaves(): void
    {
        $staff = $this->GatheringStaff->newEntity([
            'gathering_id' => 1,
            'member_id' => 1,
            'role' => 'Steward',
            'is_steward' => true,
            'email' => 'steward@example.com',
            'sort_order' => 0,
        ]);

        $this->assertNotFalse($this->GatheringStaff->save($staff));
    }

    /**
     * Test that steward with phone saves successfully
     *
     * @return void
     */
    public function testStewardWithPhoneSaves(): void
    {
        $staff = $this->GatheringStaff->newEntity([
            'gathering_id' => 1,
            'member_id' => 1,
            'role' => 'Steward',
            'is_steward' => true,
            'phone' => '555-0123',
            'sort_order' => 0,
        ]);

        $this->assertNotFalse($this->GatheringStaff->save($staff));
    }

    /**
     * Test that non-steward without contact info saves successfully
     *
     * @return void
     */
    public function testNonStewardWithoutContactInfoSaves(): void
    {
        $staff = $this->GatheringStaff->newEntity([
            'gathering_id' => 1,
            'sca_name' => 'John of Example',
            'role' => 'Water Bearer',
            'is_steward' => false,
            'sort_order' => 100,
        ]);

        $this->assertNotFalse($this->GatheringStaff->save($staff));
    }

    /**
     * Test findStewards finder
     *
     * @return void
     */
    public function testFindStewards(): void
    {
        $stewards = $this->GatheringStaff->find('stewards')->toArray();

        $this->assertNotEmpty($stewards);
        foreach ($stewards as $steward) {
            $this->assertTrue($steward->is_steward);
        }
    }

    /**
     * Test findOtherStaff finder
     *
     * @return void
     */
    public function testFindOtherStaff(): void
    {
        $otherStaff = $this->GatheringStaff->find('otherStaff')->toArray();

        $this->assertNotEmpty($otherStaff);
        foreach ($otherStaff as $staff) {
            $this->assertFalse($staff->is_steward);
        }
    }
}
