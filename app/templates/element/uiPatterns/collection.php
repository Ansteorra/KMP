<?php
declare(strict_types=1);

/** Real Dataverse controls over fictional gathering records and isolated saved views. */
$canManage = $showcaseState !== 'restricted';
echo $this->element('uiPatterns/pageHeader', [
    'eyebrow' => __('Gatherings'),
    'title' => __('Gatherings'),
    'description' => __('Find gatherings and keep the views you use in the field.'),
    'actions' => $canManage ? [[
        'label' => __('Create gathering'), 'icon' => 'plus', 'primary' => true, 'modal' => 'collection-create',
    ]] : [],
]);
?>
<?= $this->element('uiPatterns/gatherings_grid') ?>

<?php if ($canManage) : ?>
    <div class="modal fade" id="collection-create" tabindex="-1"
        aria-labelledby="collection-create-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
            <form class="modal-content" data-action="submit->ui-patterns#savePreview"
                data-message="<?= __('Gathering details saved in this review session.') ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="collection-create-title"><?= __('Create gathering') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?= __('Close') ?>"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="collection-create-name"><?= __('Gathering name') ?></label>
                    <input class="form-control mb-3" id="collection-create-name" name="name" required>
                    <label class="form-label" for="collection-create-branch"><?= __('Hosting branch') ?></label>
                    <select class="form-select mb-3" id="collection-create-branch" name="branch">
                        <option><?= __('Barony of Oakmere') ?></option><option><?= __('Shire of Willowford') ?></option>
                    </select>
                    <label class="form-label" for="collection-create-date"><?= __('Start date') ?></label>
                    <input class="form-control" id="collection-create-date" name="date" type="date" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                        <?= __('Cancel') ?>
                    </button>
                    <button class="btn btn-primary" type="submit"><?= __('Create gathering') ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
