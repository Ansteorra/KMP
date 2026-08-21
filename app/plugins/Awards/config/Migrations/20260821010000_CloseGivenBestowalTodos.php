<?php

declare(strict_types=1);

use App\Model\Entity\ActionItem;
use Awards\Model\Entity\Bestowal;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Close legacy open to-dos whose bestowal is already Given.
 */
class CloseGivenBestowalTodos extends BaseMigration
{
    private const BACKFILL_NOTE =
        'Existing bestowal is given; remaining to-do is not applicable.';

    /**
     * Preserve one audit log for every backfilled to-do closure.
     */
    public function up(): void
    {
        if (
            !$this->hasTable('awards_bestowals')
            || !$this->hasTable('action_items')
            || !$this->hasTable('action_item_logs')
        ) {
            return;
        }

        $locator = TableRegistry::getTableLocator();
        $bestowals = $locator->get('Awards.Bestowals');
        $actionItems = $locator->get('ActionItems');
        $actionItemLogs = $locator->get('ActionItemLogs');
        $givenBestowals = $bestowals->find()
            ->select(['id', 'created_by', 'modified_by'])
            ->where([
                'lifecycle_status' => Bestowal::LIFECYCLE_GIVEN,
                'deleted IS' => null,
            ])
            ->all()
            ->indexBy('id')
            ->toArray();
        if ($givenBestowals === []) {
            return;
        }

        $openItems = $actionItems->find()
            ->where([
                'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'entity_id IN' => array_keys($givenBestowals),
                'status' => ActionItem::STATUS_OPEN,
                'deleted IS' => null,
            ])
            ->all()
            ->toList();
        $now = DateTime::now();

        $actionItems->getConnection()->transactional(function () use (
            $actionItems,
            $actionItemLogs,
            $givenBestowals,
            $openItems,
            $now,
        ): void {
            foreach ($openItems as $item) {
                $bestowal = $givenBestowals[(int)$item->entity_id];
                $actorId = $bestowal->modified_by
                    ?? $bestowal->created_by
                    ?? $item->modified_by
                    ?? $item->created_by;
                $actorId = $actorId !== null ? (int)$actorId : null;
                $item->status = ActionItem::STATUS_CANCELLED;
                $item->completed_at = null;
                $item->completed_by = null;
                $item->modified = $now;
                $item->modified_by = $actorId;
                $actionItems->saveOrFail($item);

                $actionItemLogs->saveOrFail($actionItemLogs->newEntity([
                    'action_item_id' => (int)$item->id,
                    'from_status' => ActionItem::STATUS_OPEN,
                    'to_status' => ActionItem::STATUS_CANCELLED,
                    'note' => self::BACKFILL_NOTE,
                    'created' => $now,
                    'created_by' => $actorId,
                ]));
            }
        });
    }

    /**
     * Audit data closures remain valid when application code is rolled back.
     */
    public function down(): void
    {
    }
}
