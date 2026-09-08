<?php
/** Only an opaque actor/epoch binding; private offline content is never embedded here. */
$offlineContext = \App\Services\Security\OfflineIdentity::context($this->request);
$offlineBinding = $offlineContext === null ? null : array_intersect_key($offlineContext, array_flip(['owner', 'epoch', 'impersonating']));
?>
<meta name="kmp-offline-session" content="<?= h(json_encode($offlineBinding, JSON_THROW_ON_ERROR)) ?>">
