<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Http\Controllers\AppleSignInController;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;

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

    public function testRedirectRoutePathCanBeCustomized(): void
    {
        // Config shows customization is possible
        $this->assertEquals(
            'apple/redirect',
            config('services.sign_in_with_apple.routes.redirect_route')
        );
    }

    public function testCallbackRoutePathCanBeCustomized(): void
    {
        $this->assertEquals(
            'apple/callback',
            config('services.sign_in_with_apple.routes.callback_route')
        );
    }
}
