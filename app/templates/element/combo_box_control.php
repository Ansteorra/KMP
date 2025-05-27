<?php

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