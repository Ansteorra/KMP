<?php
declare(strict_types=1);

use App\Services\PublicCalendarSettings;

/** @var \App\View\AppView $this */
/** @var \Cake\Form\Form $form */
$this->extend('/layout/TwitterBootstrap/dashboard');
$this->assign('title', __('Public Events Calendar'));

$calendarRoute = ['controller' => 'Gatherings', 'action' => 'publicCalendar'];
$embedUrl = $this->Url->build($calendarRoute + ['?' => ['embed' => '1']], ['fullBase' => true]);
$embedCode = '<iframe src="' . h($embedUrl) . '" title="' . h(__('Kingdom events calendar'))
    . '" width="100%" height="720" loading="lazy"></iframe>';
$markdownOptions = [
    'type' => 'textarea',
    'rows' => 5,
    'maxlength' => 12000,
    'data-controller' => 'markdown-editor',
    'data-markdown-editor-min-height-value' => '150px',
    'help' => __('Use Markdown for headings, lists, and links. Leave blank to hide this text.'),
];
$openNewTab = ['target' => '_blank', 'rel' => 'noopener'];
$suggestedIcons = ['court-crown', 'calendar', 'shield', 'castle', 'flag', 'scroll', 'compass'];
$fontLabels = ['heading_font' => __('Heading font'), 'body_font' => __('Body font')];
$colorLabels = [
    'background' => __('Page background'), 'panel' => __('Panel background'),
    'text' => __('Text color'), 'accent' => __('Accent color'),
];
?>

<div class="app-settings form content">
    <h1 class="h3 mb-3"><?= __('Public Events Calendar') ?></h1>
    <p class="d-flex flex-wrap gap-3">
        <?= $this->Html->link(__('View saved calendar (opens in new tab)'), $calendarRoute, $openNewTab) ?>
        <?= $this->Html->link(__('View saved embed (opens in new tab)'), $embedUrl, $openNewTab) ?>
    </p>

    <?= $this->Form->create($form, ['type' => 'file']) ?>
    <fieldset class="border rounded p-3 mb-3">
        <legend class="float-none w-auto px-2 h5"><?= __('Header and introduction') ?></legend>
        <?= $this->Form->control('show_header', [
            'type' => 'checkbox', 'label' => __('Show the masthead on the standalone calendar'),
        ]) ?>
        <?= $this->Form->control('show_subscribe', [
            'type' => 'checkbox', 'label' => __('Show Subscribe to Calendar in the masthead'),
        ]) ?>
        <div class="row">
            <div class="col-md-6">
                <?= $this->Form->control('header_title', [
                    'type' => 'text', 'label' => __('Masthead title'), 'maxlength' => 120,
                    'help' => __('Leave blank to use the kingdom’s short site title.'),
                ]) ?>
            </div>
            <div class="col-md-6">
                <?= $this->Form->control('header_subtitle', [
                    'type' => 'text', 'label' => __('Masthead subtitle'), 'maxlength' => 120,
                    'help' => __('Leave blank to hide the subtitle.'),
                ]) ?>
            </div>
            <div class="col-md-6">
                <?= $this->Form->control('artwork', [
                    'type' => 'select',
                    'label' => __('Header artwork'),
                    'options' => [
                        'icon' => __('KMP catalog icon'), 'logo' => __('Uploaded logo'), 'none' => __('None'),
                    ],
                ]) ?>
            </div>
            <div class="col-md-6">
                <?= $this->Form->control('header_icon', [
                    'type' => 'text', 'label' => __('KMP icon ID'), 'list' => 'calendar-header-icons',
                    'maxlength' => 120,
                    'help' => __('Choose a suggestion or enter any KMP catalog icon ID.'),
                ]) ?>
                <datalist id="calendar-header-icons">
                    <?php foreach ($suggestedIcons as $icon) : ?>
                        <option value="<?= h($icon) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-6">
                <?= $this->Form->control('logo', [
                    'type' => 'file', 'label' => __('Calendar logo'), 'accept' => 'image/png,image/webp',
                    'help' => __('PNG or WebP, up to 2 MB and 1024 × 1024 pixels. Select Uploaded logo to display it.'),
                ]) ?>
                <?php if ($savedLogoUrl !== null) : ?>
                    <p class="d-flex flex-wrap gap-2 align-items-center">
                        <img src="<?= h($savedLogoUrl) ?>" alt="" class="object-fit-contain" width="72" height="48">
                        <span><?= __('Saved calendar logo') ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?= $this->Form->control('calendar_heading', [
                    'type' => 'text', 'label' => __('Calendar heading'), 'maxlength' => 120,
                    'help' => __('Leave blank to hide the heading above the filters.'),
                ]) ?>
            </div>
        </div>
        <?= $this->Form->control('intro', ['label' => __('Introduction')] + $markdownOptions) ?>
    </fieldset>

    <fieldset class="border rounded p-3 mb-3">
        <legend class="float-none w-auto px-2 h5"><?= __('Footer') ?></legend>
        <?= $this->Form->control('show_footer', [
            'type' => 'checkbox', 'label' => __('Show the footer on the standalone calendar'),
        ]) ?>
        <?= $this->Form->control('footer_title', [
            'type' => 'text', 'label' => __('Footer title'), 'maxlength' => 120,
            'help' => __('A title makes the footer expandable. Leave blank to show the footer text directly.'),
        ]) ?>
        <?= $this->Form->control('footer_body', ['label' => __('Footer text')] + $markdownOptions) ?>
    </fieldset>

    <fieldset class="border rounded p-3 mb-3">
        <legend class="float-none w-auto px-2 h5"><?= __('Theme and fonts') ?></legend>
        <div class="row">
            <div class="col-md-4">
                <?= $this->Form->control('theme', [
                    'label' => __('Theme'), 'type' => 'select', 'options' => PublicCalendarSettings::themeOptions(),
                ]) ?>
            </div>
            <?php foreach ($fontLabels as $field => $label) : ?>
                <div class="col-md-4">
                    <?= $this->Form->control($field, [
                        'type' => 'select', 'label' => $label, 'options' => PublicCalendarSettings::fontOptions(),
                    ]) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="form-text" id="calendar-colors-help">
            <?= __('Use six-digit hex values such as #254D77. Blank fields use the selected theme color.') ?>
        </p>
        <div class="row">
            <?php foreach ($colorLabels as $field => $label) : ?>
                <div class="col-sm-6 col-lg-3">
                    <?= $this->Form->control($field, [
                        'type' => 'text', 'label' => $label, 'maxlength' => 7,
                        'aria-describedby' => 'calendar-colors-help',
                        'autocomplete' => 'off', 'spellcheck' => 'false',
                    ]) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="border rounded p-3 mb-3">
        <legend class="float-none w-auto px-2 h5"><?= __('Embed on a website') ?></legend>
        <?= $this->Form->control('embed_origins', [
            'type' => 'textarea', 'rows' => 3, 'maxlength' => 2048, 'label' => __('Allowed websites'),
            'help' => __(
                'Enter one HTTPS origin per line, such as https://kingdom.example. '
                . 'Leave blank to restrict embeds to this calendar’s origin.',
            ),
        ]) ?>
        <?= $this->Form->control('embed_header', [
            'type' => 'checkbox', 'label' => __('Show the masthead and calendar heading in the embed'),
        ]) ?>
        <?= $this->Form->control('embed_intro', ['type' => 'checkbox', 'label' => __('Show the intro in the embed')]) ?>
        <?= $this->Form->control('embed_footer', [
            'type' => 'checkbox', 'label' => __('Show the footer in the embed'),
        ]) ?>
    </fieldset>

    <?= $this->Form->button(__('Save calendar settings'), ['class' => 'btn btn-primary']) ?>
    <?= $this->Form->end() ?>

    <div class="mt-4">
        <label for="calendar-embed-code" class="form-label"><?= __('Iframe code') ?></label>
        <textarea id="calendar-embed-code" class="form-control font-monospace" rows="3" readonly
            aria-describedby="calendar-embed-help"><?= h($embedCode) ?></textarea>
        <p id="calendar-embed-help" class="form-text">
            <?= __('After saving the allowed websites, copy this code to your website.') ?>
        </p>
    </div>
</div>
