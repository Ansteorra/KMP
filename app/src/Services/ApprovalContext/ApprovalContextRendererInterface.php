<?php

declare(strict_types=1);

namespace App\Services\ApprovalContext;

use App\Model\Entity\WorkflowInstance;

/**
 * Renders entity-aware context for the unified approvals UI.
 *
 * Plugins implement this to provide rich display context
 * for their entity types within approval workflows.
 */
interface ApprovalContextRendererInterface
{
    /**
     * Whether this renderer can handle the given workflow instance.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance to check
     * @return bool
     */
    public function canRender(WorkflowInstance $instance): bool;

    /**
     * Build an ApprovalContext for the given workflow instance.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance to render
     * @return \App\Services\ApprovalContext\ApprovalContext
     */
    public function render(WorkflowInstance $instance): ApprovalContext;
}
