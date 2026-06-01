<?php

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html lang="<?= h(\Cake\Core\Configure::read('App.language') ?: 'en') ?>">

<head>
    <?= $this->Html->charset() ?>
    <title>
        <?= $this->fetch("title") ?>
    </title>
    <?= $this->Html->meta("icon") ?>

    <?= $this->Html->css(["normalize.min", "milligram.min", "fonts", "cake"]) ?>

    <?= $this->fetch("meta") ?>
    <?= $this->fetch("css") ?>
    <?= $this->fetch("script") ?>
</head>

<body>
    <div class="error-container">
        <?= $this->Flash->render() ?>
        <main id="main-content" tabindex="-1">
            <?= $this->fetch("content") ?>
        </main>
        <button type="button" data-controller="history-back" data-action="history-back#go"><?= __("Back") ?></button>
    </div>
</body>

</html>
