<?php

/**
 * Dataverse Custom Grid Loader
 *
 * Lightweight wrapper around a Turbo frame targeting a gridData endpoint.
 * Intended for pages that provide their own layout/toolbar while reusing the
 * dataverse table + state infrastructure.
 *
 * @var \App\View\AppView $this
 * @var string $frameId
 * @var string $dataUrl
 */

$queryParams = $this->getRequest()->getQueryParams();
$dataUrlWithParams = $dataUrl;
if (!empty($queryParams)) {
    $separator = strpos($dataUrl, '?') === false ? '?' : '&';
    $dataUrlWithParams .= $separator . http_build_query($queryParams);
}
?>
<turbo-frame id="<?= h($frameId) ?>" src="<?= h($dataUrlWithParams) ?>">
    <div class="text-center p-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading grid...</p>
    </div>
</turbo-frame>