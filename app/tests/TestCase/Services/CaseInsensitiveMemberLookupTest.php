<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Identifier\KMPBruteForcePasswordIdentifier;
use App\Services\MemberAuthenticationService;
use App\Services\MemberSearchService;
use App\Test\TestCase\BaseTestCase;
use ArrayAccess;
use Cake\ORM\TableRegistry;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\Router;

class CaseInsensitiveMemberLookupTest extends BaseTestCase
{
    public function testLoginIdentityLookupIgnoresCase(): void
    {
        $identifier = new class extends KMPBruteForcePasswordIdentifier {
            public function findIdentityForTest(string $username): ArrayAccess|array|null
            {
                return $this->_findIdentity($username);
            }
        };

        $member = $identifier->findIdentityForTest('ADMIN@AMP.ANSTEORRA.ORG');

        $this->assertNotNull($member);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$member->id);
    }

    public function testEmailAvailabilityIgnoresCase(): void
    {
        $service = new MemberSearchService();

        $this->assertTrue($service->isEmailTaken('ADMIN@AMP.ANSTEORRA.ORG'));
    }

    public function testPasswordResetLookupIgnoresCase(): void
    {
        Router::createRouteBuilder('/')->fallbacks(DashedRoute::class);
        $service = new MemberAuthenticationService();
        $result = $service->generatePasswordResetToken('ADMIN@AMP.ANSTEORRA.ORG');

        $this->assertTrue($result['found']);
        $this->assertSame('admin@amp.ansteorra.org', $result['email']);
    }

    public function testEmailUniquenessRuleIgnoresCase(): void
    {
        $members = TableRegistry::getTableLocator()->get('Members');
        $member = $members->newEntity([
            'password' => 'A-valid-test-password-123!',
            'sca_name' => 'Case Collision Tester',
            'first_name' => 'Case',
            'last_name' => 'Tester',
            'email_address' => 'ADMIN@AMP.ANSTEORRA.ORG',
            'status' => 'active',
        ]);

        $this->assertFalse($members->save($member));
        $this->assertArrayHasKey('email_address', $member->getErrors());
    }
}
