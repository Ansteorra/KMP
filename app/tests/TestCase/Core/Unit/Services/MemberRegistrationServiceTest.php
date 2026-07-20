<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Unit\Services;

use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use App\Services\MemberRegistrationService;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Laminas\Diactoros\UploadedFile;

final class MemberRegistrationServiceTest extends BaseTestCase
{
    private MemberRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MemberRegistrationService();
    }

    public function testApplyRegistrationDataCopiesFormFieldsOntoMember(): void
    {
        $member = new Member();

        $this->service->applyRegistrationData($member, [
            'sca_name' => 'Lady Coverage',
            'branch_id' => self::TEST_BRANCH_LOCAL_ID,
            'first_name' => 'Casey',
            'middle_name' => 'Q',
            'last_name' => 'Tester',
            'street_address' => '123 Testing Lane',
            'city' => 'Austin',
            'state' => 'TX',
            'zip' => '78701',
            'phone_number' => '555-111-2222',
            'email_address' => 'coverage@example.com',
            'birth_month' => '5',
            'birth_year' => '1991',
        ]);

        $this->assertSame('Lady Coverage', $member->get('sca_name'));
        $this->assertEquals(self::TEST_BRANCH_LOCAL_ID, $member->get('branch_id'));
        $this->assertSame('Casey', $member->get('first_name'));
        $this->assertSame('Q', $member->get('middle_name'));
        $this->assertSame('Tester', $member->get('last_name'));
        $this->assertSame('123 Testing Lane', $member->get('street_address'));
        $this->assertSame('Austin', $member->get('city'));
        $this->assertSame('TX', $member->get('state'));
        $this->assertSame('78701', $member->get('zip'));
        $this->assertSame('555-111-2222', $member->get('phone_number'));
        $this->assertSame('coverage@example.com', $member->get('email_address'));
        $this->assertEquals(5, $member->get('birth_month'));
        $this->assertEquals(1991, $member->get('birth_year'));
    }

    public function testAssignStatusAndTokensSetsAdultMemberActiveWithResetToken(): void
    {
        $member = new Member([
            'birth_month' => 1,
            'birth_year' => 1990,
        ]);

        $this->service->assignStatusAndTokens($member);
        $expiresOn = $member->get('password_token_expires_on');

        $this->assertSame(Member::STATUS_ACTIVE, $member->get('status'));
        $this->assertNotEmpty($member->get('password'));
        $this->assertNotEmpty($member->get('password_token'));
        $this->assertInstanceOf(DateTime::class, $expiresOn);
        $this->assertGreaterThan(time(), $expiresOn->getTimestamp());
    }

    public function testAssignStatusAndTokensMarksMinorUnverifiedWithoutResetToken(): void
    {
        $member = new Member([
            'birth_month' => 1,
            'birth_year' => (int)date('Y') - 10,
        ]);

        $this->service->assignStatusAndTokens($member);

        $this->assertSame(Member::STATUS_UNVERIFIED_MINOR, $member->get('status'));
        $this->assertNotEmpty($member->get('password'));
        $this->assertEmpty($member->get('password_token'));
        $this->assertNull($member->get('password_token_expires_on'));
    }

    public function testAssignStatusAndTokensPreservesExistingAdultCredentials(): void
    {
        $existingToken = StaticHelpers::generateToken(32);
        $member = new Member([
            'birth_month' => 1,
            'birth_year' => 1990,
            'password' => 'already-set-password',
            'password_token' => $existingToken,
        ]);
        $hashedPassword = $member->get('password');

        $this->service->assignStatusAndTokens($member);

        $this->assertSame(Member::STATUS_ACTIVE, $member->get('status'));
        $this->assertSame($hashedPassword, $member->get('password'));
        $this->assertSame($existingToken, $member->get('password_token'));
        $this->assertNull($member->get('password_token_expires_on'));
    }

    public function testBuildAdultRegistrationEmailVarsIncludesResetAndSecretaryContracts(): void
    {
        $member = new Member([
            'id' => 4321,
            'sca_name' => 'Lady Adult Coverage',
            'password_token' => 'adult-reset-token',
            'membership_card_path' => 'member-card.png',
        ]);

        $vars = $this->service->buildAdultRegistrationEmailVars($member);

        $this->assertSame('http://localhost/members/reset-password/adult-reset-token', $vars['resetUrl']);
        $this->assertSame($vars['resetUrl'], $vars['registrationVars']['passwordResetUrl']);
        $this->assertSame('Lady Adult Coverage', $vars['registrationVars']['memberScaName']);
        $this->assertSame('http://localhost/members/view/4321', $vars['secretaryVars']['memberViewUrl']);
        $this->assertSame('uploaded', $vars['secretaryVars']['memberCardPresent']);
        $this->assertSame(
            $vars['registrationVars']['siteAdminSignature'],
            $vars['secretaryVars']['siteAdminSignature'],
        );
    }

    public function testBuildAdultRegistrationEmailVarsLeavesResetUrlBlankWithoutToken(): void
    {
        $member = new Member([
            'id' => 5432,
            'sca_name' => 'Lady No Token',
        ]);

        $vars = $this->service->buildAdultRegistrationEmailVars($member);

        $this->assertSame('', $vars['resetUrl']);
        $this->assertSame('', $vars['registrationVars']['passwordResetUrl']);
        $this->assertSame('not uploaded', $vars['secretaryVars']['memberCardPresent']);
    }

    public function testBuildMinorRegistrationEmailVarsIncludesMinorReviewContract(): void
    {
        $member = new Member([
            'id' => 6543,
            'sca_name' => 'Minor Coverage',
            'membership_card_path' => 'minor-card.png',
        ]);

        $vars = $this->service->buildMinorRegistrationEmailVars($member);

        $this->assertSame('http://localhost/members/view/6543', $vars['memberViewUrl']);
        $this->assertSame('Minor Coverage', $vars['memberScaName']);
        $this->assertSame('uploaded', $vars['memberCardPresent']);
        $this->assertNotSame('', $vars['siteAdminSignature']);
    }

    public function testProcessScaCardUploadRejectsSpoofedImageContent(): void
    {
        $fixturePath = dirname(__DIR__, 4) . DS . 'test_files' . DS . 'queued-job.json';
        $size = filesize($fixturePath);
        $this->assertIsInt($size);
        $file = new UploadedFile(
            $fixturePath,
            $size,
            UPLOAD_ERR_OK,
            'spoofed-member-card.png',
            'image/png',
        );

        $result = $this->service->processScaCardUpload($file);
        $message = $result['message'] ?? null;

        $this->assertFalse($result['success']);
        $this->assertSame('File content does not match an allowed image type.', $message);
    }
}
