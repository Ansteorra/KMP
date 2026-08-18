<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use App\Test\TestCase\BaseTestCase;
use DateTimeImmutable;

/**
 * Locks the reviewed scope and privacy shape of the historical closeout manifest.
 */
class HistoricalBestowalRemediationManifestTest extends BaseTestCase
{
    private const string SOURCE_WORKBOOK_SHA256 =
        'f676bc5b3d1207697573bd4a4f441df47b0bfa13a7143ad957dc47233fcc11df';

    private const string MANIFEST_SHA256 =
        '7ae9fa60c9ea59dbc3ea24575ed9cd43cd93a3fb6264595f28883c82b6e4bc4b';

    private const array EXPECTED_RECOMMENDATION_IDS = [
        1245, 2257, 1721, 1564, 2055, 2137, 318, 20, 2195, 1667, 2261, 1971, 1567, 1526, 1856, 1829,
        2198, 2028, 2016, 2015, 2014, 763, 2262, 1936, 1115, 1522, 1515, 1514, 2196, 1559, 1655, 1807,
        1806, 1757, 1720, 1715, 1713, 1712, 1711, 1949, 1905, 2127, 2053, 1879, 1926, 2273, 2272, 1744,
        1929, 2086, 2200, 1591, 1545, 1760, 2056, 1595, 1247, 2080, 1817, 1722, 1560, 1875, 1834, 2093,
        1754, 1125, 2046, 1956, 1769, 1752, 1772, 2269, 700, 2030, 1609, 1472, 1469, 2164, 2163, 1950,
        1563, 1835, 1928, 2085, 2266, 2162, 2161, 1628, 1532, 2094, 2050, 1979, 1570, 1690, 1988, 1987,
        1635, 1451, 2160, 2159, 755, 1615, 1593, 1049, 1041, 685, 2251, 2250, 591, 583, 425, 1185, 683,
        2249, 2255, 2118, 2225, 1857, 1830, 1508, 1136, 1579, 1525, 1624, 2111, 253, 2034, 1862, 1283,
        824, 1552, 2026, 1727, 133, 137, 2040, 1955, 1785, 1753, 2054, 2168, 2134, 1890, 1698, 1684,
        1255, 2048, 430, 2072, 2197, 1843, 1920, 1386, 1952, 1627, 1741, 1250, 2189, 2188, 2194, 2077,
        2076, 1557, 1512, 2185, 1953, 1873, 1657, 1572, 1792, 1736, 1886, 1884, 2158, 2157, 2186, 2025,
        572, 1556, 1510, 2039, 1809, 1652, 1940, 1939, 1938, 1992, 1991, 1990, 1861, 1254, 459, 1656,
        2066, 1278, 1714, 2087, 1811, 1815, 1701, 1681, 1673, 1671, 1674, 1672, 1275, 1922, 1828, 1795,
        1246, 1, 2126, 2291, 2284, 1621, 2174, 1821, 1659, 1644, 1618, 1665, 682, 2141, 2140, 1948,
        1216, 2041, 2298, 2156, 2155, 2079, 1717, 2178, 1976, 1975, 1974, 1973, 1972, 1742, 2172, 2165,
        1921, 1827, 2246, 2022, 2000, 2190, 1846, 2170, 1585, 1583, 1668, 1666, 1869, 1819, 1814, 2204,
        1697, 1682, 283, 1911, 1951, 1651, 2049, 1587, 1584, 1582, 1116, 1112, 2047, 1877, 1927, 2084,
    ];

    private const array EXPECTED_FINGERPRINT_KEYS = [
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

    private const array EXPECTED_RECORD_KEYS = [
        'recommendationId',
        'disposition',
        'historicalGivenDate',
        'dateSource',
        'reason',
        'expected',
    ];

    public function testCanonicalManifestRetainsReviewedScopeAndFingerprintShape(): void
    {
        $manifestPath = dirname(__DIR__, 3)
            . '/config/remediations/2026-08-ansteorra-historical-given.json';
        $manifestJson = file_get_contents($manifestPath);
        $this->assertIsString($manifestJson);
        $this->assertSame(self::MANIFEST_SHA256, hash_file('sha256', $manifestPath));
        $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $manifest['schemaVersion']);
        $this->assertSame('ansteorra', $manifest['tenant']);
        $this->assertSame('Ansteorra', $manifest['kingdom']);
        $this->assertSame(self::SOURCE_WORKBOOK_SHA256, $manifest['sourceWorkbookSha256']);
        $this->assertSame([
            'apply' => 249,
            'hold' => 6,
            'alreadyGiven' => 17,
            'separateRepair' => 1,
            'total' => 273,
        ], $manifest['expectedCounts']);

        $records = $manifest['records'];
        $this->assertCount(273, $records);
        $recommendationIds = array_column($records, 'recommendationId');
        $this->assertSame(self::EXPECTED_RECOMMENDATION_IDS, $recommendationIds);
        $this->assertCount(273, array_unique($recommendationIds));

        $this->assertSame(
            [459, 1136, 1216, 1792, 1869, 2298],
            $this->sortedIdsForDisposition($records, 'hold'),
        );
        $this->assertSame(
            [133, 137, 1386, 1469, 1472, 1621, 1752, 1769, 1926, 1936, 1956, 2046, 2126, 2134, 2194,
                2196, 2269],
            $this->sortedIdsForDisposition($records, 'already_given'),
        );
        $this->assertSame([1], $this->sortedIdsForDisposition($records, 'separate_repair'));
        $this->assertCount(249, $this->sortedIdsForDisposition($records, 'apply'));

        $recordsById = array_column($records, null, 'recommendationId');
        foreach ([2198, 2164, 2163, 2291, 2284] as $recommendationId) {
            $this->assertSame('2026-04-11', $recordsById[$recommendationId]['historicalGivenDate']);
            $this->assertSame(
                'workbook.corrections_comments',
                $recordsById[$recommendationId]['dateSource'],
            );
        }

        $applyCount = 0;
        foreach ($records as $record) {
            $this->assertSame(self::EXPECTED_RECORD_KEYS, array_keys($record));
            $this->assertArrayHasKey('expected', $record);
            $this->assertSame(self::EXPECTED_FINGERPRINT_KEYS, array_keys($record['expected']));
            $this->assertNoCleartextNameFields($record);
            $this->assertMatchesRegularExpression(
                '/^[a-f0-9]{64}$/',
                $record['expected']['memberNameSha256'],
            );
            if ($record['recommendationId'] === 1) {
                $this->assertNull($record['expected']['bestowalId']);
                $this->assertNull($record['expected']['bestowalMemberNameSha256']);
            } else {
                $this->assertMatchesRegularExpression(
                    '/^[a-f0-9]{64}$/',
                    $record['expected']['bestowalMemberNameSha256'],
                );
            }

            if ($record['disposition'] !== 'apply') {
                continue;
            }

            $applyCount++;
            $this->assertContains($record['dateSource'], [
                'workbook.op_award_date',
                'workbook.corrections_comments',
            ]);
            $this->assertIsString($record['historicalGivenDate']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $record['historicalGivenDate']);
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $record['historicalGivenDate']);
            $this->assertInstanceOf(DateTimeImmutable::class, $parsedDate);
            $this->assertSame($record['historicalGivenDate'], $parsedDate->format('Y-m-d'));

            $expected = $record['expected'];
            $this->assertIsString($expected['recommendationStatus']);
            $this->assertIsString($expected['recommendationState']);
            $this->assertNull($expected['recommendationGivenDate']);
            $this->assertNull($expected['recommendationDeleted']);
            $this->assertTrue($expected['memberId'] === null || is_int($expected['memberId']));
            $this->assertIsInt($expected['awardId']);
            $this->assertTrue($expected['gatheringId'] === null || is_int($expected['gatheringId']));
            $this->assertIsInt($expected['bestowalId']);
            $this->assertSame('open', $expected['bestowalLifecycleStatus']);
            $this->assertNull($expected['bestowalBestowedDate']);
            $this->assertTrue($expected['bestowalMemberId'] === null || is_int($expected['bestowalMemberId']));
            $this->assertIsInt($expected['bestowalAwardId']);
            $this->assertTrue(
                $expected['bestowalGatheringId'] === null || is_int($expected['bestowalGatheringId']),
            );
            $this->assertIsInt($expected['actionItemId']);
            $this->assertSame('open', $expected['actionItemStatus']);
            $this->assertTrue($expected['actionItemIsGating']);
            $this->assertSame('given', $expected['actionItemSourceRef']);
            $this->assertNull($expected['actionItemCompletionConfig']);
        }
        $this->assertSame(249, $applyCount);
    }

    /**
     * @param array<int, array<string, mixed>> $records Manifest records.
     * @return array<int>
     */
    private function sortedIdsForDisposition(array $records, string $disposition): array
    {
        $ids = array_values(array_map(
            static fn(array $record): int => $record['recommendationId'],
            array_filter(
                $records,
                static fn(array $record): bool => $record['disposition'] === $disposition,
            ),
        ));
        sort($ids);

        return $ids;
    }

    /**
     * @param array<mixed> $value Manifest subtree.
     * @return void
     */
    private function assertNoCleartextNameFields(array $value): void
    {
        foreach ($value as $key => $child) {
            if (is_string($key) && str_contains(strtolower($key), 'name')) {
                $this->assertStringEndsWith(
                    'namesha256',
                    strtolower($key),
                    sprintf('Cleartext name field "%s" must not be present in the manifest.', $key),
                );
            }
            if (is_array($child)) {
                $this->assertNoCleartextNameFields($child);
            }
        }
    }
}
