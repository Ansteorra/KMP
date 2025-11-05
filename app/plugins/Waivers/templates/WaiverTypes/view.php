<?php

/**
 * @var \App\View\AppView $this
 * @var \Waivers\Model\Entity\WaiverType $waiverType
 */
?>
<?php
$user = $this->request->getAttribute('identity');
$this->extend("/layout/TwitterBootstrap/view_record");
$this->append('css', $this->AssetMix->css('waivers'));

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Waiver Type - ' . $waiverType->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($waiverType->name) ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($user->checkCan("edit", $waiverType)) : ?>
    <?= $this->Html->link(
        'Edit',
        ['action' => 'edit', $waiverType->id],
        ['class' => 'btn btn-primary btn-sm']
    ) ?>
<?php endif; ?>
<?php if ($user->checkCan("delete", $waiverType)) : ?>
    <?= $this->Form->postLink(
        __("Delete"),
        ["action" => "delete", $waiverType->id],
        [
            "confirm" => __(
                "Are you sure you want to delete {0}?",
                $waiverType->name,
            ),
            "title" => __("Delete"),
            "class" => "btn btn-danger btn-sm",
        ],
    ) ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __('Description') ?></th>
    <td><?= $waiverType->description ? $this->Text->autoParagraph(h($waiverType->description)) : '<em>No description</em>' ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Template') ?></th>
    <td>
        <?php if (!empty($waiverType->template_path)): ?>
            <a href="<?= h($waiverType->template_path) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-box-arrow-up-right"></i> View External Template
            </a>
            <br>
            <small class="text-muted"><?= h($waiverType->template_path) ?></small>
        <?php elseif (!empty($waiverType->document_id)): ?>
            <?= $this->Html->link(
                '<i class="bi bi-download"></i> Download Template PDF',
                ['action' => 'downloadTemplate', $waiverType->id],
                [
                    'class' => 'btn btn-sm btn-success',
                    'escape' => false
                ]
            ) ?>
            <br>
            <small class="text-muted">Uploaded file: <?= h($waiverType->document->original_filename ?? 'template.pdf') ?></small>
        <?php else: ?>
            <em class="text-muted">No template configured</em>
        <?php endif; ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Retention Policy') ?></th>
    <td>
        <?= h($waiverType->retention_description) ?>
        <br>
        <small class="text-muted">
            <code><?= h($waiverType->retention_policy) ?></code>
        </small>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Convert to PDF') ?></th>
    <td>
        <?php if ($waiverType->convert_to_pdf): ?>
            <span class="badge bg-success">Yes</span>
            <small class="text-muted d-block">Uploaded images will be converted to PDF format</small>
        <?php else: ?>
            <span class="badge bg-secondary">No</span>
            <small class="text-muted d-block">Images will be stored in original format</small>
        <?php endif; ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Status') ?></th>
    <td>
        <?php if ($waiverType->is_active): ?>
            <span class="badge bg-success">Active</span>
        <?php else: ?>
            <span class="badge bg-secondary">Inactive</span>
        <?php endif; ?>
        <?php if ($user->checkCan("edit", $waiverType)) : ?>
            <?= $this->Form->postLink(
                $waiverType->is_active ? 'Deactivate' : 'Activate',
                ['action' => 'toggleActive', $waiverType->id],
                [
                    'class' => 'btn btn-warning btn-sm ms-2',
                    'confirm' => __(
                        'Are you sure you want to {0} this waiver type?',
                        $waiverType->is_active ? 'deactivate' : 'activate'
                    )
                ]
            ) ?>
        <?php endif; ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Created') ?></th>
    <td><?= $this->Timezone->format($waiverType->created, null, null, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Modified') ?></th>
    <td><?= $this->Timezone->format($waiverType->modified, null, null, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabButtons") ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabContent") ?>
<?php $this->KMP->endBlock() ?>