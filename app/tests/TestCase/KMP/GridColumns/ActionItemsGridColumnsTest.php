<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP\GridColumns;

use App\KMP\GridColumns\ActionItemsGridColumns;
use App\Model\Entity\ActionItem;
use Cake\TestSuite\TestCase;

/**
 * Tests for My To-Dos grid column metadata.
 */
class ActionItemsGridColumnsTest extends TestCase
{
    public function testTodoDropdownFiltersAreConfigured(): void
    {
        $columns = ActionItemsGridColumns::getColumns();
        $dropdowns = ActionItemsGridColumns::getDropdownFilterColumns();

        $this->assertSame('dropdown', $columns['title']['filterType']);
        $this->assertArrayHasKey('title', $dropdowns);

        $this->assertSame('dropdown', $columns['requirement']['filterType']);
        $this->assertSame('ActionItems.is_gating', $columns['requirement']['queryField']);
        $this->assertSame([
            ['value' => '1', 'label' => 'Required'],
            ['value' => '0', 'label' => 'Optional'],
        ], $columns['requirement']['filterOptions']);
        $this->assertArrayHasKey('requirement', $dropdowns);

        $this->assertSame('dropdown', $columns['gathering']['filterType']);
        $this->assertTrue($columns['gathering']['filterOnly']);
        $this->assertSame(
            ActionItemsGridColumns::NO_GATHERING_FILTER_VALUE,
            $columns['gathering']['filterOptions'][0]['value'],
        );
        $this->assertSame(
            ['class' => ActionItemsGridColumns::class, 'method' => 'applyGatheringFilter'],
            $columns['gathering']['customFilterHandler'],
        );
        $this->assertArrayHasKey('gathering', $dropdowns);
    }

    public function testSystemViewsUseLockedHiddenMemberScopeFilters(): void
    {
        $columns = ActionItemsGridColumns::getColumns();
        $memberScope = $columns['member_scope'];

        $this->assertTrue($memberScope['filterOnly']);
        $this->assertTrue($memberScope['lockedFilter']);
        $this->assertFalse($memberScope['showInFilterMenu']);
        $this->assertFalse($memberScope['exportable']);
        $this->assertSame(
            ['class' => ActionItemsGridColumns::class, 'method' => 'applyMemberScopeFilter'],
            $memberScope['customFilterHandler'],
        );

        $views = ActionItemsGridColumns::getSystemViews();
        $this->assertContains(
            ['field' => 'status_label', 'operator' => 'eq', 'value' => ActionItem::STATUS_OPEN],
            $views['sys-todos-open']['config']['filters'],
        );
        $this->assertContains(
            [
                'field' => 'member_scope',
                'operator' => 'eq',
                'value' => ActionItemsGridColumns::MEMBER_SCOPE_ACTIONABLE,
            ],
            $views['sys-todos-open']['config']['filters'],
        );
        $this->assertContains(
            ['field' => 'status_label', 'operator' => 'eq', 'value' => ActionItem::STATUS_COMPLETED],
            $views['sys-todos-completed']['config']['filters'],
        );
        $this->assertContains(
            [
                'field' => 'member_scope',
                'operator' => 'eq',
                'value' => ActionItemsGridColumns::MEMBER_SCOPE_COMPLETED_BY_ME,
            ],
            $views['sys-todos-completed']['config']['filters'],
        );
    }
}
