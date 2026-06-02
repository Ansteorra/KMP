<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\EmailTemplate $emailTemplate
 */

$this->assign('title', $emailTemplate->isNew() ? __('Add Email Template') : __('Edit Email Template'));
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': ' . ($emailTemplate->isNew() ? 'Add Email Template' : 'Edit Email Template');
$this->KMP->endBlock(); ?>

<div class="emailTemplates form content">
    <?= $this->Form->create($emailTemplate, [
        'data-controller' => 'email-template-form',
        'data-action' => 'submit->email-template-form#serializeVariableContract',
    ]) ?>
    <fieldset>
        <legend>
            <?= $this->element('backButton') ?>
            <?= $emailTemplate->isNew() ? __('Add Email Template') : __('Edit Email Template') ?>
        </legend>

        <!-- ── Workflow-native identity (primary authoring fields) ── -->
        <div class="row">
            <div class="col-md-6">
                <?= $this->Form->control('name', [
                    'label' => __('Name'),
                    'placeholder' => __('Human-readable label, e.g. "Warrant Issued"'),
                    'data-email-template-form-target' => 'nameField',
                    'data-action' => 'input->email-template-form#nameChanged',
                ]) ?>
            </div>
            <div class="col-md-6">
                <?= $this->Form->control('slug', [
                    'label' => __('Slug'),
                    'placeholder' => 'e.g. warrant-issued',
                    'help' => __('Stable workflow key (lowercase, hyphens only). Auto-generated from Name if left blank.'),
                    'data-email-template-form-target' => 'slugField',
                ]) ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <?= $this->Form->control('description', [
                    'label' => __('Description'),
                    'type' => 'textarea',
                    'rows' => 2,
                    'placeholder' => __('Brief description of when this email is sent and who receives it'),
                ]) ?>
            </div>
        </div>

        <div data-controller="variable-insert">
            <?= $this->Form->control('subject_template', [
                'label' => 'Subject Template',
                'placeholder' => 'Use {{variableName}} for dynamic content',
                'data-variable-insert-target' => 'field',
                'data-email-template-form-target' => 'subjectTemplate',
            ]) ?>

            <?php if (!empty($emailTemplate->available_vars)) : ?>
            <div class="mb-3">
                <small class="text-muted d-block mb-1">Quick insert:</small>
                <div class="btn-group flex-wrap" role="group">
                    <?php foreach ($emailTemplate->available_vars as $var) :
                        $varName = is_array($var) ? (string)($var['name'] ?? '') : (string)$var;
                        if ($varName === '') {
                            continue;
                        }
                        $varDescription = is_array($var) ? (string)($var['description'] ?? $varName) : $varName;
                        ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-action="variable-insert#insert"
                        data-variable-insert-variable-param="<?= h($varName) ?>"
                        title="<?= h($varDescription) ?>">
                        {{<?= h($varName) ?>}}
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?= $this->Form->control('is_active', [
            'label' => 'Active (use this template instead of file-based template)',
            'switch' => true,
        ]) ?>
    </fieldset>

    <section class="alert alert-info mt-4" aria-labelledby="template-conditional-help-heading">
        <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-start">
            <div>
                <h2 class="h6 alert-heading mb-1" id="template-conditional-help-heading">
                    <i class="bi bi-lightbulb" aria-hidden="true"></i>
                    <?= __('Template conditional blocks') ?>
                </h2>
                <p class="small mb-0">
                    <?= __(
                        'Use conditional blocks to include text only when workflow data is present, useful, ' .
                        'or has a specific value.',
                    ) ?>
                </p>
            </div>
            <button
                class="btn btn-sm btn-info"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#template-conditional-help-details"
                aria-expanded="false"
                aria-controls="template-conditional-help-details"
            >
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                <?= __('Show examples') ?>
            </button>
        </div>
        <div class="collapse mt-3" id="template-conditional-help-details">
            <p class="small mb-2">
                <?= __('A bare variable is useful when it is not missing, blank, null, false, or an empty list.') ?>
            </p>
            <div class="row small g-3">
                <div class="col-lg-6">
                    <h3 class="h6"><?= __('Presence checks') ?></h3>
                    <p class="mb-1"><?= __('Show a section only when a value exists:') ?></p>
                    <pre class="border rounded bg-light p-2 mb-2 text-dark"><code>{{#if awardReason}}
Reason: {{awardReason}}
{{/if}}</code></pre>
                    <p class="mb-1"><?= __('Show fallback wording when the value is missing or blank:') ?></p>
                    <pre class="border rounded bg-light p-2 mb-0 text-dark"><code>{{#if awardReason}}
Reason: {{awardReason}}
{{/if}}
{{#if awardReason == ""}}
No award reason was provided.
{{/if}}</code></pre>
                </div>
                <div class="col-lg-6">
                    <h3 class="h6"><?= __('Value checks') ?></h3>
                    <p class="mb-1"><?= __('Compare values with equality, inequality, AND, and OR:') ?></p>
                    <pre class="border rounded bg-light p-2 mb-2 text-dark"><code>{{#if status == "Approved"}}
This request was approved.
{{/if}}</code></pre>
                    <pre class="border rounded bg-light p-2 mb-0 text-dark"><code>{{#if status == "Approved" || status == "Pending"}}
This request is still active.
{{/if}}</code></pre>
                </div>
            </div>
            <p class="small mb-0 mt-2">
                <?= __(
                    'Use variable names without curly braces inside the condition. ' .
                    'Comparisons must quote the expected value.',
                ) ?>
            </p>
        </div>
    </section>

    <!-- Template Tabs -->
    <div data-controller="detail-tabs" class="mt-4">
        <nav>
            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                <button class="nav-link active" id="nav-html-tab" data-bs-toggle="tab" data-bs-target="#nav-html"
                    type="button" role="tab" aria-controls="nav-html" aria-selected="true"
                    data-detail-tabs-target='tabBtn'>
                    <i class="bi bi-filetype-html"></i> HTML Template (Markdown)
                </button>
                <button class="nav-link" id="nav-text-tab" data-bs-toggle="tab" data-bs-target="#nav-text" type="button"
                    role="tab" aria-controls="nav-text" aria-selected="false" data-detail-tabs-target='tabBtn'>
                    <i class="bi bi-file-text"></i> Text Template
                </button>
                <button class="nav-link" id="nav-vars-tab" data-bs-toggle="tab" data-bs-target="#nav-vars" type="button"
                    role="tab" aria-controls="nav-vars" aria-selected="false" data-detail-tabs-target='tabBtn'>
                    <i class="bi bi-braces"></i> Variables Contract
                </button>
            </div>
        </nav>

        <div class="tab-content" id="nav-tabContent">
            <!-- HTML Template Tab -->
            <div class="tab-pane fade show active" id="nav-html" role="tabpanel" aria-labelledby="nav-html-tab"
                data-detail-tabs-target="tabContent">
                <div class="m-3">
                    <p class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Enter your email template using <strong>Markdown</strong> syntax.
                        It will be automatically converted to HTML when the email is sent.
                    </p>

                    <div data-controller="email-template-editor"
                        data-email-template-editor-variables-value='<?= h(json_encode($emailTemplate->available_vars ?? [])) ?>'
                        data-email-template-editor-placeholder-value="Enter the email template in Markdown format..."
                        data-email-template-editor-min-height-value="400px">

                        <?= $this->Form->control('html_template', [
                            'label' => false,
                            'type' => 'textarea',
                            'data-email-template-editor-target' => 'editor',
                            'data-email-template-form-target' => 'htmlTemplate',
                            'data-action' => 'input->email-template-form#templateChanged',
                            'rows' => 15,
                        ]) ?>

                        <div data-email-template-editor-target="variableButtons"></div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <h6 class="alert-heading"><i class="bi bi-lightbulb"></i> Markdown Quick Reference</h6>
                        <div class="row small">
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li><code>**bold**</code> → <strong>bold</strong></li>
                                    <li><code>*italic*</code> → <em>italic</em></li>
                                    <li><code>[link](url)</code> → <a href="#">link</a></li>
                                    <li><code># Heading</code> → Heading 1</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li><code>- list item</code> → bullet list</li>
                                    <li><code>1. item</code> → numbered list</li>
                                    <li><code>`code`</code> → <code>code</code></li>
                                    <li><code>---</code> → horizontal line</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Text Template Tab -->
            <div class="tab-pane fade" id="nav-text" role="tabpanel" aria-labelledby="nav-text-tab"
                data-detail-tabs-target="tabContent">
                <div class="m-3" data-controller="variable-insert">
                    <p class="text-muted">
                        Plain text version of the email for clients that don't support HTML.
                        If left empty, a text version will be auto-generated from the HTML template.
                    </p>

                    <?= $this->Form->control('text_template', [
                        'label' => false,
                        'type' => 'textarea',
                        'class' => 'font-monospace',
                        'rows' => 15,
                        'placeholder' => 'Enter the plain text email template (optional)...',
                        'data-variable-insert-target' => 'field',
                        'data-email-template-form-target' => 'textTemplate',
                        'data-action' => 'input->email-template-form#templateChanged',
                    ]) ?>

                    <?php if (!empty($emailTemplate->available_vars)) : ?>
                    <div class="mt-2">
                        <div class="mb-2"><strong>Available Variables:</strong> Click to insert</div>
                        <div class="btn-group flex-wrap mb-2" role="group">
                            <?php foreach ($emailTemplate->available_vars as $var) :
                                $varName = is_array($var) ? (string)($var['name'] ?? '') : (string)$var;
                                if ($varName === '') {
                                    continue;
                                }
                                $varDescription = is_array($var) ? (string)($var['description'] ?? $varName) : $varName;
                                ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                data-action="variable-insert#insert"
                                data-variable-insert-variable-param="<?= h($varName) ?>"
                                title="<?= h($varDescription) ?>">
                                {{<?= h($varName) ?>}}
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Variables Contract Tab -->
            <div class="tab-pane fade" id="nav-vars" role="tabpanel" aria-labelledby="nav-vars-tab"
                data-detail-tabs-target="tabContent">
                <div class="m-3">
                    <p class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        The <strong>Variables Contract</strong> declares every placeholder this template can use.
                        Workflow nodes use this to validate data before sending. Add one row for each placeholder.
                    </p>

                    <!-- Parsed placeholders helper -->
                    <div id="parsed-placeholders-panel" class="mb-3" style="display:none;"
                        data-email-template-form-target="parsedVarsPanel">
                        <div class="card border-info">
                            <div class="card-header bg-info bg-opacity-10 py-2">
                                <small class="fw-semibold"><i class="bi bi-magic"></i> Placeholders detected in your templates</small>
                            </div>
                            <div class="card-body py-2">
                                <p class="small text-muted mb-2">
                                    These <code>{{variable}}</code> names were found in your HTML/text templates.
                                    Click one to add it to the contract.
                                </p>
                                <div data-email-template-form-target="parsedVarsList" class="d-flex flex-wrap gap-1"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                            <div>
                                <h3 class="h6 mb-0"><?= __('Variables') ?></h3>
                                <p class="small text-muted mb-0">
                                    <?= __('Define what each template placeholder means and whether it is required.') ?>
                                </p>
                            </div>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                data-action="email-template-form#addVariable"
                            >
                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                <?= __('Add variable') ?>
                            </button>
                        </div>
                        <div class="card-body">
                            <div
                                class="d-flex flex-column gap-3"
                                data-email-template-form-target="variableRows"
                                aria-live="polite"
                            ></div>
                            <p
                                class="text-muted mb-0"
                                data-email-template-form-target="emptyVariableMessage"
                            >
                                <?= __('No variables are defined yet. Add a variable or click a detected placeholder above.') ?>
                            </p>
                        </div>
                    </div>

                    <template data-email-template-form-target="variableRowTemplate">
                        <div class="border rounded p-3" data-variable-contract-row>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">
                                        <?= __('Variable name') ?>
                                        <input
                                            type="text"
                                            class="form-control"
                                            placeholder="recipientName"
                                            aria-label="<?= h(__('Variable name')) ?>"
                                            data-email-template-form-target="variableName"
                                            data-action="input->email-template-form#variableContractChanged"
                                            autocomplete="off"
                                            pattern="[A-Za-z_][A-Za-z0-9_]*"
                                        >
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">
                                        <?= __('Description') ?>
                                        <input
                                            type="text"
                                            class="form-control"
                                            placeholder="<?= h(__('Full name of the recipient')) ?>"
                                            aria-label="<?= h(__('Variable description')) ?>"
                                            data-email-template-form-target="variableDescription"
                                            data-action="input->email-template-form#variableContractChanged"
                                        >
                                    </label>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold">
                                        <?= __('Type') ?>
                                        <select
                                            class="form-select"
                                            aria-label="<?= h(__('Variable type')) ?>"
                                            data-email-template-form-target="variableType"
                                            data-action="change->email-template-form#variableContractChanged"
                                        >
                                            <option value="string"><?= __('Text') ?></option>
                                            <option value="number"><?= __('Number') ?></option>
                                            <option value="boolean"><?= __('Yes/No') ?></option>
                                            <option value="date"><?= __('Date') ?></option>
                                            <option value="url"><?= __('URL') ?></option>
                                            <option value="array"><?= __('List') ?></option>
                                            <option value="object"><?= __('Object') ?></option>
                                        </select>
                                    </label>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check mb-2">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            aria-label="<?= h(__('Variable is required')) ?>"
                                            data-email-template-form-target="variableRequired"
                                            data-action="change->email-template-form#variableContractChanged"
                                        >
                                        <label class="form-check-label small fw-semibold">
                                            <?= __('Required') ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-1 text-md-end">
                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm"
                                        data-action="email-template-form#removeVariable"
                                        aria-label="<?= h(__('Remove variable')) ?>"
                                        title="<?= h(__('Remove variable')) ?>"
                                    >
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <?= $this->Form->hidden('variables_schema', [
                        'value' => !empty($emailTemplate->variables_schema)
                            ? json_encode($emailTemplate->variables_schema, JSON_PRETTY_PRINT)
                            : '',
                        'data-email-template-form-target' => 'variablesSchema',
                    ]) ?>
                </div>
            </div>
        </div>
    </div>

    <?= $this->Form->hidden('available_vars', [
        'value' => json_encode($emailTemplate->available_vars ?? []),
        'data-email-template-form-target' => 'availableVars',
    ]) ?>

    <div class="mt-4">
        <?= $this->Form->button(__('Submit'), ['class' => 'btn btn-primary']) ?>
        <?= $this->Html->link(__('Cancel'), ['action' => 'index'], ['class' => 'btn btn-secondary']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
