<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests;

use Orchestra\Testbench\Dusk\TestCase;

abstract class BrowserTestCase extends TestCase
{
    use CreatesApplication;

    public static function setUpBeforeClass(): void
    {
        $chromeDir = '';

        if (is_file('/Applications/Chromium.app/Contents/MacOS/Chromium')) {
            $chromeDir = ' --chrome-dir="/Applications/Chromium.app/Contents/MacOS/Chromium"';
        }

        exec('vendor/bin/dusk-updater detect' . $chromeDir . ' --auto-update --no-interaction');
        parent::setUpBeforeClass();
    }
}
