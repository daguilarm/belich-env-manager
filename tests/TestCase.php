<?php

namespace Daguilar\EnvManager\Tests;

use Daguilar\EnvManager\EnvManagerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            EnvManagerServiceProvider::class,
        ];
    }
}
