<?php

/**
 * Workflow Visual Designer
 *
 * Full-page canvas for designing workflow graphs with drag-and-drop
 * node palette, connection wiring, and node configuration panel.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WorkflowDefinition|null $workflow
 * @var \App\Model\Entity\WorkflowVersion|null $draftVersion
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Workflow Designer';
$this->KMP->endBlock();

echo $this->KMP->startBlock("css");
echo $this->Vite->css('drawflow');
echo $this->Vite->css('workflow-designer');
$this->KMP->endBlock();

$triggerTypeOptions = [
    'event' => __('Event-Driven'),
    'manual' => __('Manual'),
    'scheduled' => __('Scheduled'),
    'api' => __('API'),
];
$executionModeOptions = [
    'durable' => __('Durable'),
    'ephemeral' => __('Ephemeral'),
];
?>

<div class="workflows designer content"
    data-controller="workflow-designer"
    data-workflow-designer-registry-url-value="<?= $this->Url->build(['action' => 'registry']) ?>"
    <?php if ($draftVersion) : ?>
    data-workflow-designer-load-url-value="<?= $this->Url->build(['action' => 'loadVersion', $draftVersion->id]) ?>"
    <?php endif; ?>
    data-workflow-designer-workflow-id-value="<?= $workflow ? h($workflow->id) : '' ?>"
    data-workflow-designer-version-id-value="<?= $draftVersion ? h($draftVersion->id) : '' ?>"
    data-workflow-designer-csrf-token-value="<?= $this->request->getAttribute('csrfToken') ?>">

    <!-- Toolbar -->
    <div class="workflow-toolbar"
        data-controller="workflow-toolbar"
        data-workflow-toolbar-save-url-value="<?= $this->Url->build(['action' => 'save']) ?>"
        data-workflow-toolbar-publish-url-value="<?= $this->Url->build(['action' => 'publish']) ?>"
        <?php if ($workflow) : ?>
        data-workflow-toolbar-update-metadata-url-value="<?= $this->Url->build(['action' => 'updateMetadata', $workflow->id]) ?>"
        <?php endif; ?>
        data-workflow-toolbar-csrf-token-value="<?= $this->request->getAttribute('csrfToken') ?>"
        data-workflow-toolbar-workflow-designer-outlet=".workflows.designer.content">
        <h5 class="mb-0 me-2">
            <?php if ($workflow) : ?>
                <i class="bi bi-diagram-3 me-1"></i><span data-workflow-toolbar-target="workflowName"><?= h($workflow->name) ?></span>
                <?php if ($draftVersion) : ?>
                    <span class="badge bg-warning text-dark ms-2"><?= __('Draft v{0}', $draftVersion->version_number) ?></span>
                <?php endif; ?>
                <?php if ($workflow->execution_mode === 'ephemeral') : ?>
                    <span class="badge bg-info text-dark ms-2" data-workflow-toolbar-target="executionModeBadge" title="<?= __('Ephemeral workflows run in-memory with no persistence. Async nodes (approvals, delays) are not supported.') ?>">
                        <i class="bi bi-lightning-charge me-1"></i><?= __('Ephemeral') ?>
                    </span>
                <?php else : ?>
                    <span class="badge bg-primary ms-2" data-workflow-toolbar-target="executionModeBadge" title="<?= __('Durable workflows persist execution state and support async nodes like approvals and delays.') ?>">
                        <i class="bi bi-database me-1"></i><?= __('Durable') ?>
                    </span>
                <?php endif; ?>
            <?php else : ?>
                <i class="bi bi-diagram-3 me-1"></i><?= __('New Workflow') ?>
            <?php endif; ?>
        </h5>

        <div class="wf-zoom-controls ms-3">
            <button class="btn btn-sm" data-action="workflow-toolbar#zoomOut" title="Zoom Out" aria-label="<?= __('Zoom Out') ?>">
                <i class="bi bi-dash"></i>
            </button>
            <span class="wf-zoom-level" data-workflow-toolbar-target="zoomLevel">100%</span>
            <button class="btn btn-sm" data-action="workflow-toolbar#zoomIn" title="Zoom In" aria-label="<?= __('Zoom In') ?>">
                <i class="bi bi-plus"></i>
            </button>
            <button class="btn btn-sm" data-action="workflow-toolbar#zoomReset" title="Reset Zoom" aria-label="<?= __('Reset Zoom') ?>">
                <i class="bi bi-arrows-angle-expand"></i>
            </button>
        </div>

        <div class="toolbar-separator"></div>

        <button class="btn btn-sm btn-outline-secondary" data-action="workflow-toolbar#undo" title="Undo (Ctrl+Z)" aria-label="<?= __('Undo') ?>">
            <i class="bi bi-arrow-counterclockwise"></i>
        </button>
        <button class="btn btn-sm btn-outline-secondary" data-action="workflow-toolbar#redo" title="Redo (Ctrl+Y)" aria-label="<?= __('Redo') ?>">
            <i class="bi bi-arrow-clockwise"></i>
        </button>

        <div class="toolbar-separator"></div>

        <button class="btn btn-sm btn-outline-secondary" data-action="workflow-toolbar#validateWorkflow" title="Validate">
            <i class="bi bi-check-circle me-1"></i><?= __('Validate') ?>
        </button>

        <div class="ms-auto d-flex gap-2">
            <?php if ($workflow) : ?>
                <button class="btn btn-sm btn-outline-secondary"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#workflow-metadata-modal">
                    <i class="bi bi-pencil-square me-1"></i><?= __('Edit Details') ?>
                </button>
                <?= $this->Html->link(
                    '<i class="bi bi-arrow-left me-1"></i>' . __('Back'),
                    ['action' => 'index'],
                    ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]
                ) ?>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-primary"
                data-action="workflow-toolbar#save"
                data-workflow-toolbar-target="saveBtn">
                <i class="bi bi-save me-1"></i><?= __('Save Draft') ?>
            </button>
            <button class="btn btn-sm btn-primary"
                data-action="workflow-toolbar#publish"
                data-workflow-toolbar-target="publishBtn">
                <i class="bi bi-rocket-takeoff me-1"></i><?= __('Publish') ?>
            </button>
        </div>
        <?php if ($workflow) : ?>
            <div class="modal fade" id="workflow-metadata-modal" tabindex="-1" aria-labelledby="workflow-metadata-modal-title" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
                    <div class="modal-content">
                        <form data-workflow-toolbar-target="metadataForm" data-action="submit->workflow-toolbar#saveMetadata">
                            <div class="modal-header">
                                <h2 class="modal-title fs-5" id="workflow-metadata-modal-title"><?= __('Edit Workflow Details') ?></h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>"></button>
                            </div>
                            <div class="modal-body bg-light-subtle">
                                <div class="alert alert-danger d-none" role="alert" data-workflow-toolbar-target="metadataStatus"></div>
                                <fieldset class="border rounded-3 bg-white shadow-sm p-3">
                                    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                                        <i class="bi bi-diagram-3 text-primary me-1" aria-hidden="true"></i>
                                        <?= __('Workflow Metadata') ?>
                                    </legend>
                                       <div class="row g-3">
                                       <div class="col-md-6">
                                           <label class="form-label" for="workflow-metadata-name"><?= __('Workflow Name') ?></label>
                                           <input class="form-control" id="workflow-metadata-name" name="name" value="<?= h($workflow->name) ?>" maxlength="255" required>
                                       </div>
                                       <div class="col-md-6">
                                           <label class="form-label" for="workflow-metadata-slug"><?= __('Slug') ?></label>
                                           <input class="form-control" id="workflow-metadata-slug" name="slug" value="<?= h($workflow->slug) ?>" maxlength="100" pattern="[a-z0-9-]+" required aria-describedby="workflow-metadata-slug-help">
                                           <div class="form-text" id="workflow-metadata-slug-help"><?= __('Use lowercase letters, numbers, and dashes.') ?></div>
                                       </div>
                                       <div class="col-12">
                                           <label class="form-label" for="workflow-metadata-description"><?= __('Description') ?></label>
                                           <textarea class="form-control" id="workflow-metadata-description" name="description" rows="3"><?= h((string)$workflow->description) ?></textarea>
                                       </div>
                                       <div class="col-md-4">
                                           <label class="form-label" for="workflow-metadata-trigger-type"><?= __('Trigger Type') ?></label>
                                           <select class="form-select" id="workflow-metadata-trigger-type" name="trigger_type" required>
                                               <?php foreach ($triggerTypeOptions as $value => $label) : ?>
                                                   <option value="<?= h($value) ?>" <?= $workflow->trigger_type === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                               <?php endforeach; ?>
                                           </select>
                                       </div>
                                       <div class="col-md-4">
                                           <label class="form-label" for="workflow-metadata-entity-type"><?= __('Object Type') ?></label>
                                           <input class="form-control" id="workflow-metadata-entity-type" name="entity_type" value="<?= h((string)$workflow->entity_type) ?>" maxlength="100" placeholder="App\Model\Entity\Member">
                                       </div>
                                       <div class="col-md-4">
                                           <label class="form-label" for="workflow-metadata-execution-mode"><?= __('Execution Mode') ?></label>
                                           <select class="form-select" id="workflow-metadata-execution-mode" name="execution_mode" required>
                                               <?php foreach ($executionModeOptions as $value => $label) : ?>
                                                   <option value="<?= h($value) ?>" <?= $workflow->execution_mode === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                               <?php endforeach; ?>
                                           </select>
                                       </div>
                                       </div>
                                   </fieldset>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                                <button type="submit" class="btn btn-primary" data-workflow-toolbar-target="metadataSaveBtn">
                                    <i class="bi bi-save me-1"></i><?= __('Save Details') ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Validation Results (initially hidden) -->
    <div data-workflow-designer-target="validationResults" class="wf-validation-results" aria-live="polite" style="display:none;"></div>

    <!-- Main Designer Area -->
    <div class="workflow-designer-container wf-designer-container">
        <!-- Left: Node Palette -->
        <div class="workflow-palette wf-palette"
            data-workflow-designer-target="nodePalette">
            <div style="padding: 0.25rem 0 0.5rem;">
                <span style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #667085;">
                    <i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i><?= __('Nodes') ?>
                </span>
            </div>
            <p class="visually-hidden" id="workflow-palette-keyboard-help">
                <?= __('Press Enter or Space on a node to add it to the center of the canvas. Dragging is optional.') ?>
            </p>
            <div class="visually-hidden" id="workflow-palette-add-status" role="status" aria-live="polite" aria-atomic="true"></div>
            <p class="text-muted small"><?= __('Loading...') ?></p>
        </div>

        <!-- Center: Canvas -->
        <div class="workflow-canvas"
            data-workflow-designer-target="canvas"
            role="region"
            aria-label="<?= __('Workflow canvas') ?>"
            aria-describedby="workflow-palette-keyboard-help"
            data-action="drop->workflow-designer#onCanvasDrop dragover->workflow-designer#onCanvasDragOver">
        </div>

        <!-- Right: Config Panel -->
        <div class="workflow-config-panel wf-config-panel"
            data-workflow-designer-target="nodeConfig">
            <div class="config-panel-resize-handle"
                role="separator"
                aria-orientation="vertical"
                aria-label="<?= __('Resize configuration panel') ?>"
                data-action="mousedown->workflow-designer#onResizeStart"></div>
            <div class="config-panel-header">
                <h6><i class="bi bi-sliders me-1"></i><?= __('Configuration') ?></h6>
            </div>
            <div class="config-panel-empty">
                <i class="bi bi-hand-index"></i>
                <p><?= __('Select a node on the canvas to configure it') ?></p>
            </div>
        </div>
    </div>

</div>
