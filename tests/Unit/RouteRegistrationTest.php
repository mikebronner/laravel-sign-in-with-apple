<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Http\Controllers\AppleSignInController;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

class RouteRegistrationTest extends UnitTestCase
{
    public function testAppleRedirectRouteIsRegistered(): void
    {
        $routes = $this->app['router']->getRoutes();

        $redirectRoute = $routes->getByName('apple.redirect');

        $this->assertNotNull($redirectRoute);
        $this->assertEquals('apple/redirect', $redirectRoute->uri);
        $this->assertTrue(in_array('GET', $redirectRoute->methods));
    }

    public function testAppleCallbackRouteIsRegistered(): void
    {
        $routes = $this->app['router']->getRoutes();

        $callbackRoute = $routes->getByName('apple.callback');

        $this->assertNotNull($callbackRoute);
        $this->assertEquals('apple/callback', $callbackRoute->uri);
        $this->assertTrue(in_array('POST', $callbackRoute->methods));
    }

    public function testRedirectRouteUsesAppleSignInController(): void
    {
        $routes = $this->app['router']->getRoutes();
        $route = $routes->getByName('apple.redirect');

        $controller = $route->getAction('controller');
        $this->assertStringContainsString('AppleSignInController', $controller);
        $this->assertStringContainsString('redirect', $controller);
    }

    public function testCallbackRouteUsesAppleSignInController(): void
    {
        $routes = $this->app['router']->getRoutes();
        $route = $routes->getByName('apple.callback');

        $controller = $route->getAction('controller');
        $this->assertStringContainsString('AppleSignInController', $controller);
        $this->assertStringContainsString('callback', $controller);
    }

    public function testRoutesAreProtectedWithWebMiddleware(): void
    {
        $routes = $this->app['router']->getRoutes();

        $redirectRoute = $routes->getByName('apple.redirect');
        $callbackRoute = $routes->getByName('apple.callback');

        $this->assertTrue(in_array('web', $redirectRoute->middleware()));
        $this->assertTrue(in_array('web', $callbackRoute->middleware()));
    }

    public function testCallbackRouteExcludesCsrfVerification(): void
    {
        $routes = $this->app['router']->getRoutes();
        $callbackRoute = $routes->getByName('apple.callback');

        $excludedMiddleware = $callbackRoute->excludedMiddleware();
        $this->assertContains(VerifyCsrfToken::class, $excludedMiddleware);
    }

    public function testRoutesAreNotRegisteredWhenDisabled(): void
    {
        $this->app['config']->set('services.apple.sign_in.routes.enabled', false);

        // Clear routes and re-boot the service provider with routes disabled
        $this->app['router']->setRoutes(new \Illuminate\Routing\RouteCollection());

        $provider = new \GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider($this->app);
        $provider->boot();

        $routes = $this->app['router']->getRoutes();
        $routes->refreshNameLookups();

        $this->assertNull($routes->getByName('apple.redirect'));
        $this->assertNull($routes->getByName('apple.callback'));
    }

    public function testRedirectRoutePathCanBeCustomized(): void
    {
        $this->app['config']->set('services.apple.sign_in.routes.redirect_route', 'auth/apple/login');

        $this->reloadRoutes();

        $routes = $this->app['router']->getRoutes();
        $redirectRoute = $routes->getByName('apple.redirect');

        $this->assertNotNull($redirectRoute);
        $this->assertEquals('auth/apple/login', $redirectRoute->uri);
    }

    public function testCallbackRoutePathCanBeCustomized(): void
    {
        $this->app['config']->set('services.apple.sign_in.routes.callback_route', 'auth/apple/handle');

        $this->reloadRoutes();

        $routes = $this->app['router']->getRoutes();
        $callbackRoute = $routes->getByName('apple.callback');

        $this->assertNotNull($callbackRoute);
        $this->assertEquals('auth/apple/handle', $callbackRoute->uri);
    }

    /**
     * Clear existing routes and reload the package route file.
     */
    private function reloadRoutes(): void
    {
        $this->app['router']->setRoutes(new \Illuminate\Routing\RouteCollection());

        require __DIR__ . '/../../routes/web.php';

        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function testControllerCanBeOverriddenByApplication(): void
    {
        $this->app['router']->setRoutes(new \Illuminate\Routing\RouteCollection());

        // Simulate an app overriding the callback route with a custom controller
        $this->app['router']->group(['middleware' => ['web']], function ($router) {
            $router->post('apple/callback', [\GeneaLabs\LaravelSignInWithApple\Tests\Fixtures\Http\Controllers\SiwaController::class, 'callback'])
                ->name('apple.callback');
        });

        $routes = $this->app['router']->getRoutes();
        $routes->refreshNameLookups();
        $callbackRoute = $routes->getByName('apple.callback');

        $this->assertNotNull($callbackRoute);
        $controller = $callbackRoute->getAction('controller');
        $this->assertStringContainsString('SiwaController', $controller);
    }
}
