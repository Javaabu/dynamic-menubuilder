<?php

namespace Javaabu\DynamicMenubuilder\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Javaabu\DynamicMenubuilder\DynamicMenubuilderServiceProvider;
use Javaabu\DynamicMenubuilder\Tests\TestSupport\Providers\TestServiceProvider;

abstract class TestCase extends BaseTestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('app.key', 'base64:yWa/ByhLC/GUvfToOuaPD7zDwB64qkc/QkaQOrT5IpE=');

        $this->app['config']->set('session.serialization', 'php');

    }

    protected function getPackageProviders($app)
    {
        return [
            DynamicMenubuilderServiceProvider::class,
            TestServiceProvider::class
        ];
    }
}
