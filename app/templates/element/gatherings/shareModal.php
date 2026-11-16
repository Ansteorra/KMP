<?php
declare(strict_types=1);
?>
<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true"
    data-controller="qrcode" data-qrcode-url-value="<?= h($publicLandingUrl) ?>"
    data-qrcode-modal-id-value="qrCodeModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrCodeModalLabel">
                    <kmp-icon name="qrcode"  aria-hidden="true"></kmp-icon> Share Event QR Code
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-3">Scan this QR code to view the event landing page</p>
                <div data-qrcode-target="canvas" class="d-inline-block p-3 bg-white border rounded"></div>
                <div class="mt-3">
                    <label class="text-muted d-block mb-2" for="publicLandingUrlInput">
                        <small>Or copy the link:</small>
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="<?= h($publicLandingUrl) ?>" readonly
                            id="publicLandingUrlInput">
                        <button class="btn btn-outline-secondary" type="button"
                            data-controller="clipboard" data-action="clipboard#copy"
                            data-clipboard-text-value="<?= h($publicLandingUrl) ?>"
                            data-clipboard-success-message-value="<?= h(__('Link copied to clipboard!')) ?>">
                            <kmp-icon name="clipboard" aria-hidden="true"></kmp-icon>
                            Copy
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="<?= $publicLandingUrl ?>" target="_blank" class="btn btn-primary">
                    <kmp-icon name="external-link"  aria-hidden="true"></kmp-icon> Open Public Page
                </a>
            </div>
        </div>
    </div>
</div>
