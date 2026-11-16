<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;
use App\Services\ServiceResult;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Throwable;

/** Changes a completed outcome without replaying completion or changing checklist history. */
class BestowalOutcomeChangeService
{
    use LocatorAwareTrait;

    /**
     * @param \Cake\ORM\Table|null $bestowals Tenant bestowals table.
     * @param \Awards\Services\BestowalFinalizationService|null $finalization Snapshot resolver.
     */
    public function __construct(
        private ?Table $bestowals = null,
        private ?BestowalFinalizationService $finalization = null,
    ) {
        $this->bestowals ??= $this->fetchTable('Awards.Bestowals');
        $this->finalization ??= new BestowalFinalizationService();
    }

    /** @return array<string, string> Allowed choices from completed terminal snapshots. */
    public function options(int $bestowalId): array
    {
        $options = [];
        foreach ($this->finalization->terminalItems($bestowalId) as $item) {
            if ($item->isCompleted()) {
                foreach (BestowalTodoTemplateItem::terminalOutcomesFor($item) as $status => $label) {
                    if (
                        trim((string)$status) !== '' && mb_strlen((string)$status) <= 100
                        && !in_array(mb_strtolower(trim((string)$status)), ['open', 'cancelled'], true)
                    ) {
                        $options[$status] = $label;
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Serialize with other owner changes and append an audit note on the completed terminal To-Do.
     *
     * @param int $bestowalId Bestowal ID.
     * @param string $expectedStatus State displayed when opening the form.
     * @param string $status Requested snapshotted outcome.
     * @param \App\KMP\KmpIdentityInterface $actor Authorized editor.
     * @param string|null $note Optional explanation.
     * @return \App\Services\ServiceResult
     */
    public function change(
        int $bestowalId,
        string $expectedStatus,
        string $status,
        KmpIdentityInterface $actor,
        ?string $note = null,
    ): ServiceResult {
        $connection = $this->bestowals->getConnection();
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        $connection->enableSavePoints();
        try {
            return $connection->transactional(function () use ($bestowalId, $expectedStatus, $status, $actor, $note) {
                $bestowal = $this->bestowals->find()->where(['Bestowals.id' => $bestowalId])
                    ->epilog('FOR UPDATE')->first();
                if (!$bestowal || !$bestowal->isCompleted()) {
                    return new ServiceResult(false, __('Only completed bestowals can change terminal state.'));
                }
                if (!$actor->checkCan('updateState', $bestowal)) {
                    return new ServiceResult(false, __('You cannot change this bestowal.'));
                }
                if ($bestowal->lifecycle_status !== $expectedStatus) {
                    return new ServiceResult(false, __('The terminal state changed. Reload the bestowal and try again.'));
                }
                $options = $this->options($bestowalId);
                if (!array_key_exists($status, $options)) {
                    return new ServiceResult(false, __('Choose a valid terminal state from this bestowal\'s outcomes.'));
                }
                if ($status === $expectedStatus) {
                    return new ServiceResult(true, null, $bestowal);
                }
                $terminal = null;
                foreach ($this->finalization->terminalItems($bestowalId) as $item) {
                    if ($item->isCompleted() && array_key_exists($status, BestowalTodoTemplateItem::terminalOutcomesFor($item))) {
                        $terminal = $item;
                        break;
                    }
                }
                if ($terminal === null) {
                    return new ServiceResult(false, __('The terminal To-Do changed. Reload the bestowal and try again.'));
                }
                $bestowal->lifecycle_status = $status;
                $bestowal->modified_by = (int)$actor->getIdentifier();
                $this->bestowals->saveOrFail($bestowal);

                $audit = __('Terminal state changed from "{0}" to "{1}".', ucfirst($expectedStatus), $options[$status]);
                if (trim((string)$note) !== '') {
                    $audit .= "\n" . trim($note);
                }
                $logs = $this->fetchTable('ActionItemLogs');
                $logs->saveOrFail($logs->newEntity([
                    'action_item_id' => $terminal->id,
                    'from_status' => ActionItem::STATUS_COMPLETED,
                    'to_status' => ActionItem::STATUS_COMPLETED,
                    'note' => $audit,
                    'created_by' => (int)$actor->getIdentifier(),
                ]));

                return new ServiceResult(true, null, $bestowal);
            });
        } catch (Throwable $e) {
            Log::error('Bestowal terminal state change failed: ' . $e->getMessage());

            return new ServiceResult(false, __('The terminal state could not be changed.'));
        } finally {
            if (!$savePointsWereEnabled) {
                $connection->disableSavePoints();
            }
        }
    }
}
