<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
$mapsApiKey = $this->KMP->getAppSetting('GoogleMaps.ApiKey', '');
$fallbackMessage = __('Address suggestions are unavailable. Enter a venue name or street address.');
?>
<div data-controller="gathering-location-autocomplete"
    data-gathering-location-autocomplete-api-key-value="<?= h($mapsApiKey) ?>"
    data-gathering-location-autocomplete-fallback-message-value="<?= h($fallbackMessage) ?>">
    <?= $this->Form->control('location', [
        'type' => 'text',
        'autocomplete' => 'off',
        'data-gathering-location-autocomplete-target' => 'input',
        'data-action' => 'input->gathering-location-autocomplete#addressChanged',
        'help' => [
            'content' => __('Enter a venue name or street address so attendees can find it.'),
            'role' => 'status',
            'data-gathering-location-autocomplete-target' => 'help',
        ],
    ]) ?>
    <?= $this->Form->hidden('latitude', ['data-gathering-location-autocomplete-target' => 'latitude']) ?>
    <?= $this->Form->hidden('longitude', ['data-gathering-location-autocomplete-target' => 'longitude']) ?>
</div>
