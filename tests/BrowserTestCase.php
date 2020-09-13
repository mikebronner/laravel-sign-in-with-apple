<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests;

use Orchestra\Testbench\Dusk\TestCase;

abstract class BrowserTestCase extends TestCase
{
    use CreatesApplication;

    protected static $baseServeHost = '127.0.0.1';
    protected static $baseServePort = 9000;
}
