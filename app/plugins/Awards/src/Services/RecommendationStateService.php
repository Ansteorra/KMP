<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use DateTimeZone;
use Exception;

/**
 * Handles recommendation state machine transitions and bulk state updates.
 *
 * Encapsulates the transactional logic for changing recommendation states,
 * including gathering assignment, given-date setting, close-reason recording,
 * and bulk-note creation. Also handles kanban drag-and-drop reordering.
 *
 * @see \Awards\Model\Entity\Recommendation::getStatuses() For state → status mapping
 */
class RecommendationStateService
{
    /**
     * Perform a transactional bulk state transition for multiple recommendations.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table instance.
     * @param array{ids: array<string>, newState: string, gathering_id: string|null, given: string|null, note: string|null, close_reason: string|null} $data Bulk update parameters.
     * @param int $authorId The current user ID for note attribution.
     * @return bool True on success, false on failure.
     */
    public function bulkUpdateStates(
        Table $recommendationsTable,
        array $data,
        int $authorId,
    ): bool {
        $ids = $data['ids'];
        $newState = $data['newState'];
        $gatheringId = $data['gathering_id'] ?? null;
        $given = $data['given'] ?? null;
        $note = $data['note'] ?? null;
        $closeReason = $data['close_reason'] ?? null;

        $recommendationsTable->getConnection()->begin();
        try {
            $statusList = Recommendation::getStatuses();
            $newStatus = '';

            // Find the status corresponding to the new state
            foreach ($statusList as $key => $value) {
                foreach ($value as $state) {
                    if ($state === $newState) {
                        $newStatus = $key;
                        break 2;
                    }
                }
            }

            // Build flat associative array for updateAll
            $updateFields = [
                'state' => $newState,
                'status' => $newStatus,
            ];

            if (Recommendation::supportsGatheringAssignmentForState((string)$newState)) {
                if ($gatheringId) {
                    $updateFields['gathering_id'] = $gatheringId;
                }
            } else {
                $updateFields['gathering_id'] = null;
            }

            if ($given) {
                // Create DateTime at midnight UTC to preserve the exact date
                $updateFields['given'] = new DateTime($given . ' 00:00:00', new DateTimeZone('UTC'));
            }

            if ($closeReason) {
                $updateFields['close_reason'] = $closeReason;
            }

            if (!$recommendationsTable->updateAll($updateFields, ['id IN' => $ids])) {
                throw new Exception('Failed to update recommendations');
            }

            if ($note) {
                foreach ($ids as $id) {
                    $newNote = $recommendationsTable->Notes->newEmptyEntity();
                    $newNote->entity_id = $id;
                    $newNote->subject = 'Recommendation Bulk Updated';
                    $newNote->entity_type = 'Awards.Recommendations';
                    $newNote->body = $note;
                    $newNote->author_id = $authorId;

                    if (!$recommendationsTable->Notes->save($newNote)) {
                        throw new Exception('Failed to save note');
                    }
                }
            }

            $recommendationsTable->getConnection()->commit();

            return true;
        } catch (Exception $e) {
            $recommendationsTable->getConnection()->rollback();
            Log::error('Error updating recommendations: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Update a single recommendation's state and position for kanban drag-and-drop.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table instance.
     * @param \Awards\Model\Entity\Recommendation $recommendation The recommendation entity.
     * @param string $newCol The new kanban column (state) value.
     * @param string|int|null $placeBefore ID of the recommendation to place before, or -1/null.
     * @param string|int|null $placeAfter ID of the recommendation to place after, or -1/null.
     * @return string 'success' or 'failed'.
     */
    public function kanbanMove(
        Table $recommendationsTable,
        Recommendation $recommendation,
        string $newCol,
        string|int|null $placeBefore,
        string|int|null $placeAfter,
    ): string {
        $recommendation->state = $newCol;
        $placeAfter = $placeAfter ?? -1;
        $placeBefore = $placeBefore ?? -1;
        $recommendation->state_date = DateTime::now();

        $recommendationsTable->getConnection()->begin();

        try {
            if (!$recommendationsTable->save($recommendation)) {
                throw new Exception('Failed to save recommendation state');
            }

            if ($placeBefore != -1) {
                if (!$recommendationsTable->moveBefore($recommendation->id, $placeBefore)) {
                    throw new Exception('Failed to move recommendation before target');
                }
            }

            if ($placeAfter != -1) {
                if (!$recommendationsTable->moveAfter($recommendation->id, $placeAfter)) {
                    throw new Exception('Failed to move recommendation after target');
                }
            }

            $recommendationsTable->getConnection()->commit();

            return 'success';
        } catch (Exception $e) {
            $recommendationsTable->getConnection()->rollback();
            Log::error('Error updating kanban: ' . $e->getMessage());

            return 'failed';
        }
    }
}
