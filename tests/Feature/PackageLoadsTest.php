<?php

namespace IndexLens\IndexLens\Tests\Feature;

use IndexLens\IndexLens\IndexLensServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageLoadsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            IndexLensServiceProvider::class,
        ];
    }

    public function test_service_is_registered(): void
    {
        $this->assertTrue($this->app->bound('indexlens'));
    }
}
