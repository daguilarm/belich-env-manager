<?php

namespace Daguilar\BelichEnvManager\Tests;

use Daguilar\BelichEnvManager\BelichEnvManagerServiceProvider;
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
            BelichEnvManagerServiceProvider::class,
        ];
    }
}
