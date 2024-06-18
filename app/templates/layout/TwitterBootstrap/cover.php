<?php

/**
 * @var \Cake\View\View $this
 */

use Cake\Core\Configure;

echo $this->KMP->startBlock("html");
printf('<html lang="%s" class="h-100">', Configure::read("App.language"));
$this->KMP->endBlock();

$this->Html->css("BootstrapUI.cover", ["block" => true]);

$this->prepend(
    "tb_body_attrs",
    'class="d-flex h-100 text-center text-white bg-dark ' .
        implode(" ", [
            h($this->request->getParam("controller")),
            h($this->request->getParam("action")),
        ]) .
        '" ',
);

echo $this->KMP->startBlock("tb_body_start");
?>

<body <?= $this->fetch("tb_body_attrs") ?>>
    <div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">
        <header class="mb-auto">
            <div>
                <h3 class="float-md-start mb-0"><?= Configure::read(
                                                    "App.title",
                                                ) ?></h3>
                <nav class="nav nav-masthead justify-content-center float-md-end">
                    <?= $this->fetch("tb_topnav") ?>
                </nav>
            </div>
        </header>
        <main role="main" class="px-3">
            <?= $this->fetch("content") ?>
        </main>
        <?php $this->KMP->endBlock(); ?>

        <?php echo $this->KMP->startBlock("tb_body_end"); ?>
    </div>
</body>
<?php $this->KMP->endBlock(); ?>

<?php
echo $this->element('copyrightFooter', []);

echo $this->fetch("modals");
?>