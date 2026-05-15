<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Model\Entity\WorkflowDefinition;
use App\Model\Table\WorkflowDefinitionsTable;
use Cake\ORM\TableRegistry;

/**
 * Lookup helpers for global workflow definitions.
 */
trait WorkflowDefinitionFinderTrait
{
    /**
     * Find a workflow definition by slug.
     *
     * @param string $slug Workflow definition slug
     * @return \App\Model\Entity\WorkflowDefinition|null
     */
    public function findWorkflowDefinitionBySlug(string $slug): ?WorkflowDefinition
    {
        return $this->getWorkflowDefinitionsTable()
            ->find()
            ->where(['slug' => $slug])
            ->first();
    }

    /**
     * Get the WorkflowDefinitions table instance.
     *
     * @return \App\Model\Table\WorkflowDefinitionsTable
     */
    private function getWorkflowDefinitionsTable(): WorkflowDefinitionsTable
    {
        return TableRegistry::getTableLocator()->get('WorkflowDefinitions');
    }
}
