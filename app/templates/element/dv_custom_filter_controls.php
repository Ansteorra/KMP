<?php

/**
 * Dataverse Custom Filter Dropdown
 *
 * Skeleton markup for the filter dropdown. Intended to be paired with a
 * separate pills container wherever the host layout desires.
 *
 * @var \App\View\AppView $this
 * @var string $controllerName
 * @var string $dropdownId
 * @var string $searchLabel
 */

$controllerName = $controllerName ?? 'grid-view';
$dropdownId = $dropdownId ?? 'filterDropdown';
$searchLabel = $searchLabel ?? __('Search');
?>
<div class="dropdown">
    <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button"
        id="<?= h($dropdownId) ?>" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
        data-filter-button>
        <kmp-icon name="filter"  aria-hidden="true"></kmp-icon>
        <span><?= __('Filter') ?></span>
    </button>

    <div class="dropdown-menu dropdown-menu-end p-0"
        style="width: min(26.25rem, calc(100vw - 2rem));
            max-height: min(32.5rem, calc(100dvh - 2rem)); overflow-y: auto;"
        aria-labelledby="<?= h($dropdownId) ?>">
        <div class="border-bottom">
            <div class="px-3 py-2 bg-light">
                <strong class="text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <?= __('Search') ?>
                </strong>
            </div>
            <div class="px-3 py-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">
                        <kmp-icon name="search" aria-hidden="true"></kmp-icon>
                    </span>
                    <input type="text" class="form-control" placeholder="<?= __('Search...') ?>"
                        aria-label="<?= h($searchLabel) ?>"
                        data-<?= h($controllerName) ?>-target="searchInput"
                        data-action="keyup-><?= h($controllerName) ?>#handleSearchKeyup
                            keydown.enter-><?= h($controllerName) ?>#performSearch">
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
            <div class="d-flex flex-column flex-sm-row" style="max-height: 360px; overflow-y: auto;">
                <div class="list-group list-group-flush col-sm-5"
                    role="group" aria-label="<?= h(__('Filter fields')) ?>" data-filter-nav-container></div>
                <div class="flex-grow-1 border-start" data-filter-panels-container></div>
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
