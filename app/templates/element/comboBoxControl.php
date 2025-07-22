<?php

/**
 * KMP Combo Box Control Element
 * 
 * Reusable form control element that provides dropdown selection functionality
 * with autocomplete capabilities and support for custom value entry. This element
 * creates a hybrid control combining dropdown selection with search functionality
 * for enhanced user experience in data selection scenarios.
 * 
 * Features:
 * - Dropdown selection with search/filter capability
 * - Support for predefined data sets (no AJAX required)
 * - Optional custom value entry when enabled
 * - Keyboard navigation and accessibility support
 * - Clear button for easy value reset
 * - Integration with CakePHP form system
 * - Bootstrap styling and responsive design
 * - Disabled option support with visual indicators
 * 
 * Control Structure:
 * - Text input field for user interaction and display
 * - Hidden field for storing selected value ID
 * - Hidden field for storing display text
 * - JSON data container for search options
 * - Clear button for resetting selection
 * - Dropdown list for available options
 * 
 * Data Format:
 * The control accepts data in several formats:
 * - Simple key-value pairs: ['1' => 'Option One', '2' => 'Option Two']
 * - Complex arrays with additional data: ['1' => ['text' => 'Option One', 'data' => {...}]]
 * - Disabled options using pipe syntax: ['1|false' => 'Disabled Option']
 * 
 * JavaScript Integration:
 * - Stimulus.js autocomplete controller (data-controller='ac')
 * - Minimum length set to 0 for immediate dropdown behavior
 * - Custom value support toggle
 * - JSON data embedded in template for offline operation
 * - Keyboard interaction handling
 * 
 * Configuration Options:
 * - inputField: Base name for form fields (appends -Disp and -Id)
 * - resultField: Field name for storing selected value ID
 * - data: Array of options for selection
 * - label: Display label for the form control
 * - required: Whether field is required for form validation
 * - allowOtherValues: Enable custom value entry beyond predefined options
 * - additionalAttrs: Array of additional HTML attributes
 * 
 * Usage Examples:
 * ```php
 * // Basic dropdown with predefined options
 * echo $this->element('comboBoxControl', [
 *     'inputField' => 'status',
 *     'resultField' => 'status_id',
 *     'data' => [
 *         'active' => 'Active',
 *         'inactive' => 'Inactive',
 *         'pending' => 'Pending'
 *     ],
 *     'label' => 'Member Status',
 *     'required' => true,
 *     'allowOtherValues' => false
 * ]);
 * 
 * // Complex options with additional data
 * echo $this->element('comboBoxControl', [
 *     'inputField' => 'branch',
 *     'resultField' => 'branch_id',
 *     'data' => [
 *         '1' => ['text' => 'Branch A', 'data' => ['region' => 'North']],
 *         '2' => ['text' => 'Branch B', 'data' => ['region' => 'South']],
 *         '3|false' => 'Inactive Branch'  // Disabled option
 *     ],
 *     'label' => 'Select Branch',
 *     'allowOtherValues' => true
 * ]);
 * ```
 * 
 * Form Integration:
 * - Creates display field ({inputField}-Disp) for user interaction
 * - Creates value field ({resultField}) for storing selected ID
 * - Creates text field ({inputField}) for storing display text
 * - Integrates with CakePHP form validation system
 * - Supports Bootstrap form styling and feedback
 * 
 * Disabled Options:
 * Options can be marked as disabled using pipe syntax in the key:
 * - 'key|false' marks the option as disabled
 * - 'key|true' or 'key' marks the option as enabled (default)
 * - Disabled options appear in dropdown but cannot be selected
 * 
 * Accessibility Features:
 * - Proper ARIA roles and attributes for screen readers
 * - Keyboard navigation support for all interactions
 * - Focus management during search and selection
 * - Clear visual indicators for selection states
 * - Semantic HTML structure for optimal accessibility
 * 
 * Performance Considerations:
 * - JSON data is embedded in template (no AJAX overhead)
 * - Suitable for datasets up to several hundred options
 * - Consider autocomplete element for large datasets requiring AJAX
 * - Efficient DOM manipulation for search and filtering
 * 
 * @var \App\View\AppView $this The view instance with form helper
 * @var \Cake\View\Helper\FormHelper $Form CakePHP form helper instance
 * @var string $inputField Base name for form input fields
 * @var string $resultField Field name for storing selected value ID
 * @var array $data Array of options for selection (key-value pairs or complex arrays)
 * @var string|null $label Display label for the form control
 * @var bool $required Whether the field is required for validation
 * @var bool $allowOtherValues Enable custom value entry capability
 * @var array $additionalAttrs Additional HTML attributes for customization
 * 
 * @see /assets/js/controllers/auto-complete-controller.js For JavaScript implementation
 * @see \App\View\Helper\KmpHelper::comboBoxControl() For helper method alternative
 * @see /templates/element/autoCompleteControl.php For AJAX-powered alternative
 */

/**
 * Combo Box Control Element
 * 
 * @var \Cake\View\View $this
 * @var \Cake\View\Helper\FormHelper $Form
 * @var string $inputField
 * @var string $resultField
 * @var array $data
 * @var string|null $label
 * @var bool $required
 * @var bool $allowOtherValues
 * @var array $additionalAttrs
 */

$attrs = '';
if ($additionalAttrs) {
    foreach ($additionalAttrs as $key => $value) {
        $attrs .= $key . "='" . \App\KMP\StaticHelpers::makeSafeForHtmlAttribute($value) . "' ";
    }
}

$listData = [];
foreach ($data as $key => $value) {
    $enabled = true;
    if (!is_int($key) && strpos($key, '|') !== false) {
        $keyParts = explode('|', $key);
        $key = $keyParts[0];
        $enabled = $keyParts[1] == 'true';
    }

    if (is_string($value)) {
        $listData[] = ['value' => $key, 'text' => $value, 'enabled' => $enabled];
    } else {
        $listData[] = ['value' => $key, 'text' => $value['text'], 'data' => $value, 'enabled' => $enabled];
    }
}

$textEntry = $Form->control($inputField . '-Disp', [
    'required' => $required,
    'type' => 'text',
    'label' => $label,
    'data-ac-target' => 'input',
    'container' => ['style' => 'margin:0 !important;'],
    'append' => ['clearBtn'],
]);

$textEntry = str_replace(
    '<span class="input-group-text">clearBtn</span>',
    "<button class='btn btn-outline-secondary' data-ac-target='clearBtn' data-action='ac#clear' disabled>Clear</button>",
    $textEntry
);
?>

<div data-controller='ac'
    role='combobox'
    class='position-relative mb-3 kmp_autoComplete'
    data-ac-allow-other-value='<?= $allowOtherValues ? 'true' : 'false' ?>'
    data-ac-min-length-value='0'
    <?= $attrs ?>>

    <script type='application/json' data-ac-target='dataList' class='d-none'>
        <?= json_encode($listData) ?>
    </script>

    <?= $Form->control($resultField, [
        'type' => 'hidden',
        'data-ac-target' => 'hidden',
    ]) ?>

    <?= $Form->control($inputField, [
        'type' => 'hidden',
        'data-ac-target' => 'hiddenText',
    ]) ?>

    <?= $textEntry ?>

    <ul data-ac-target='results'
        class='list-group z-3 col-12 position-absolute auto-complete-list'
        hidden='hidden'></ul>
</div>