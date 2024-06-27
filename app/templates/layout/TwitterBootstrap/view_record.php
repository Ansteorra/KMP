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
?>

<body <?= $this->fetch("tb_body_attrs") ?>>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <div class="navbar-brand col-md-3 col-lg-2 me-0 px-3">
            <?= $this->Html->image($this->KMP->getAppSetting("KMP.BannerLogo", "badge.png"), [
                "alt" => "Logo",
                "height" => "24",
                "class" => "d-inline-block mb-1",
            ]) ?>
            <span class="fs-5"><?= h($this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP")) ?></span>
        </div>
        <span class="w-100"></span>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <?= $this->Html->link(
                    __("Sign out"),
                    ["controller" => "Members", "action" => "logout", 'plugin' => null],
                    ["class" => "nav-link"],
                ) ?>
            </li>
            <li class="nav-item text-nowrap">
                <button class="navbar-toggler position-absolute d-md-none collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu"
                    aria-expanded="false" aria-label="Toggle navigation">
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
                /** Default `flash` block. */
                if (!$this->fetch("tb_flash")) {
                    echo $this->KMP->startBlock("tb_flash");
                    if (isset($this->Flash)) {
                        echo $this->Flash->render();
                    }
                    $this->KMP->endBlock();
                } ?>
                <div class="row align-items-start">
                    <div class="col">
                        <h3>
                            <a href="#" onclick="window.history.back();" class="bi bi-arrow-left-circle"></a>
                            <?php echo $this->fetch("pageTitle") ?>
                        </h3>
                    </div>
                    <div class="col text-end">
                        <?php echo $this->fetch("recordActions") ?>
                    </div>
                </div>
                <?php
                $this->KMP->endBlock(); ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tbody>
                            <?= $this->fetch("recordDetails") ?>
                            <?= $this->element('pluginDetailBodies', [
                                'pluginViewCells' => $pluginViewCells,
                                'id' => $recordId,
                                'model' => $recordModel,
                            ]) ?>
                        </tbody>
                    </table>
                </div>
                <nav>
                    <div class="nav nav-tabs" id="nav-tabButtons" role="tablist">
                        <?= $this->element('pluginTabButtons', [
                            'pluginViewCells' => $pluginViewCells,
                            'id' => $recordId,
                            'model' => $recordModel,
                            'activateFirst' => false
                        ]) ?>
                        <?= $this->fetch("tabButtons") ?>
                    </div>
                </nav>
                <div class="tab-content" id="nav-tabContent">
                    <?= $this->element('pluginTabBodies', [
                        'pluginViewCells' => $pluginViewCells,
                        'id' => $recordId,
                        'model' => $recordModel,
                        'activateFirst' => false
                    ]) ?>
                    <?= $this->fetch("tabContent") ?>
                </div>
                <?= $this->KMP->startBlock("tb_body_end"); ?>
            </main>
        </div>
    </div>
</body>
<?php
$this->KMP->endBlock();
echo $this->fetch("content");
echo $this->element('copyrightFooter', []);