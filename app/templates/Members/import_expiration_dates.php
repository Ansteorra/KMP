<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Member Expiration Dates Import';
$this->KMP->endBlock(); ?>
<h3>
    Import Expiration Dates
</h3>
<div>
    <p>
        This page is used to import expiration dates for members. The expiration dates are imported from a CSV file. The
        CSV file must have the following columns:
    </p>
    <ul>
        <li>Member Number</li>
        <li>Expiration Date</li>
    </ul>
    <p>
        The Member Number must match the Member Number of the member in the database. The Expiration Date must be in the
        format of YYYY-MM-DD. The file must be in CSV format with the first row being a header row.
    </p>
    <p>
        The following is an example of a valid CSV file:
    <pre>
            Member Number,Expiration Date
            12345,2021-12-31
            67890,2022-12-31
        </pre>
    </p>
    <?= $this->Form->create(null, [
        "type" => "file",
        "url" => ["controller" => "Members", "action" => "importExpirationDates"],
    ]) ?>
    <fieldset>
        <?php echo $this->Form->control("importData", [
            "type" => "file",
            "accept" => ".csv",
        ]); ?>
        <?= $this->Form->button(__("Upload", ["class" => "btn-primary"])) ?>
        <?= $this->Form->end() ?>