<?php

/**
 * KMP Auto Complete Control Element
 * 
 * Reusable form control element that provides advanced autocomplete functionality
 * with AJAX search capabilities, keyboard navigation, and customizable behavior.
 * This element integrates with the Stimulus.js autocomplete controller to deliver
 * a rich user experience for data selection and entry.
 * 
 * Features:
 * - AJAX-powered search with configurable endpoints
 * - Keyboard navigation (arrow keys, enter, escape)
 * - Support for custom value entry when enabled
 * - Configurable minimum search length
 * - Visual feedback with loading indicators
 * - Clear button for easy value reset
 * - Integration with CakePHP form system
 * - Bootstrap styling and responsive design
 * 
 * Control Structure:
 * - Text input field for user interaction and display
 * - Hidden field for storing selected value ID
 * - Clear button for resetting selection
 * - Dropdown list for search results display
 * - Loading indicators for AJAX operations
 * 
 * JavaScript Integration:
 * - Stimulus.js autocomplete controller (data-controller='ac')
 * - Configurable AJAX endpoint URL
 * - Minimum length validation before search
 * - Custom value support toggle
 * - Keyboard interaction handling
 * - Focus management and accessibility
 * 
 * Configuration Options:
 * - inputField: Base name for form fields (appends -Disp and -Id)
 * - resultField: Field name for storing selected value ID
 * - url: AJAX endpoint URL for search operations
 * - label: Display label for the form control
 * - required: Whether field is required for form validation
 * - allowOtherValues: Enable custom value entry beyond search results
 * - minLength: Minimum characters required before triggering search
 * - additionalAttrs: Array of additional HTML attributes
 * 
 * AJAX Endpoint Requirements:
 * ```php
 * // Controller method should return JSON:
 * public function autocomplete()
 * {
 *     $term = $this->request->getQuery('term');
 *     $results = $this->MyModel->search($term);
 *     
 *     $this->viewBuilder()->setClassName('Json');
 *     $this->set('data', $results->map(function($item) {
 *         return ['id' => $item->id, 'text' => $item->display_name];
 *     })->toArray());
 * }
 * ```
 * 
 * Usage Examples:
 * ```php
 * // Basic member autocomplete
 * echo $this->element('autoCompleteControl', [
 *     'inputField' => 'member',
 *     'resultField' => 'member_id',
 *     'url' => '/members/autocomplete',
 *     'label' => 'Select Member',
 *     'required' => true,
 *     'allowOtherValues' => false,
 *     'minLength' => 2
 * ]);
 * 
 * // Autocomplete with custom values allowed
 * echo $this->element('autoCompleteControl', [
 *     'inputField' => 'organization',
 *     'resultField' => 'organization_id',
 *     'url' => '/organizations/autocomplete',
 *     'label' => 'Organization',
 *     'allowOtherValues' => true,
 *     'additionalAttrs' => ['data-category' => 'organizations']
 * ]);
 * ```
 * 
 * Form Integration:
 * - Creates both display field ({inputField}-Disp) and value field ({resultField})
 * - Display field shows user-friendly text and handles interaction
 * - Value field (hidden) stores the selected ID for form submission
 * - Integrates with CakePHP form validation system
 * - Supports Bootstrap form styling and validation feedback
 * 
 * Accessibility Features:
 * - Proper ARIA roles and attributes for screen readers
 * - Keyboard navigation support for all interactions
 * - Focus management during search and selection
 * - Clear visual indicators for loading and selection states
 * - Semantic HTML structure for optimal accessibility
 * 
 * @var \App\View\AppView $this The view instance with form helper
 * @var \Cake\View\Helper\FormHelper $Form CakePHP form helper instance
 * @var string $inputField Base name for form input fields
 * @var string $resultField Field name for storing selected value ID
 * @var string $url AJAX endpoint URL for search operations
 * @var string|null $label Display label for the form control
 * @var bool $required Whether the field is required for validation
 * @var bool $allowOtherValues Enable custom value entry capability
 * @var int $minLength Minimum characters before triggering search (default: 1)
 * @var array $additionalAttrs Additional HTML attributes for customization
 * 
 * @see /assets/js/controllers/auto-complete-controller.js For JavaScript implementation
 * @see \App\View\Helper\KmpHelper::autoCompleteControl() For helper method alternative
 * @see /templates/element/comboBoxControl.php For dropdown alternative
 */

/**
 * Auto Complete Control Element
 * 
 * @var \Cake\View\View $this
 * @var \Cake\View\Helper\FormHelper $Form
 * @var string $inputField
 * @var string $resultField
 * @var string $url
 * @var string|null $label
 * @var bool $required
 * @var bool $allowOtherValues
 * @var int $minLength
 * @var array $additionalAttrs
 */

$attrs = '';
$class = '';
if ($additionalAttrs) {
    if (isset($additionalAttrs['class'])) {
        $class = $additionalAttrs['class'];
    }
    foreach ($additionalAttrs as $key => $value) {
        $attrs .= $key . "='" . \App\KMP\StaticHelpers::makeSafeForHtmlAttribute($value) . "' ";
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
    data-ac-url-value='<?= h($url) ?>'
    role='combobox'
    class='position-relative mb-3 kmp_autoComplete <?= h($class) ?>'
    data-ac-allow-other-value='<?= $allowOtherValues ? 'true' : 'false' ?>'
    data-ac-min-length-value='<?= (int)$minLength ?>'
    <?= $attrs ?>>

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