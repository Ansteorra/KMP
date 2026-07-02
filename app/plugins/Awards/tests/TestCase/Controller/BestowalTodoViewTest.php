<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\ActionItem;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Cake\ORM\TableRegistry;

/**
 * Integration tests for the Bestowal view To-Dos checklist tab.
 */
class BestowalTodoViewTest extends HttpIntegrationTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * Persist a bestowal with the fields required by current validation.
     *
     * @return \Awards\Model\Entity\Bestowal
     */
    private function makeBestowal(): Bestowal
    {
        $award = TableRegistry::getTableLocator()->get('Awards.Awards')
            ->find()->select(['id'])->firstOrFail();
        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Test Recipient',
            'award_id' => $award->id,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => 0,
        ]);

        return $bestowals->saveOrFail($bestowal);
    }

    /**
     * Persist an action item owned by the given bestowal.
     *
     * @param int $bestowalId Owning bestowal id
     * @param array<string, mixed> $overrides Field overrides
     * @return \App\Model\Entity\ActionItem
     */
    private function makeTodo(int $bestowalId, array $overrides = []): ActionItem
    {
        $table = TableRegistry::getTableLocator()->get('ActionItems');
        $data = array_merge([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => 'Scroll finished',
            'description' => 'Calligraphy complete',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
        ], $overrides);

        return $table->saveOrFail($table->newEntity($data));
    }

    /**
     * The checklist renders open gating items with a Complete control and the
     * Mark Given action stays disabled while a required check is open.
     *
     * @return void
     */
    public function testViewRendersOpenTodoChecklist(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('To-Dos');
        $this->assertResponseContains('Scroll finished');
        $this->assertResponseContains('Required');
        $this->assertResponseContains('Complete all required checks');
    }

    public function testViewRendersGatheringRequirementForUnbackfilledEventScheduledTodo(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Event Scheduled',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
        ]);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Gathering required');
        $this->assertResponseContains('Assign Gathering and Complete');
    }

    /**
     * When every gating item is complete, the Mark Given action is offered.
     *
     * @return void
     */
    public function testViewOffersMarkGivenWhenGatingComplete(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, [
            'status' => ActionItem::STATUS_COMPLETED,
        ]);

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Mark Given');
        $this->assertResponseContains('All required checks are complete.');
    }
}
