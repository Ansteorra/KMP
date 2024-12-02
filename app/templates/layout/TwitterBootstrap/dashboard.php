<?php

/**
 * @var \Cake\View\View $this
 */

use Cake\Core\Configure;
use App\Model\Table\MembersTable;


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
$headerLinks = $this->KMP->getAppSettingsStartWith("KMP.HeaderLink.");
$url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]" . "/keepalive";
?>

<body <?= $this->fetch("tb_body_attrs") ?> data-controller="session-extender"
    data-session-extender-url-value="<?= $url ?>">

    <script>
    extendSesh(url)
    </script>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <div class="navbar-brand col-md-3 col-lg-2 me-0 px-3">
            <?= $this->Html->image($this->KMP->getAppSetting("KMP.BannerLogo"), [
                "alt" => "Logo",
                "height" => "24",
                "class" => "d-inline-block mb-1",
            ]) ?>
            <span class="fs-5"><?= h($this->KMP->getAppSetting("KMP.ShortSiteTitle")) ?></span>
        </div>
        <ul class="navbar-nav flex-row px-3">
            <?php foreach ($headerLinks as $key => $value) :
                $key = str_replace("KMP.HeaderLink.", "", $key);
                $keys = explode(".", $key);
                $key = $keys[0];
                if (count($keys) > 1) {
                    $key = $keys[1] == "no-label" ? "" : $keys[0];
                }
                $parts = explode("|", $value);
                $url = $parts[0];
                $css = "";
                if (count($parts) > 1) {
                    $css = $parts[1];
                }
            ?>
            <li class="nav-item text-nowrap mx-1">
                <a class="btn btn-outline-secondary <?= $css ?>" href="<?= $url ?>"><?= $key ?></a>
            </li>
            <?php endforeach; ?>
            <li class="nav-item text-nowrap mx-1">
                <?= $this->Html->link(
                    __("Sign out"),
                    ["controller" => "Members", "action" => "logout", 'plugin' => null],
                    ["class" => "btn btn-outline-secondary"],
                ) ?>
            </li>
            <li class="nav-item text-nowrap mx-1">
                <button class="btn btn-outline-secondary d-md-none collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </li>
        </ul>
    </header>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar pt-5 collapse"
                style="overflow-y: auto">
                <div class="position-sticky pt-3">
                    <?= $this->cell("Navigation") ?>
                </div>
            </nav>

            <main role="main" class="col-md-9 ms-sm-auto col-lg-10 px-md-4 my-3">
                <?php
                $this->KMP->endBlock();
                echo $this->KMP->startBlock("tb_body_end");
                ?>
            </main>
        </div>
    </div>
</body>
<?php
$this->KMP->endBlock();


/** Default `flash` block. */
if (!$this->fetch("tb_flash")) {
    echo $this->KMP->startBlock("tb_flash");
    if (isset($this->Flash)) {
        echo $this->Flash->render();
    }
    $this->KMP->endBlock();
}
echo $this->fetch("content");
echo $this->element('copyrightFooter', []);