<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var list<array<string, mixed>> $releases
 * @var array<string, mixed> $compatibility
 */
$this->assign('title', __('Release Compatibility'));
?>
<h1 class="h2 mb-3"><?= __('Release Compatibility') ?></h1>
<section class="card mb-4" aria-labelledby="manifest-heading">
    <div class="card-body">
        <h2 id="manifest-heading" class="h5"><?= __('Current Manifest Check') ?></h2>
        <?php if (!empty($compatibility['available'])) : ?>
            <p><?= __('Version {0} supports tenant schemas {1} through {2}.', h($compatibility['appVersion']), h($compatibility['minTenantSchema']), h($compatibility['maxTenantSchema'])) ?></p>
            <p><?= __('Active tenants checked: {0}; incompatible tenants: {1}.', h((string)$compatibility['activeTenantCount']), h((string)$compatibility['incompatibleCount'])) ?></p>
            <?php if (!empty($compatibility['errors'])) : ?>
                <ul>
                    <?php foreach ((array)$compatibility['errors'] as $error) :
                        ?><li><?= h((string)$error) ?></li><?php
                    endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php else : ?>
            <p class="text-muted mb-0"><?= h($compatibility['message'] ?? __('Unavailable')) ?></p>
        <?php endif; ?>
    </div>
</section>
<section class="card" aria-labelledby="releases-heading">
    <div class="card-body">
        <h2 id="releases-heading" class="h5"><?= __('Recorded Releases') ?></h2>
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr><th><?= __('Image Tag') ?></th><th><?= __('Git SHA') ?></th><th><?= __('Min Schema') ?></th><th><?= __('Max Schema') ?></th><th><?= __('Status') ?></th><th><?= __('Created') ?></th></tr></thead>
            <tbody>
            <?php foreach ($releases as $release) : ?>
                <tr><td><?= h($release['image_tag'] ?? '') ?></td><td><?= h($release['git_sha'] ?? '') ?></td><td><?= h($release['min_schema'] ?? '') ?></td><td><?= h($release['max_schema'] ?? '') ?></td><td><?= h($release['status'] ?? '') ?></td><td><?= h($release['created_at'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if ($releases === []) :
                ?><tr><td colspan="6" class="text-muted"><?= __('No release records found.') ?></td></tr><?php
            endif; ?>
            </tbody>
        </table></div>
    </div>
</section>
