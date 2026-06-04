<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;
use Awards\Model\Table\RecommendationsTable;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Exception\PersistenceFailedException;
use DateTimeZone;
use RuntimeException;
use Throwable;

class RecommendationUpdateService
{
    use RecommendationMutationSupportTrait;

    private RecommendationGroupingService $groupingService;
    private RecommendationStateLogService $stateLogService;
    private RecommendationBestowalStatePolicyService $statePolicyService;

    /**
     * @param \Awards\Services\RecommendationGroupingService|null $groupingService Optional grouping service.
     * @param \Awards\Services\RecommendationStateLogService|null $stateLogService Optional state-log service.
     * @param \Awards\Services\RecommendationBestowalStatePolicyService|null $statePolicyService Optional state policy.
     */
    public function __construct(
        ?RecommendationGroupingService $groupingService = null,
        ?RecommendationStateLogService $stateLogService = null,
        ?RecommendationBestowalStatePolicyService $statePolicyService = null,
    ) {
        $this->stateLogService = $stateLogService ?? new RecommendationStateLogService();
        $this->groupingService = $groupingService ?? new RecommendationGroupingService(
            stateLogService: $this->stateLogService,
        );
        $this->statePolicyService = $statePolicyService ?? new RecommendationBestowalStatePolicyService();
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
        $given = $workingData['given'] ?? null;
        $note = $workingData['note'] ?? null;
        $requestedState = $this->normalizeRequestedState($workingData);
        $currentState = (string)($recommendation->state ?? '');
        $stateIsChanging = $requestedState !== null
            && $requestedState !== $currentState;
        $handoffRequested = $stateIsChanging && $this->statePolicyService->isHandoffState($requestedState);

        if (
            $stateIsChanging
            || $this->statePolicyService->isBestowalManagedState($currentState)
        ) {
            try {
                if ($this->statePolicyService->isBestowalManagedState($currentState)) {
                    $this->statePolicyService->assertUserCanTargetRecommendationState($currentState);
                }
                if ($stateIsChanging) {
                    $this->statePolicyService->assertUserCanTargetRecommendationState($requestedState);
                }
                if ($handoffRequested) {
                    if ($recommendation->recommendation_group_id !== null) {
                        throw new RuntimeException(
                            'Grouped child recommendations must be handed off through their group head.',
                        );
                    }
                    $this->statePolicyService->assertBestowalSyncMappingsConfigured();
                }
            } catch (RuntimeException $exception) {
                return [
                    'success' => false,
                    'recommendation' => $recommendation,
                    'output' => null,
                    'eventName' => null,
                    'eventPayload' => null,
                    'errorCode' => 'invalid_state_transition',
                    'message' => $exception->getMessage(),
                    'errors' => [],
                ];
            }
        }

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
        $beforeState = (string)($recommendation->state ?? 'New');
        $beforeStatus = (string)($recommendation->status ?? $this->stateLogService->inferStatusForState($beforeState));
        $createdNote = null;
        $memberIdInputProvided = false;
        if (array_key_exists('member_id', $workingData)) {
            $memberIdValue = $workingData['member_id'];
            $memberIdInputProvided = (is_int($memberIdValue) && $memberIdValue > 0)
                || (is_string($memberIdValue) && ctype_digit($memberIdValue) && (int)$memberIdValue > 0);
        }
        if ($memberIdInputProvided) {
            $explicitNotFound = false;
        }
        if (!$memberIdInputProvided && $explicitNotFound !== true) {
            unset($workingData['member_id']);
        }

        unset($workingData['given'], $workingData['note'], $workingData['not_found']);

        try {
            $savedId = $recommendationsTable->getConnection()->transactional(
                function () use (
                    &$recommendation,
                    &$createdNote,
                    $recommendationsTable,
                    $workingData,
                    $beforeMemberId,
                    $beforeState,
                    $beforeStatus,
                    $explicitNotFound,
                    $memberIdInputProvided,
                    $given,
                    $note,
                    $authorId,
                ): int {
                    $recommendation = $recommendationsTable->patchEntity(
                        $recommendation,
                        $workingData,
                        ['associated' => ['Gatherings']],
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

                    if ($given !== null && $given !== '') {
                        $recommendation->given = new DateTime(
                            $given . ' 00:00:00',
                            new DateTimeZone('UTC'),
                        );
                    } else {
                        $recommendation->given = null;
                    }

                    $saved = $recommendationsTable->saveOrFail(
                        $recommendation,
                        ['associated' => ['Gatherings']],
                    );

                    $this->stateLogService->logStateTransition(
                        (int)$saved->id,
                        $beforeState,
                        (string)$saved->state,
                        $beforeStatus,
                        $saved->status !== null ? (string)$saved->status : null,
                        $authorId,
                    );

                    if ($beforeStatus !== (string)$saved->status && $saved->recommendation_group_id === null) {
                        $this->groupingService->syncLinkedChildrenState($saved, $authorId);
                    }

                    if ($note) {
                        $createdNote = $recommendationsTable->Notes->newEmptyEntity();
                        $createdNote->entity_id = $saved->id;
                        $createdNote->subject = 'Recommendation Updated';
                        $createdNote->entity_type = 'Awards.Recommendations';
                        $createdNote->body = $note;
                        $createdNote->author_id = $authorId;
                        $recommendationsTable->Notes->saveOrFail($createdNote);
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
                'errorCode' => 'handoff_failed',
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
            'previousState' => $beforeState,
            'previousStatus' => $beforeStatus,
            'stateChanged' => $beforeState !== (string)$savedRecommendation->state,
            'noteId' => $createdNote?->id,
            'noteSubject' => $createdNote?->subject,
            'noteBody' => $createdNote?->body,
            'notFound' => $explicitNotFound ?? false,
        ];
        $output = $this->buildOutputData($recommendationsTable, $savedRecommendation, $extraOutput);

        return [
            'success' => true,
            'recommendation' => $savedRecommendation,
            'output' => $output,
            'eventName' => null,
            'eventPayload' => null,
            'errorCode' => null,
            'message' => null,
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizeRequestedState(array $data): ?string
    {
        if (!array_key_exists('state', $data)) {
            return null;
        }

        $state = trim((string)$data['state']);

        return $state === '' ? null : $state;
    }

}
