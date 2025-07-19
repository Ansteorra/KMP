<?php

/**
 * KMP Dashboard Layout Template
 * 
 * Advanced layout template providing a complete dashboard interface with navigation,
 * header management, and responsive sidebar. This layout is optimized for administrative
 * interfaces and member management workflows within the KMP application.
 * 
 * Features:
 * - Responsive dashboard interface with collapsible sidebar
 * - Dynamic header with configurable links and branding
 * - Integrated navigation system with permission-based menu items
 * - Session extension management with automatic keepalive
 * - Bootstrap 5 responsive grid system integration
 * - Sticky navigation header with dark theme
 * - Mobile-responsive sidebar with toggle functionality
 * 
 * Layout Structure:
 * - Header: Fixed navigation bar with logo, header links, and user controls
 * - Sidebar: Collapsible navigation menu (3-column width on desktop)
 * - Main Content: Primary content area (9-column width on desktop)
 * - Session Management: Automatic session extension with configurable timeout
 * 
 * Header Configuration:
 * - Dynamic header links from app settings (KMP.HeaderLink.*)
 * - Configurable logo and site title branding
 * - Sign out functionality with proper logout routing
 * - Mobile-responsive navigation toggle button
 * 
 * Sidebar Navigation:
 * - Dynamic menu structure via Navigation view cell
 * - Permission-based menu item visibility
 * - Hierarchical menu organization with sub-items
 * - Responsive collapse behavior for mobile devices
 * 
 * Session Management:
 * - Automatic session extension via session-extender Stimulus controller
 * - Configurable keepalive URL endpoint
 * - User activity tracking and timeout prevention
 * - Seamless user experience without interruption
 * 
 * Responsive Design:
 * - Mobile-first approach with Bootstrap responsive utilities
 * - Collapsible sidebar for mobile and tablet devices
 * - Sticky header that remains visible during scrolling
 * - Optimized content layout for various screen sizes
 * 
 * Integration Points:
 * - KMP helper for application settings and block management
 * - Navigation view cell for dynamic menu generation
 * - Flash component for system notifications
 * - Session extender controller for automatic keepalive
 * - Bootstrap 5 component system for UI consistency
 * 
 * Usage Examples:
 * ```php
 * // In a controller action, use this layout:
 * $this->extend('/layout/TwitterBootstrap/dashboard');
 * 
 * // Custom title for dashboard pages:
 * echo $this->KMP->startBlock('title');
 * echo 'Members Dashboard - ' . $this->KMP->getAppSetting('KMP.ShortSiteTitle');
 * $this->KMP->endBlock();
 * ```
 * 
 * Header Link Configuration:
 * ```php
 * // In app settings, configure header links:
 * 'KMP.HeaderLink.Reports.url' => '/reports',
 * 'KMP.HeaderLink.Reports.css' => 'btn-primary',
 * 'KMP.HeaderLink.Help.no-label' => '/help'
 * ```
 * 
 * @var \App\View\AppView $this The view instance with KMP helpers
 * @var array $headerLinks Dynamic header links from application settings
 * @var string $fullBaseUrl Application base URL for session management
 * @var string $content Main dashboard content from child templates
 * 
 * @see \App\View\Cell\NavigationCell For sidebar menu generation
 * @see /assets/js/controllers/session-extender-controller.js For session management
 * @see \App\View\Helper\KmpHelper For application settings access
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
// get the full host from the app settings
$fullBaseUrl = Configure::read("App.fullBaseUrl");
$url = $fullBaseUrl . "/keepalive";
?>

<body <?= $this->fetch("tb_body_attrs") ?> data-controller="session-extender"
    data-session-extender-url-value="<?= $url ?>">
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
