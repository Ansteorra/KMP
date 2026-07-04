<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\ActionItem;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\KMP\GridColumns\BestowalsGridColumns;
use Awards\Model\Entity\Bestowal;
use Cake\ORM\TableRegistry;
use DOMDocument;
use DOMXPath;

/**
 * Coverage for the bestowal grid to-do summary badge and the remaining-to-do
 * dropdown filter handler (works across multiple to-do paths via shared keys).
 */
class BestowalTodoGridTest extends HttpIntegrationTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();
    }

    /**
     * @param string $scaName Recipient display name
     * @return \Awards\Model\Entity\Bestowal
     */
    private function makeBestowal(string $scaName, string $lifecycleStatus = Bestowal::LIFECYCLE_OPEN): Bestowal
    {
        $award = TableRegistry::getTableLocator()->get('Awards.Awards')
            ->find()->select(['id'])->firstOrFail();
        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => $scaName,
            'award_id' => $award->id,
            'lifecycle_status' => $lifecycleStatus,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);

        return $bestowals->saveOrFail($bestowal);
    }

    /**
     * @param int $bestowalId Owning bestowal id
     * @param string $sourceRef Template item key
     * @param string $status Action item status
     * @param bool $gating Whether the check gates Mark Given
     * @return \App\Model\Entity\ActionItem
     */
    private function makeTodo(
        int $bestowalId,
        string $sourceRef,
        string $status = ActionItem::STATUS_OPEN,
        bool $gating = true,
    ): ActionItem {
        $table = TableRegistry::getTableLocator()->get('ActionItems');

        return $table->saveOrFail($table->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => ucfirst(str_replace('_', ' ', $sourceRef)),
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => $status,
            'is_gating' => $gating,
            'sort_order' => 1,
            'source_ref' => $sourceRef,
        ]));
    }

    /**
     * The main grid renders the to-do progress badge with a popover trigger and
     * an accessible label conveying the required-check progress.
     *
     * @return void
     */
    public function testGridDataRendersTodoSummaryBadge(): void
    {
        $name = 'todo-grid-badge-' . uniqid();
        $bestowal = $this->makeBestowal($name);
        $this->makeTodo((int)$bestowal->id, 'has_scroll', ActionItem::STATUS_COMPLETED);
        $this->makeTodo((int)$bestowal->id, 'event_scheduled', ActionItem::STATUS_OPEN);

        $url = '/awards/bestowals/grid-data?' . http_build_query(['search' => $name]);
        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains($name);
        $this->assertResponseContains('data-bs-toggle="popover"');
        $this->assertResponseContains('To-Do progress: 1 of 2 required checks complete');
    }

    /**
     * Full table refreshes keep the bulk-selection cell on every row, but only
     * render a visible checkbox for bestowals with a To-Do the user can complete.
     *
     * @return void
     */
    public function testGridDataRendersSelectionCellForEveryBestowalRow(): void
    {
        $prefix = 'todo-grid-checkbox-' . uniqid();
        $open = $this->makeBestowal($prefix . '-open');
        $noActionableTodo = $this->makeBestowal($prefix . '-no-actionable');
        $cancelled = $this->makeBestowal($prefix . '-cancelled', Bestowal::LIFECYCLE_CANCELLED);
        $this->makeTodo((int)$open->id, 'has_scroll');
        $this->makeTodo((int)$noActionableTodo->id, 'has_scroll', ActionItem::STATUS_COMPLETED);

        $url = '/awards/bestowals/grid-data?' . http_build_query([
            'search' => $prefix,
            'limit' => 10,
            'ignore_default' => 1,
        ]);
        $this->get($url);

        $this->assertResponseOk();

        $dom = new DOMDocument();
        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $dom->loadHTML((string)$this->_response->getBody());
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);
        $xpath = new DOMXPath($dom);

        $expectedCellCount = null;
        foreach (
            [
                (int)$open->id => true,
                (int)$noActionableTodo->id => false,
                (int)$cancelled->id => false,
            ] as $bestowalId => $shouldRenderCheckbox
        ) {
            $rows = $xpath->query('//tr[@data-id="' . $bestowalId . '"]');
            $this->assertNotFalse($rows);
            $this->assertSame(1, $rows->length, 'Expected one rendered row for bestowal ' . $bestowalId);

            $cells = $xpath->query('./td', $rows->item(0));
            $this->assertNotFalse($cells);
            $expectedCellCount ??= $cells->length;
            $this->assertSame(
                $expectedCellCount,
                $cells->length,
                'Every bestowal row should render the same number of cells.',
            );

            $checkboxes = $xpath->query(
                './td[1]//input[@type="checkbox" and @data-grid-view-target="rowCheckbox"]',
                $rows->item(0),
            );
            $this->assertNotFalse($checkboxes);
            $this->assertSame(
                $shouldRenderCheckbox ? 1 : 0,
                $checkboxes->length,
                'Unexpected bulk checkbox visibility for bestowal ' . $bestowalId,
            );
        }
    }

    /**
     * The "__remaining" filter narrows to bestowals with an open required check
     * and the "__complete" filter excludes them.
     *
     * @return void
     */
    public function testRemainingFilterNarrowsByOpenGating(): void
    {
        $remaining = $this->makeBestowal('todo-filter-remaining-' . uniqid());
        $complete = $this->makeBestowal('todo-filter-complete-' . uniqid());
        $this->makeTodo((int)$remaining->id, 'has_scroll', ActionItem::STATUS_OPEN);
        $this->makeTodo((int)$complete->id, 'has_scroll', ActionItem::STATUS_COMPLETED);

        $scope = [(int)$remaining->id, (int)$complete->id];
        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');

        $remainingIds = BestowalsGridColumns::applyTodoRemainingFilter(
            $bestowals->find()->where(['Bestowals.id IN' => $scope]),
            '__remaining',
        )->all()->extract('id')->toList();

        $completeIds = BestowalsGridColumns::applyTodoRemainingFilter(
            $bestowals->find()->where(['Bestowals.id IN' => $scope]),
            '__complete',
        )->all()->extract('id')->toList();

        $this->assertSame([(int)$remaining->id], array_map('intval', $remainingIds));
        $this->assertSame([(int)$complete->id], array_map('intval', $completeIds));
    }

    /**
     * The "open:<key>" filter matches a specific check across paths, ignoring
     * bestowals whose open check is a different key.
     *
     * @return void
     */
    public function testOpenCheckKeyFilterMatchesSharedKey(): void
    {
        $hasScroll = $this->makeBestowal('todo-filter-scroll-' . uniqid());
        $scheduled = $this->makeBestowal('todo-filter-scheduled-' . uniqid());
        $this->makeTodo((int)$hasScroll->id, 'has_scroll', ActionItem::STATUS_OPEN);
        $this->makeTodo((int)$scheduled->id, 'event_scheduled', ActionItem::STATUS_OPEN);

        $scope = [(int)$hasScroll->id, (int)$scheduled->id];
        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');

        $matchedIds = BestowalsGridColumns::applyTodoRemainingFilter(
            $bestowals->find()->where(['Bestowals.id IN' => $scope]),
            'open:has_scroll',
        )->all()->extract('id')->toList();

        $this->assertSame([(int)$hasScroll->id], array_map('intval', $matchedIds));
    }

    /**
     * "Has any remaining To Dos" includes a bestowal whose only open check is
     * optional, while "required remaining" excludes it and "completed all"
     * excludes it (because an optional check is still open).
     *
     * @return void
     */
    public function testAnyRemainingSeparatesOptionalFromRequired(): void
    {
        $optionalOpen = $this->makeBestowal('todo-filter-optional-' . uniqid());
        $allDone = $this->makeBestowal('todo-filter-alldone-' . uniqid());
        // Optional (non-gating) check still open on the first bestowal.
        $this->makeTodo((int)$optionalOpen->id, 'regalia_allotted', ActionItem::STATUS_OPEN, false);
        // Second bestowal has every check completed.
        $this->makeTodo((int)$allDone->id, 'has_scroll', ActionItem::STATUS_COMPLETED);

        $scope = [(int)$optionalOpen->id, (int)$allDone->id];
        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');

        $anyRemaining = BestowalsGridColumns::applyTodoRemainingFilter(
            $bestowals->find()->where(['Bestowals.id IN' => $scope]),
            '__remaining_any',
        )->all()->extract('id')->toList();

        $requiredRemaining = BestowalsGridColumns::applyTodoRemainingFilter(
            $bestowals->find()->where(['Bestowals.id IN' => $scope]),
            '__remaining',
        )->all()->extract('id')->toList();

        $completedAll = BestowalsGridColumns::applyTodoRemainingFilter(
            $bestowals->find()->where(['Bestowals.id IN' => $scope]),
            '__complete',
        )->all()->extract('id')->toList();

        $this->assertSame([(int)$optionalOpen->id], array_map('intval', $anyRemaining));
        $this->assertSame([], array_map('intval', $requiredRemaining));
        $this->assertSame([(int)$allDone->id], array_map('intval', $completedAll));
    }

    /**
     * The dropdown filter options always offer the path-agnostic remaining and
     * complete states.
     *
     * @return void
     */
    public function testFilterOptionsIncludePathAgnosticStates(): void
    {
        $values = array_column(BestowalsGridColumns::getTodoRemainingFilterOptions(), 'value');

        $this->assertContains('__remaining_any', $values);
        $this->assertContains('__remaining', $values);
        $this->assertContains('__complete', $values);
    }
}
