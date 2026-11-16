<?php
declare(strict_types=1);

/** Shared candidate header; actions and example capability decisions are supplied by callers. */
$actions = $actions ?? [];
$badges = $badges ?? [];
?>
<header class="ui-page-header">
    <div class="ui-context-line">
        <?php if (!empty($back)) : ?>
            <span class="ui-back"><?= $this->element('backButton', ['text' => __('Back')]) ?></span>
        <?php endif; ?>
        <?php if (!empty($eyebrow)) :
            ?><span class="ui-eyebrow"><?= h($eyebrow) ?></span><?php
        endif; ?>
        <?php foreach ($badges as $badge) : ?>
            <span class="ui-status ui-status-<?= h($badge['tone'] ?? 'neutral') ?>"><?= h($badge['label']) ?></span>
        <?php endforeach; ?>
    </div>
    <div class="ui-heading-row">
        <div class="ui-heading-copy">
            <h1><?= h($title) ?></h1>
            <?php if (!empty($description)) :
                ?><p><?= h($description) ?></p><?php
            endif; ?>
        </div>
        <?php if ($actions) : ?>
            <div class="ui-actions">
                <?php foreach ($actions as $action) : ?>
                    <?php
                    $buttonClass = 'btn ' . (!empty($action['primary']) ? 'btn-primary' : 'btn-outline-secondary');
                    $message = $action['message'] ?? __('This action is available in the preview.');
                    ?>
                    <?php if (!empty($action['href'])) : ?>
                        <a class="<?= h($buttonClass) ?>" href="<?= h($action['href']) ?>">
                            <?php if (!empty($action['icon'])) :
                                ?><?= $this->Icon->render($action['icon']) ?><?php
                            endif; ?>
                            <?= h($action['label']) ?>
                        </a>
                    <?php else : ?>
                        <button type="button" class="<?= h($buttonClass) ?>"
                            <?php if (!empty($action['modal'])) : ?>
                                data-bs-toggle="modal" data-bs-target="#<?= h($action['modal']) ?>"
                            <?php else : ?>
                                data-action="ui-patterns#<?= h($action['action'] ?? 'notice') ?>"
                                data-message="<?= h($message) ?>"
                            <?php endif; ?>
                        >
                            <?php if (!empty($action['icon'])) :
                                ?><?= $this->Icon->render($action['icon']) ?><?php
                            endif; ?>
                            <?= h($action['label']) ?>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</header>
