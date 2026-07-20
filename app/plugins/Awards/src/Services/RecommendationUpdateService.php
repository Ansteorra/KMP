<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Model\Table\RecommendationsTable;
use Cake\Log\Log;
use Cake\ORM\Exception\PersistenceFailedException;
use RuntimeException;
use Throwable;

class RecommendationUpdateService
{
    use RecommendationMutationSupportTrait;

    private RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService;

    /**
     * @param \Awards\Services\RecommendationApprovalWorkflowLifecycleService|null $approvalLifecycleService Optional lifecycle service.
     */
    public function __construct(
        ?RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService = null,
    ) {
        $this->approvalLifecycleService = $approvalLifecycleService
            ?? new RecommendationApprovalWorkflowLifecycleService();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(
        RecommendationsTable $recommendationsTable,
        Recommendation $recommendation,
        array $data,
        int $authorId,
    ): array {
        if ($recommendation->isLockedByBestowal()) {
            return [
                'success' => false,
                'recommendation' => $recommendation,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => 'bestowal_locked',
                'message' => 'This recommendation is linked to a bestowal and cannot be edited here.',
                'errors' => [],
            ];
        }

        $resolved = $this->resolveMemberPublicId($recommendationsTable, $data);
        if ($resolved['success'] === false) {
            return [
                'success' => false,
                'recommendation' => $recommendation,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => $resolved['errorCode'],
                'message' => $resolved['message'],
                'errors' => [],
            ];
        }

        $workingData = $resolved['data'];
        $restartApprovalWorkflowConfirmed = $this->normalizeCheckboxValue(
            $workingData['approval_workflow_restart_confirmed'] ?? false,
        );
        unset(
            $workingData['approval_workflow_restart_confirmed'],
            $workingData['current_approval_process_id'],
            $workingData['current_award_id'],
        );
        $note = $workingData['note'] ?? null;

        $explicitNotFound = array_key_exists('not_found', $data)
            ? $this->normalizeCheckboxValue($data['not_found'])
            : null;
        $beforeMemberId = $recommendation->member_id !== null ? (int)$recommendation->member_id : null;
        if ($beforeMemberId === null && $recommendation->id !== null) {
            $persistedRecommendation = $recommendationsTable->find()
                ->select(['id', 'member_id'])
                ->where(['id' => $recommendation->id])
                ->first();
            $beforeMemberId = $persistedRecommendation?->member_id !== null
                ? (int)$persistedRecommendation->member_id
                : null;
        }
        $createdNote = null;
        $memberIdInputProvided = false;
        if (array_key_exists('member_id', $workingData)) {
            $memberIdValue = $workingData['member_id'];
            $memberIdInputProvided = (is_int($memberIdValue) && $memberIdValue >= 0)
                || (is_string($memberIdValue) && ctype_digit($memberIdValue));
        }
        if ($memberIdInputProvided) {
            $explicitNotFound = false;
        }
        if (!$memberIdInputProvided && $explicitNotFound !== true) {
            unset($workingData['member_id']);
        }

        $awardWorkflowChange = $this->detectAwardWorkflowChange($recommendationsTable, $recommendation, $workingData);
        if (
            $awardWorkflowChange['requiresConfirmation']
            && !$restartApprovalWorkflowConfirmed
        ) {
            return [
                'success' => false,
                'recommendation' => $recommendation,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => 'approval_workflow_restart_confirmation_required',
                'message' => 'Changing the award will cancel the current approval workflow. '
                    . 'Confirm the change to continue.',
                'errors' => [],
            ];
        }

        unset(
            $workingData['close_reason'],
            $workingData['gathering_id'],
            $workingData['gathering_name'],
            $workingData['gatherings'],
            $workingData['given'],
            $workingData['note'],
            $workingData['not_found'],
            $workingData['state'],
            $workingData['status'],
        );

        $restartEventName = null;
        $restartEventPayload = null;
        $cancelledApprovalRunIds = [];

        try {
            $savedId = $recommendationsTable->getConnection()->transactional(
                function () use (
                    &$recommendation,
                    &$createdNote,
                    $recommendationsTable,
                    $workingData,
                    $beforeMemberId,
                    $explicitNotFound,
                    $memberIdInputProvided,
                    $note,
                    $authorId,
                    $awardWorkflowChange,
                    &$restartEventName,
                    &$restartEventPayload,
                    &$cancelledApprovalRunIds,
                ): int {
                    $recommendation = $recommendationsTable->patchEntity(
                        $recommendation,
                        $workingData,
                    );

                    $this->normalizeSpecialty($recommendation);

                    if (!$memberIdInputProvided && $explicitNotFound !== true && $beforeMemberId !== null) {
                        $recommendation->member_id = $beforeMemberId;
                    }

                    if ($explicitNotFound !== null) {
                        $recommendation->not_found = $explicitNotFound;
                    }

                    if (empty($recommendation->member_id)) {
                        $recommendation->member_id = null;
                    }

                    if (($explicitNotFound === true) || $recommendation->member_id === null) {
                        $recommendation->member_id = null;
                        $recommendation->call_into_court = null;
                        $recommendation->court_availability = null;
                        $recommendation->person_to_notify = null;
                    } elseif ($beforeMemberId !== $recommendation->member_id) {
                        $recommendation->call_into_court = null;
                        $recommendation->court_availability = null;
                        $recommendation->person_to_notify = null;
                        $this->hydrateMemberDefaults($recommendationsTable, $recommendation);
                    }

                    $this->applyCourtPreferenceDefaults($recommendation);

                    $saved = $recommendationsTable->saveOrFail($recommendation);

                    if ($note) {
                        $createdNote = $recommendationsTable->Notes->newEmptyEntity();
                        $createdNote->entity_id = $saved->id;
                        $createdNote->subject = 'Recommendation Updated';
                        $createdNote->entity_type = 'Awards.Recommendations';
                        $createdNote->body = $note;
                        $createdNote->author_id = $authorId;
                        $recommendationsTable->Notes->saveOrFail($createdNote);
                    }

                    if ($awardWorkflowChange['requiresConfirmation']) {
                        $cancelledApprovalRunIds = $this->approvalLifecycleService->cancelActiveRunsForAwardChange(
                            [(int)$saved->id],
                            $authorId,
                        );
                        if ($awardWorkflowChange['newApprovalProcessId'] !== null) {
                            $restartEventName = 'Awards.ExistingRecommendationApprovalRequested';
                            $restartEventPayload = [
                                'recommendationId' => (int)$saved->id,
                                'actorId' => $authorId,
                                'restartReason' => RecommendationApprovalRun::TERMINAL_REASON_AWARD_CHANGED,
                                'cancelledRunIds' => $cancelledApprovalRunIds,
                            ];
                        }
                    }

                    return (int)$saved->id;
                },
            );
        } catch (PersistenceFailedException $exception) {
            $entity = $exception->getEntity();
            if ($entity instanceof Recommendation) {
                $recommendation = $entity;
            }

            Log::error('Error updating recommendation: ' . $exception->getMessage());

            return [
                'success' => false,
                'recommendation' => $recommendation,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => 'save_failed',
                'message' => 'The recommendation could not be saved.',
                'errors' => $recommendation->getErrors(),
            ];
        } catch (RuntimeException $exception) {
            Log::error('Error updating recommendation: ' . $exception->getMessage());

            return [
                'success' => false,
                'recommendation' => $recommendation,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => 'update_failed',
                'message' => $exception->getMessage(),
                'errors' => $recommendation->getErrors(),
            ];
        } catch (Throwable $exception) {
            Log::error('Error updating recommendation: ' . $exception->getMessage());

            return [
                'success' => false,
                'recommendation' => $recommendation,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => 'update_failed',
                'message' => 'An error occurred while editing the recommendation.',
                'errors' => $recommendation->getErrors(),
            ];
        }

        $savedRecommendation = $recommendationsTable->get($savedId, contain: ['Gatherings']);
        if ($explicitNotFound !== null) {
            $savedRecommendation->not_found = $explicitNotFound;
        }
        $savedMemberId = $savedRecommendation->member_id !== null ? (int)$savedRecommendation->member_id : null;
        $extraOutput = [
            'previousMemberId' => $beforeMemberId,
            'memberChanged' => $beforeMemberId !== $savedMemberId,
            'noteId' => $createdNote?->id,
            'noteSubject' => $createdNote?->subject,
            'noteBody' => $createdNote?->body,
            'notFound' => $explicitNotFound ?? false,
            'approvalWorkflowCancelledRunIds' => $cancelledApprovalRunIds,
        ];
        $output = $this->buildOutputData($recommendationsTable, $savedRecommendation, $extraOutput);

        return [
            'success' => true,
            'recommendation' => $savedRecommendation,
            'output' => $output,
            'eventName' => $restartEventName,
            'eventPayload' => $restartEventPayload,
            'errorCode' => null,
            'message' => null,
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $workingData
     * @return array{requiresConfirmation: bool, currentApprovalProcessId: ?int, newApprovalProcessId: ?int}
     */
    private function detectAwardWorkflowChange(
        RecommendationsTable $recommendationsTable,
        Recommendation $recommendation,
        array $workingData,
    ): array {
        $activeRuns = $recommendation->id !== null
            ? $this->approvalLifecycleService->findActiveRuns([(int)$recommendation->id])
            : [];
        $activeRun = $activeRuns[0] ?? null;
        $currentApprovalProcessId = $activeRun instanceof RecommendationApprovalRun
            ? (int)$activeRun->approval_process_id
            : null;
        $currentAwardId = $recommendation->award_id !== null ? (int)$recommendation->award_id : null;
        $newAwardId = $this->normalizeOptionalInteger($workingData['award_id'] ?? null);

        if ($currentApprovalProcessId === null || $newAwardId === null || $newAwardId === $currentAwardId) {
            return [
                'requiresConfirmation' => false,
                'currentApprovalProcessId' => $currentApprovalProcessId,
                'newApprovalProcessId' => null,
            ];
        }

        $newAward = $recommendationsTable->Awards->find()
            ->select(['id', 'approval_process_id'])
            ->where(['id' => $newAwardId])
            ->first();
        $newApprovalProcessId = $newAward?->approval_process_id !== null
            ? (int)$newAward->approval_process_id
            : null;

        return [
            'requiresConfirmation' => $newApprovalProcessId !== $currentApprovalProcessId,
            'currentApprovalProcessId' => $currentApprovalProcessId,
            'newApprovalProcessId' => $newApprovalProcessId,
        ];
    }

    /**
     * Convert positive numeric form values to integers.
     */
    private function normalizeOptionalInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit($value)) {
            $integer = (int)$value;

            return $integer > 0 ? $integer : null;
        }

        return null;
    }
}
