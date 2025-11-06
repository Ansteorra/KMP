<?php

/**
 * Waiver Attestation Modal
 * 
 * Modal for attesting that a waiver is not needed for a specific activity/waiver type combination.
 * The modal displays configurable reasons from the waiver type.
 * 
 * This modal is controlled by the waiver-attestation-controller.js Stimulus controller.
 */
?>

<!-- Waiver Attestation Modal -->
<div class="modal fade"
    id="waiverAttestationModal"
    tabindex="-1"
    aria-labelledby="waiverAttestationModalLabel"
    aria-hidden="true"
    data-waivers-waiver-attestation-target="modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="waiverAttestationModalLabel">
                    <i class="bi bi-shield-check"></i> <?= __('Attest Waiver Not Needed') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="lead"><?= __('Why was this waiver not needed?') ?></p>
                <p class="text-muted">
                    <?= __('Select the reason why this waiver requirement does not apply to this activity.') ?>
                </p>

                <!-- Reason Selection (populated dynamically) -->
                <div data-waivers-waiver-attestation-target="reasonList" class="mb-3">
                    <!-- Radio buttons will be dynamically inserted here -->
                </div>

                <!-- Optional Notes -->
                <div class="mb-3">
                    <label for="attestationNotes" class="form-label"><?= __('Additional Notes (Optional)') ?></label>
                    <textarea class="form-control"
                        id="attestationNotes"
                        rows="3"
                        data-waivers-waiver-attestation-target="notes"
                        placeholder="<?= __('Any additional context or explanation...') ?>"></textarea>
                </div>

                <!-- Error Display -->
                <div class="alert alert-danger d-none"
                    data-waivers-waiver-attestation-target="error"
                    role="alert">
                </div>

                <!-- Success Display -->
                <div class="alert alert-success d-none"
                    data-waivers-waiver-attestation-target="success"
                    role="alert">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= __('Cancel') ?>
                </button>
                <button type="button"
                    class="btn btn-primary"
                    data-waivers-waiver-attestation-target="submitBtn"
                    data-action="click->waivers-waiver-attestation#submitAttestation">
                    <i class="bi bi-shield-check"></i> <?= __('Submit Attestation') ?>
                </button>
            </div>
        </div>
    </div>
</div>