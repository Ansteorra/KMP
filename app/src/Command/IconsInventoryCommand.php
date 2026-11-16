<?php
declare(strict_types=1);

namespace App\Command;

use App\KMP\TenantMetadata;
use App\Services\IconCatalog;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;

/** Read-only report of persisted icon identifiers in the selected tenant; never converts unknown data. */
class IconsInventoryCommand extends Command
{
    /** @inheritDoc */
    public static function defaultName(): string
    {
        return 'icons inventory';
    }

    /** @inheritDoc */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Read-only icon inventory: icon columns, JSON icon keys, and icon-related app settings.')
            ->addOption('tenant', [
                'help' => 'Select an active or suspended tenant by slug; otherwise use the configured database.',
            ])
            ->addOption('limit', ['default' => '10000', 'help' => 'Maximum rows per column; truncation is reported.']);
    }

    /** @inheritDoc */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $slug = $args->getOption('tenant');
        if ($slug === null) {
            return $this->inventory($args, $io);
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/D', $slug)) {
            $io->err('Choose a valid tenant slug.');

            return self::CODE_ERROR;
        }
        $row = ConnectionManager::get('platform')->execute(
            'SELECT * FROM tenants WHERE slug = :slug LIMIT 1',
            ['slug' => $slug],
        )->fetch('assoc');
        if (!$row || !in_array($row['status'], ['active', 'suspended'], true)) {
            $io->err('The tenant is not available for an icon inventory.');

            return self::CODE_ERROR;
        }
        $manager = new TenantConnectionManager(SecretStoreFactory::fromConfig());

        return $manager->withTenant(TenantMetadata::fromPlatformRow($row), fn() => $this->inventory($args, $io));
    }

    /**
     * @param \Cake\Console\Arguments $args CLI options
     * @param \Cake\Console\ConsoleIo $io Console output
     * @return int
     */
    private function inventory(Arguments $args, ConsoleIo $io): int
    {
        $connection = ConnectionManager::get('default');
        $limit = max(1, min(1000000, (int)$args->getOption('limit')));
        $columns = $connection->execute(
            'SELECT table_name, column_name, data_type FROM information_schema.columns '
            . "WHERE table_schema = current_schema() AND (column_name ILIKE '%icon%' "
            . "OR data_type IN ('json', 'jsonb') OR table_name = 'app_settings') "
            . "AND data_type IN ('text', 'character varying', 'json', 'jsonb') "
            . 'ORDER BY table_name, ordinal_position',
        )->fetchAll('assoc');
        $counts = [];
        $truncated = [];
        foreach ($columns as $column) {
            $table = $column['table_name'];
            $field = $column['column_name'];
            if (!preg_match('/^[a-z0-9_]+$/D', $table . $field)) {
                continue;
            }
            $sql = 'SELECT "' . $field . '" AS value FROM "' . $table . '"';
            if ($table === 'app_settings') {
                if ($field !== 'value') {
                    continue;
                }
                $sql .= " WHERE name ILIKE '%icon%' OR name LIKE 'KMP.HeaderLink.%'"
                    . " OR name LIKE 'KMP.FooterLink.%' OR value LIKE '{%' OR value LIKE '[%'";
            }
            if (in_array($column['data_type'], ['json', 'jsonb'], true)) {
                $sql .= ' WHERE CAST("' . $field . '" AS text) ~* ' .
                    '\'"icon(_?name|_?class|_?id)?"[[:space:]]*:\'';
            }
            $rows = $connection->execute($sql . ' LIMIT ' . ($limit + 1))->fetchAll('assoc');
            if (count($rows) > $limit) {
                $truncated[] = $table . '.' . $field;
                array_pop($rows);
            }
            foreach ($rows as $row) {
                $value = $row['value'];
                if (!is_string($value) || $value === '') {
                    continue;
                }
                $location = $table . '.' . $field;
                if ($table === 'app_settings' && str_contains($value, '|')) {
                    $parts = explode('|', $value);
                    foreach (preg_split('/\s+/', $parts[1] ?? '') as $token) {
                        if (
                            str_starts_with($token, 'bi-')
                            && !preg_match('/^bi-(?:2xs|xs|sm|lg|xl|2xl|[1-9]x|10x)$/D', $token)
                        ) {
                            $this->record($token, $location, $counts);
                        }
                    }
                    if (!empty($parts[2])) {
                        $this->record($parts[2], $location, $counts);
                    }
                    continue;
                }
                $json = json_decode($value, true);
                if (is_array($json)) {
                    $this->collectJson($json, $location, $counts);
                } elseif (str_contains($field, 'icon') || $table === 'app_settings') {
                    if (!in_array($field, ['icon_source', 'icon_asset_key', 'icon_document_id'], true)) {
                        $this->record($value, $location, $counts);
                    }
                }
            }
        }
        ksort($counts);
        $io->out(json_encode([
            'readOnly' => true, 'revision' => IconCatalog::data()['revision'], 'truncatedColumns' => $truncated,
            'identifiers' => array_values($counts),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $truncated === [] ? self::CODE_SUCCESS : self::CODE_ERROR;
    }

    /**
     * @param array<mixed> $json Decoded settings
     * @param string $location Column, without personal record data
     * @param array<string, array<string, mixed>> $counts Report accumulator
     * @return void
     */
    private function collectJson(array $json, string $location, array &$counts): void
    {
        foreach ($json as $key => $value) {
            if (is_array($value)) {
                $this->collectJson($value, $location, $counts);
            } elseif (is_string($value) && preg_match('/^icon(?:_?name|_?class|_?id)?$/iD', (string)$key)) {
                $this->record($value, $location, $counts);
            }
        }
    }

    /**
     * @param string $value Stored identifier
     * @param string $location Column
     * @param array<string, array<string, mixed>> $counts Report accumulator
     * @return void
     */
    private function record(string $value, string $location, array &$counts): void
    {
        $key = $location . ':' . hash('sha256', $value);
        if (!isset($counts[$key])) {
            // Malformed values may contain private URLs/markup. Report their hash, never their contents.
            $display = strlen($value) <= 256 && preg_match('/^[a-zA-Z0-9 :_-]+$/D', $value)
                ? $value : '[invalid identifier: ' . hash('sha256', $value) . ']';
            $counts[$key] = ['location' => $location, 'identifier' => $display,
                'canonical' => IconCatalog::canonical($value), 'count' => 0];
        }
        $counts[$key]['count']++;
    }
}
