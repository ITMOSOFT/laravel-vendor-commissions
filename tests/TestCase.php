<?php

namespace ItmoSoft\VendorCommissions\Tests;

use Illuminate\Support\Facades\Schema;
use ItmoSoft\VendorCommissions\VendorCommissionsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [VendorCommissionsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        $app['config']->set('itmosoft-vendor-commissions.vendor_model', TestVendor::class);
        $app['config']->set('itmosoft-vendor-commissions.table_prefix', 'itmosoft_');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('test_vendors', function ($table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        $this->artisan('migrate')->run();
    }
}
