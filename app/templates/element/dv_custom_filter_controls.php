<?php

/**
 * Dataverse Custom Filter Dropdown
 *
 * Skeleton markup for the filter dropdown. Intended to be paired with a
 * separate pills container wherever the host layout desires.
 *
 * @var \App\View\AppView $this
 * @var string $controllerName
 */

$controllerName = $controllerName ?? 'grid-view';
?>
<div class="dropdown">
    <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button"
        id="filterDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
        data-filter-button>
        <i class="bi bi-funnel"></i>
        <span><?= __('Filter') ?></span>
    </button>

    <div class="dropdown-menu dropdown-menu-end p-0"
        style="min-width: 420px; max-height: 520px; overflow: hidden;" aria-labelledby="filterDropdown">
        <div class="border-bottom">
            <div class="px-3 py-2 bg-light">
                <strong class="text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <?= __('Search') ?>
                </strong>
            </div>
            <div class="px-3 py-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="<?= __('Search...') ?>"
                        data-<?= h($controllerName) ?>-target="searchInput"
                        data-action="keyup-><?= h($controllerName) ?>#handleSearchKeyup keydown.enter-><?= h($controllerName) ?>#performSearch">
                </div>
                <small class="text-muted d-block mt-1">
                    <?= __('Use search or filters to narrow the results.') ?>
                </small>
            </div>
        </div>

        <div class="border-bottom">
            <div class="px-3 py-2 bg-light d-flex justify-content-between align-items-center">
                <strong class="text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <?= __('Filters') ?>
                </strong>
                <small class="text-muted"><?= __('Select a filter to see options') ?></small>
            </div>
            <div class="d-flex" style="max-height: 360px; overflow-y: auto;">
                <div class="list-group list-group-flush flex-shrink-0" style="min-width: 190px;"
                    role="tablist" data-filter-nav-container></div>
                <div class="flex-grow-1 border-start" style="min-width: 230px;" data-filter-panels-container></div>
            </div>
        </div>

        <div class="px-3 py-2 bg-light border-top" data-clear-filters-container style="display: none;">
            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0"
                data-action="click-><?= h($controllerName) ?>#clearAllFilters">
                <?= __('Clear all filters') ?>
            </button>
        </div>
    </div>
</div>
