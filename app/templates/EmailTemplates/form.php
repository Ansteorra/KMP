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
    <?= $this->Form->create($emailTemplate, ['data-controller' => 'email-template-form']) ?>
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
                        Workflow nodes use this to validate data before sending.
                        Each entry: <code>{"name":"varName","description":"...","type":"string","required":true}</code>
                    </p>

                    <!-- Parsed placeholders helper -->
                    <div id="parsed-placeholders-panel" class="mb-3" style="display:none;"
                        data-email-template-form-target="parsedVarsPanel">
                        <div class="card border-info">
                            <div class="card-header bg-info bg-opacity-10 py-2">
                                <small class="fw-semibold"><i class="bi bi-magic"></i> Placeholders detected in your templates</small>
                            </div>
                            <div class="card-body py-2">
                                <p class="small text-muted mb-2">These <code>{{variable}}</code> names were found in your HTML/text templates. Copy them into the schema below to document the variable contract.</p>
                                <div data-email-template-form-target="parsedVarsList" class="d-flex flex-wrap gap-1"></div>
                            </div>
                        </div>
                    </div>

                    <?= $this->Form->control('variables_schema', [
                        'label' => __('Variables Schema (JSON)'),
                        'type' => 'textarea',
                        'class' => 'font-monospace',
                        'rows' => 10,
                        'placeholder' => '[{"name":"recipientName","description":"Full name of the recipient","type":"string","required":true}]',
                        'value' => !empty($emailTemplate->variables_schema)
                            ? json_encode($emailTemplate->variables_schema, JSON_PRETTY_PRINT)
                            : '',
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
