<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
/** @var string $showcaseState */

$canSubmit = $showcaseState !== 'restricted';
$readerUrl = $this->Url->build(['controller' => 'UiPatterns', 'action' => 'index', '?' => ['pattern' => 'reader']]);
$collectionUrl = $this->Url->build([
    'controller' => 'UiPatterns', 'action' => 'index', '?' => ['pattern' => 'collection'],
]);
$anotherSubmissionUrl = $this->Url->build([
    'controller' => 'UiPatterns', 'action' => 'index', '?' => ['pattern' => 'task'],
]);
?>
<?= $this->element('uiPatterns/pageHeader', [
    'eyebrow' => __('Waivers'),
    'title' => __('Submit gathering waivers'),
    'description' => __('Choose the waiver type, add a clear copy, and check it before submitting.'),
    'back' => true,
]) ?>

<?php if ($showcaseState === 'empty') : ?>
    <section class="ui-surface ui-empty" aria-labelledby="task-empty-title">
        <?= $this->Icon->render('file-upload') ?>
        <h2 id="task-empty-title"><?= __('Choose a gathering first') ?></h2>
        <p><?= __('Waiver types and activities depend on the gathering you are collecting for.') ?></p>
        <a class="btn btn-primary" href="<?= h($collectionUrl) ?>"><?= __('Find a gathering') ?></a>
    </section>
<?php else : ?>
    <section class="ui-surface ui-task-context" aria-label="<?= __('Gathering context') ?>">
        <div class="d-flex align-items-start gap-3">
            <?= $this->Icon->render('calendar-event') ?>
            <div>
                <a class="fw-semibold" href="<?= h($readerUrl) ?>"><?= __('Oakmere Winter Court') ?></a>
                <p class="text-muted mb-0"><?= __('December 5, 2026 · Barony of Oakmere') ?></p>
            </div>
        </div>
    </section>

    <?php if (!$canSubmit) : ?>
        <section class="ui-surface ui-empty" aria-labelledby="task-restricted-title">
            <?= $this->Icon->render('lock') ?>
            <h2 id="task-restricted-title"><?= __('Waiver collection access required') ?></h2>
            <p><?= __('You can view this gathering. Submitting its waivers requires separate access.') ?></p>
            <a class="btn btn-outline-primary" href="<?= h($readerUrl) ?>"><?= __('View gathering') ?></a>
        </section>
    <?php else : ?>
        <?php if ($showcaseState === 'error') : ?>
            <div class="alert alert-danger" role="alert" data-task-error>
                <strong><?= __('The last submission did not finish.') ?></strong>
                <?= __('Review your file and try again. No waiver has been marked as uploaded.') ?>
            </div>
        <?php endif; ?>

        <form data-action="submit->ui-patterns#savePreview" data-preview-task="waiver"
            data-message="<?= __('Submission reviewed. No file was uploaded and no waiver record changed.') ?>">
            <nav aria-label="<?= __('Waiver submission steps') ?>" class="ui-stepper">
                <?php foreach ([1 => __('Waiver type'), 2 => __('Add file'), 3 => __('Review')] as $step => $label) : ?>
                    <button type="button" data-ui-patterns-target="stepButton" data-step="<?= $step ?>"
                        data-action="ui-patterns#goToStep" <?= $step === 1 ? 'aria-current="step"' : 'disabled' ?>
                        aria-controls="task-step-<?= $step ?>">
                        <span class="ui-step-number" aria-hidden="true"><?= $step ?></span>
                        <span><?= h($label) ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>

            <section class="ui-surface ui-task-panel" id="task-step-1" data-ui-patterns-target="step"
                data-step="1" aria-labelledby="task-type-title">
                <h2 id="task-type-title" class="ui-section-heading" tabindex="-1"
                    data-ui-patterns-target="stepHeading"><?= __('Which waiver are you submitting?') ?></h2>
                <p class="text-muted"><?= __('Use a separate submission for each waiver type.') ?></p>
                <div class="mb-4">
                    <label class="form-label" for="task-waiver-type"><?= __('Waiver type') ?></label>
                    <select class="form-select" id="task-waiver-type" name="waiver_type" required
                        data-ui-patterns-target="waiverType" aria-describedby="task-type-help">
                        <option value="Adult roster waiver"><?= __('Adult roster waiver') ?></option>
                        <option value="Minor waiver"><?= __('Minor waiver') ?></option>
                    </select>
                    <div class="form-text" id="task-type-help">
                        <?= __('Check that the heading on your document matches the selected type.') ?>
                    </div>
                </div>
                <div class="ui-summary">
                    <div>
                        <span class="ui-field-label"><?= __('Activities at this gathering') ?></span>
                        <p class="mb-0"><?= __('Archery · Scribal arts · Kingdom court · Bardic circle') ?></p>
                    </div>
                </div>
                <p class="text-muted mt-3 mb-0">
                    <?= __('An exemption or a lost waiver is recorded separately from a file submission.') ?>
                </p>
            </section>

            <section class="ui-surface ui-task-panel" id="task-step-2" data-ui-patterns-target="step"
                data-step="2" aria-labelledby="task-file-title" hidden>
                <h2 id="task-file-title" class="ui-section-heading" tabindex="-1"
                    data-ui-patterns-target="stepHeading"><?= __('Add a clear copy') ?></h2>
                <p class="text-muted"><?= __('Include every page, with names, signatures, and dates readable.') ?></p>
                <label class="form-label" for="task-waiver-file"><?= __('Waiver file') ?></label>
                <input class="form-control" id="task-waiver-file" type="file" name="waiver_file"
                    accept="application/pdf,image/jpeg,image/png" data-ui-patterns-target="file"
                    data-action="change->ui-patterns#selectFile" aria-describedby="task-file-help">
                <div class="form-text" id="task-file-help">
                    <?= __('PDF, JPG, or PNG. Files selected here stay on this device and are not uploaded.') ?>
                </div>
                <div class="ui-file-selection mt-4">
                    <?= $this->Icon->render('file') ?>
                    <div>
                        <span class="ui-field-label"><?= __('Selected file') ?></span>
                        <p class="mb-0" data-ui-patterns-target="fileName"><?= __('No file selected') ?></p>
                    </div>
                </div>
                <button class="btn btn-outline-secondary mt-3" type="button"
                    data-action="ui-patterns#useSampleFile">
                    <?= __('Use a sample waiver for this review') ?>
                </button>
            </section>

            <section class="ui-surface ui-task-panel" id="task-step-3" data-ui-patterns-target="step"
                data-step="3" aria-labelledby="task-review-title" hidden>
                <h2 id="task-review-title" class="ui-section-heading" tabindex="-1"
                    data-ui-patterns-target="stepHeading"><?= __('Review the submission') ?></h2>
                <dl class="ui-summary">
                    <div><dt><?= __('Gathering') ?></dt><dd><?= __('Oakmere Winter Court') ?></dd></div>
                    <div><dt><?= __('Waiver type') ?></dt><dd data-ui-patterns-target="reviewType"></dd></div>
                    <div><dt><?= __('File') ?></dt><dd data-ui-patterns-target="reviewFile"></dd></div>
                    <div>
                        <dt><?= __('Result after submission') ?></dt><dd><?= __('Uploaded · awaiting review') ?></dd>
                    </div>
                </dl>
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="task-attestation" name="attestation" required>
                    <label class="form-check-label" for="task-attestation">
                        <?= __('I checked every page is present and readable, and this is the correct gathering.') ?>
                    </label>
                </div>
            </section>

            <div class="ui-task-footer">
                <a class="btn btn-outline-secondary" href="<?= h($readerUrl) ?>"><?= __('Cancel') ?></a>
                <div class="ui-actions ms-auto">
                    <button type="button" class="btn btn-outline-secondary" data-ui-patterns-target="previousButton"
                        data-action="ui-patterns#previousStep" hidden>
                        <?= $this->Icon->render('arrow-left') ?> <?= __('Previous step') ?>
                    </button>
                    <button type="button" class="btn btn-primary" data-ui-patterns-target="nextButton"
                        data-action="ui-patterns#nextStep">
                        <?= __('Continue') ?> <?= $this->Icon->render('arrow-right') ?>
                    </button>
                    <button type="submit" class="btn btn-primary" data-ui-patterns-target="submitButton" hidden>
                        <?= $this->Icon->render('file-upload') ?> <?= __('Submit waiver') ?>
                    </button>
                </div>
            </div>
        </form>
        <section class="ui-surface" data-ui-patterns-target="taskComplete"
            aria-labelledby="task-complete-title" hidden>
            <h2 id="task-complete-title" tabindex="-1" data-ui-patterns-target="completionHeading">
                <?= __('Waiver submission preview complete') ?>
            </h2>
            <p><?= __('You reviewed the submission. No file was uploaded and no waiver record was changed.') ?></p>
            <div class="ui-actions">
                <a class="btn btn-primary" href="<?= h($readerUrl) ?>"><?= __('Back to gathering') ?></a>
                <a class="btn btn-outline-secondary" href="<?= h($anotherSubmissionUrl) ?>">
                    <?= __('Review another submission') ?>
                </a>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
