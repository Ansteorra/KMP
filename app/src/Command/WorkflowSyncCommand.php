<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\WorkflowVersion;
use App\Services\WorkflowEngine\DefaultWorkflowVersionManager;
use App\Services\WorkflowEngine\WorkflowVersionManagerInterface;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

class WorkflowSyncCommand extends WorkflowDiffCommand
{
    /**
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory
     * @param \App\Services\WorkflowEngine\WorkflowVersionManagerInterface|null $versionManager Version manager
     */
    public function __construct(
        ?CommandFactoryInterface $factory = null,
        private ?WorkflowVersionManagerInterface $versionManager = null,
    ) {
        parent::__construct($factory);
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'workflow sync';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->setDescription('Publish repo workflow definition JSON when DB versions drift.');
        $parser->addOption('dry-run', [
            'boolean' => true,
            'default' => false,
            'help' => 'Report changes without creating/publishing workflow versions.',
        ]);
        $parser->addOption('published-by', [
            'default' => '0',
            'help' => 'Member ID to record as the publisher for generated versions.',
        ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dryRun = (bool)$args->getOption('dry-run');
        $publishedBy = (int)$args->getOption('published-by');
        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $created = 0;
        $published = 0;
        $unchanged = 0;
        $failed = 0;

        foreach ($this->loadSeedDefinitions() as $seed) {
            $meta = $seed['meta'];
            $repoDefinition = $seed['definition'];
            $definition = $definitionsTable->find()
                ->where(['slug' => $meta['slug']])
                ->first();
            if (!$definition) {
                $io->out(sprintf('%s: create workflow definition', $meta['slug']));
                if ($dryRun) {
                    $created++;
                    continue;
                }

                $definition = $definitionsTable->newEntity([
                    'name' => $meta['name'],
                    'slug' => $meta['slug'],
                    'description' => $meta['description'],
                    'trigger_type' => $meta['trigger_type'],
                    'trigger_config' => $meta['trigger_config'],
                    'entity_type' => $meta['entity_type'],
                    'execution_mode' => $meta['execution_mode'] ?? 'durable',
                    'is_active' => $meta['is_active'] ?? true,
                ]);
                if (!$definitionsTable->save($definition)) {
                    $io->err(sprintf('%s: failed to create definition', $meta['slug']));
                    $failed++;
                    continue;
                }
                $created++;
            }

            $current = $versionsTable->find()
                ->where([
                    'workflow_definition_id' => $definition->id,
                    'status' => WorkflowVersion::STATUS_PUBLISHED,
                ])
                ->order(['version_number' => 'DESC'])
                ->first();
            $publishedDefinition = $current !== null ? ($current->definition ?? []) : [];
            if ($current && $this->canonicalJson($repoDefinition) === $this->canonicalJson($publishedDefinition)) {
                $unchanged++;
                continue;
            }

            $io->out(sprintf('%s: publish repo definition%s', $meta['slug'], $dryRun ? ' (dry-run)' : ''));
            if ($dryRun) {
                $published++;
                continue;
            }

            $draft = $this->getVersionManager()->createDraft(
                (int)$definition->id,
                $repoDefinition,
                null,
                'Published from repository workflow definition sync.',
            );
            if (!$draft->isSuccess()) {
                $io->err(sprintf(
                    '%s: draft failed: %s',
                    $meta['slug'],
                    $draft->getError() ?? 'unknown error',
                ));
                $failed++;
                continue;
            }

            $publish = $this->getVersionManager()->publish((int)$draft->getData()['versionId'], $publishedBy);
            if (!$publish->isSuccess()) {
                $io->err(sprintf('%s: publish failed: %s', $meta['slug'], $publish->getError() ?? 'unknown error'));
                $failed++;
                continue;
            }
            $published++;
        }

        $io->out(sprintf(
            'Workflow sync complete: created=%d published=%d unchanged=%d failed=%d%s',
            $created,
            $published,
            $unchanged,
            $failed,
            $dryRun ? ' (dry-run)' : '',
        ));

        return $failed === 0 ? Command::CODE_SUCCESS : Command::CODE_ERROR;
    }

    /**
     * Get the workflow version manager.
     *
     * @return \App\Services\WorkflowEngine\WorkflowVersionManagerInterface
     */
    private function getVersionManager(): WorkflowVersionManagerInterface
    {
        return $this->versionManager ??= new DefaultWorkflowVersionManager();
    }
}
