<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests;

use Orchestra\Testbench\BrowserKit\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use CreatesApplication;

    public $baseUrl = 'http://testing.dev';
}
