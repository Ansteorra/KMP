<?php
declare(strict_types=1);

use App\Model\Entity\Member;

foreach ($query as $sca_member) :
    $disabled = !$sca_member->warrantable && $office->requires_warrant;
    $extraClasses = $disabled ? 'list-group-item-secondary' : '';
    $status = match ($sca_member->status) {
        Member::STATUS_VERIFIED_MEMBERSHIP,
        Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
        Member::STATUS_VERIFIED_MINOR => __('Membership verified'),
        default => __('Membership not verified'),
    };
    $label = __(
        '{0} — {1} — {2} — Account #{3}',
        $sca_member->sca_name,
        $status,
        $sca_member->branch->name ?? __('No group'),
        $sca_member->id,
    );
    ?>
<li class="list-group-item <?= h($extraClasses) ?>" role="option"
    data-ac-value="<?= h((string)$sca_member->id) ?>" data-ac-label="<?= h($label) ?>"
    aria-disabled="<?= $disabled ? 'true' : 'false' ?>">
    <?= h($label) ?>
    <span class="d-block small">
        <?= $sca_member->warrantable ? __('Eligible for warrant') : __('Not eligible for warrant') ?>
    </span>
</li>
<?php endforeach; ?>
