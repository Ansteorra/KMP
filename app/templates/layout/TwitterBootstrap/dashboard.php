<?php
/**
 * @var \Cake\View\View $this
 */
use Cake\Core\Configure;
$user = $this->request->getAttribute('identity');

$this->Html->css('BootstrapUI.dashboard', ['block' => true]);
$this->prepend(
    'tb_body_attrs',
    ' class="' .
        implode(' ', [h($this->request->getParam('controller')), h($this->request->getParam('action'))]) .
        '" '
);
$this->start('tb_body_start');
?>
<body <?= $this->fetch('tb_body_attrs') ?>>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <div class="navbar-brand col-md-3 col-lg-2 me-0 px-3">
            <?= $this->Html->image(Configure::read('App.appGraphic'),['alt'=>"Logo",'height'=>"24",'class'=>"d-inline-block mb-1"]) ?>
            <span class="fs-5"><?= h(Configure::read('App.title'))?></span>
        </div>
        <span class="w-100"></span>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
            <?= $this->Html->link(__('Sign out'), ['controller' => 'Members', 'action' => 'logout'], ['class' => 'nav-link']) ?>
            </li>
            <li class="nav-item text-nowrap">
            <button
            class="navbar-toggler position-absolute d-md-none collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#sidebarMenu"
            aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>
            </li>
        </ul>
    </header>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" style="">
                <div class="position-sticky pt-3">
                <nav class="nav flex-column nav-underline mx-2">
                    <?php
                        if($user->can('index', 'Roles')) {
                            $activeclass = $this->request->getParam('controller') === 'Roles' ? 'active' : '';
                            echo $this->Html->link(__(' Roles'), ['controller' => 'Roles', 'action' => 'index'], ['class' => 'nav-link fs-5 bi bi-universal-access-circle pb-0 '.$activeclass]);
                            if($this->request->getParam('controller') === 'Roles' && $user->can('add', 'Roles')) {
                                echo $this->Html->link(__(' New Role'), ['controller' => 'Roles', 'action' => 'add'], ['class' => 'nav-link bi bi-plus ms-3 fs-6 pt-0']);
                            }
                        }
                        if($user->can('index', 'Permissions')) {
                            $activeclass = $this->request->getParam('controller') === 'Permissions' ? 'active' : '';
                            echo $this->Html->link(__(' Permissions'), ['controller' => 'Permissions', 'action' => 'index'], ['class' => 'nav-link fs-5 bi bi-clipboard-check pb-0 '.$activeclass]);
                            if($this->request->getParam('controller') === 'Permissions' && $user->can('add', 'Roles')) {
                                echo $this->Html->link(__(' New Permission'), ['controller' => 'Permissions', 'action' => 'add'], ['class' => 'nav-link bi bi-plus ms-3 pt-0 fs-6']);
                            }
                        }
                        ?>
                </nav>
                </div>
            </nav>

            <main role="main" class="col-md-9 ms-sm-auto col-lg-10 px-md-4 my-3">
<?php
/**
 * Default `flash` block.
 */
if (!$this->fetch('tb_flash')) {
    $this->start('tb_flash');
    if (isset($this->Flash)) {
        echo $this->Flash->render();
    }
    $this->end();
}
$this->end();
$this->start('tb_body_end');
?>
            </main>
        </div>
    </div>
<?php
    echo $this->fetch('modals');
?>
</body>
<?php
$this->end();

echo $this->fetch('content');
