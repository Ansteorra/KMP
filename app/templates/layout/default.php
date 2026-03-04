<?php

/**
 * KMP Default Layout Template
 * 
 * This is the main layout template for the KMP application, providing the base HTML structure
 * for standard pages. It implements a block-based architecture using KMP's helper system
 * and integrates with Laravel Mix for asset management and Bootstrap UI framework.
 * 
 * Features:
 * - Block-based content organization with configurable sections
 * - Laravel Mix asset integration with automatic versioning
 * - Bootstrap UI framework support with responsive design
 * - CSRF protection and security headers
 * - Turbo framework integration for enhanced navigation
 * - Configurable meta tags and application settings
 * - Flash message system integration
 * - Modular script and stylesheet loading
 * 
 * Block Structure:
 * - html: Main HTML element with language configuration
 * - title: Page title with application branding
 * - tb_footer: Footer content area
 * - tb_body_attrs: Body element attributes with controller/action classes
 * - tb_body_start/tb_body_end: Body element wrapper blocks
 * - tb_flash: Flash message display area
 * - meta: Meta tags and favicon configuration
 * - css: Stylesheet loading block
 * - topscript: JavaScript loading block for core assets
 * - content: Main page content area
 * - modals: Modal dialog container
 * - script: Additional JavaScript loading block
 * 
 * Asset Loading Strategy:
 * - Core assets loaded in head for immediate availability
 * - Bundle splitting with manifest, core, controllers, and index chunks
 * - AssetMix helper provides cache-busting and optimization
 * - CSRF token embedded for AJAX request protection
 * 
 * Integration Points:
 * - KMP helper for block management and application settings
 * - Flash component for user notifications
 * - AssetMix helper for Laravel Mix asset compilation
 * - CakePHP URL helper for base URL generation
 * - Bootstrap UI framework for consistent styling
 * 
 * Usage Examples:
 * ```php
 * // In a view template, extend this layout:
 * $this->extend('/layout/default');
 * 
 * // Customize the title block:
 * echo $this->KMP->startBlock('title');
 * echo 'Custom Page Title';
 * $this->KMP->endBlock();
 * 
 * // Add additional CSS or JavaScript:
 * $this->append('css', $this->Html->css('custom'));
 * $this->append('script', $this->Html->script('custom'));
 * ```
 * 
 * @var \App\View\AppView $this The view instance with KMP helpers and components
 * @var string|null $title Optional page title override
 * @var array $meta Optional meta tag overrides
 * @var string|null $content Main page content from child templates
 * @var string|null $modals Modal dialog content
 * 
 * @see \App\View\Helper\KmpHelper For block management and application settings
 * @see \AssetMix\View\Helper\AssetMixHelper For asset compilation integration
 * @see /templates/layout/TwitterBootstrap/ For specialized layout variants
 */

use Cake\Core\Configure;
use Cake\I18n\FrozenTime;

/**
 * Default `html` block.
 */
if (!$this->fetch("html")) {
    echo $this->KMP->startBlock("html");
    if (Configure::check("App.language")) {
        printf('<html lang="%s">', Configure::read("App.language"));
    } else {
        echo "<html lang='en' >";
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
    echo '<div id="flash-messages">';
    echo $this->Flash->render();
    echo '</div>';
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
if (!empty($impersonationState)) {
    $startedAgo = null;
    if (!empty($impersonationState['started_at'])) {
        try {
            $startedAgo = FrozenTime::parse($impersonationState['started_at'])->timeAgoInWords();
        } catch (\Throwable $exception) {
            $startedAgo = null;
        }
    }
?>
    <div
        class="alert alert-warning impersonation-banner d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-3 mb-3">
        <div>
            <strong><?= __('Impersonation active') ?>:</strong>
            <?= __('You are acting as {0}.', h($impersonationState['impersonated_member_name'] ?? '#')) ?>
            <?php if ($startedAgo): ?>
                <span class="ms-2 text-muted"><?= __('Started {0}', h($startedAgo)) ?></span>
            <?php endif; ?>
            <div class="small text-muted mb-0">
                <?= __('Original admin: {0}. All changes are being logged.', h($impersonationState['impersonator_name'] ?? '')) ?>
            </div>
        </div>
        <div>
            <?= $this->Form->postLink(
                __('Return to admin account'),
                ['controller' => 'Members', 'action' => 'stopImpersonating', 'plugin' => null],
                [
                    'class' => 'btn btn-sm btn-outline-dark',
                    'confirm' => __('Stop impersonating and return to your admin account?'),
                ],
            ) ?>
        </div>
    </div>
<?php
}
echo $this->fetch("content");
echo $this->fetch("tb_footer");
echo $this->fetch("modals");
echo $this->fetch("tb_body_end");
echo $this->fetch("script");
//echo $this->AssetCompress->script('app-combined');

?>

</html>
