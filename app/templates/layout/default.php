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
        printf('<html data-turbo="false" lang="%s">', Configure::read("App.language"));
    } else {
        echo "<html data-turbo='false' lang='en' >";
    }
    $this->KMP->endBlock();
}

/**
 * Default `title` block.
 */
if (!$this->fetch("title")) {
    echo $this->KMP->startBlock("title");
    echo $this->KMP->getAppSetting("KMP.ShortSiteTitle");
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

echo $this->KMP->startBlock("css");
echo $this->AssetMix->css('app');
$this->KMP->endBlock();

/**
 * Prepend `script` block with Popper and Bootstrap scripts
 * Change popper.min and bootstrap.min to use the compressed version
 */
echo $this->KMP->startBlock("topscript");
echo $this->AssetMix->script('manifest');
echo $this->AssetMix->script('core');
echo $this->AssetMix->script('controllers');
echo $this->AssetMix->script('index');
$this->KMP->endBlock();

?>
<!doctype html>
<?= $this->fetch("html") ?>

<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= h($this->fetch("title")) ?></title>
    <script>
    window.urlRoot = "<?= $this->Url->build("/") ?>";
    </script>
    <meta name="turbo-prefetch" content="false">
    <?= $this->fetch("meta") ?>
    <?php $css = $this->fetch("css"); ?>
    <?= $this->Html->meta('csrf-token', $this->request->getAttribute('csrfToken')) ?>
    <?= $this->fetch("css") ?>
    <?= $this->fetch("manifest") ?>
    <?= $this->fetch("topscript") ?>
</head>

<?php
echo $this->fetch("tb_body_start");
echo $this->fetch("tb_flash");
echo $this->fetch("content");
echo $this->fetch("tb_footer");
echo $this->fetch("modals");
echo $this->fetch("tb_body_end");
echo $this->fetch("script");
//echo $this->AssetCompress->script('app-combined');

?>

</html>