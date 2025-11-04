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
    data-detail-tabs-target="tabContent" data-tab-order="20" style="order: 20;">

    <div class="row">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="bi bi-geo-alt-fill"></i> <?= __('Gathering Location') ?>
            </h4>

            <!-- Location Address Display -->
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?= __('Address') ?></h5>
                    <p class="card-text fs-5">
                        <i class="bi bi-pin-map-fill text-primary"></i>
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
                        <i class="bi bi-signpost-2-fill"></i> <?= __('Get Directions') ?>
                    </button>

                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-box-arrow-up-right"></i> <?= __('Open In...') ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="#" data-action="click->gathering-map#openInGoogleMaps">
                                    <i class="bi bi-google"></i> <?= __('Google Maps') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-action="click->gathering-map#openInAppleMaps">
                                    <i class="bi bi-apple"></i> <?= __('Apple Maps') ?>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <button type="button" class="btn btn-outline-secondary"
                        onclick='navigator.clipboard.writeText(<?= json_encode($gathering->location, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>).then(() => alert(<?= json_encode(__("Address copied to clipboard!")) ?>))'
                        title="<?= h(__('Copy address to clipboard')) ?>">
                        <i class="bi bi-clipboard"></i> <?= __('Copy Address') ?>
                    </button>
                </div>

                <!-- Help text -->
                <div class="alert alert-info mt-3" role="alert">
                    <i class="bi bi-info-circle-fill"></i>
                    <strong><?= __('Tip:') ?></strong>
                    <?= __('Click "Get Directions" to open this location in your preferred mapping application and get turn-by-turn navigation.') ?>
                </div>
            </div>
        </div>
    </div>
</div>