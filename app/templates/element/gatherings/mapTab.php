<?php

/**
 * Map Tab Element for Gathering View
 *
 * Displays an interactive map of the gathering location with options to
 * open in external mapping services for directions.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\Member $user
 */

// Get Google Maps API key from app settings
$apiKey = $this->KMP->getAppSetting('GoogleMaps.ApiKey', '');
?>

<div class="related tab-pane fade m-3" id="nav-location" role="tabpanel" aria-labelledby="nav-location-tab"
    data-detail-tabs-target="tabContent" data-tab-order="6" style="order: 6;">

    <div class="row">
        <div class="col-12">
            <h4 class="mb-3">
                <kmp-icon name="map-pin"  aria-hidden="true"></kmp-icon> <?= __('Gathering Location') ?>
            </h4>

            <!-- Location Address Display -->
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?= __('Address') ?></h5>
                    <p class="card-text fs-5">
                        <kmp-icon name="map-pin" class="text-primary" aria-hidden="true"></kmp-icon>
                        <?= h($gathering->location) ?>
                    </p>
                </div>
            </div>

            <!-- Map Container -->
            <div data-controller="gathering-map" data-gathering-map-location-value="<?= h($gathering->location) ?>"
                data-gathering-map-gathering-name-value="<?= h($gathering->name) ?>"
                data-gathering-map-api-key-value="<?= h($apiKey) ?>" data-gathering-map-zoom-value="15"
                <?php if (!empty($gathering->latitude) && !empty($gathering->longitude)): ?>
                data-gathering-map-latitude-value="<?= h($gathering->latitude) ?>"
                data-gathering-map-longitude-value="<?= h($gathering->longitude) ?>" <?php endif; ?>>

                <!-- Error display area -->
                <div data-gathering-map-target="error" class="alert alert-warning" style="display: none;">
                </div>

                <!-- Map display area -->
                <div data-gathering-map-target="map" class="border rounded"
                    style="width: 100%; height: 500px; min-height: 400px;">
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><?= __('Loading map...') ?></span>
                            </div>
                            <p class="mt-2 text-muted"><?= __('Loading map...') ?></p>
                        </div>
                    </div>
                </div>

                <!-- Map Action Buttons -->
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-primary" data-action="click->gathering-map#getDirections"
                        title="<?= __('Get directions in Google Maps') ?>">
                        <kmp-icon name="sign-right"  aria-hidden="true"></kmp-icon> <?= __('Get Directions') ?>
                    </button>

                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <kmp-icon name="external-link"  aria-hidden="true"></kmp-icon> <?= __('Open In...') ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="#" data-action="click->gathering-map#openInGoogleMaps">
                                    <kmp-icon name="brand-google"  aria-hidden="true"></kmp-icon> <?= __('Google Maps') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-action="click->gathering-map#openInAppleMaps">
                                    <kmp-icon name="brand-apple"  aria-hidden="true"></kmp-icon> <?= __('Apple Maps') ?>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <button type="button" class="btn btn-outline-secondary"
                        data-controller="clipboard"
                        data-action="clipboard#copy"
                        data-clipboard-text-value="<?= h($gathering->location) ?>"
                        data-clipboard-success-message-value="<?= h(__('Address copied to clipboard!')) ?>"
                        title="<?= h(__('Copy address to clipboard')) ?>"><kmp-icon name="clipboard" aria-hidden="true"></kmp-icon>
                        <?= __(' Copy Address') ?>
                    </button>
                </div>

                <!-- Help text -->
                <div class="alert alert-info mt-3" role="alert">
                    <kmp-icon name="info-circle"  aria-hidden="true"></kmp-icon>
                    <strong><?= __('Tip:') ?></strong>
                    <?= __('Click "Get Directions" to open this location in your preferred mapping application and get turn-by-turn navigation.') ?>
                </div>
            </div>
        </div>
    </div>
</div>
