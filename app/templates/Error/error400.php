<?php

use Cake\Core\Configure;
use Cake\Error\Debugger;

if (Configure::read("debug")) :

    $this->layout = "dev_error";

    $this->assign("title", $message);
    $this->assign("templateName", "error400.php");

    $this->start("file");
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
<?php
    echo $this->element("auto_table_warning");
    $this->end();

else : ?>
<?php

    $this->extend("/layout/TwitterBootstrap/signin");

    ?>

<div class="card">
    <?= $this->html->image("NoAccessKnight.png", [
            "class" => "card-img",
            "alt" => "No Access Knight",
        ]) ?>
    <div class="card-img-overlay">
        <h1 class="card-title text-start">
            <?= __("Page not found") ?>
        </h1>
    </div>
</div>
<?php
endif;
?>