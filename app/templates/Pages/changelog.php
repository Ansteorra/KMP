<?php

declare(strict_types=1);

/**
 * Changelog display page
 *
 * @var \App\View\AppView $this
 * @var string $changelogContent HTML content from parsed CHANGELOG.md
 * @var string|null $lastSyncedDate Date of last changelog sync
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle', 'KMP') . ': Changelog';
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
Changelog
<?php $this->KMP->endBlock() ?>

<?php
// Ensure variables are set with defaults
$changelogContent = $changelogContent ?? '';
$lastSyncedDate = $lastSyncedDate ?? null;
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-text me-2"></i>Application Changelog
                    </h5>
                    <?php if (!empty($lastSyncedDate) && $lastSyncedDate !== 'none'): ?>
                        <small class="text-muted">
                            Last updated: <?= h($lastSyncedDate) ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="card-body changelog-content">
                    <?php if (!empty($changelogContent)): ?>
                        <?= $changelogContent ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No changelog entries available yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .changelog-content h1 {
        font-size: 1.75rem;
        border-bottom: 2px solid var(--bs-primary);
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .changelog-content h2 {
        font-size: 1.35rem;
        color: var(--bs-primary);
        margin-top: 2rem;
        margin-bottom: 1rem;
        padding-bottom: 0.25rem;
        border-bottom: 1px solid var(--bs-border-color);
    }

    .changelog-content h3 {
        font-size: 1.1rem;
        color: var(--bs-secondary);
        margin-top: 1.25rem;
        margin-bottom: 0.75rem;
    }

    .changelog-content ul {
        margin-bottom: 1rem;
    }

    .changelog-content li {
        margin-bottom: 0.35rem;
    }

    .changelog-content hr {
        margin: 2rem 0;
        border-color: var(--bs-border-color);
    }

    .changelog-content code {
        background-color: var(--bs-light);
        padding: 0.125rem 0.375rem;
        border-radius: 0.25rem;
        font-size: 0.875em;
    }

    .changelog-content a {
        color: var(--bs-link-color);
    }
</style>