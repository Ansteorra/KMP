<?php

/**
 * KMP Copyright Footer Element
 * 
 * Reusable footer element that provides application branding, footer navigation,
 * and copyright information. This element integrates with the application settings
 * system to display configurable footer links and branding information.
 * 
 * Features:
 * - Dynamic footer links from application settings
 * - Configurable footer navigation with custom styling
 * - Automatic copyright year generation
 * - Application version display
 * - Integration with GitHub Issue Submitter plugin
 * - Bootstrap-responsive footer layout
 * 
 * Footer Link Configuration:
 * Footer links are managed through application settings using the pattern:
 * - KMP.FooterLink.{name}.{property} = value
 * - Supports URL configuration and CSS class customization
 * - Automatic label generation from setting key names
 * - Support for no-label links using special syntax
 * 
 * Configuration Examples:
 * ```php
 * // Standard footer link with label
 * 'KMP.FooterLink.Privacy' => '/privacy|text-muted',
 * 'KMP.FooterLink.Terms' => '/terms',
 * 
 * // Link without label display
 * 'KMP.FooterLink.Help.no-label' => '/help|btn btn-sm btn-outline-primary'
 * ```
 * 
 * Layout Integration:
 * - Uses Bootstrap flex utilities for responsive layout
 * - Three-column structure with centered footer links
 * - Right-aligned copyright and version information
 * - Auto-margin spacing for optimal visual balance
 * 
 * Plugin Integration:
 * - GitHub Issue Submitter cell integration for feedback
 * - Automatic plugin detection and conditional display
 * - Support for additional footer plugins through cell system
 * 
 * Responsive Design:
 * - Mobile-responsive layout with appropriate spacing
 * - Flexible navigation that adapts to content length
 * - Proper text wrapping and alignment on small screens
 * 
 * Usage in Layouts:
 * ```php
 * // Include in layout templates:
 * echo $this->element('copyrightFooter', []);
 * 
 * // Or with additional data:
 * echo $this->element('copyrightFooter', [
 *     'additionalClass' => 'custom-footer-styling'
 * ]);
 * ```
 * 
 * Customization Options:
 * - Footer link styling through CSS class configuration
 * - Copyright text customization through app settings
 * - Additional footer content through plugin cell system
 * - Responsive behavior modification through Bootstrap utilities
 * 
 * @var \App\View\AppView $this The view instance
 * @var string $appName Long site title from application settings
 * @var string $appVersion Current application version
 * @var array $footerLinks Configured footer links with URLs and styling
 * @var string|null $additionalClass Optional CSS classes for footer customization
 * 
 * @see \App\View\Helper\KmpHelper For application settings access
 * @see \GitHubIssueSubmitter\View\Cell\IssueSubmitterCell For feedback integration
 * @see /config/app.php For footer link configuration examples
 */

$appName = $this->KMP->getAppSetting("KMP.LongSiteTitle");
$appVersion = $this->KMP->getAppSetting("App.version");

$footerLinks = $this->KMP->getAppSettingsStartWith("KMP.FooterLink.");
echo $this->KMP->startBlock("tb_footer"); ?>

<footer class="mt-auto text-end me-3">
    <div class="row">
        <div class="col"></div>
        <ul class="navbar-nav flex-row px-3 col">
            <?php foreach ($footerLinks as $key => $value) :
                $key = str_replace("KMP.FooterLink.", "", $key);
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
                <li class="nav-item text-nowrap mx-2">
                    <a class="<?= $css ?>" href="<?= $url ?>"><?= $key ?></a>
                </li>
            <?php endforeach; ?>
            <li class="nav-item text-nowrap mx-2">
                <?= $this->cell('GitHubIssueSubmitter.IssueSubmitter::display', []) ?>
            </li>
        </ul>

        <div class="col"></div>
    </div>
    <div class="px-5">
        &copy;<?= h(date("Y")) ?> <?= h($appName) ?> : <?= h($appVersion) ?>
    </div>
</footer>
<?php
$this->KMP->endBlock();
?>