<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
$guided = $guided ?? false;
$descriptionHasError = $this->Form->isFieldError('description');
?>
<?php if ($guided) : ?>
<section class="border rounded p-3 mb-3" id="gathering-wizard-step-3" aria-labelledby="gathering-public-details"
    data-gathering-wizard-target="panel">
    <h2 id="gathering-public-details" class="h5" tabindex="-1"><?= __('What should visitors see?') ?></h2>
    <fieldset class="mb-3" data-action="change->gathering-wizard#updateWebsite">
        <legend class="h6"><?= __('Where should the kingdom calendar link to this gathering?') ?></legend>
        <p class="form-text">
            <?= __('KMP can host a page with your event details, schedule, activities, and staff.') ?>
        </p>
        <?= $this->Form->control('website_destination', [
            'type' => 'radio',
            'label' => false,
            'default' => 'kmp',
            'required' => true,
            'options' => [
                'kmp' => __('Use the KMP public page (recommended)'),
                'external' => __('Link to another website'),
                'none' => __('Show the gathering without a website link'),
            ],
            'help' => __('This choice applies after the gathering is published to the kingdom calendar.'),
        ]) ?>
    </fieldset>
    <div data-gathering-wizard-target="externalFields">
<?php else : ?>
<fieldset class="border rounded p-3 mt-3">
    <legend class="float-none w-auto px-2 h5"><?= __('Public event details') ?></legend>
<?php endif; ?>
    <?= $this->Form->control('website_url', [
        'type' => 'url',
        'label' => $guided ? __('External event website') : __('External event website (optional)'),
        'placeholder' => 'https://…',
        'help' => $guided ? __('Enter the full URL, starting with https:// or http://.')
            : __('The kingdom calendar opens this website when you provide a URL. '
                . 'Leave it blank to link to the enabled KMP public event page.'),
    ]) ?>
    <?= $this->Form->control('public_page_enabled', [
        'type' => 'checkbox',
        'label' => $guided ? __('Also enable a KMP public event page') : __('Enable KMP public event page'),
        'help' => $guided ? __('Keep a separate KMP page for your schedule, activities, and staff.')
            : __('You can preview the page after saving. Publishing to the kingdom calendar is a separate step. '
                . 'If both website options are blank or off, a published event appears without a website link.'),
    ]) ?>
    <?php if ($guided) : ?>
    </div>
    <?php endif; ?>
    <?= $this->Form->label('description', __('Event description'), ['class' => 'form-label mb-1']) ?>
    <p id="description-help" class="form-text mt-0 mb-2">
        <?= h(__(
            'Include admission, parking, accessibility, and what attendees should bring. Use Markdown for formatting. '
            . 'After saving, use Schedule, Activities, and Staff to add more public details.',
        )) ?>
    </p>
    <?= $this->Form->control('description', [
        'type' => 'textarea',
        'rows' => 4,
        'label' => false,
        'data-controller' => $guided ? null : 'markdown-editor',
        'data-gathering-wizard-target' => $guided ? 'description' : null,
        'data-markdown-editor-min-height-value' => '200px',
        'aria-describedby' => 'description-help' . ($descriptionHasError ? ' description-error' : ''),
        'aria-invalid' => $descriptionHasError ? 'true' : 'false',
    ]) ?>
    <?php if ($guided) : ?>
    <details class="border rounded p-3">
        <summary class="fw-semibold mb-2"><?= __('Add a pre-registration link (optional)') ?></summary>
    <?php endif; ?>
    <div class="row">
        <div class="col-md-7">
            <?= $this->Form->control('preregister_url', [
                'type' => 'url',
                'label' => __('Pre-Registration Link'),
                'placeholder' => 'https://…',
                'help' => __('Link to an external registration or payment page from the calendar and KMP event page.'),
            ]) ?>
        </div>
        <div class="col-md-5">
            <?= $this->Form->control('preregister_closes_on', [
                'type' => 'date',
                'label' => __('Pre-Registration Closes'),
                'empty' => true,
                'help' => __(
                    'Last day the pre-registration link is shown. Leave blank to keep it open until the event.',
                ),
            ]) ?>
        </div>
    </div>
    <?php if ($guided) : ?>
    </details>
</section>
    <?php else : ?>
</fieldset>
    <?php endif; ?>
