<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Entity;

use App\Model\Entity\Member;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use InvalidArgumentException;

class MemberTest extends BaseTestCase
{
    protected function makeAdultMember(array $data = []): Member
    {
        $defaults = [
            'sca_name' => 'Test Name',
            'first_name' => 'First',
            'last_name' => 'Last',
            'street_address' => '123 Main',
            'city' => 'City',
            'state' => 'ST',
            'zip' => '12345',
            'phone_number' => '555-555-5555',
            'email_address' => 'test@example.com',
            'birth_month' => (int)date('n'),
            'birth_year' => (int)date('Y') - 25,
            'status' => Member::STATUS_VERIFIED_MEMBERSHIP,
            'membership_expires_on' => new Date(date('Y-m-d', strtotime('+1 year'))),
        ];
        $merged = array_merge($defaults, $data);
        return new Member($merged);
    }

    protected function makeMinorMember(array $data = []): Member
    {
        $defaults = [
            'sca_name' => 'Minor Name',
            'birth_month' => (int)date('n'),
            'birth_year' => (int)date('Y') - 10,
            'status' => Member::STATUS_UNVERIFIED_MINOR,
        ];
        $merged = array_merge($defaults, $data);
        return new Member($merged);
    }

    public function testPasswordSetterHashesNonEmptyValue(): void
    {
        $member = new Member(['password' => 'PlainSecret']);
        $this->assertNotEquals('PlainSecret', $member->password, 'Password should be hashed');
        $this->assertStringStartsWith('$2y$', $member->password, 'Bcrypt hash should start with $2y$');
    }

    public function testPasswordSetterIgnoresEmptyString(): void
    {
        $member = new Member(['password' => 'InitialSecret']);
        $initialHash = $member->password;
        $member->password = '';
        $this->assertSame($initialHash, $member->password, 'Empty password assignment should retain previous hash');
    }

    public function testStatusSetterAllowsValidStatuses(): void
    {
        $member = new Member();
        $valid = [
            Member::STATUS_ACTIVE,
            Member::STATUS_DEACTIVATED,
            Member::STATUS_VERIFIED_MEMBERSHIP,
            Member::STATUS_UNVERIFIED_MINOR,
            Member::STATUS_VERIFIED_MINOR,
            Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
            Member::STATUS_MINOR_PARENT_VERIFIED,
        ];
        foreach ($valid as $status) {
            $member->status = $status;
            $this->assertEquals($status, $member->status);
        }
    }

    public function testStatusSetterRejectsInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $member = new Member();
        $member->status = 'not_a_real_status';
    }

    public function testAgeVirtual(): void
    {
        $year = (int)date('Y') - 30;
        $month = 6;
        $member = new Member(['birth_year' => $year, 'birth_month' => $month]);
        $expectedAge = (new DateTime())->diff((new DateTime())->setDate($year, $month, 1))->y;
        $this->assertEquals($expectedAge, $member->age);
    }

    public function testAgeVirtualReturnsNullWithoutBirthInfo(): void
    {
        $member = new Member(['birth_year' => null, 'birth_month' => null]);
        $this->assertNull($member->age);
    }

    public function testBirthdateVirtual(): void
    {
        $year = 2000;
        $month = 5;
        $member = new Member(['birth_year' => $year, 'birth_month' => $month]);
        $birthdate = $member->birthdate;
        $this->assertInstanceOf(DateTime::class, $birthdate);
        $this->assertEquals($year, (int)$birthdate->format('Y'));
        $this->assertEquals($month, (int)$birthdate->format('n'));
        $this->assertEquals(1, (int)$birthdate->format('j'));
    }

    public function testBirthdateVirtualNullWhenIncomplete(): void
    {
        $member = new Member(['birth_year' => 2000, 'birth_month' => null]);
        $this->assertNull($member->birthdate);
    }

    public function testNameForHeraldAllComponents(): void
    {
        $member = new Member([
            'sca_name' => 'Aiden MacGregor',
            'title' => 'Lord',
            'pronunciation' => 'AY-den mac-GREG-or',
            'pronouns' => 'he/him',
        ]);
        $this->assertEquals('Lord Aiden MacGregor (AY-den mac-GREG-or) - he/him', $member->name_for_herald);
    }

    public function testNameForHeraldMinimal(): void
    {
        $member = new Member(['sca_name' => 'Elena of the Woods']);
        $this->assertEquals('Elena of the Woods', $member->name_for_herald);
    }

    public function testExpiresOnToString(): void
    {
        $member = new Member(['membership_expires_on' => new Date('2030-12-31')]);
        $this->assertEquals('2030-12-31', $member->expires_on_to_string);
        $member->membership_expires_on = null;
        $this->assertSame('', $member->expires_on_to_string);
    }

    public function testPublicDataForMinor(): void
    {
        $member = $this->makeMinorMember(['sca_name' => 'Minor Person']);
        $public = $member->publicData();
        $this->assertArrayHasKey('sca_name', $public);
        $this->assertArrayHasKey('branch', $public, 'Branch key should exist even if null for minor');
        $this->assertArrayHasKey('publicLinks', $public);
        $this->assertArrayHasKey('publicAdditionalInfo', $public);
        $this->assertCount(4, $public, 'Minor public data should only expose limited keys');
    }

    public function testPublicDataForAdultFiltersSensitiveFields(): void
    {
        $member = $this->makeAdultMember([
            'password' => 'Secret',
            'failed_login_attempts' => 3,
            'parent_id' => 5,
            'membership_number' => 12345,
            'additional_info' => ['Website' => 'https://example.com'],
        ]);
        $public = $member->publicData();
        $this->assertArrayNotHasKey('password', $public);
        $this->assertArrayNotHasKey('failed_login_attempts', $public);
        $this->assertArrayNotHasKey('parent_id', $public);
        $this->assertArrayNotHasKey('membership_expires_on', $public);
        $this->assertArrayHasKey('publicLinks', $public);
        $this->assertArrayHasKey('publicAdditionalInfo', $public);
    }

    public function testGetNonWarrantableReasonsUnder18(): void
    {
        $member = $this->makeMinorMember();
        $reasons = $member->getNonWarrantableReasons();
        $this->assertContains('Member is under 18', $reasons);
        $this->assertFalse($member->warrantable);
    }

    public function testGetNonWarrantableReasonsMissingLegalName(): void
    {
        $member = $this->makeAdultMember(['first_name' => null, 'last_name' => null]);
        // Force status to verified to isolate name issue
        $member->status = Member::STATUS_VERIFIED_MEMBERSHIP;
        $reasons = $member->getNonWarrantableReasons();
        $this->assertContains('Legal name is not set', $reasons);
    }

    public function testGetNonWarrantableReasonsMembershipNotVerified(): void
    {
        $member = $this->makeAdultMember(['status' => Member::STATUS_ACTIVE]);
        $reasons = $member->getNonWarrantableReasons();
        $this->assertContains('Membership is not verified', $reasons);
    }

    public function testGetNonWarrantableReasonsMembershipExpired(): void
    {
        $member = $this->makeAdultMember([
            'status' => Member::STATUS_VERIFIED_MEMBERSHIP,
            'membership_expires_on' => new Date(date('Y-m-d', strtotime('-1 day'))),
        ]);
        $reasons = $member->getNonWarrantableReasons();
        $this->assertContains('Membership is expired', $reasons);
    }

    public function testGetNonWarrantableReasonsFullyEligible(): void
    {
        $member = $this->makeAdultMember();
        $member->status = Member::STATUS_VERIFIED_MEMBERSHIP;
        $member->membership_expires_on = new Date(date('Y-m-d', strtotime('+1 year')));
        $reasons = $member->getNonWarrantableReasons();
        $this->assertEmpty($reasons);
        $this->assertTrue($member->warrantable);
    }

    public function testWarrantableReviewPopulatesReasons(): void
    {
        $member = $this->makeAdultMember(['status' => Member::STATUS_ACTIVE]);
        $member->warrantableReview();
        $this->assertNotEmpty($member->non_warrantable_reasons);
        $this->assertContains('Membership is not verified', $member->non_warrantable_reasons);
    }

    public function testAgeUpReviewTransitionsUnverifiedMinor(): void
    {
        $member = new Member([
            'status' => Member::STATUS_UNVERIFIED_MINOR,
            'birth_month' => (int)date('n'),
            'birth_year' => (int)date('Y') - 18,
            'parent_id' => 10,
        ]);
        $member->ageUpReview();
        $this->assertNull($member->parent_id);
        $this->assertEquals(Member::STATUS_ACTIVE, $member->status);
    }

    public function testAgeUpReviewTransitionsVerifiedMinor(): void
    {
        $member = new Member([
            'status' => Member::STATUS_VERIFIED_MINOR,
            'birth_month' => (int)date('n'),
            'birth_year' => (int)date('Y') - 18,
            'parent_id' => 11,
        ]);
        $member->ageUpReview();
        $this->assertNull($member->parent_id);
        $this->assertEquals(Member::STATUS_VERIFIED_MEMBERSHIP, $member->status);
    }

    public function testAgeUpReviewTransitionsMinorMembershipVerified(): void
    {
        $member = new Member([
            'status' => Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
            'birth_month' => (int)date('n'),
            'birth_year' => (int)date('Y') - 18,
            'parent_id' => 12,
        ]);
        $member->ageUpReview();
        $this->assertNull($member->parent_id);
        $this->assertEquals(Member::STATUS_VERIFIED_MEMBERSHIP, $member->status);
    }

    public function testAgeUpReviewNoChangeForAdult(): void
    {
        $member = $this->makeAdultMember(['parent_id' => null]);
        $originalStatus = $member->status;
        $member->ageUpReview();
        $this->assertEquals($originalStatus, $member->status);
    }

    public function testAgeUpReviewNoChangeForDeactivated(): void
    {
        $member = new Member([
            'status' => Member::STATUS_DEACTIVATED,
            'birth_month' => (int)date('n'),
            'birth_year' => (int)date('Y') - 18,
            'parent_id' => 13,
        ]);
        $member->ageUpReview();
        $this->assertEquals(Member::STATUS_DEACTIVATED, $member->status);
        $this->assertEquals(13, $member->parent_id, 'Deactivated should not be modified');
    }
}
