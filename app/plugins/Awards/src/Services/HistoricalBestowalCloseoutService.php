<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use App\Model\Entity\ActionItem;
use App\Model\Entity\Member;
use App\Services\ActionItems\ActionItemService;
use App\Services\ServiceResult;
use Awards\Model\Entity\Bestowal;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Executes the reviewed Ansteorra historical-bestowal closeout batch.
 *
 * The service is intentionally bound to one immutable manifest. It performs a
 * complete read-only preflight before any write, uses the normal action-item and
 * bestowal finalization services, and verifies both the resulting projections
 * and their audit records inside one transaction.
 */
class HistoricalBestowalCloseoutService
{
    use LocatorAwareTrait;

    public const CANONICAL_MANIFEST_SHA256 =
        '7ae9fa60c9ea59dbc3ea24575ed9cd43cd93a3fb6264595f28883c82b6e4bc4b';

    public const SOURCE_WORKBOOK_SHA256 =
        'f676bc5b3d1207697573bd4a4f441df47b0bfa13a7143ad957dc47233fcc11df';

    private const TENANT = 'ansteorra';

    private const KINGDOM = 'Ansteorra';

    private const APPLY_COUNT = 249;

    private const EXPECTED_COUNTS = [
        'apply' => 249,
        'hold' => 6,
        'alreadyGiven' => 17,
        'separateRepair' => 1,
        'total' => 273,
    ];

    private const MANIFEST_KEYS = [
        'schemaVersion',
        'tenant',
        'kingdom',
        'sourceWorkbookSha256',
        'expectedCounts',
        'records',
    ];

    private const RECORD_KEYS = [
        'recommendationId',
        'disposition',
        'historicalGivenDate',
        'dateSource',
        'reason',
        'expected',
    ];

    private const FINGERPRINT_KEYS = [
        'recommendationStatus',
        'recommendationState',
        'recommendationGivenDate',
        'recommendationDeleted',
        'memberId',
        'memberNameSha256',
        'awardId',
        'gatheringId',
        'bestowalId',
        'bestowalLifecycleStatus',
        'bestowalBestowedDate',
        'bestowalMemberId',
        'bestowalMemberNameSha256',
        'bestowalAwardId',
        'bestowalGatheringId',
        'actionItemId',
        'actionItemStatus',
        'actionItemIsGating',
        'actionItemSourceRef',
        'actionItemCompletionConfig',
    ];

    private const DISPOSITIONS = [
        'apply',
        'hold',
        'already_given',
        'separate_repair',
    ];

    private const RESULT_ACTIONABLE = 'actionable';

    private const RESULT_ALREADY_APPLIED = 'already_applied';

    private const RESULT_CHANGED = 'changed';

    private const RESULT_VERIFIED_CONTROL = 'verified_control';

    private const RESULT_DRIFT = 'drift';

    private ActionItemService $actionItemService;

    private BestowalFinalizationService $finalizationService;

    private RecommendationStateLogService $stateLogService;

    private Table $recommendations;

    private Table $bestowals;

    private Table $bestowalRecommendations;

    private Table $actionItems;

    private Table $actionItemLogs;

    private Table $recommendationStateLogs;

    private Table $members;

    /**
     * @param \App\Services\ActionItems\ActionItemService|null $actionItemService To-do lifecycle service.
     * @param \Awards\Services\BestowalFinalizationService|null $finalizationService Bestowal finalizer.
     * @param \Awards\Services\RecommendationStateLogService|null $stateLogService Recommendation audit service.
     * @param \Cake\ORM\Table|null $recommendations Recommendations table.
     * @param \Cake\ORM\Table|null $bestowals Bestowals table.
     * @param \Cake\ORM\Table|null $bestowalRecommendations Bestowal link table.
     * @param \Cake\ORM\Table|null $actionItems Action items table.
     * @param \Cake\ORM\Table|null $actionItemLogs Action-item logs table.
     * @param \Cake\ORM\Table|null $recommendationStateLogs Recommendation state-log table.
     * @param \Cake\ORM\Table|null $members Members table.
     */
    public function __construct(
        ?ActionItemService $actionItemService = null,
        ?BestowalFinalizationService $finalizationService = null,
        ?RecommendationStateLogService $stateLogService = null,
        ?Table $recommendations = null,
        ?Table $bestowals = null,
        ?Table $bestowalRecommendations = null,
        ?Table $actionItems = null,
        ?Table $actionItemLogs = null,
        ?Table $recommendationStateLogs = null,
        ?Table $members = null,
    ) {
        $this->actionItemService = $actionItemService ?? new ActionItemService();
        $this->finalizationService = $finalizationService ?? new BestowalFinalizationService();
        $this->stateLogService = $stateLogService ?? new RecommendationStateLogService();
        $this->recommendations = $recommendations ?? $this->fetchTable('Awards.Recommendations');
        $this->bestowals = $bestowals ?? $this->fetchTable('Awards.Bestowals');
        $this->bestowalRecommendations = $bestowalRecommendations
            ?? $this->fetchTable('Awards.BestowalRecommendations');
        $this->actionItems = $actionItems ?? $this->fetchTable('ActionItems');
        $this->actionItemLogs = $actionItemLogs ?? $this->fetchTable('ActionItemLogs');
        $this->recommendationStateLogs = $recommendationStateLogs
            ?? $this->fetchTable('Awards.RecommendationsStatesLogs');
        $this->members = $members ?? $this->fetchTable('Members');
    }

    /**
     * Dry-run or atomically apply the canonical remediation.
     *
     * @param string $manifestPath Path to the checked-in manifest.
     * @param string $expectedManifestSha256 Operator-supplied digest assertion.
     * @param int $expectedApplyCount Operator-supplied apply-count assertion.
     * @param int $actorId Active member recorded in audit rows.
     * @param string $tenant Tenant assertion.
     * @param string $changeReference Durable change-management reference.
     * @param bool $apply Whether to persist; false is read-only.
     * @return \App\Services\ServiceResult
     */
    public function run(
        string $manifestPath,
        string $expectedManifestSha256,
        int $expectedApplyCount,
        int $actorId,
        string $tenant,
        string $changeReference,
        bool $apply,
    ): ServiceResult {
        $manifestHash = str_repeat('0', 64);
        $records = [];

        try {
            $this->validateRunInputs(
                $expectedManifestSha256,
                $expectedApplyCount,
                $actorId,
                $tenant,
                $changeReference,
            );
            [$manifest, $manifestHash] = $this->loadManifest($manifestPath, $expectedManifestSha256);
            $records = $this->validateManifest($manifest);
            $this->assertRuntimeGuards($actorId);
            $this->assertSharedConnection();
        } catch (Throwable $e) {
            return new ServiceResult(false, $e->getMessage(), $this->resultData($manifestHash, [], []));
        }

        if (!$apply) {
            try {
                $inspections = $this->normalizeApplyPopulation(
                    $this->inspectRecords($records, false),
                );
            } catch (Throwable $e) {
                return new ServiceResult(false, $e->getMessage(), $this->resultData($manifestHash, $records, []));
            }

            return new ServiceResult(
                !$this->hasDrift($inspections),
                $this->hasDrift($inspections) ? 'Preflight drift detected; no changes were made.' : null,
                $this->resultData($manifestHash, $records, $inspections),
            );
        }

        $connection = $this->recommendations->getConnection();
        $connection->enableSavePoints();
        $latestInspections = [];

        try {
            /** @var array{success: bool, reason: string|null, inspections: array<int, array<string, mixed>>} $outcome */
            $outcome = $connection->transactional(function () use (
                $connection,
                $records,
                $manifestHash,
                $actorId,
                $changeReference,
                &$latestInspections,
            ): array {
                $this->acquireBatchLock($connection);
                $latestInspections = $this->normalizeApplyPopulation(
                    $this->inspectRecords($records, true),
                );

                if ($this->hasDrift($latestInspections)) {
                    return [
                        'success' => false,
                        'reason' => 'Locked preflight found drift; no changes were made.',
                        'inspections' => $latestInspections,
                    ];
                }

                $actionable = $this->countResult($latestInspections, self::RESULT_ACTIONABLE);
                $alreadyApplied = $this->countResult($latestInspections, self::RESULT_ALREADY_APPLIED);
                if ($alreadyApplied === self::APPLY_COUNT) {
                    return [
                        'success' => true,
                        'reason' => null,
                        'inspections' => $latestInspections,
                    ];
                }
                if ($actionable !== self::APPLY_COUNT) {
                    throw new RuntimeException('The locked preflight did not resolve all apply records uniformly.');
                }

                $changedIds = [];
                foreach ($records as $record) {
                    if ($record['disposition'] !== 'apply') {
                        continue;
                    }
                    $this->applyRecord($record, $manifestHash, $actorId, $changeReference);
                    $changedIds[(int)$record['recommendationId']] = true;
                }

                $latestInspections = $this->inspectRecords($records, true);
                if ($this->hasDrift($latestInspections)) {
                    throw new RuntimeException('Post-apply verification found drift; the batch was rolled back.');
                }
                if ($this->countResult($latestInspections, self::RESULT_ALREADY_APPLIED) !== self::APPLY_COUNT) {
                    throw new RuntimeException('Post-apply verification did not find all apply records completed.');
                }

                foreach ($latestInspections as &$inspection) {
                    if (isset($changedIds[(int)$inspection['recommendationId']])) {
                        $inspection['result'] = self::RESULT_CHANGED;
                        $inspection['reason'] = '';
                    }
                }
                unset($inspection);

                return [
                    'success' => true,
                    'reason' => null,
                    'inspections' => $latestInspections,
                ];
            });
        } catch (Throwable $e) {
            try {
                $latestInspections = $this->normalizeApplyPopulation(
                    $this->inspectRecords($records, false),
                );
            } catch (Throwable) {
                $latestInspections = [];
            }

            return new ServiceResult(
                false,
                'Apply failed and was rolled back: ' . $e->getMessage(),
                $this->resultData($manifestHash, $records, $latestInspections),
            );
        }

        return new ServiceResult(
            $outcome['success'],
            $outcome['reason'],
            $this->resultData($manifestHash, $records, $outcome['inspections']),
        );
    }

    /**
     * @param string $expectedHash Expected manifest digest.
     * @param int $expectedApplyCount Expected apply count.
     * @param int $actorId Actor ID.
     * @param string $tenant Tenant slug.
     * @param string $changeReference Change reference.
     * @return void
     */
    private function validateRunInputs(
        string $expectedHash,
        int $expectedApplyCount,
        int $actorId,
        string $tenant,
        string $changeReference,
    ): void {
        if (!hash_equals(self::CANONICAL_MANIFEST_SHA256, strtolower($expectedHash))) {
            throw new RuntimeException('The expected manifest digest is not the canonical reviewed digest.');
        }
        if ($expectedApplyCount !== self::APPLY_COUNT) {
            throw new RuntimeException('The expected apply count must be exactly 249.');
        }
        if ($actorId <= 0) {
            throw new RuntimeException('A positive audit actor ID is required.');
        }
        if ($tenant !== self::TENANT) {
            throw new RuntimeException('This remediation is restricted to the ansteorra tenant.');
        }
        if (
            $changeReference === ''
            || strlen($changeReference) > 200
            || preg_match('/[\x00-\x1f\x7f]/', $changeReference)
        ) {
            throw new RuntimeException('The change reference must be 1-200 printable characters.');
        }
    }

    /**
     * Read once, hash those exact bytes, then decode them.
     *
     * @param string $path Manifest path.
     * @param string $expectedHash Expected digest.
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function loadManifest(string $path, string $expectedHash): array
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The remediation manifest is not a readable file.');
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('The remediation manifest could not be read.');
        }
        $manifestHash = hash('sha256', $bytes);
        if (
            !hash_equals(self::CANONICAL_MANIFEST_SHA256, $manifestHash)
            || !hash_equals(strtolower($expectedHash), $manifestHash)
        ) {
            throw new RuntimeException('The remediation manifest digest does not match the reviewed digest.');
        }

        try {
            $manifest = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The remediation manifest is not valid JSON.', 0, $e);
        }
        if (!is_array($manifest) || array_is_list($manifest)) {
            throw new RuntimeException('The remediation manifest root must be an object.');
        }

        return [$manifest, $manifestHash];
    }

    /**
     * @param array<string, mixed> $manifest Decoded manifest.
     * @return array<int, array<string, mixed>> Validated records.
     */
    private function validateManifest(array $manifest): array
    {
        if (array_keys($manifest) !== self::MANIFEST_KEYS) {
            throw new RuntimeException('The remediation manifest has unexpected top-level fields.');
        }
        if (
            $manifest['schemaVersion'] !== 1
            || $manifest['tenant'] !== self::TENANT
            || $manifest['kingdom'] !== self::KINGDOM
            || $manifest['sourceWorkbookSha256'] !== self::SOURCE_WORKBOOK_SHA256
            || $manifest['expectedCounts'] !== self::EXPECTED_COUNTS
        ) {
            throw new RuntimeException('The remediation manifest identity or expected counts are invalid.');
        }
        if (!is_array($manifest['records']) || !array_is_list($manifest['records'])) {
            throw new RuntimeException('The remediation manifest records must be a list.');
        }

        $records = $manifest['records'];
        if (count($records) !== self::EXPECTED_COUNTS['total']) {
            throw new RuntimeException('The remediation manifest must contain exactly 273 records.');
        }

        $seen = [];
        $counts = array_fill_keys(self::DISPOSITIONS, 0);
        foreach ($records as $index => $record) {
            if (!is_array($record) || array_keys($record) !== self::RECORD_KEYS) {
                throw new RuntimeException(sprintf('Manifest record %d has an invalid shape.', $index + 1));
            }
            $recommendationId = $record['recommendationId'];
            $disposition = $record['disposition'];
            if (!is_int($recommendationId) || $recommendationId <= 0 || isset($seen[$recommendationId])) {
                throw new RuntimeException(sprintf('Manifest record %d has an invalid or duplicate ID.', $index + 1));
            }
            if (!is_string($disposition) || !in_array($disposition, self::DISPOSITIONS, true)) {
                throw new RuntimeException(sprintf(
                    'Recommendation #%d has an invalid disposition.',
                    $recommendationId,
                ));
            }
            if (!is_array($record['expected']) || array_keys($record['expected']) !== self::FINGERPRINT_KEYS) {
                throw new RuntimeException(sprintf(
                    'Recommendation #%d has an invalid fingerprint.',
                    $recommendationId,
                ));
            }
            $this->validateFingerprintTypes($recommendationId, $record['expected']);
            $this->validateDispositionFields($record);
            $seen[$recommendationId] = true;
            $counts[$disposition]++;
        }

        if (
            $counts['apply'] !== self::EXPECTED_COUNTS['apply']
            || $counts['hold'] !== self::EXPECTED_COUNTS['hold']
            || $counts['already_given'] !== self::EXPECTED_COUNTS['alreadyGiven']
            || $counts['separate_repair'] !== self::EXPECTED_COUNTS['separateRepair']
        ) {
            throw new RuntimeException('The remediation manifest disposition counts are invalid.');
        }

        return $records;
    }

    /**
     * @param int $recommendationId Recommendation ID.
     * @param array<string, mixed> $expected Fingerprint.
     * @return void
     */
    private function validateFingerprintTypes(int $recommendationId, array $expected): void
    {
        foreach (['recommendationStatus', 'recommendationState', 'memberNameSha256'] as $key) {
            if (!is_string($expected[$key]) || $expected[$key] === '') {
                throw new RuntimeException(sprintf('Recommendation #%d has an invalid %s.', $recommendationId, $key));
            }
        }
        foreach (['memberNameSha256', 'bestowalMemberNameSha256'] as $key) {
            if ($expected[$key] !== null && !preg_match('/\A[a-f0-9]{64}\z/', (string)$expected[$key])) {
                throw new RuntimeException(sprintf(
                    'Recommendation #%d has an invalid name digest.',
                    $recommendationId,
                ));
            }
        }
        foreach (
            [
                'memberId',
                'awardId',
                'gatheringId',
                'bestowalId',
                'bestowalMemberId',
                'bestowalAwardId',
                'bestowalGatheringId',
                'actionItemId',
            ] as $key
        ) {
            if ($expected[$key] !== null && (!is_int($expected[$key]) || $expected[$key] <= 0)) {
                throw new RuntimeException(sprintf('Recommendation #%d has an invalid %s.', $recommendationId, $key));
            }
        }
        foreach (
            [
                'recommendationGivenDate',
                'recommendationDeleted',
                'bestowalLifecycleStatus',
                'bestowalBestowedDate',
                'actionItemStatus',
                'actionItemSourceRef',
            ] as $key
        ) {
            if ($expected[$key] !== null && !is_string($expected[$key])) {
                throw new RuntimeException(sprintf('Recommendation #%d has an invalid %s.', $recommendationId, $key));
            }
        }
        if ($expected['actionItemIsGating'] !== null && !is_bool($expected['actionItemIsGating'])) {
            throw new RuntimeException(sprintf(
                'Recommendation #%d has an invalid action-item gate.',
                $recommendationId,
            ));
        }
        if ($expected['actionItemCompletionConfig'] !== null && !is_array($expected['actionItemCompletionConfig'])) {
            throw new RuntimeException(sprintf(
                'Recommendation #%d has an invalid completion configuration.',
                $recommendationId,
            ));
        }
    }

    /**
     * @param array<string, mixed> $record Manifest record.
     * @return void
     */
    private function validateDispositionFields(array $record): void
    {
        $recommendationId = (int)$record['recommendationId'];
        $expected = $record['expected'];
        if ($record['disposition'] === 'apply') {
            if (
                !is_string($record['historicalGivenDate'])
                || !$this->isIsoDate($record['historicalGivenDate'])
                || !in_array(
                    $record['dateSource'],
                    ['workbook.op_award_date', 'workbook.corrections_comments'],
                    true,
                )
                || $record['reason'] !== null
                || $expected['recommendationGivenDate'] !== null
                || $expected['recommendationDeleted'] !== null
                || $expected['bestowalLifecycleStatus'] !== Bestowal::LIFECYCLE_OPEN
                || $expected['bestowalBestowedDate'] !== null
                || $expected['actionItemStatus'] !== ActionItem::STATUS_OPEN
                || $expected['actionItemIsGating'] !== true
                || $expected['actionItemSourceRef'] !== 'given'
                || $expected['actionItemCompletionConfig'] !== null
                || !is_int($expected['bestowalId'])
                || !is_int($expected['actionItemId'])
            ) {
                throw new RuntimeException(sprintf('Recommendation #%d has invalid apply fields.', $recommendationId));
            }

            return;
        }

        if (
            $record['historicalGivenDate'] !== null
            || $record['dateSource'] !== null
            || !is_string($record['reason'])
            || $record['reason'] === ''
        ) {
            throw new RuntimeException(sprintf('Recommendation #%d has invalid control fields.', $recommendationId));
        }
    }

    /**
     * @param int $actorId Actor member ID.
     * @return void
     */
    private function assertRuntimeGuards(int $actorId): void
    {
        $kingdom = StaticHelpers::getAppSetting('KMP.KingdomName');
        if ($kingdom !== self::KINGDOM) {
            throw new RuntimeException('The connected database is not the Ansteorra tenant.');
        }

        $actor = $this->members->find()
            ->select(['id', 'status'])
            ->where([
                $this->members->aliasField('id') => $actorId,
                $this->members->aliasField('status') => Member::STATUS_ACTIVE,
            ])
            ->first();
        if ($actor === null) {
            throw new RuntimeException('The audit actor is not an active member in this tenant.');
        }
    }

    /**
     * Ensure every table participating in the atomic operation uses one connection.
     *
     * @return void
     */
    private function assertSharedConnection(): void
    {
        $connection = $this->recommendations->getConnection();
        foreach (
            [
                $this->bestowals,
                $this->bestowalRecommendations,
                $this->actionItems,
                $this->actionItemLogs,
                $this->recommendationStateLogs,
                $this->members,
            ] as $table
        ) {
            if ($table->getConnection() !== $connection) {
                throw new RuntimeException('All remediation tables must use the same database connection.');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $records Manifest records.
     * @param bool $lock Whether to acquire row locks.
     * @return array<int, array<string, mixed>> Record inspection results.
     */
    private function inspectRecords(array $records, bool $lock): array
    {
        $results = [];
        foreach ($records as $record) {
            $results[] = $this->inspectRecord($record, $lock);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $record Manifest record.
     * @param bool $lock Whether to acquire row locks.
     * @return array<string, mixed>
     */
    private function inspectRecord(array $record, bool $lock): array
    {
        $recommendationId = (int)$record['recommendationId'];
        $base = [
            'recommendationId' => $recommendationId,
            'disposition' => (string)$record['disposition'],
            'result' => self::RESULT_DRIFT,
            'reason' => '',
        ];

        $recommendation = $this->findOne(
            $this->recommendations,
            [$this->recommendations->aliasField('id') => $recommendationId],
            $lock,
        );
        if ($recommendation === null) {
            $base['reason'] = 'Recommendation is missing or deleted.';

            return $base;
        }

        $expected = $record['expected'];
        $expectedBestowalId = $expected['bestowalId'];
        $bestowal = null;
        $actionItem = null;

        if ($expectedBestowalId === null) {
            if ($recommendation->bestowal_id !== null) {
                $base['reason'] = 'Recommendation unexpectedly has a bestowal link.';

                return $base;
            }
            $links = $this->findAll(
                $this->bestowalRecommendations,
                [$this->bestowalRecommendations->aliasField('recommendation_id') => $recommendationId],
                $lock,
            );
            if ($links !== []) {
                $base['reason'] = 'Recommendation unexpectedly has a bestowal join row.';

                return $base;
            }
        } else {
            $bestowalId = (int)$expectedBestowalId;
            $bestowal = $this->findOne(
                $this->bestowals,
                [$this->bestowals->aliasField('id') => $bestowalId],
                $lock,
            );
            if ($bestowal === null) {
                $base['reason'] = 'Expected bestowal is missing or deleted.';

                return $base;
            }
            if ((int)$recommendation->bestowal_id !== $bestowalId) {
                $base['reason'] = 'Recommendation bestowal link changed.';

                return $base;
            }
            if ((int)$bestowal->primary_recommendation_id !== $recommendationId) {
                $base['reason'] = 'Bestowal primary recommendation link changed.';

                return $base;
            }

            $links = $this->findAll(
                $this->bestowalRecommendations,
                ['OR' => [
                    $this->bestowalRecommendations->aliasField('bestowal_id') => $bestowalId,
                    $this->bestowalRecommendations->aliasField('recommendation_id') => $recommendationId,
                ]],
                $lock,
            );
            if (
                count($links) !== 1
                || (int)$links[0]->bestowal_id !== $bestowalId
                || (int)$links[0]->recommendation_id !== $recommendationId
            ) {
                $base['reason'] = 'Bestowal/recommendation join cardinality changed.';

                return $base;
            }

            $foreignLinkedRecommendations = $this->findAll(
                $this->recommendations,
                [$this->recommendations->aliasField('bestowal_id') => $bestowalId],
                $lock,
            );
            if (
                count($foreignLinkedRecommendations) !== 1
                || (int)$foreignLinkedRecommendations[0]->id !== $recommendationId
            ) {
                $base['reason'] = 'Bestowal has an unexpected linked recommendation.';

                return $base;
            }

            $activeItems = array_values(array_filter(
                $this->findAll(
                    $this->actionItems,
                    [
                        $this->actionItems->aliasField('entity_type') => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                        $this->actionItems->aliasField('entity_id') => $bestowalId,
                    ],
                    $lock,
                ),
                static fn(EntityInterface $item): bool => $item->status !== ActionItem::STATUS_CANCELLED,
            ));
            if ($expected['actionItemId'] === null) {
                if ($activeItems !== []) {
                    $base['reason'] = 'Bestowal unexpectedly has an active action item.';

                    return $base;
                }
            } elseif (count($activeItems) !== 1 || (int)$activeItems[0]->id !== (int)$expected['actionItemId']) {
                $base['reason'] = 'Bestowal action-item cardinality changed.';

                return $base;
            } else {
                $actionItem = $activeItems[0];
            }
        }

        $actual = $this->fingerprint($recommendation, $bestowal, $actionItem);
        $preMismatch = $this->firstFingerprintMismatch($expected, $actual);
        if (
            $preMismatch === null
            && $expected['actionItemStatus'] === ActionItem::STATUS_OPEN
            && $actionItem !== null
            && ($actionItem->completed_at !== null || $actionItem->completed_by !== null)
        ) {
            $base['reason'] = 'Open action item unexpectedly has completion metadata.';

            return $base;
        }
        if ($record['disposition'] !== 'apply') {
            if ($preMismatch !== null) {
                $base['reason'] = 'Control fingerprint changed at field ' . $preMismatch . '.';

                return $base;
            }
            $base['result'] = self::RESULT_VERIFIED_CONTROL;

            return $base;
        }

        if ($preMismatch === null) {
            $base['result'] = self::RESULT_ACTIONABLE;

            return $base;
        }

        $postExpected = $this->postApplyFingerprint($record);
        $postMismatch = $this->firstFingerprintMismatch($postExpected, $actual);
        if (
            $postMismatch === null
            && $actionItem !== null
            && $this->hasCompletedAuditEvidence($record, $actionItem)
        ) {
            $base['result'] = self::RESULT_ALREADY_APPLIED;

            return $base;
        }

        $base['reason'] = 'Apply fingerprint changed at field ' . ($postMismatch ?? 'auditEvidence') . '.';

        return $base;
    }

    /**
     * @param \Cake\Datasource\EntityInterface $recommendation Recommendation entity.
     * @param \Cake\Datasource\EntityInterface|null $bestowal Bestowal entity.
     * @param \Cake\Datasource\EntityInterface|null $actionItem Action-item entity.
     * @return array<string, mixed>
     */
    private function fingerprint(
        EntityInterface $recommendation,
        ?EntityInterface $bestowal,
        ?EntityInterface $actionItem,
    ): array {
        return [
            'recommendationStatus' => (string)$recommendation->status,
            'recommendationState' => (string)$recommendation->state,
            'recommendationGivenDate' => $this->dateValue($recommendation->given),
            'recommendationDeleted' => $this->dateTimeValue($recommendation->deleted),
            'memberId' => $this->nullableInt($recommendation->member_id),
            'memberNameSha256' => hash('sha256', (string)$recommendation->member_sca_name),
            'awardId' => $this->nullableInt($recommendation->award_id),
            'gatheringId' => $this->nullableInt($recommendation->gathering_id),
            'bestowalId' => $bestowal !== null ? (int)$bestowal->id : null,
            'bestowalLifecycleStatus' => $bestowal !== null ? (string)$bestowal->lifecycle_status : null,
            'bestowalBestowedDate' => $bestowal !== null ? $this->dateValue($bestowal->bestowed_at) : null,
            'bestowalMemberId' => $bestowal !== null ? $this->nullableInt($bestowal->member_id) : null,
            'bestowalMemberNameSha256' => $bestowal !== null
                ? hash('sha256', (string)$bestowal->member_sca_name)
                : null,
            'bestowalAwardId' => $bestowal !== null ? $this->nullableInt($bestowal->award_id) : null,
            'bestowalGatheringId' => $bestowal !== null ? $this->nullableInt($bestowal->gathering_id) : null,
            'actionItemId' => $actionItem !== null ? (int)$actionItem->id : null,
            'actionItemStatus' => $actionItem !== null ? (string)$actionItem->status : null,
            'actionItemIsGating' => $actionItem !== null ? (bool)$actionItem->is_gating : null,
            'actionItemSourceRef' => $actionItem !== null ? $actionItem->source_ref : null,
            'actionItemCompletionConfig' => $actionItem !== null ? $actionItem->completion_config : null,
        ];
    }

    /**
     * @param array<string, mixed> $expected Expected fingerprint.
     * @param array<string, mixed> $actual Actual fingerprint.
     * @return string|null First mismatched field.
     */
    private function firstFingerprintMismatch(array $expected, array $actual): ?string
    {
        foreach (self::FINGERPRINT_KEYS as $key) {
            if ($expected[$key] !== $actual[$key]) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record Manifest record.
     * @return array<string, mixed>
     */
    private function postApplyFingerprint(array $record): array
    {
        $expected = $record['expected'];
        $expected['recommendationStatus'] = 'Closed';
        $expected['recommendationState'] = 'Given';
        $expected['recommendationGivenDate'] = $record['historicalGivenDate'];
        $expected['bestowalLifecycleStatus'] = Bestowal::LIFECYCLE_GIVEN;
        $expected['bestowalBestowedDate'] = $record['historicalGivenDate'];
        $expected['actionItemStatus'] = ActionItem::STATUS_COMPLETED;

        return $expected;
    }

    /**
     * @param array<string, mixed> $record Apply record.
     * @param string $manifestHash Canonical manifest hash.
     * @param int $actorId Actor ID.
     * @param string $changeReference Change reference.
     * @return void
     */
    private function applyRecord(
        array $record,
        string $manifestHash,
        int $actorId,
        string $changeReference,
    ): void {
        $recommendationId = (int)$record['recommendationId'];
        $bestowalId = (int)$record['expected']['bestowalId'];
        $actionItemId = (int)$record['expected']['actionItemId'];
        $historicalDate = (string)$record['historicalGivenDate'];
        $bestowedAt = new DateTime($historicalDate . ' 00:00:00', new DateTimeZone('UTC'));

        $recommendationBefore = $this->rowSnapshot($this->recommendations, $recommendationId);
        $bestowalBefore = $this->rowSnapshot($this->bestowals, $bestowalId);
        $actionItemBefore = $this->rowSnapshot($this->actionItems, $actionItemId);
        $lastActionLogId = $this->maximumId(
            $this->actionItemLogs,
            [$this->actionItemLogs->aliasField('action_item_id') => $actionItemId],
        );
        $lastStateLogId = $this->maximumId(
            $this->recommendationStateLogs,
            [$this->recommendationStateLogs->aliasField('recommendation_id') => $recommendationId],
        );

        $bestowal = $this->bestowals->get($bestowalId);
        $bestowal->bestowed_at = $bestowedAt;
        $bestowal->modified_by = $actorId;
        $this->bestowals->saveOrFail($bestowal);

        $note = $this->auditNote($changeReference, $manifestHash, $historicalDate);
        $completionResult = $this->actionItemService->complete(
            $actionItemId,
            $actorId,
            $note,
            false,
        );
        if (!$completionResult->isSuccess()) {
            throw new RuntimeException(sprintf(
                'Recommendation #%d action item could not be completed: %s',
                $recommendationId,
                $completionResult->getError() ?? 'unknown error',
            ));
        }

        $finalizationResult = $this->finalizationService->markGiven($bestowalId, $actorId, $bestowedAt);
        if (!$finalizationResult->isSuccess()) {
            throw new RuntimeException(sprintf(
                'Recommendation #%d bestowal could not be finalized: %s',
                $recommendationId,
                $finalizationResult->getError() ?? 'unknown error',
            ));
        }

        $reloadedRecommendation = $this->recommendations->get($recommendationId);
        $stateLog = $this->stateLogService->logStateTransition(
            $recommendationId,
            (string)$record['expected']['recommendationState'],
            (string)$reloadedRecommendation->state,
            (string)$record['expected']['recommendationStatus'],
            (string)$reloadedRecommendation->status,
            $actorId,
        );
        if ($stateLog === null) {
            throw new RuntimeException(sprintf(
                'Recommendation #%d did not produce a recommendation-state audit row.',
                $recommendationId,
            ));
        }

        $this->assertNewActionAudit($actionItemId, $actorId, $note, $lastActionLogId);
        if ((int)$stateLog->id <= $lastStateLogId) {
            throw new RuntimeException(sprintf(
                'Recommendation #%d did not produce a new state-log row.',
                $recommendationId,
            ));
        }

        $this->assertUnrelatedFieldsUnchanged(
            $recommendationBefore,
            $this->rowSnapshot($this->recommendations, $recommendationId),
            ['status', 'state', 'state_date', 'given', 'close_reason', 'modified', 'modified_by'],
            sprintf('Recommendation #%d', $recommendationId),
        );
        $this->assertUnrelatedFieldsUnchanged(
            $bestowalBefore,
            $this->rowSnapshot($this->bestowals, $bestowalId),
            ['lifecycle_status', 'bestowed_at', 'modified', 'modified_by'],
            sprintf('Bestowal #%d', $bestowalId),
        );
        $this->assertUnrelatedFieldsUnchanged(
            $actionItemBefore,
            $this->rowSnapshot($this->actionItems, $actionItemId),
            ['status', 'completed_at', 'completed_by', 'modified', 'modified_by'],
            sprintf('Action item #%d', $actionItemId),
        );
    }

    /**
     * @param int $actionItemId Action-item ID.
     * @param int $actorId Actor ID.
     * @param string $note Exact remediation note.
     * @param int $previousMaximumId Previous log maximum ID.
     * @return void
     */
    private function assertNewActionAudit(
        int $actionItemId,
        int $actorId,
        string $note,
        int $previousMaximumId,
    ): void {
        $log = $this->actionItemLogs->find()
            ->where([
                $this->actionItemLogs->aliasField('action_item_id') => $actionItemId,
                $this->actionItemLogs->aliasField('from_status') => ActionItem::STATUS_OPEN,
                $this->actionItemLogs->aliasField('to_status') => ActionItem::STATUS_COMPLETED,
                $this->actionItemLogs->aliasField('created_by') => $actorId,
                $this->actionItemLogs->aliasField('note') => $note,
                $this->actionItemLogs->aliasField('id') . ' >' => $previousMaximumId,
            ])
            ->first();
        if ($log === null) {
            throw new RuntimeException(sprintf(
                'Action item #%d did not produce its required audit row.',
                $actionItemId,
            ));
        }
    }

    /**
     * @param array<string, mixed> $record Apply record.
     * @param \Cake\Datasource\EntityInterface $actionItem Completed action item.
     * @return bool
     */
    private function hasCompletedAuditEvidence(array $record, EntityInterface $actionItem): bool
    {
        $actorId = $this->nullableInt($actionItem->completed_by);
        if ($actorId === null || $actionItem->completed_at === null) {
            return false;
        }

        $actionLogs = $this->actionItemLogs->find()
            ->where([
                $this->actionItemLogs->aliasField('action_item_id') => (int)$actionItem->id,
                $this->actionItemLogs->aliasField('from_status') => ActionItem::STATUS_OPEN,
                $this->actionItemLogs->aliasField('to_status') => ActionItem::STATUS_COMPLETED,
                $this->actionItemLogs->aliasField('created_by') => $actorId,
            ])
            ->all();
        $hasActionLog = false;
        foreach ($actionLogs as $log) {
            $note = (string)$log->note;
            if (
                str_starts_with($note, 'Historical bestowal closeout; change=')
                && str_contains($note, 'manifest_sha256=' . self::CANONICAL_MANIFEST_SHA256)
                && str_contains($note, 'historical_given_date=' . $record['historicalGivenDate'])
            ) {
                $hasActionLog = true;
                break;
            }
        }
        if (!$hasActionLog) {
            return false;
        }

        return $this->recommendationStateLogs->find()
            ->where([
                $this->recommendationStateLogs->aliasField('recommendation_id') => $record['recommendationId'],
                $this->recommendationStateLogs->aliasField('from_state') => $record['expected']['recommendationState'],
                $this->recommendationStateLogs->aliasField('to_state') => 'Given',
                $this->recommendationStateLogs->aliasField('from_status') =>
                    $record['expected']['recommendationStatus'],
                $this->recommendationStateLogs->aliasField('to_status') => 'Closed',
                $this->recommendationStateLogs->aliasField('created_by') => $actorId,
            ])
            ->count() > 0;
    }

    /**
     * @param string $changeReference Change reference.
     * @param string $manifestHash Manifest hash.
     * @param string $historicalDate Historical date.
     * @return string
     */
    private function auditNote(string $changeReference, string $manifestHash, string $historicalDate): string
    {
        return sprintf(
            'Historical bestowal closeout; change=%s; manifest_sha256=%s; historical_given_date=%s',
            $changeReference,
            $manifestHash,
            $historicalDate,
        );
    }

    /**
     * @param \Cake\Datasource\ConnectionInterface $connection Shared connection.
     * @return void
     */
    private function acquireBatchLock(ConnectionInterface $connection): void
    {
        if (!$connection->getDriver() instanceof Postgres) {
            return;
        }

        $connection->execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $connection->execute(
            'SELECT pg_advisory_xact_lock(hashtext(:scope))',
            ['scope' => 'awards:ansteorra:historical-bestowal-closeout:2026-08'],
        );
    }

    /**
     * @param \Cake\ORM\Table $table Table.
     * @param array<string, mixed> $conditions Conditions.
     * @param bool $lock Whether to append FOR UPDATE on PostgreSQL.
     * @return \Cake\Datasource\EntityInterface|null
     */
    private function findOne(Table $table, array $conditions, bool $lock): ?EntityInterface
    {
        $query = $table->find()->where($conditions);
        $this->lockQuery($query, $lock);
        $row = $query->first();

        return $row instanceof EntityInterface ? $row : null;
    }

    /**
     * @param \Cake\ORM\Table $table Table.
     * @param array<string, mixed> $conditions Conditions.
     * @param bool $lock Whether to append FOR UPDATE on PostgreSQL.
     * @return array<int, \Cake\Datasource\EntityInterface>
     */
    private function findAll(Table $table, array $conditions, bool $lock): array
    {
        $query = $table->find()->where($conditions)->orderBy([$table->aliasField('id') => 'ASC']);
        $this->lockQuery($query, $lock);

        return array_values(array_filter(
            $query->all()->toList(),
            static fn(mixed $row): bool => $row instanceof EntityInterface,
        ));
    }

    /**
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @param bool $lock Whether to acquire a lock.
     * @return void
     */
    private function lockQuery(SelectQuery $query, bool $lock): void
    {
        if ($lock && $query->getRepository()->getConnection()->getDriver() instanceof Postgres) {
            $query->epilog('FOR UPDATE');
        }
    }

    /**
     * @param \Cake\ORM\Table $table Table.
     * @param int $id Row ID.
     * @return array<string, mixed>
     */
    private function rowSnapshot(Table $table, int $id): array
    {
        $row = $table->find()
            ->select($table->getSchema()->columns())
            ->disableHydration()
            ->where([$table->aliasField('id') => $id])
            ->first();
        if (!is_array($row)) {
            throw new RuntimeException(sprintf('%s row #%d is missing.', $table->getAlias(), $id));
        }

        $normalized = [];
        foreach ($row as $field => $value) {
            $normalized[(string)$field] = $this->snapshotValue($value);
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $before Before snapshot.
     * @param array<string, mixed> $after After snapshot.
     * @param array<int, string> $allowedFields Expected mutation fields.
     * @param string $label Safe record label.
     * @return void
     */
    private function assertUnrelatedFieldsUnchanged(
        array $before,
        array $after,
        array $allowedFields,
        string $label,
    ): void {
        foreach ($allowedFields as $field) {
            unset($before[$field], $after[$field]);
        }
        if ($before !== $after) {
            throw new RuntimeException($label . ' changed outside the allowed closeout fields.');
        }
    }

    /**
     * @param mixed $value Snapshot value.
     * @return mixed
     */
    private function snapshotValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.uP');
        }
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $child) {
                $normalized[$key] = $this->snapshotValue($child);
            }

            return $normalized;
        }

        return $value;
    }

    /**
     * @param \Cake\ORM\Table $table Table.
     * @param array<string, mixed> $conditions Conditions.
     * @return int Maximum matching ID, or zero.
     */
    private function maximumId(Table $table, array $conditions): int
    {
        $row = $table->find()
            ->select(['maximum_id' => $table->find()->func()->max($table->aliasField('id'))])
            ->where($conditions)
            ->disableHydration()
            ->first();

        return (int)($row['maximum_id'] ?? 0);
    }

    /**
     * @param array<int, array<string, mixed>> $inspections Inspection results.
     * @return array<int, array<string, mixed>> Updated results.
     */
    private function normalizeApplyPopulation(array $inspections): array
    {
        $actionable = $this->countResult($inspections, self::RESULT_ACTIONABLE);
        $alreadyApplied = $this->countResult($inspections, self::RESULT_ALREADY_APPLIED);
        if ($actionable === 0 || $alreadyApplied === 0) {
            return $inspections;
        }

        foreach ($inspections as &$inspection) {
            if ($inspection['disposition'] === 'apply') {
                $inspection['result'] = self::RESULT_DRIFT;
                $inspection['reason'] = 'Apply records are not uniformly in the reviewed pre-state or post-state.';
            }
        }
        unset($inspection);

        return $inspections;
    }

    /**
     * @param string $manifestHash Manifest digest.
     * @param array<int, array<string, mixed>> $records Manifest records.
     * @param array<int, array<string, mixed>> $inspections Inspection results.
     * @return array<string, mixed>
     */
    private function resultData(string $manifestHash, array $records, array $inspections): array
    {
        $counts = [
            'total' => count($records),
            'apply' => 0,
            'hold' => 0,
            'alreadyGiven' => 0,
            'separateRepair' => 0,
            'actionable' => $this->countResult($inspections, self::RESULT_ACTIONABLE),
            'alreadyApplied' => $this->countResult($inspections, self::RESULT_ALREADY_APPLIED),
            'changed' => $this->countResult($inspections, self::RESULT_CHANGED),
            'drift' => $this->countResult($inspections, self::RESULT_DRIFT),
        ];
        foreach ($records as $record) {
            if ($record['disposition'] === 'apply') {
                $counts['apply']++;
            } elseif ($record['disposition'] === 'hold') {
                $counts['hold']++;
            } elseif ($record['disposition'] === 'already_given') {
                $counts['alreadyGiven']++;
            } elseif ($record['disposition'] === 'separate_repair') {
                $counts['separateRepair']++;
            }
        }

        return [
            'manifestHash' => $manifestHash,
            'summary' => $counts,
            'records' => $inspections,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $inspections Inspection results.
     * @param string $result Result name.
     * @return int
     */
    private function countResult(array $inspections, string $result): int
    {
        return count(array_filter(
            $inspections,
            static fn(array $inspection): bool => ($inspection['result'] ?? null) === $result,
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $inspections Inspection results.
     * @return bool
     */
    private function hasDrift(array $inspections): bool
    {
        return $this->countResult($inspections, self::RESULT_DRIFT) > 0;
    }

    /**
     * @param mixed $value Nullable integer value.
     * @return int|null
     */
    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int)$value;
    }

    /**
     * @param mixed $value Date value.
     * @return string|null
     */
    private function dateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string)$value, 0, 10);
    }

    /**
     * @param mixed $value Date-time value.
     * @return string|null
     */
    private function dateTimeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return (string)$value;
    }

    /**
     * @param string $value ISO date.
     * @return bool
     */
    private function isIsoDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
