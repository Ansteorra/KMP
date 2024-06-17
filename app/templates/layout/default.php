<?php

/**
 * @var \Cake\View\View $this
 */

use Cake\Core\Configure;

/**
 * Default `html` block.
 */
if (!$this->fetch("html")) {
    echo $this->KMP->startBlock("html");
    if (Configure::check("App.language")) {
        printf('<html lang="%s">', Configure::read("App.language"));
    } else {
        echo "<html lang='en'>";
    }
    $this->KMP->endBlock();
}

/**
 * Default `title` block.
 */
if (!$this->fetch("title")) {
    echo $this->KMP->startBlock("title");
    echo Configure::read("App.title");
    $this->KMP->endBlock();
}

/**
 * Default `footer` block.
 */
if (!$this->fetch("tb_footer")) {
    echo $this->KMP->startBlock("tb_footer");

    $this->KMP->endBlock();
}

/**
 * Default `body` block.
 */
$this->prepend(
    "tb_body_attrs",
    ' class="' .
        implode(" ", [
            h($this->request->getParam("controller")),
            h($this->request->getParam("action")),
        ]) .
        '" ',
);
if (!$this->fetch("tb_body_start")) {
    echo $this->KMP->startBlock("tb_body_start");
    echo "<body" . $this->fetch("tb_body_attrs") . ">";
    $this->KMP->endBlock();
}
/**
 * Default `flash` block.
 */
if (!$this->fetch("tb_flash")) {
    echo $this->KMP->startBlock("tb_flash");
    echo $this->Flash->render();
    $this->KMP->endBlock();
}
if (!$this->fetch("tb_body_end")) {
    echo $this->KMP->startBlock("tb_body_end");
    echo "</body>";
    $this->KMP->endBlock();
}

/**
 * Prepend `meta` block with `author` and `favicon`.
 */
if (Configure::check("App.author")) {
    $this->prepend(
        "meta",
        $this->Html->meta("author", null, [
            "name" => "author",
            "content" => Configure::read("App.author"),
        ]),
    );
}
$this->prepend(
    "meta",
    $this->Html->meta("favicon.ico", "/favicon.ico", ["type" => "icon"]),
);

/**
 * Prepend `css` block with Bootstrap stylesheets
 * Change to bootstrap.min to use the compressed version
 */
$this->prepend("css", $this->Html->css(["BootstrapUI.bootstrap.min"]));
$this->prepend(
    "css",
    $this->Html->css([
        "BootstrapUI./font/bootstrap-icons",
        "BootstrapUI./font/bootstrap-icon-sizes",
    ]),
);

/**
 * Prepend `script` block with Popper and Bootstrap scripts
 * Change popper.min and bootstrap.min to use the compressed version
 */
$this->prepend(
    "script",
    $this->Html->script([
        "https://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.7.1.min.js",
        "BootstrapUI.popper.min",
        "BootstrapUI.bootstrap.min",
        "app/utilities.js",
        "app/autocomplete.js",
    ]),
);
?>
<!doctype html>
<?= $this->fetch("html") ?>

<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= h($this->fetch("title")) ?></title>
    <?= $this->fetch("meta") ?>
    <?= $this->fetch("css") ?>
    <?= $this->fetch("manifest") ?>
</head>

<?php
echo $this->fetch("tb_body_start");
echo $this->fetch("tb_flash");
echo $this->fetch("content");
echo $this->fetch("tb_footer");
echo $this->fetch("tb_body_end");
echo $this->fetch("script");
?>

</html>