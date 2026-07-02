<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\ActionItems;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemCompletionForm;
use App\Services\ActionItems\ActionItemCompletionFormProviderInterface;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Services\ActionItems\ActionItemService;
use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;

class ActionItemServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ActionItemCompletionFormRegistry::clear();
    }

    protected function tearDown(): void
    {
        ActionItemCompletionFormRegistry::clear();
        parent::tearDown();
    }

    public function testCompleteStillEnforcesRequiredFieldWhenEligibilityBypassed(): void
    {
        ActionItemCompletionFormRegistry::register(
            'test',
            new class implements ActionItemCompletionFormProviderInterface {
                public function canHandle(ActionItem $item): bool
                {
                    return $item->entity_type === 'Tests.RequiredOwner';
                }

                public function buildForm(ActionItem $item, KmpIdentityInterface $user): ?ActionItemCompletionForm
                {
                    return null;
                }

                public function applySubmission(
                    ActionItem $item,
                    array $data,
                    int $actorId,
                    KmpIdentityInterface $user,
                ): ServiceResult {
                    return new ServiceResult(true);
                }

                public function validateCompletion(ActionItem $item): ServiceResult
                {
                    return new ServiceResult(false, 'Required field missing.');
                }
            },
        );
        $item = $this->makeRequiredItem();
        $service = new ActionItemService();

        $result = $service->complete((int)$item->id, self::ADMIN_MEMBER_ID, null, false);

        $this->assertFalse($result->success);
        $this->assertSame('Required field missing.', $result->reason);
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isOpen());
    }

    private function makeRequiredItem(): ActionItem
    {
        $table = TableRegistry::getTableLocator()->get('ActionItems');

        return $table->saveOrFail($table->newEntity([
            'entity_type' => 'Tests.RequiredOwner',
            'entity_id' => 90001,
            'title' => 'Required field todo',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
            'completion_config' => [
                'required_fields' => [
                    [
                        'provider' => 'Tests.Required',
                        'field' => 'required_value',
                    ],
                ],
            ],
        ]));
    }
}
