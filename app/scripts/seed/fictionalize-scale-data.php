#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Maintain believable fictional identities in the scale seed snapshots.
 *
 * PHP version 8.4
 *
 * @category Seed
 * @package  KMP
 * @author   KMP Contributors <noreply@kmp.invalid>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/Ansteorra/KMP
 */

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols, Generic.Files.LineLength.TooLong

/**
 * Replace numbered scale-seed display values with deterministic fictional data.
 *
 * Safe test-data markers such as scale.member+####@example.test,
 * SCALE-######, and scale-seed-gathering-#### remain unchanged.
 *
 * Usage from the repository root:
 *   php app/scripts/seed/fictionalize-scale-data.php --write
 *   php app/scripts/seed/fictionalize-scale-data.php --check
 *   php app/scripts/seed/fictionalize-scale-data.php --apply-local-database
 */

const MIN_MEMBER_COUNT = 500;
const MIN_GATHERING_COUNT = 100;
const MAX_FICTIONAL_MEMBER_COUNT = 1500;

$applicationRoot = dirname(__DIR__, 2);
$repositoryRoot = dirname($applicationRoot);
$seedPaths = [
    $repositoryRoot . '/dev_seed_clean.sql',
    $applicationRoot . '/tests/pg_seed_baseline.sql',
    $applicationRoot . '/tests/pg_seed.sql',
];

$options = getopt('', ['apply-local-database', 'check', 'write']);
$write = array_key_exists('write', $options);
$check = array_key_exists('check', $options);
$applyLocalDatabase = array_key_exists('apply-local-database', $options);

if (count(array_filter([$write, $check, $applyLocalDatabase])) !== 1) {
    fwrite(STDERR, "Choose exactly one of --write, --check, or --apply-local-database.\n");
    exit(2);
}

if ($applyLocalDatabase) {
    $counts = applyLocalDatabase();
    fwrite(
        STDOUT,
        sprintf(
            "Updated local database (%d fictional members, %d fictional gatherings)\n",
            $counts['members'],
            $counts['gatherings'],
        ),
    );
    exit(0);
}

$expectedFixtureNumbers = null;
foreach ($seedPaths as $seedPath) {
    if (!is_file($seedPath)) {
        fwrite(STDERR, "Missing seed snapshot: {$seedPath}\n");
        exit(1);
    }

    $contents = file_get_contents($seedPath);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read seed snapshot: {$seedPath}\n");
        exit(1);
    }

    $fixtureNumbers = fixtureNumbersFromSnapshot($contents, $seedPath);
    if ($expectedFixtureNumbers === null) {
        $expectedFixtureNumbers = $fixtureNumbers;
    } elseif ($fixtureNumbers !== $expectedFixtureNumbers) {
        throw new RuntimeException("Scale fixture markers are not synchronized in {$seedPath}");
    }

    if ($write) {
        $contents = rewriteSeedSnapshot($contents, $seedPath, $fixtureNumbers);
        if (file_put_contents($seedPath, $contents) === false) {
            fwrite(STDERR, "Unable to write seed snapshot: {$seedPath}\n");
            exit(1);
        }
    }

    validateSeedSnapshot($contents, $seedPath, $fixtureNumbers);
    fwrite(
        STDOUT,
        sprintf(
            "%s %s (%d fictional members, %d fictional gatherings)\n",
            $write ? 'Updated' : 'Validated',
            basename($seedPath),
            count($fixtureNumbers['members']),
            count($fixtureNumbers['gatherings']),
        ),
    );
}

/**
 * Return a deterministic fictional member identity.
 *
 * @param int $number Synthetic fixture sequence number.
 * @return array{sca_name: string, first_name: string, last_name: string}
 */
function fictionalMember(int $number): array
{
    $personaSets = [
        [
            'given' => [
                'Alys', 'Beatrice', 'Cecily', 'Edmund', 'Geoffrey',
                'Hugh', 'Joan', 'Matilda', 'Robert', 'Thomas',
            ],
            'byname' => [
                'atte Brook', 'Baker', 'Blackwood', 'Carter', 'Cooper', 'Fletcher', 'Forester', 'Grey', 'Harper',
                'Hawthorn', 'Mercer', 'Miller', 'of Alderley', 'of Ashcombe', 'of Dunwich', 'of Fenmere',
                'of Hartwick', 'of Ravensford', 'Reed', 'Sawyer', 'Shepherd', 'Stone', 'Tanner', 'Thatcher',
                'Weaver', 'White', 'Woodward', 'Wren', 'Wycombe', 'Yarrow',
            ],
        ],
        [
            'given' => [
                'Ása', 'Ástríðr', 'Brynhildr', 'Eiríkr', 'Freydís',
                'Guðrún', 'Hákon', 'Ívarr', 'Ragnarr', 'Sigríðr',
            ],
            'byname' => [
                'of Birka', 'of Hedeby', 'of Jorvik', 'of Kaupang', 'of Ribe', 'of Skara', 'of Uppsala',
                'Bearheart', 'Deep-Minded', 'Far-Traveled', 'Fox', 'Graycloak', 'Ironhand', 'Keen-Eyed',
                'Longstride', 'Oakenshield', 'Raven', 'Redcloak', 'Ring-Giver', 'Seafarer', 'Shield-Bearer',
                'Silverhand', 'Skald', 'Spear-Bearer', 'Stormcrow', 'Strong-Arm', 'Swiftfoot', 'the Bold',
                'the Quiet', 'Wolf-Friend',
            ],
        ],
        [
            'given' => [
                'Áed', 'Áine', 'Brigit', 'Catríona', 'Cormac',
                'Deirdre', 'Domnall', 'Étaín', 'Fáelán', 'Muirenn',
            ],
            'byname' => [
                'of Argyll', 'of Atholl', 'of Bute', 'of Carrick', 'of Clonmacnoise', 'of Connacht', 'of Derry',
                'of Donegal', 'of Kells', 'of Leinster', 'of Meath', 'of Munster', 'of Ossory', 'of Skye',
                'Bellmaker', 'Blackbird', 'Bright-Spear', 'Harper', 'Hound-Keeper', 'Red-Hand', 'Scholar',
                'Shield-Bearer', 'Silversmith', 'Swift', 'the Fair', 'the Keen', 'the Learned', 'the Quiet',
                'the Red', 'Wolfhound',
            ],
        ],
        [
            'given' => [
                'Agnès', 'Aliénor', 'Étienne', 'Geneviève', 'Guillaume',
                'Isabeau', 'Marguerite', 'Martin', 'Philippe', 'Thibaut',
            ],
            'byname' => [
                'de Amiens', 'de Bayeux', 'de Blois', 'de Caen', 'de Chartres', 'de Dijon', 'de Foix',
                'de Limoges', 'de Lyon', 'de Meaux', 'de Narbonne', 'de Poitiers', 'de Reims', 'de Rouen',
                'de Tours', 'du Bois', 'du Lac', 'Charpentier', 'Clerc', 'Couturier', 'Fauconnier', 'Fournier',
                'Le Brun', 'Le Sage', 'Marchand', 'Mercier', 'Perrin', 'Tailleur', 'Verrier', 'Vigneron',
            ],
        ],
        [
            'given' => [
                'Beatriz', 'Catalina', 'Diego', 'Fernando', 'Inés',
                'Isabel', 'Leonor', 'Rodrigo', 'Sancho', 'Teresa',
            ],
            'byname' => [
                'de Alarcón', 'de Burgos', 'de Calatrava', 'de Córdoba', 'de Cuenca', 'de Granada', 'de Jaca',
                'de León', 'de Lugo', 'de Mérida', 'de Navarra', 'de Oviedo', 'de Salamanca', 'de Segovia',
                'de Sevilla', 'de Toledo', 'del Campo', 'del Mar', 'Alfaro', 'Blanco', 'Caballero', 'Carpintero',
                'Castañeda', 'Falcón', 'Herrero', 'Lorenzo', 'Montoya', 'Pascual', 'Romero', 'Vega',
            ],
        ],
    ];

    $legalFirstNames = [
        'Avery', 'Bailey', 'Blake', 'Cameron', 'Casey', 'Dakota', 'Devon', 'Drew', 'Elliot', 'Emerson',
        'Finley', 'Harper', 'Hayden', 'Jamie', 'Jordan', 'Kai', 'Kendall', 'Lane', 'Logan', 'Morgan',
        'Parker', 'Peyton', 'Quinn', 'Reese', 'Riley', 'Robin', 'Rowan', 'Sage', 'Sawyer', 'Shannon',
        'Skyler', 'Taylor', 'Alex', 'Andrea', 'Chris', 'Dana', 'Jesse', 'Kelly', 'Leslie', 'Marion',
        'Micah', 'Noel', 'Reagan', 'Rory', 'Sam', 'Sidney', 'Terry', 'Tracy', 'Wren', 'Zion',
    ];
    $legalLastNames = [
        'Adams', 'Bennett', 'Brooks', 'Campbell', 'Carter', 'Collins', 'Cooper', 'Diaz', 'Edwards', 'Flores',
        'Foster', 'Garcia', 'Gray', 'Green', 'Hall', 'Hayes', 'Howard', 'Hughes', 'James', 'Kelly',
        'Lee', 'Martin', 'Mitchell', 'Morgan', 'Parker', 'Price', 'Reed', 'Rivera', 'Ross', 'Turner',
    ];

    $zeroBased = $number - 1;
    $cultureIndex = $zeroBased % count($personaSets);
    $withinCulture = intdiv($zeroBased, count($personaSets));
    $personaSet = $personaSets[$cultureIndex];
    $given = $personaSet['given'][$withinCulture % count($personaSet['given'])];
    $byname = $personaSet['byname'][intdiv($withinCulture, count($personaSet['given']))];

    return [
        'sca_name' => $given . ' ' . $byname,
        'first_name' => $legalFirstNames[$zeroBased % count($legalFirstNames)],
        'last_name' => $legalLastNames[intdiv($zeroBased, count($legalFirstNames))],
    ];
}

/**
 * Return a believable fictional gathering title for a scale fixture.
 *
 * @param int $number Synthetic fixture sequence number.
 * @return string
 */
function fictionalGathering(int $number): string
{
    $categories = [
        'Kingdom Calendar Event',
        'Local Martial Practice',
        'Local Meeting',
        'Kingdom Event',
        'Fencing Practice',
        'Equestrian Practice',
        'Missile Practice',
        'Local Martial Practice - Armored Only',
        'Local Martial Practice - Rapier Only',
        'Local Martial Practice - Armored and Rapier',
        'Virtual Kingdom Calendar Event',
    ];
    $branches = [
        'Alderford', 'Brackenmere', 'Falcon Ridge', 'Greywood', 'Highwater', 'Ironwood', 'Linden Grove',
        'Oakheart', "Raven's Crossing", 'Red River', 'Silver Keep', 'Starglen', 'Stonebridge', 'Three Rivers',
        'Westermark', 'Willowmere', 'Ashford', 'Bright Hills', 'Cedar Vale', 'Dragonfield', 'Elmbridge',
        'Fox Hollow', 'Golden Plain', 'Hawkridge', 'Kingswood', 'Moonstone',
    ];
    $kingdomEvents = [
        'Crown Tournament', 'Coronation', 'Kingdom Arts and Sciences', "King's College",
        "Laurel's Prize Tournament",
        "Queen's Champion", 'Royal Round Table', 'War College', 'Winterkingdom', 'Heraldic and Scribal Symposium',
        'Kingdom Equestrian Championship', 'Kingdom Archery Championship', 'Kingdom Rapier Championship',
    ];
    $weekendEvents = [
        'Beltane', 'Candlemas', 'Defender of the Flame', 'Battle of the Pines', 'Harvest Feast', 'Midsummer Revel',
        'Three Kings', 'Spring War Muster', 'Autumn Baronial Gathering', 'Pilgrimage of Saint Boniface',
        'Feast of Saint William', 'Crossroads Defender', 'Festival of the Golden Lily',
    ];
    $virtualEvents = [
        'Online Kingdom Calendar Meeting', 'Virtual Heraldic Consultation', 'Online Officers Round Table',
        'Virtual Arts and Sciences Symposium', 'Online Newcomers Session', 'Virtual Seneschals Meeting',
        'Online Exchequers Meeting', 'Virtual Chroniclers Meeting', 'Online Webministers Meeting',
        'Virtual Accessibility Workshop', 'Online Event Stewards Meeting', 'Virtual Marshallate Meeting',
        'Online Kingdom History Night',
    ];

    $zeroBased = $number - 1;
    $category = $categories[$zeroBased % count($categories)];
    $occurrence = intdiv($zeroBased, count($categories));
    $branch = $branches[$occurrence % count($branches)];

    return match ($category) {
        'Kingdom Calendar Event' => $kingdomEvents[$occurrence % count($kingdomEvents)],
        'Local Martial Practice' => $branch . ' Fighter Practice',
        'Local Meeting' => $branch . ' Populace Meeting',
        'Kingdom Event' => $weekendEvents[$occurrence % count($weekendEvents)],
        'Fencing Practice' => $branch . ' Rapier Practice',
        'Equestrian Practice' => $branch . ' Equestrian Practice',
        'Missile Practice' => $branch . ' Archery and Thrown Weapons Practice',
        'Local Martial Practice - Armored Only' => $branch . ' Armored Combat Practice',
        'Local Martial Practice - Rapier Only' => $branch . ' Rapier and Cut and Thrust Practice',
        'Local Martial Practice - Armored and Rapier' => $branch . ' Combined Fighter Practice',
        'Virtual Kingdom Calendar Event' => $virtualEvents[$occurrence % count($virtualEvents)],
    };
}

/**
 * Rewrite human-facing scale fixture values while preserving test markers.
 *
 * @param string                                                       $contents       SQL snapshot contents.
 * @param string                                                       $seedPath       Snapshot path for errors.
 * @param array{members: array<int, int>, gatherings: array<int, int>} $fixtureNumbers Marker sequences.
 * @return string
 */
function rewriteSeedSnapshot(string $contents, string $seedPath, array $fixtureNumbers): string
{
    $scaReplacements = [];
    foreach ($fixtureNumbers['members'] as $number) {
        $member = fictionalMember($number);
        $suffix = sprintf('%04d', $number);
        $scaReplacements['Scale Member ' . $suffix] = sqlValue($member['sca_name']);

        $oldLegalName = "'Scale{$suffix}',NULL,'Loadtest'";
        $oldLegalNameSpaced = "'Scale{$suffix}', NULL, 'Loadtest'";
        $newLegalName = sprintf(
            "'%s',NULL,'%s'",
            sqlValue($member['first_name']),
            sqlValue($member['last_name']),
        );
        $newLegalNameSpaced = sprintf(
            "'%s', NULL, '%s'",
            sqlValue($member['first_name']),
            sqlValue($member['last_name']),
        );
        $contents = str_replace(
            [$oldLegalName, $oldLegalNameSpaced],
            [$newLegalName, $newLegalNameSpaced],
            $contents,
        );
    }
    $contents = strtr($contents, $scaReplacements);

    $gatheringPattern = '/Scale Gathering ([0-9]{4}) - ([^\']+)/';
    $gatheringNumbers = array_fill_keys($fixtureNumbers['gatherings'], true);
    $contents = preg_replace_callback(
        $gatheringPattern,
        static function (array $matches) use ($gatheringNumbers): string {
            $number = (int)$matches[1];
            if (!isset($gatheringNumbers[$number])) {
                return $matches[0];
            }

            return sqlValue(fictionalGathering($number));
        },
        $contents,
    );
    if ($contents === null) {
        throw new RuntimeException("Unable to rewrite gathering names in {$seedPath}");
    }

    return $contents;
}

/**
 * Confirm every expected fictional fixture is present in a seed snapshot.
 *
 * @param string                                                       $contents       SQL snapshot contents.
 * @param string                                                       $seedPath       Snapshot path for errors.
 * @param array{members: array<int, int>, gatherings: array<int, int>} $fixtureNumbers Marker sequences.
 * @return void
 */
function validateSeedSnapshot(string $contents, string $seedPath, array $fixtureNumbers): void
{
    if (preg_match('/Scale Member [0-9]{4}|Scale Gathering [0-9]{4}/', $contents) === 1) {
        throw new RuntimeException("Numbered display values remain in {$seedPath}");
    }

    foreach ($fixtureNumbers['members'] as $number) {
        $member = fictionalMember($number);
        $suffix = sprintf('%04d', $number);
        $memberPattern = sprintf(
            "/'%s'\\s*,\\s*'%s'\\s*,\\s*NULL\\s*,\\s*'%s'.{0,500}'scale\\.member\\+%s@example\\.test'/s",
            preg_quote(sqlValue($member['sca_name']), '/'),
            preg_quote(sqlValue($member['first_name']), '/'),
            preg_quote(sqlValue($member['last_name']), '/'),
            $suffix,
        );
        if (preg_match($memberPattern, $contents) !== 1) {
            throw new RuntimeException("Member {$suffix} does not have the expected fictional identity in {$seedPath}");
        }
    }

    foreach ($fixtureNumbers['gatherings'] as $number) {
        $suffix = sprintf('%04d', $number);
        $gatheringPattern = sprintf(
            "/'%s'\\s*,\\s*'scale-seed-gathering-%s'/",
            preg_quote(sqlValue(fictionalGathering($number)), '/'),
            $suffix,
        );
        if (preg_match($gatheringPattern, $contents) !== 1) {
            throw new RuntimeException("Gathering {$suffix} does not have the expected fictional title in {$seedPath}");
        }
    }
}

/**
 * Read and validate the synthetic marker ranges carried by a SQL snapshot.
 *
 * @param string $contents SQL snapshot contents.
 * @param string $seedPath Snapshot path for errors.
 * @return array{members: array<int, int>, gatherings: array<int, int>}
 */
function fixtureNumbersFromSnapshot(string $contents, string $seedPath): array
{
    $members = fixtureNumbersFromText(
        $contents,
        '/scale\.member\+([0-9]{4})@example\.test/',
    );
    $gatherings = fixtureNumbersFromText(
        $contents,
        '/scale-seed-gathering-([0-9]{4})/',
    );

    assertFixtureRange($members, MIN_MEMBER_COUNT, 'member', $seedPath, MAX_FICTIONAL_MEMBER_COUNT);
    assertFixtureRange($gatherings, MIN_GATHERING_COUNT, 'gathering', $seedPath);

    return ['members' => $members, 'gatherings' => $gatherings];
}

/**
 * Extract sorted unique fixture numbers from snapshot text.
 *
 * @param string $contents SQL snapshot contents.
 * @param string $pattern  Marker-matching regular expression.
 * @return array<int, int>
 */
function fixtureNumbersFromText(string $contents, string $pattern): array
{
    preg_match_all($pattern, $contents, $matches);
    $numbers = array_values(array_unique(array_map('intval', $matches[1] ?? [])));
    sort($numbers, SORT_NUMERIC);

    return $numbers;
}

/**
 * Ensure the fixture markers form a sufficiently large contiguous sequence.
 *
 * @param array<int, int> $numbers     Sorted unique fixture numbers.
 * @param int             $minimum     Required fixture count.
 * @param string          $fixtureType Human-readable fixture type.
 * @param string          $source      Source description for errors.
 * @param int|null        $maximum     Optional generator capacity.
 * @return void
 */
function assertFixtureRange(
    array $numbers,
    int $minimum,
    string $fixtureType,
    string $source,
    ?int $maximum = null,
): void {
    $count = count($numbers);
    if ($count < $minimum) {
        throw new RuntimeException(
            sprintf(
                'Expected at least %d scale %ss in %s; found %d.',
                $minimum,
                $fixtureType,
                $source,
                $count,
            ),
        );
    }
    if ($maximum !== null && $count > $maximum) {
        throw new RuntimeException(
            sprintf(
                'The fictional generator supports at most %d scale %ss in %s; found %d.',
                $maximum,
                $fixtureType,
                $source,
                $count,
            ),
        );
    }

    $expected = range(1, $count);
    if ($numbers !== $expected) {
        throw new RuntimeException("Scale {$fixtureType} markers are not contiguous from 0001 in {$source}");
    }
}

/**
 * Apply the deterministic mappings to a local seeded database before baking.
 *
 * @return array{members: int, gatherings: int}
 */
function applyLocalDatabase(): array
{
    include dirname(__DIR__, 2) . '/vendor/autoload.php';
    include dirname(__DIR__, 2) . '/config/bootstrap.php';

    $config = ConnectionManager::getConfig('default');
    $host = (string)($config['host'] ?? '');
    if (isset($config['url']) && is_string($config['url']) && $config['url'] !== '') {
        $urlHost = parse_url($config['url'], PHP_URL_HOST);
        if (is_string($urlHost)) {
            $host = $urlHost;
        }
    }
    $localHosts = ['127.0.0.1', 'db', 'localhost', 'mariadb', 'mysql', 'postgres'];
    if (!in_array(strtolower($host), $localHosts, true)) {
        throw new RuntimeException(
            "Refusing to update database host '{$host}'; --apply-local-database only permits local Docker hosts.",
        );
    }

    $connection = ConnectionManager::get('default');
    $memberNumbers = fixtureNumbersFromDatabase(
        $connection,
        'SELECT email_address AS marker FROM members WHERE email_address LIKE ?',
        ['scale.member+%@example.test'],
        '/^scale\.member\+([0-9]{4})@example\.test$/',
    );
    $gatheringNumbers = fixtureNumbersFromDatabase(
        $connection,
        'SELECT description AS marker FROM gatherings WHERE description LIKE ?',
        ['scale-seed-gathering-%'],
        '/^scale-seed-gathering-([0-9]{4})$/',
    );
    assertFixtureRange($memberNumbers, MIN_MEMBER_COUNT, 'member', 'local database', MAX_FICTIONAL_MEMBER_COUNT);
    assertFixtureRange($gatheringNumbers, MIN_GATHERING_COUNT, 'gathering', 'local database');

    $connection->transactional(
        static function ($connection) use ($memberNumbers, $gatheringNumbers): void {
            foreach ($memberNumbers as $number) {
                $member = fictionalMember($number);
                $suffix = sprintf('%04d', $number);
                $membershipNumber = sprintf('SCALE-%06d', $number);
                $email = 'scale.member+' . $suffix . '@example.test';
                $oldScaName = 'Scale Member ' . $suffix;

                $statement = $connection->execute(
                    'UPDATE members SET sca_name = ?, first_name = ?, last_name = ? '
                    . 'WHERE email_address = ? AND membership_number = ?',
                    [
                    $member['sca_name'],
                    $member['first_name'],
                    $member['last_name'],
                    $email,
                    $membershipNumber,
                    ],
                );
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException("Unable to update local scale member {$suffix}.");
                }

                $connection->execute(
                    'UPDATE awards_recommendations SET requester_sca_name = ? WHERE requester_sca_name = ?',
                    [$member['sca_name'], $oldScaName],
                );
                $connection->execute(
                    'UPDATE awards_recommendations SET member_sca_name = ? WHERE member_sca_name = ?',
                    [$member['sca_name'], $oldScaName],
                );
            }

            foreach ($gatheringNumbers as $number) {
                $suffix = sprintf('%04d', $number);
                $statement = $connection->execute(
                    'UPDATE gatherings SET name = ? WHERE description = ?',
                    [fictionalGathering($number), 'scale-seed-gathering-' . $suffix],
                );
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException("Unable to update local scale gathering {$suffix}.");
                }
            }

            // Match the referential cleanup already carried by every SQL seed snapshot.
            $connection->execute(
                'UPDATE documents SET uploaded_by = NULL WHERE uploaded_by IN (?, ?)',
                [2884, 2889],
            );
            $connection->execute(
                'UPDATE documents SET modified_by = NULL WHERE modified_by IN (?, ?)',
                [2884, 2889],
            );
            $connection->execute('UPDATE gathering_staff SET member_id = NULL WHERE member_id = ?', [2890]);
            $connection->execute(
                'UPDATE impersonation_action_logs SET impersonated_member_id = 1 '
                . 'WHERE impersonated_member_id IN (?, ?, ?, ?, ?)',
                [2884, 2885, 2889, 2890, 2891],
            );
            $connection->execute(
                'UPDATE impersonation_session_logs SET impersonated_member_id = 1 '
                . 'WHERE impersonated_member_id IN (?, ?, ?, ?, ?)',
                [2884, 2885, 2889, 2890, 2891],
            );
            $connection->execute(
                'UPDATE waivers_gathering_waiver_closures SET closed_by = NULL WHERE closed_by = ?',
                [2891],
            );
            $connection->execute(
                'UPDATE waivers_gathering_waivers SET declined_by = NULL WHERE declined_by = ?',
                [2891],
            );
            $connection->execute(
                'UPDATE waivers_gathering_waivers SET modified_by = NULL WHERE modified_by IN (?, ?, ?, ?)',
                [2884, 2885, 2889, 2891],
            );
        },
    );

    $numberedMembers = fetchCount(
        $connection,
        'SELECT COUNT(*) AS fixture_count FROM members WHERE sca_name LIKE ?',
        ['Scale Member %'],
    );
    $numberedGatherings = fetchCount(
        $connection,
        'SELECT COUNT(*) AS fixture_count FROM gatherings WHERE name LIKE ?',
        ['Scale Gathering %'],
    );
    $numberedRecommendations = fetchCount(
        $connection,
        'SELECT COUNT(*) AS fixture_count FROM awards_recommendations '
        . 'WHERE requester_sca_name LIKE ? OR member_sca_name LIKE ?',
        ['Scale Member %', 'Scale Member %'],
    );
    if ($numberedMembers !== 0 || $numberedGatherings !== 0 || $numberedRecommendations !== 0) {
        throw new RuntimeException('Numbered human-facing scale labels remain after the local database update.');
    }

    return ['members' => count($memberNumbers), 'gatherings' => count($gatheringNumbers)];
}

/**
 * Read unique fixture numbers from machine-readable database markers.
 *
 * @param \Cake\Database\Connection         $connection Database connection.
 * @param string             $sql        Marker query.
 * @param array<int, string> $parameters Bound query parameters.
 * @param string             $pattern    Marker-matching regular expression.
 * @return array<int, int>
 */
function fixtureNumbersFromDatabase(
    Connection $connection,
    string $sql,
    array $parameters,
    string $pattern,
): array {
    $statement = $connection->execute($sql, $parameters);
    $numbers = [];
    while (($row = $statement->fetch('assoc')) !== false) {
        $marker = $row['marker'] ?? null;
        if (is_string($marker) && preg_match($pattern, $marker, $matches) === 1) {
            $numbers[(int)$matches[1]] = true;
        }
    }

    $numbers = array_keys($numbers);
    sort($numbers, SORT_NUMERIC);

    return $numbers;
}

/**
 * Return a portable integer count from a database query.
 *
 * @param \Cake\Database\Connection $connection Database connection.
 * @param string                    $sql        Count query.
 * @param array<int, string>        $parameters Bound query parameters.
 * @return int
 */
function fetchCount(Connection $connection, string $sql, array $parameters): int
{
    $row = $connection->execute($sql, $parameters)->fetch('assoc');

    return (int)($row['fixture_count'] ?? 0);
}

/**
 * Escape a string for the single-quoted SQL literals in the seed snapshots.
 *
 * @param string $value Unescaped value.
 * @return string
 */
function sqlValue(string $value): string
{
    return str_replace("'", "''", $value);
}
