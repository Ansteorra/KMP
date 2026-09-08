<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Services\RecommendationSubmissionInput;
use InvalidArgumentException;

class RecommendationSubmissionInputTest extends BaseTestCase
{
    public function testOnlyExplicitSubmissionFieldsAndLinksSurvive(): void
    {
        $input = RecommendationSubmissionInput::normalize([
            'reason' => 'Synthetic nomination',
            'requester_id' => self::ADMIN_MEMBER_ID,
            'created_by' => self::ADMIN_MEMBER_ID,
            'state' => 'Approved',
            'bestowal_id' => 1,
            'member' => ['email_address' => 'private@example.test'],
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'gatherings' => ['_ids' => ['2', 3, 2]],
        ], true);
        $this->assertSame([
            'reason' => 'Synthetic nomination',
            'gatherings' => ['_ids' => [2, 3]],
        ], $input);
    }

    public function testNestedGatheringWritesAreRejected(): void
    {
        $payloads = [
            [['id' => 1, 'name' => 'Changed gathering']],
            [['_new' => true, 'name' => 'Injected gathering']],
            ['_ids' => [1], 0 => ['public_page_enabled' => true]],
            ['_ids' => [['id' => 1]]],
        ];
        foreach ($payloads as $gatherings) {
            try {
                RecommendationSubmissionInput::normalize(['gatherings' => $gatherings]);
                $this->fail('Nested associations must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
