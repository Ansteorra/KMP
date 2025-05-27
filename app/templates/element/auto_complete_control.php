<?php

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