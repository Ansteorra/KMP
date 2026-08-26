<?php

/**
 * @var \App\View\AppView $this
 * @var \Officers\Model\Entity\Office $office
 * @var array<int|string, string> $report_to_offices
 * @var array<int|string, string> $deputy_to_offices
 * @var array<int|string, string> $roles
 */

$controlAttrs = function (?string $target, int|string|null $selectedId, array $options): array {
    $attrs = [];
    if ($target !== null) {
        $attrs = [
            'data-office-form-target' => $target,
            'data-action' => 'ready->office-form#toggleIsDeputy',
        ];
    }
    if ($selectedId !== null && $selectedId !== '') {
        $attrs['data-ac-init-selection-value'] = json_encode([
            'value' => (string)$selectedId,
            'text' => (string)($options[$selectedId] ?? ''),
        ]);
    }

    return $attrs;
};
?>
<div data-office-form-target="reportsToBlock">
    <?= $this->KMP->comboBoxControl(
        $this->Form,
        'reports_to_office',
        'reports_to_id',
        $report_to_offices,
        __('Reports To'),
        false,
        false,
        $controlAttrs('reportsTo', $office->reports_to_id, $report_to_offices),
    ) ?>
</div>
<div data-office-form-target="deputyToBlock">
    <?= $this->KMP->comboBoxControl(
        $this->Form,
        'deputy_to_office',
        'deputy_to_id',
        $deputy_to_offices,
        __('Deputy To'),
        true,
        false,
        $controlAttrs('deputyTo', $office->deputy_to_id, $deputy_to_offices),
    ) ?>
</div>
<?= $this->KMP->comboBoxControl(
    $this->Form,
    'grants_role',
    'grants_role_id',
    $roles,
    __('Grants Role'),
    false,
    false,
    $controlAttrs(null, $office->grants_role_id, $roles),
) ?>
