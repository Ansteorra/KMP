<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\Member;
use App\Services\MemberPrivacy;
use App\Test\TestCase\BaseTestCase;

class MemberPrivacyTest extends BaseTestCase
{
    public function testListProjectionExcludesPrivateFieldsWithoutMutatingOriginal(): void
    {
        $member = new Member([
            'id' => self::TEST_MEMBER_AGATHA_ID, 'sca_name' => 'Synthetic public name',
            'first_name' => 'PRIVATE_CANARY', 'email_address' => 'private@example.test',
            'membership_number' => 'SECRET_NUMBER', 'additional_info' => ['private' => 'SECRET_NOTE'],
            'branch_id' => self::TEST_BRANCH_LOCAL_ID,
        ]);
        $safe = MemberPrivacy::listRow($member, false);
        $this->assertSame('Synthetic public name', $safe->sca_name);
        $this->assertNull($safe->first_name);
        $this->assertNull($safe->email_address);
        $this->assertNull($safe->membership_number);
        $this->assertNull($safe->additional_info);
        $this->assertSame('PRIVATE_CANARY', $member->first_name);
        $this->assertSame($member, MemberPrivacy::listRow($member, true));
    }
}
