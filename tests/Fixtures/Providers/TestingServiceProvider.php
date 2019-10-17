<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Fixtures\Providers;

use Illuminate\Support\ServiceProvider;

class TestingServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        //
    }

    public function register()
    {
        $this->loadRoutesFrom(__DIR__ . "/../routes/web.php");
    }
}
