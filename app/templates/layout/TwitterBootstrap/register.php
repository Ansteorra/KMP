<?php

/**
 * @var \Cake\View\View $this
 */

$this->Html->css("BootstrapUI.signin", ["block" => true]);
$this->prepend(
    "tb_body_attrs",
    ' class="' .
        implode(" ", [
            h($this->request->getParam("controller")),
            h($this->request->getParam("action")),
        ]) .
        '" ',
);
echo $this->KMP->startBlock("tb_body_start");
/**
 * Default `flash` block.
 */
if (!$this->fetch("tb_flash")) {
    echo $this->KMP->startBlock("tb_flash");
    echo $this->Flash->render();
    $this->KMP->endBlock();
}
?>

<body <?= $this->fetch("tb_body_attrs") ?>>
    <?php
    $this->KMP->endBlock();

    echo $this->KMP->startBlock("tb_body_end");
    echo "</body>";
    $this->KMP->endBlock();

    echo $this->KMP->startBlock("tb_footer");
    echo " ";
    $this->KMP->endBlock();

    echo $this->element('copyrightFooter', []);

    echo $this->fetch("content");
