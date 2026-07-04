<?php

/**
 * @var \Cake\View\View $this
 */

echo $this->KMP->startBlock("css");
echo $this->Vite->css('signin');
$this->KMP->endBlock();

$this->prepend(
    "tb_body_attrs",
    ' class="text-center ' .
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
    echo '<div id="flash-messages">';
    echo $this->Flash->render();
    echo '</div>';
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

    $this->KMP->endBlock();

    echo $this->element('copyrightFooter', []);

    echo $this->fetch("content");
    echo $this->fetch("modals");