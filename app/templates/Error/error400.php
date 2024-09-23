<?php

use Cake\Core\Configure;
use Cake\Error\Debugger;
use Cake\Routing\Asset;

if (Configure::read("debug")) :

    $this->layout = "dev_error";

    $this->assign("title", $message);
    $this->assign("templateName", "error400.php");

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
<?php
    echo $this->element("auto_table_warning");
    $this->KMP->endBlock();

else : ?>
<?php

    $this->extend("/layout/TwitterBootstrap/signin");

    ?>

<div class="card" data-controller="delay-forward" data-delay-forward-delay-ms-value="5000"
    data-delay-forward-url-value="<?= Asset::Url("/", ['fullBase' => true]) ?>">
    <?= $this->html->image("NoAccessKnight.png", [
            "class" => "card-img",
            "alt" => "No Access Knight",
        ]) ?>
    <div class="card-img-overlay text-start pt-1">
        <h4 class="card-title text-start mb-0">Page Not Found</h4>
        <span class="card-title ">Redirecting you to your profile...</span>
    </div>
</div>
<?php
endif;
?>