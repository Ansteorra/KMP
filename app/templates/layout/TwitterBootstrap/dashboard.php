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
                        $memberArea = "";
                        $memberArea .= $this->Kmp->appControllerNav ( $user->sca_name, ['controller' =>'Members', 'action'=> 'view', $user->id], $this->request, $this->Html, $user, 'bi-person-fill', true, [
                                        ['suburl' => ['controller' =>'Members', 'action'=> 'ViewCard', $user->id], 
                                            'label' => 'My Auth Card', 
                                            'icon' => 'bi-person-vcard', 
                                            'overrideSelf' => true
                                        ],
                                        ['suburl' => ['controller' =>'AuthorizationApprovals', 'action'=> 'MyQueue'], 
                                            'label' => 'My Auth Queue', 
                                            'icon' => 'bi-person-fill-check', 
                                            'overrideSelf' => false
                                        ]
                                ]);
                        if($memberArea){
                            echo $this->Kmp->appControllerNavSpacer('Members', $this->Html, 'bi-people');
                            echo $memberArea;
                        }
                        $sysConfig = "";
                        $sysConfig .= $this->Kmp->appControllerNav('App Settings', 'AppSettings', $this->request, $this->Html, $user, 'bi-card-list', false);
                        $sysConfig .= $this->Kmp->appControllerNav('Branches', 'Branches', $this->request, $this->Html, $user, 'bi-diagram-3', false, [
                            ['suburl' => ['controller' =>'Branches', 'action'=> 'add'], 'label' => 'New Branch', 'icon' => 'bi-plus']
                        ]);
                        $sysConfig .= $this->Kmp->appControllerNav('Authorization Groups', 'AuthorizationGroups', $this->request, $this->Html, $user, 'bi-archive', false, [
                            ['suburl' => ['controller' =>'AuthorizationGroups', 'action'=> 'add'], 'label' => 'New Auth Group', 'icon' => 'bi-plus']
                        ]);
                        $sysConfig .= $this->Kmp->appControllerNav('Authorization Types', 'AuthorizationTypes', $this->request, $this->Html, $user, 'bi-collection', false, [
                            ['suburl' => ['controller' =>'AuthorizationTypes', 'action'=> 'add'], 'label' => 'New Auth Type', 'icon' => 'bi-plus']
                        ]);
                        if($sysConfig){
                            echo $this->Kmp->appControllerNavSpacer('System Config', $this->Html, 'bi-database-gear');
                            echo $sysConfig;
                        }
                        $security = "";
                        $security .= $this->Kmp->appControllerNav('Roles', 'Roles', $this->request, $this->Html, $user, 'bi-universal-access-circle', false, [
                            ['suburl' => ['controller' =>'Roles', 'action'=> 'add'], 'label' => 'New Role', 'icon' => 'bi-plus']
                        ]);
                        $security .= $this->Kmp->appControllerNav('Permissions', 'Permissions', $this->request, $this->Html, $user, 'bi-clipboard-check', false, [
                            ['suburl' => ['controller' =>'Permissions', 'action'=> 'add'], 'label' => 'New Permission', 'icon' => 'bi-plus']
                        ]);
                        if($security){
                            echo $this->Kmp->appControllerNavSpacer('Security', $this->Html, 'bi-house-lock');
                            echo $security;
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
