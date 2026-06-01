<?php
/**
 * Read-only notice when a recommendation is linked to a bestowal.
 *
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Recommendation $recommendation
 */

use Awards\Model\Entity\Recommendation;
use Cake\ORM\TableRegistry;

if (!$recommendation instanceof Recommendation || !$recommendation->isLockedByBestowal()) {
    return;
}

$bestowalId = (int)$recommendation->bestowal_id;
$canViewBestowal = false;
if ($bestowalId > 0) {
    $bestowal = $recommendation->bestowal ?? TableRegistry::getTableLocator()->get('Awards.Bestowals')->get($bestowalId);
    $identity = $this->getRequest()->getAttribute('identity');
    $canViewBestowal = $identity !== null && $identity->checkCan('view', $bestowal);
}
?>
<div class="alert alert-warning mb-3" role="status">
    <i class="bi bi-lock-fill" aria-hidden="true"></i>
    <?= __('This recommendation is linked to a bestowal and is read-only here. Update it from the bestowal record.') ?>
    <?php if ($canViewBestowal) : ?>
        <?= $this->Html->link(
            __('View Bestowal'),
            [
                'plugin' => 'Awards',
                'controller' => 'Bestowals',
                'action' => 'view',
                $bestowalId,
            ],
            ['class' => 'alert-link ms-1', 'data-turbo-frame' => '_top']
        ) ?>
    <?php endif; ?>
</div>
<input type="hidden" value="1" data-recommendation-locked="1">
