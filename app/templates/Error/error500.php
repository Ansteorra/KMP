<?php

/**
 * @var \App\View\AppView $this
 * @var \Cake\Database\StatementInterface $error
 * @var string $message
 * @var string $url
 */

use Cake\Core\Configure;
use Cake\Error\Debugger;
use Intervention\Image\Gd\Shapes\EllipseShape;

if (Configure::read("debug")) :

    $this->layout = "dev_error";

    $this->assign("title", $message);
    $this->assign("templateName", "error500.php");

    echo $this->KMP->startBlock("file");
?>
    <?php if (!empty($error->queryString)) : ?>
        <p class="notice">
            <strong>SQL Query: </strong>
            <?= h($error->queryString) ?>
        </p>
    <?php endif; ?>
    <?php if (!empty($error->params)) : ?>
        <strong>SQL Query Params: </strong>
        <?php Debugger::dump($error->params); ?>
    <?php endif; ?>
    <?php if ($error instanceof Error) : ?>
        <?php $file = $error->getFile(); ?>
        <?php $line = $error->getLine(); ?>
        <strong>Error in: </strong>
        <?= $this->Html->link(
            sprintf("%s, line %s", Debugger::trimPath($file), $line),
            Debugger::editorUrl($file, $line),
        ) ?>
    <?php endif; ?>
<?php
    echo $this->element("auto_table_warning");
    $this->KMP->endBlock();

else :
?>
    <?php

    $this->extend("/layout/TwitterBootstrap/signin");

    ?>

    <div class="card">
        <?= $this->html->image("NoAccessKnight.png", [
            "class" => "card-img",
            "alt" => "No Access Knight",
        ]) ?>
        <div class="card-img-overlay">
            <h3 class="card-title text-start"><?= h($message) ?></h3>
        </div>
    </div>
    <?= $this->Html->link(
        "Return to your Profile",
        ['controller' => 'Members', 'action' => 'view', $user->id],
        ["class" => "btn btn-primary mt-3"]
    ) ?>
<?php endif; ?>