<?php /** @var \App\View\AppView $this */ ?>
<div class="modal fade" id="passkeyModal" tabindex="-1" aria-labelledby="passkeyModalTitle"
    data-controller="security-dialog" data-security-dialog-url-value="/passkeys">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="passkeyModalTitle">Sign in with a passkey</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close passkey settings"></button>
            </div>
            <div class="modal-body">
                <turbo-frame id="passkey-settings" data-security-dialog-target="frame"
                    data-action="turbo:frame-load->security-dialog#loaded turbo:fetch-request-error->security-dialog#failed turbo:frame-missing->security-dialog#failed">
                    <p role="status">Loading your passkeys…</p>
                </turbo-frame>
            </div>
        </div>
    </div>
</div>
