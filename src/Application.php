<?php
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

use Authentication\AuthenticationServiceProviderInterface;
use Authentication\AuthenticationService;
use Authentication\Middleware\AuthenticationMiddleware;
use Psr\Http\Message\ServerRequestInterface;

use Cake\Core\Configure;
use Cake\Core\Exception\MissingPluginException;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Psr\Http\Message\ResponseInterface;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication implements AuthenticationServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function bootstrap()
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        $this->addPlugin('Authentication');

        $this->addPlugin('Cake/Localized');

        $this->addPlugin('Crud');

        $this->addPlugin('ADmad/JwtAuth');

        // Enabling CORS middleware - see https://github.com/ozee31/cakephp-cors/tree/cake-3
        $this->addPlugin('Cors', ['bootstrap' => true, 'routes' => false]);

        if (PHP_SAPI === 'cli') {
            try {
                $this->addPlugin('Bake');
            } catch (MissingPluginException $e) {
                // Do not halt if the plugin is missing
            }

            $this->addPlugin('Migrations');
        }

        /*
         * Only try to load DebugKit in development mode
         * Debug Kit should not be installed on a production system
         */
        if (Configure::read('debug')) {
            try {
                $this->addPlugin('DebugKit');
            } catch (MissingPluginException $e) {
                // Do not halt if the plugin is missing
            }

        }
    }

    /**
     * Returns a service provider instance.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @param \Psr\Http\Message\ResponseInterface $response Response
     * @return \Authentication\AuthenticationServiceInterface
     */
    public function getAuthenticationService(ServerRequestInterface $request, ResponseInterface $response)
    {
        $service = new AuthenticationService();
        $service->setConfig([
            'unauthenticatedRedirect' => '/users/login',
            'queryParam' => 'redirect',
        ]);

        $fields = [
            'username' => 'email',
            'password' => 'password'
        ];

        // Load identifiers
        $service->loadIdentifier('Authentication.Password', compact('fields'));

        // Load the authenticators, you want session first
        $service->loadAuthenticator('Authentication.Session');
        $service->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => '/users/login'
        ]);

        return $service;
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware($middlewareQueue)
    {

        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(null, Configure::read('Error')))

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime')
            ]))

            // Add routing middleware.
            // Routes collection cache enabled by default, to disable route caching
            // pass null as cacheConfig, example: `new RoutingMiddleware($this)`
            // you might want to disable this cache in case your routing is extremely simple
            ->add(new RoutingMiddleware($this, '_cake_routes_'))

            // Authentication should be added *after* RoutingMiddleware.
            // So that subdirectory information and routes are loaded.
            ->add(new AuthenticationMiddleware($this));

        return $middlewareQueue;
    }

    public function routes($routes) {
        // $_defaultConfig = [
        //     'cookieName' => 'csrfToken',
        //     'expiry' => 0,
        //     'secure' => false,
        //     'httpOnly' => false,
        //     'field' => '_csrfToken',
        // ];
        $options = [];
        $routes->registerMiddleware('csrf', new CsrfProtectionMiddleware($options));
        parent::routes($routes);
    }
}
