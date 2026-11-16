<?php
declare(strict_types=1);

/** @var \App\View\AppView $this
 * @var bool $value
 */
echo $this->Icon->render($value ? 'circle-check' : 'circle-x', [
    'class' => $value ? 'text-success' : 'text-danger',
    'label' => $value ? __('Yes') : __('No'),
]);
