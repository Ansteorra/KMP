<?php
declare(strict_types=1);

/**
 * Saved grid-view tabs shared by standard grids and custom renderers.
 *
 * The grid-view Stimulus controller populates the available and active views.
 *
 * @var \App\View\AppView $this
 * @var array $gridState Complete grid state object
 * @var string|null $controllerName Stimulus controller identifier
 * @var string|null $ariaLabel Accessible label for the view tabs
 */

$controllerName = $controllerName ?? 'grid-view';
$ariaLabel = $ariaLabel ?? __('Saved views');
$canAddViews = $gridState['config']['canAddViews'] ?? true;
$showAllTab = $gridState['config']['showAllTab'] ?? true;
$showViewTabs = $gridState['config']['showViewTabs'] ?? true;
?>
<?php if ($showViewTabs) : ?>
    <div class="mb-3" data-saved-views-region>
        <ul class="nav nav-tabs" role="tablist"
            aria-label="<?= h($ariaLabel) ?>" data-view-tabs-container>
            <?php if (!$showAllTab) : ?>
                <li class="d-none" data-no-all-tab></li>
            <?php endif; ?>

            <?php if ($canAddViews) : ?>
                <li class="nav-item ms-auto" role="presentation">
                    <button type="button" class="nav-link"
                        data-action="click-><?= h($controllerName) ?>#saveView"
                        title="<?= h(__('Save current settings as a new view')) ?>"
                        aria-label="<?= h(__('Save current settings as a new view')) ?>">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span class="visually-hidden"><?= __('Save view') ?></span>
                    </button>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>
