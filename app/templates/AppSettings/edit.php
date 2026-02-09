<?php

/**
 * App Settings Edit Template
 * 
 * Edit form for app settings, loaded in a turbo-frame within a modal.
 * Handles string, JSON, and YAML value types with appropriate editors.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AppSetting $appSetting
 */
?>
<turbo-frame id="editAppSettingFrame">
    <?= $this->Form->create($appSetting, [
        'url' => ['action' => 'edit', $appSetting->id],
        'id' => 'edit_app_setting_form',
        'data-controller' => 'turbo-modal app-setting-edit',
        'data-action' => 'turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
        'data-turbo' => 'true',
    ]) ?>

    <div class="modal-header">
        <h5 class="modal-title">
            <i class="bi bi-pencil me-2"></i><?= __('Edit App Setting') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body">
        <fieldset>
            <!-- Setting Name (read-only) -->
            <div class="mb-3">
                <label class="form-label"><?= __('Setting Name') ?></label>
                <input type="text" class="form-control" value="<?= h($appSetting->name) ?>" readonly disabled>
                <?= $this->Form->hidden('name', ['value' => $appSetting->name]) ?>
            </div>

            <!-- Setting Type (read-only) -->
            <div class="mb-3">
                <label class="form-label"><?= __('Type') ?></label>
                <span class="badge bg-<?= $appSetting->type === 'yaml' ? 'warning' : ($appSetting->type === 'json' ? 'info' : 'secondary') ?>">
                    <?= h(strtoupper($appSetting->type ?? 'string')) ?>
                </span>
            </div>

            <!-- Value Editor -->
            <?php if ($appSetting->type === 'yaml' || $appSetting->type === 'json'): ?>
                <!-- Complex type - use code editor with syntax validation -->
                <div class="mb-3"
                    data-controller="code-editor"
                    data-code-editor-language-value="<?= h($appSetting->type) ?>"
                    data-code-editor-validate-on-change-value="true"
                    data-code-editor-min-height-value="300px">
                    <label for="raw_value" class="form-label">
                        <?= __('Value') ?>
                        <span class="badge bg-<?= $appSetting->type === 'yaml' ? 'warning text-dark' : 'info' ?>">
                            <?= h(strtoupper($appSetting->type)) ?>
                        </span>
                    </label>
                    <textarea
                        name="raw_value"
                        id="raw_value"
                        class="form-control font-monospace"
                        rows="15"
                        data-code-editor-target="textarea"
                        data-action="input->code-editor#validateContent"><?= h($appSetting->raw_value) ?></textarea>
                    <div data-code-editor-target="errorDisplay" class="d-none"></div>
                    <div class="form-text">
                        <?php if ($appSetting->type === 'yaml'): ?>
                            <i class="bi bi-info-circle me-1"></i><?= __('Enter valid YAML. Use 2-space indentation. Press Tab to indent.') ?>
                        <?php else: ?>
                            <i class="bi bi-info-circle me-1"></i><?= __('Enter valid JSON. Press Tab to indent.') ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Simple string type -->
                <?php
                $rawValue = $appSetting->raw_value;
                $isMultiline = strpos($rawValue ?? '', "\n") !== false || strlen($rawValue ?? '') > 100;
                $isNumeric = is_numeric($rawValue) && !$isMultiline;
                $isBoolean = !$isNumeric && in_array(strtolower($rawValue ?? ''), ['true', 'false', 'yes', 'no'], true);
                ?>

                <?php if ($isNumeric): ?>
                    <!-- Numeric value - use number input to preserve type -->
                    <div class="mb-3">
                        <label for="raw_value" class="form-label"><?= __('Value') ?></label>
                        <input
                            type="number"
                            name="raw_value"
                            id="raw_value"
                            class="form-control"
                            value="<?= h($rawValue) ?>"
                            step="<?= strpos($rawValue, '.') !== false ? 'any' : '1' ?>"
                            data-app-setting-edit-target="valueInput">
                    </div>
                <?php elseif ($isBoolean): ?>
                    <!-- Boolean-like value - use dropdown with yes/no values -->
                    <div class="mb-3">
                        <label for="raw_value" class="form-label"><?= __('Value') ?></label>
                        <?= $this->Form->select('raw_value', [
                            'yes' => __('Yes'),
                            'no' => __('No'),
                        ], [
                            'value' => in_array(strtolower($rawValue), ['true', 'yes'], true) ? 'yes' : 'no',
                            'class' => 'form-select',
                            'data-app-setting-edit-target' => 'valueInput',
                        ]) ?>
                    </div>
                <?php elseif ($isMultiline): ?>
                    <!-- Multi-line string - use textarea -->
                    <div class="mb-3">
                        <label for="raw_value" class="form-label"><?= __('Value') ?></label>
                        <textarea
                            name="raw_value"
                            id="raw_value"
                            class="form-control"
                            rows="5"
                            data-app-setting-edit-target="valueInput"><?= h($rawValue) ?></textarea>
                    </div>
                <?php else: ?>
                    <!-- Simple string - use input -->
                    <div class="mb-3">
                        <label for="raw_value" class="form-label"><?= __('Value') ?></label>
                        <input
                            type="text"
                            name="raw_value"
                            id="raw_value"
                            class="form-control"
                            value="<?= h($rawValue) ?>"
                            data-app-setting-edit-target="valueInput">
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Required indicator -->
            <?php if ($appSetting->required): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <?= __('This is a required setting and cannot be deleted.') ?>
                </div>
            <?php endif; ?>
        </fieldset>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <?= __('Cancel') ?>
        </button>
        <?= $this->Form->button(__('Save Changes'), [
            'class' => 'btn btn-primary',
            'type' => 'submit',
        ]) ?>
    </div>

    <?= $this->Form->end() ?>
</turbo-frame>