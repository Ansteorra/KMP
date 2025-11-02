<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\EmailTemplate $emailTemplate
 * @var array $allMailers
 */

$this->assign('title', $emailTemplate->isNew() ? __('Add Email Template') : __('Edit Email Template'));
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': ' . ($emailTemplate->isNew() ? 'Add Email Template' : 'Edit Email Template');
$this->KMP->endBlock(); ?>

<div class="emailTemplates form content">
    <?= $this->Form->create($emailTemplate, [
        'data-controller' => $emailTemplate->isNew() ? 'email-template-form' : '',
        'data-email-template-form-mailers-value' => $emailTemplate->isNew() ? json_encode($allMailers) : '',
    ]) ?>
    <fieldset>
        <legend>
            <?= $this->element('backButton') ?>
            <?= $emailTemplate->isNew() ? __('Add Email Template') : __('Edit Email Template') ?>
        </legend>

        <div class="row">
            <div class="col-md-6">
                <?php if ($emailTemplate->isNew()): ?>
                <?php
                    // Build options array from allMailers
                    $mailerOptions = [];
                    foreach ($allMailers as $mailer) {
                        $mailerOptions[$mailer['class']] = $mailer['shortName'];
                    }
                    ?>
                <?= $this->Form->control('mailer_class', [
                        'label' => 'Mailer Class',
                        'id' => 'mailer-class-select',
                        'options' => $mailerOptions,
                        'data-email-template-form-target' => 'mailerSelect',
                        'data-action' => 'email-template-form#mailerChanged',
                    ]) ?>
                <?php else: ?>
                <div class="mb-3">
                    <label class="form-label">Mailer Class</label>
                    <div class="form-control-plaintext"><code><?= h($emailTemplate->mailer_class) ?></code></div>
                    <?= $this->Form->hidden('mailer_class') ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <?php if ($emailTemplate->isNew()): ?>
                <?= $this->Form->control('action_method', [
                        'label' => 'Action Method',
                        'id' => 'action-method-select',
                        'data-email-template-form-target' => 'actionSelect',
                        'data-action' => 'email-template-form#actionChanged',
                    ]) ?>
                <?php else: ?>
                <div class="mb-3">
                    <label class="form-label">Action Method</label>
                    <div class="form-control-plaintext"><code><?= h($emailTemplate->action_method) ?></code></div>
                    <?= $this->Form->hidden('action_method') ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div data-controller="variable-insert">
            <?= $this->Form->control('subject_template', [
                'label' => 'Subject Template',
                'placeholder' => 'Use {{variableName}} for dynamic content',
                'data-variable-insert-target' => 'field',
                'data-email-template-form-target' => 'subjectTemplate',
            ]) ?>

            <?php if (!empty($emailTemplate->available_vars)): ?>
            <div class="mb-3">
                <small class="text-muted d-block mb-1">Quick insert:</small>
                <div class="btn-group flex-wrap" role="group">
                    <?php foreach ($emailTemplate->available_vars as $var): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-action="variable-insert#insert"
                        data-variable-insert-variable-param="<?= h($var['name']) ?>"
                        title="<?= h($var['description'] ?? $var['name']) ?>">
                        {{<?= h($var['name']) ?>}}
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
                    ]) ?>

                    <?php if (!empty($emailTemplate->available_vars)): ?>
                    <div class="mt-2">
                        <div class="mb-2"><strong>Available Variables:</strong> Click to insert</div>
                        <div class="btn-group flex-wrap mb-2" role="group">
                            <?php foreach ($emailTemplate->available_vars as $var): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                data-action="variable-insert#insert"
                                data-variable-insert-variable-param="<?= h($var['name']) ?>"
                                title="<?= h($var['description'] ?? $var['name']) ?>">
                                {{<?= h($var['name']) ?>}}
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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