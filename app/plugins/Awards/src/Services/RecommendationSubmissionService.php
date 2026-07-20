<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;
use Awards\Model\Table\RecommendationsTable;
use Cake\Log\Log;
use Cake\ORM\Exception\PersistenceFailedException;
use Throwable;

class RecommendationSubmissionService
{
    use RecommendationMutationSupportTrait;

    public const EVENT_NAME = 'Awards.RecommendationSubmitted';

    private RecommendationStateLogService $stateLogService;

    public function __construct(?RecommendationStateLogService $stateLogService = null)
    {
        $this->stateLogService = $stateLogService ?? new RecommendationStateLogService();
    }

    /**
     * @param array<string, mixed> $data
     * @param array{id: int, sca_name: string, email_address: string, phone_number: string|null} $requesterContext
     * @return array<string, mixed>
     */
    public function submitAuthenticated(
        RecommendationsTable $recommendationsTable,
        array $data,
        array $requesterContext,
    ): array {
        return $this->submit(
            $recommendationsTable,
            $data,
            function (Recommendation $recommendation) use ($requesterContext): void {
                $recommendation->requester_id = $requesterContext['id'];
                $recommendation->requester_sca_name = $requesterContext['sca_name'];
                $recommendation->contact_email = $requesterContext['email_address'];
                $recommendation->contact_number = $requesterContext['phone_number'] ?? '';
            },
            'Error submitting authenticated recommendation: ',
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function submitPublic(RecommendationsTable $recommendationsTable, array $data): array
    {
        return $this->submit(
            $recommendationsTable,
            $data,
            function (Recommendation $recommendation) use ($recommendationsTable): void {
                if ($recommendation->requester_id === null) {
                    return;
                }

                $requester = $recommendationsTable->Requesters->get(
                    $recommendation->requester_id,
                    select: ['sca_name'],
                );
                $recommendation->requester_sca_name = $requester->sca_name;
            },
            'Error submitting public recommendation: ',
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param callable(\Awards\Model\Entity\Recommendation): void $hydrateRequester
     * @return array<string, mixed>
     */
    private function submit(
        RecommendationsTable $recommendationsTable,
        array $data,
        callable $hydrateRequester,
        string $logPrefix,
    ): array {
        $resolved = $this->resolveMemberPublicId($recommendationsTable, $data);
        if ($resolved['success'] === false) {
            return [
                'success' => false,
                'recommendation' => null,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => $resolved['errorCode'],
                'message' => $resolved['message'],
                'errors' => [],
            ];
        }

        $workingData = $resolved['data'];
        $notFound = $this->normalizeCheckboxValue($data['not_found'] ?? null);
        unset($workingData['not_found']);

        $recommendation = $recommendationsTable->newEmptyEntity();

        try {
            $savedId = $recommendationsTable->getConnection()->transactional(
                function () use (
                    &$recommendation,
                    $recommendationsTable,
                    $workingData,
                    $hydrateRequester,
                    $notFound,
                ): int {
                    $recommendation = $recommendationsTable->patchEntity(
                        $recommendation,
                        $workingData,
                        ['associated' => ['Gatherings']],
                    );

                    $hydrateRequester($recommendation);
                    $this->initializeSubmissionState($recommendation);
                    $recommendation->not_found = $notFound;

                    $this->normalizeSpecialty($recommendation);

                    if (empty($recommendation->member_id)) {
                        $recommendation->member_id = null;
                    }

                    if ($recommendation->not_found) {
                        $recommendation->member_id = null;
                    } elseif ($recommendation->member_id !== null) {
                        $this->hydrateMemberDefaults($recommendationsTable, $recommendation);
                    }

                    $this->applyCourtPreferenceDefaults($recommendation);

                    $saved = $recommendationsTable->saveOrFail(
                        $recommendation,
                        ['associated' => ['Gatherings']],
                    );

                    $this->stateLogService->logStateTransition(
                        (int)$saved->id,
                        'New',
                        (string)$saved->state,
                        'New',
                        $saved->status !== null ? (string)$saved->status : null,
                        $saved->modified_by !== null ? (int)$saved->modified_by : null,
                    );

                    return (int)$saved->id;
                },
            );
        } catch (PersistenceFailedException $exception) {
            $entity = $exception->getEntity();
            if ($entity instanceof Recommendation) {
                $recommendation = $entity;
            }

            Log::error($logPrefix . $exception->getMessage());

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
        } catch (Throwable $exception) {
            Log::error($logPrefix . $exception->getMessage());

            return [
                'success' => false,
                'recommendation' => $recommendation,
                'output' => null,
                'eventName' => null,
                'eventPayload' => null,
                'errorCode' => 'submit_failed',
                'message' => 'An error occurred while submitting the recommendation.',
                'errors' => $recommendation->getErrors(),
            ];
        }

        $savedRecommendation = $recommendationsTable->get($savedId, contain: ['Gatherings']);
        $savedRecommendation->not_found = $notFound;
        $output = $this->buildOutputData(
            $recommendationsTable,
            $savedRecommendation,
            ['notFound' => $notFound],
        );

        return [
            'success' => true,
            'recommendation' => $savedRecommendation,
            'output' => $output,
            'eventName' => self::EVENT_NAME,
            'eventPayload' => [
                'recommendationId' => $output['recommendationId'],
                'awardId' => $output['awardId'],
                'memberId' => $output['memberId'],
                'requesterId' => $output['requesterId'],
                'branchId' => $output['branchId'],
                'state' => $output['state'],
                'memberScaName' => $output['memberScaName'],
                'awardName' => $output['awardName'],
                'reason' => $output['reason'],
                'contactEmail' => $output['contactEmail'],
            ],
            'errorCode' => null,
            'message' => null,
            'errors' => [],
        ];
    }
}
