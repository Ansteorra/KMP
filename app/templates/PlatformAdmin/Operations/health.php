<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/** @var array<string, mixed> $health */
$this->assign('title', __('Platform Health'));
?>
<h1 class="h2 mb-3"><?= __('Platform Health') ?></h1>
<div class="card">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3"><?= __('State') ?></dt><dd class="col-sm-9"><?= h($health['state'] ?? '') ?></dd>
            <dt class="col-sm-3"><?= __('Message') ?></dt><dd class="col-sm-9"><?= h($health['message'] ?? '') ?></dd>
            <dt class="col-sm-3"><?= __('Connection') ?></dt><dd class="col-sm-9"><?= h($health['connection'] ?? '') ?></dd>
            <dt class="col-sm-3"><?= __('Retries') ?></dt><dd class="col-sm-9"><?= h((string)($health['retries'] ?? '0')) ?></dd>
        </dl>
    </div>
</div>
