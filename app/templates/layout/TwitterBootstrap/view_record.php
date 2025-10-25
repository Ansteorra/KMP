<?php

/**
 * KMP View Record Layout Template
 *
 * Specialized layout template designed for detailed record viewing interfaces.
 * This layout provides a comprehensive view structure optimized for displaying
 * individual entity records with full navigation and contextual tools.
 *
 * Features:
 * - Full-width record display with responsive design
 * - Integrated navigation sidebar for contextual menu access
 * - Header management with configurable links and branding
 * - Session extension management with automatic keepalive
 * - Detailed record view optimization with proper spacing
 * - Bootstrap 5 responsive grid system for content organization
 *
 * Layout Structure:
 * - Header: Fixed navigation bar with logo, header links, and user controls
 * - Sidebar: Full navigation menu with record context awareness
 * - Main Content: Optimized for detailed record display and related data
 * - Record Context: Enhanced spacing and typography for data presentation
 *
 * Use Cases:
 * - Member profile pages with comprehensive details
 * - Warrant record viewing with related information
 * - Branch detail pages with organizational structure
 * - Officer assignment records with context
 * - Administrative record review interfaces
 *
 * Design Philosophy:
 * - Maximum content visibility with minimal UI distraction
 * - Consistent navigation access across all record views
 * - Responsive design ensuring readability on all devices
 * - Integration with KMP's permission system for context-aware menus
 *
 * Navigation Integration:
 * - Full navigation sidebar remains available for quick access
 * - Context-aware menu items based on current record type
 * - Permission-based menu filtering for authorized actions
 * - Breadcrumb support through navigation context
 *
 * Session Management:
 * - Automatic session extension during record review
 * - User activity tracking for detailed viewing sessions
 * - Timeout prevention for lengthy record analysis
 *
 * Content Optimization:
 * - Enhanced spacing for detailed data presentation
 * - Typography optimized for extended reading
 * - Responsive layout adaptations for various screen sizes
 * - Print-friendly styling considerations
 *
 * Integration Points:
 * - KMP helper system for settings and block management
 * - Navigation view cell for contextual menu generation
 * - Flash messaging system for user feedback
 * - Session extension controller for activity management
 * - Bootstrap component system for consistent UI
 *
 * Usage Examples:
 * ```php
 * // In a view action controller:
 * $this->extend('/layout/TwitterBootstrap/view_record');
 *
 * // Set page title for record view:
 * echo $this->KMP->startBlock('title');
 * echo $member->sca_name . ' - Member Profile';
 * $this->KMP->endBlock();
 * ```
 *
 * Template Integration:
 * ```html
 * <!-- Record view templates can utilize enhanced spacing -->
 * <div class="record-details">
 *     <h2>Member Information</h2>
 *     <!-- Detailed record content here -->
 * </div>
 * ```
 *
 * @var \App\View\AppView $this The view instance with KMP helpers
 * @var array $headerLinks Dynamic header links from application settings
 * @var string $fullBaseUrl Application base URL for session management
 * @var string $content Record view content from child templates
 * @var mixed $entity Optional entity being viewed for context
 *
 * @see \App\View\Cell\NavigationCell For navigation menu generation
 * @see /assets/js/controllers/session-extender-controller.js For session management
 * @see /templates/layout/TwitterBootstrap/dashboard.php For dashboard layout variant
 */

use Cake\Core\Configure;

$this->prepend(
    'tb_body_attrs',
    ' class="' .
        implode(' ', [
            h($this->request->getParam('controller')),
            h($this->request->getParam('action')),
        ]) .
        '" ',
);
echo $this->KMP->startBlock('tb_body_start');
$headerLinks = $this->KMP->getAppSettingsStartWith('KMP.HeaderLink.');
// get the full host from the app settings
$fullBaseUrl = Configure::read('App.fullBaseUrl');
$url = $fullBaseUrl . '/keepalive';
?>

<body <?= $this->fetch('tb_body_attrs') ?> data-controller="session-extender"
    data-session-extender-url-value="<?= $url ?>">

    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <div class="navbar-brand col-md-3 col-lg-2 me-0 px-3">
            <?= $this->Html->image($this->KMP->getAppSetting('KMP.BannerLogo'), [
                'alt' => 'Logo',
                'height' => '24',
                'class' => 'd-inline-block mb-1',
            ]) ?>
            <span class="fs-5"><?= h($this->KMP->getAppSetting('KMP.ShortSiteTitle')) ?></span>
        </div>
        <ul class="navbar-nav flex-row px-3">
            <?php foreach ($headerLinks as $key => $value) :
                $key = str_replace('KMP.HeaderLink.', '', $key);
                $keys = explode('.', $key);
                $key = $keys[0];
                if (count($keys) > 1) {
                    $key = $keys[1] == 'no-label' ? '' : $keys[0];
                }
                $parts = explode('|', $value);
                $url = $parts[0];
                $css = '';
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
                    __('Sign out'),
                    ['controller' => 'Members', 'action' => 'logout', 'plugin' => null],
                    ['class' => 'btn btn-outline-secondary'],
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
                    <?= $this->cell('Navigation') ?>
                </div>
            </nav>

            <main role="main" class="col-md-9 ms-sm-auto col-lg-10 px-md-4 my-3">
                <div class="row align-items-start">
                    <div class="col">
                        <h3>
                            <?php
                            $historyCount = count($pageStack);
                            if ($historyCount < 2) {
                                echo '<a href="#" onclick="window.history.back();" class="bi "></a>';
                            } else {
                                echo '<a href="' . $pageStack[$historyCount - 2] . '" class="bi bi-arrow-left-circle"></a>';
                            }
                            ?>
                            <?php echo $this->fetch('pageTitle') ?>
                        </h3>
                    </div>
                    <div class="col text-end">
                        <?php echo $this->fetch('recordActions') ?>
                    </div>
                </div>
                <?php
                $this->KMP->endBlock(); ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tbody>
                            <?= $this->fetch('recordDetails') ?>
                            <?= $this->element('pluginDetailBodies', [
                                'pluginViewCells' => $pluginViewCells,
                                'id' => $recordId,
                                'model' => $recordModel,
                            ]) ?>
                        </tbody>
                    </table>
                </div>
                <div class="row" data-controller="detail-tabs">
                    <nav>
                        <!-- Tab navigation using CSS flexbox order for mixed plugin/template tab sorting
                             Plugin tabs have order from their ViewCellRegistry configuration
                             Template tabs can specify order via data-tab-order and inline style
                             Example: <button ... data-tab-order="5" style="order: 5;"> -->
                        <div class="nav nav-tabs" id="nav-tabButtons" role="tablist" style="display: flex;">
                            <?= $this->element('pluginTabButtons', [
                                'pluginViewCells' => $pluginViewCells,
                                'id' => $recordId,
                                'model' => $recordModel,
                                'activateFirst' => false,
                            ]) ?>
                            <?= $this->fetch('tabButtons') ?>
                        </div>
                    </nav>
                    <!-- Tab content panels with matching order for consistent tab/content pairing -->
                    <div class="tab-content" id="nav-tabContent" style="display: flex; flex-direction: column;">
                        <?= $this->element('pluginTabBodies', [
                            'pluginViewCells' => $pluginViewCells,
                            'id' => $recordId,
                            'model' => $recordModel,
                            'activateFirst' => false,
                        ]) ?>
                        <?= $this->fetch('tabContent') ?>
                    </div>
                </div>
                <?= $this->KMP->startBlock('tb_body_end'); ?>
            </main>
        </div>
    </div>
</body>
<?php
$this->KMP->endBlock();
if (!$this->fetch('tb_flash')) {
    echo $this->KMP->startBlock('tb_flash');
    if (isset($this->Flash)) {
        echo $this->Flash->render();
    }
    $this->KMP->endBlock();
}

echo $this->fetch('content');
echo $this->element('copyrightFooter', []);
