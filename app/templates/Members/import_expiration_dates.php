<?php
$this->extend('/layout/TwitterBootstrap/dashboard');

$this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Member Data Import';
$this->KMP->endBlock();
?>
<section aria-labelledby="member-data-import-heading">
    <h1 id="member-data-import-heading" class="h3"><?= __('Member Data Import') ?></h1>

    <p>
        <?= __(
            'Import membership or background check expiration dates by member number. '
            . 'Choose the date type before uploading the CSV file.',
        ) ?>
    </p>

    <div class="alert alert-info" role="note">
        <p class="mb-2">
            <?= __('The CSV must contain exactly two columns and use YYYY-MM-DD dates:') ?>
        </p>
        <ul class="mb-0">
            <li><?= __('Member Number') ?></li>
            <li><?= __('Expiration Date') ?></li>
        </ul>
    </div>

    <p class="mb-2"><?= __('Example CSV:') ?></p>
    <pre class="bg-light border rounded p-3"><code>Member Number,Expiration Date
12345,2031-12-31
67890,2032-01-31</code></pre>

    <?= $this->Form->create(null, [
        'type' => 'file',
        'url' => ['controller' => 'Members', 'action' => 'importExpirationDates'],
    ]) ?>
    <fieldset class="border rounded-3 bg-white shadow-sm p-3 mb-3">
        <legend class="float-none w-auto px-2 fs-6 fw-semibold">
            <?= __('Import settings') ?>
        </legend>
        <?= $this->Form->control('import_type', [
            'type' => 'select',
            'options' => $importTypes,
            'empty' => __('Choose the expiration date type'),
            'label' => __('Expiration date type'),
            'required' => true,
        ]) ?>
        <?= $this->Form->control('importData', [
            'type' => 'file',
            'accept' => '.csv,text/csv',
            'label' => __('CSV file'),
            'required' => true,
        ]) ?>
    </fieldset>
    <?= $this->Form->button(__('Import dates'), ['class' => 'btn btn-primary']) ?>
    <?= $this->Form->end() ?>
</section>
