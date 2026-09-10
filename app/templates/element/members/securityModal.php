<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
?>
<div class="modal fade" id="securityModal" tabindex="-1" aria-labelledby="securityModalTitle"
    data-controller="security-dialog" data-security-dialog-url-value="/members/security">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="securityModalTitle">Security</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close Security"></button>
            </div>
            <div class="modal-body">
                <turbo-frame id="security-settings" data-security-dialog-target="frame"
                    data-action="turbo:frame-load->security-dialog#loaded
                        turbo:fetch-request-error->security-dialog#failed turbo:frame-missing->security-dialog#failed">
                    <p role="status">Loading Security…</p>
                </turbo-frame>
            </div>
        </div>
    </div>
</div>
