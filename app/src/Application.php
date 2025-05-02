<?php

declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.3.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App;

// Authentication usings

use App\Event\CallForNavHandler;
use Authentication\Identifier\AbstractIdentifier;
use Authentication\Middleware\AuthenticationMiddleware;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
// Authorization usings
use App\Policy\ControllerResolver;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ActiveWindowManager\DefaultActiveWindowManager;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\DefaultWarrantManager;
use App\Services\AuthorizationService as KmpAuthorizationService;
use Authorization\Middleware\AuthorizationMiddleware;
use Authorization\Policy\OrmResolver;
use Authorization\Policy\ResolverCollection;
use Authorization\AuthorizationServiceInterface;
use Authorization\AuthorizationServiceProviderInterface;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\Router;
use Authorization\Exception\MissingIdentityException;
use Psr\Http\Message\ServerRequestInterface;
use Authorization\Exception\ForbiddenException;
use Cake\Event\EventManager;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 *
 * @extends \Cake\Http\BaseApplication<\App\Application>
 */
class Application extends BaseApplication implements
    AuthenticationServiceProviderInterface,
    AuthorizationServiceProviderInterface
{
    //

    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        // add plugins
        if (PHP_SAPI !== "cli") {
            FactoryLocator::add(
                "Table",
                (new TableLocator())->allowFallbackClass(false),
            );
        }
        $handler = new CallForNavHandler();
        EventManager::instance()->on($handler);


        $currentConfigVersion = "25.01.11.a"; // update this each time you change the config

        $configVersion = StaticHelpers::getAppSetting("KMP.configVersion", "0.0.0", null, true);
        if ($configVersion != $currentConfigVersion) {
            StaticHelpers::setAppSetting("KMP.configVersion", $currentConfigVersion, null, true);
            StaticHelpers::getAppSetting("KMP.BranchInitRun", "", null, true);
            StaticHelpers::getAppSetting("KMP.KingdomName", "please_set", null, true);
            StaticHelpers::getAppSetting("Member.ViewCard.Graphic", "auth_card_back.gif", null, true);
            StaticHelpers::getAppSetting("Member.ViewCard.HeaderColor", "gold", null, true);
            StaticHelpers::getAppSetting("Member.ViewCard.Template", "view_card", null, true);
            StaticHelpers::getAppSetting("Member.ViewMobileCard.Template", "view_mobile_card", null, true);
            StaticHelpers::getAppSetting("KMP.Login.Graphic", "populace_badge.png", null, true);
            StaticHelpers::getAppSetting("Members.AccountVerificationContactEmail", "please_set", null, true);
            StaticHelpers::getAppSetting("Members.AccountDisabledContactEmail", "please_set", null, true);
            StaticHelpers::getAppSetting("KMP.EnablePublicRegistration", "yes", null, true);
            StaticHelpers::getAppSetting("Email.SystemEmailFromAddress", "site@test.com", null, true);
            StaticHelpers::getAppSetting("Email.SiteAdminSignature", "site", null, true);
            StaticHelpers::getAppSetting("KMP.LongSiteTitle", "Kingdom Management Portal", null, true);
            StaticHelpers::getAppSetting("Members.NewMemberSecretaryEmail", "member@test.com", null, true);
            StaticHelpers::getAppSetting("Members.NewMinorSecretaryEmail", "minorSet@test.com", null, true);
            StaticHelpers::getAppSetting("KMP.AppSettings.HelpUrl", "https://github.com/Ansteorra/KMP/wiki/App-Settings", null, true);
            StaticHelpers::getAppSetting("App.version", "0.0.0", null, true);
            StaticHelpers::getAppSetting("KMP.BannerLogo", "badge.png", null, true);
            StaticHelpers::getAppSetting("KMP.ShortSiteTitle", "KMP", null, true);
            StaticHelpers::getAppSetting("Member.MobileCard.ThemeColor", "gold", null, true);
            StaticHelpers::getAppSetting("Member.MobileCard.BgColor", "gold", null, true);
            StaticHelpers::getAppSetting("Activity.SecretaryEmail", "please_set", null, true);
            StaticHelpers::getAppSetting("Activity.SecretaryName", "please_set", null, true);
            StaticHelpers::getAppSetting("Warrant.LastCheck", DateTime::now()->subDays(1)->toDateString(), null, true);
            StaticHelpers::getAppSetting("KMP.RequireActiveWarrantForSecurity", "yes", null, true);
            StaticHelpers::getAppSetting("Warrant.RosterApprovalsRequired", 2, null, true);
            StaticHelpers::getAppSetting("Branches.Types", yaml_emit([
                "Kingdom",
                "Principality",
                "Region",
                "Local Group",
                "N/A",
            ]), 'yaml', true);
        }
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The modified middleware queue.
     */
    public function middleware(
        MiddlewareQueue $middlewareQueue,
    ): MiddlewareQueue {
        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(Configure::read("Error"), $this))
            // Handle plugin/theme assets like CakePHP normally does.
            ->add(
                new AssetMiddleware([
                    "cacheTime" => Configure::read("Asset.cacheTime"),
                ]),
            )
            // Add routing middleware.
            // If you have a large number of routes connected, turning on routes
            // caching in production could improve performance.
            // See https://github.com/CakeDC/cakephp-cached-routing
            ->add(new RoutingMiddleware($this))
            // Parse various types of encoded request bodies so that they are
            // available as array through $request->getData()
            // https://book.cakephp.org/4/en/controllers/middleware.html#body-parser-middleware
            ->add(new BodyParserMiddleware())
            // Cross Site Request Forgery (CSRF) Protection Middleware
            // https://book.cakephp.org/4/en/security/csrf.html#cross-site-request-forgery-csrf-middleware
            ->add(
                new CsrfProtectionMiddleware([
                    "httponly" => true,
                ]),
            )
            // Add the AuthenticationMiddleware. It should be
            // after routing and body parser.
            ->add(new AuthenticationMiddleware($this))
            ->add(
                new AuthorizationMiddleware($this, [
                    "identityDecorator" => function ($auth, $user) {
                        return $user->setAuthorization($auth);
                    },
                    'requireAuthorizationCheck' => true,
                    'unauthorizedHandler' => [
                        'className' => 'Authorization.Redirect',
                        'url' => '/pages/unauthorized',
                        'queryParam' => 'redirectUrl',
                        'exceptions' => [
                            MissingIdentityException::class,
                            ForbiddenException::class,
                        ],
                    ],
                ]),
            )
            ->add('Muffin/Footprint.Footprint');

        return $middlewareQueue;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
        $container->add(
            ActiveWindowManagerInterface::class,
            DefaultActiveWindowManager::class,
        );
        $container->add(
            WarrantManagerInterface::class,
            DefaultWarrantManager::class,
        )->addArgument(ActiveWindowManagerInterface::class);
        $container->add(
            \App\Services\CsvExportService::class
        );
    }

    /**
     * Returns a service provider instance.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Authentication\AuthenticationServiceInterface
     */
    public function getAuthenticationService(
        ServerRequestInterface $request,
    ): AuthenticationServiceInterface {
        $service = new AuthenticationService();

        // Define where users should be redirected to when they are not authenticated
        $service->setConfig([
            "unauthenticatedRedirect" => Router::url([
                "prefix" => false,
                "plugin" => null,
                "controller" => "Members",
                "action" => "login",
            ]),
            "queryParam" => "redirect",
        ]);

        $fields = [
            AbstractIdentifier::CREDENTIAL_USERNAME => "email_address",
            AbstractIdentifier::CREDENTIAL_PASSWORD => "password",
        ];
        // Load the authenticators. Session should be first.
        $service->loadAuthenticator("Authentication.Session");
        $service->loadAuthenticator("Authentication.Form", [
            "fields" => $fields,
            "loginUrl" => Router::url([
                "prefix" => false,
                "plugin" => null,
                "controller" => "Members",
                "action" => "login",
            ]),
        ]);

        // Load identifiers
        $service->loadIdentifier("KMPBruteForcePassword", [
            "resolver" => [
                "className" => "Authentication.Orm",
                "userModel" => "Members",
            ],
            "fields" => $fields,
            // Other config options
            "passwordHasher" => [
                "className" => "Authentication.Fallback",
                "hashers" => [
                    "Authentication.Default",
                    [
                        "className" => "Authentication.Legacy",
                        "hashType" => "md5",
                        "salt" => false, // turn off default usage of salt
                    ],
                ],
            ],
        ]);

        return $service;
    }

    public function getAuthorizationService(
        ServerRequestInterface $request,
    ): AuthorizationServiceInterface {
        $lastResortResolver = new ControllerResolver();
        $ormResolver = new OrmResolver();
        $resolver = new ResolverCollection([$ormResolver, $lastResortResolver]);

        return new KmpAuthorizationService($resolver);
    }
}
