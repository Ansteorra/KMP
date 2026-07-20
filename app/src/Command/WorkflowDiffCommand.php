<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\WorkflowVersion;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use InitWorkflowDefinitionsSeed;
use RuntimeException;

class WorkflowDiffCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'workflow diff';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->setDescription('Compare repo workflow definition JSON files with published DB versions.');

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $differences = $this->diffDefinitions($io);
        if ($differences === 0) {
            $io->success('Workflow definitions match published DB versions.');

            return Command::CODE_SUCCESS;
        }

        $io->err(sprintf('Workflow definition drift detected: %d difference(s).', $differences));

        return Command::CODE_ERROR;
    }

    /**
     * Compare repo seed definitions to published database versions.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int Number of differences found
     */
    protected function diffDefinitions(ConsoleIo $io): int
    {
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $differences = 0;

        foreach ($this->loadSeedDefinitions() as $seed) {
            $definition = $definitionsTable->find()
                ->where(['slug' => $seed['meta']['slug']])
                ->first();
            if (!$definition) {
                $io->out(sprintf('%s: missing from DB', $seed['meta']['slug']));
                $differences++;
                continue;
            }

            $published = $versionsTable->find()
                ->where([
                    'workflow_definition_id' => $definition->id,
                    'status' => WorkflowVersion::STATUS_PUBLISHED,
                ])
                ->order(['version_number' => 'DESC'])
                ->first();
            if (!$published) {
                $io->out(sprintf('%s: no published DB version', $seed['meta']['slug']));
                $differences++;
                continue;
            }

            if ($this->canonicalJson($seed['definition']) !== $this->canonicalJson($published->definition ?? [])) {
                $io->out(sprintf(
                    '%s: repo JSON differs from published DB version #%d',
                    $seed['meta']['slug'],
                    $published->id,
                ));
                $differences++;
            }
        }

        return $differences;
    }

    /**
     * Load workflow definition JSON files referenced by the seed metadata.
     *
     * @return array<int, array{meta: array<string, mixed>, definition: array<string, mixed>}>
     */
    protected function loadSeedDefinitions(): array
    {
        require_once CONFIG . 'Seeds/InitWorkflowDefinitionsSeed.php';

        $seed = new InitWorkflowDefinitionsSeed();
        $basePath = CONFIG . 'Seeds' . DS . 'WorkflowDefinitions' . DS;
        $definitions = [];
        foreach ($seed->getWorkflowMeta() as $meta) {
            $path = $basePath . $meta['json_file'];
            $json = json_decode((string)file_get_contents($path), true);
            if (!is_array($json)) {
                throw new RuntimeException("Invalid workflow definition JSON: {$path}");
            }
            $definitions[] = ['meta' => $meta, 'definition' => $json];
        }

        return $definitions;
    }

    /**
     * Encode arrays with recursively sorted keys for stable comparison.
     *
     * @param array $value Value to encode
     * @return string Canonical JSON
     */
    protected function canonicalJson(array $value): string
    {
        $this->sortRecursive($value);

        return (string)json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Recursively sort array keys.
     *
     * @param array $value Value to sort by reference
     * @return void
     */
    private function sortRecursive(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
    }
}
